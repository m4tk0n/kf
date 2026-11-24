<?php
// src/storage.php
declare(strict_types=1);

/** Zapíše celý obsah streamu; fwrite() může zapsat méně bajtů, než bylo požadováno. */
function write_stream_all($fh, string $content, string $fileLabel): void
{
    $length = strlen($content);
    $offset = 0;
    while ($offset < $length) {
        $written = @fwrite($fh, substr($content, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('JSON zápis se nezdařil: ' . $fileLabel);
        }
        $offset += $written;
    }
    if (!@fflush($fh)) {
        throw new RuntimeException('JSON se nepodařilo dokončit na disku: ' . $fileLabel);
    }
    if (function_exists('fsync') && !@fsync($fh)) {
        throw new RuntimeException('JSON se nepodařilo synchronizovat: ' . $fileLabel);
    }
}

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Nelze vytvořit adresář: ' . basename($dir));
    }
}

/**
 * Vrátí počet běžných souborů uvnitř adresáře (rekurzivně).
 * Položky uvedené v $keep se nezapočítávají; symbolické odkazy se nikdy nesledují.
 */
function dir_count_files(string $dir, array $keep = []): int
{
    if (!is_dir($dir) || is_link($dir)) {
        return 0;
    }

    $count = 0;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || in_array($name, $keep, true)) {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path) && !is_link($path)) {
            $count += dir_count_files($path, $keep);
        } else {
            $count++;
        }
    }
    return $count;
}

/**
 * Smaže obsah adresáře rekurzivně, samotný adresář ponechá.
 * Chrání položky podle názvu v $keep a nikdy nenásleduje symbolické odkazy.
 * Vrací počet smazaných souborů.
 */
function dir_delete_contents(string $dir, array $keep = []): int
{
    if (!is_dir($dir) || is_link($dir)) {
        return 0;
    }

    $deleted = 0;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || in_array($name, $keep, true)) {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path) && !is_link($path)) {
            $deleted += dir_delete_contents($path, $keep);
            $remaining = array_diff(scandir($path) ?: [], ['.', '..']);
            if (!$remaining && !@rmdir($path)) {
                throw new RuntimeException('Nelze odstranit adresář: ' . basename($path));
            }
        } else {
            if (!@unlink($path)) {
                throw new RuntimeException('Nelze odstranit soubor: ' . basename($path));
            }
            $deleted++;
        }
    }
    return $deleted;
}

/** Připojí řádek do logu (zámek flock, rotace: při překročení limitu se ponechá poslední polovina). */
function log_append(string $relFile, string $line): void
{
    $file = abs_path($relFile);
    ensure_dir(dirname($file));
    $fh = @fopen($file, 'c+b');
    if (!$fh) {
        return;
    }
    if (flock($fh, LOCK_EX)) {
        $max = (int)cfg('storage.security_log_max_bytes');
        $size = (int)(fstat($fh)['size'] ?? 0);
        if ($max > 0 && $size > $max) {
            fseek($fh, -intdiv($max, 2), SEEK_END);
            fgets($fh);
            $tail = (string)stream_get_contents($fh);
            ftruncate($fh, 0);
            rewind($fh);
            write_stream_all($fh, $tail, basename($file));
        }
        fseek($fh, 0, SEEK_END);
        write_stream_all($fh, preg_replace('/[\\r\\n]+/', ' ', $line) . "\n", basename($file));
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

/** Úprava jednoho sdíleného JSON souboru pod zámkem (rate-limit, ...). Callback dostane pole odkazem. */
function json_locked(string $relFile, callable $fn): mixed
{
    $file = abs_path($relFile);
    ensure_dir(dirname($file));
    $fh = @fopen($file, 'c+b');
    if (!$fh) {
        throw new RuntimeException('Soubor nelze otevřít pro zápis: ' . basename($file));
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            throw new RuntimeException('Soubor nelze zamknout: ' . basename($file));
        }
        rewind($fh);
        $raw = stream_get_contents($fh);
        if ($raw === false) {
            throw new RuntimeException('Soubor nelze přečíst: ' . basename($file));
        }
        $data = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $result = $fn($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!ftruncate($fh, 0) || !rewind($fh)) {
            throw new RuntimeException('JSON soubor nelze připravit k zápisu: ' . basename($file));
        }
        write_stream_all($fh, $json, basename($file));
        flock($fh, LOCK_UN);
        return $result;
    } finally {
        fclose($fh);
    }
}
