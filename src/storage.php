<?php
// src/storage.php
declare(strict_types=1);

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
            fwrite($fh, $tail);
        }
        fseek($fh, 0, SEEK_END);
        fwrite($fh, preg_replace('/[\\r\\n]+/', ' ', $line) . "\n");
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

/** Úprava jednoho sdíleného JSON souboru pod zámkem (rate-limit, ...). Callback dostane pole odkazem. */
function json_locked(string $relFile, callable $fn, bool $pretty = false): mixed
{
    $file = abs_path($relFile);
    ensure_dir(dirname($file));
    $fh = fopen($file, 'c+b');
    if (!$fh) {
        throw new RuntimeException('Soubor nelze otevřít: ' . basename($file));
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            throw new RuntimeException('Soubor nelze zamknout: ' . basename($file));
        }
        $raw = (string)stream_get_contents($fh);
        $data = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $result = $fn($data);
        ftruncate($fh, 0);
        rewind($fh);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = json_encode($data, $flags);
        fwrite($fh, $json . ($pretty ? "\n" : ''));
        fflush($fh);
        flock($fh, LOCK_UN);
        return $result;
    } finally {
        fclose($fh);
    }
}
