<?php
// src/storage.php
declare(strict_types=1);

function ensure_dir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Nelze vytvořit adresář: ' . basename($dir));
    }
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
        fwrite($fh, preg_replace('/[\r\n]+/', ' ', $line) . "\n");
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

/** Úprava jednoho sdíleného JSON souboru pod zámkem (rate-limit, ...). Callback dostane pole odkazem. */
function json_locked(string $relFile, callable $fn): mixed
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
        fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fh);
        flock($fh, LOCK_UN);
        return $result;
    } finally {
        fclose($fh);
    }
}
