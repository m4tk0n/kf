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

/** Načte sdílený JSON soubor (bez zámku); chybějící nebo vadný soubor = prázdné pole. */
function json_read_file(string $relFile): array
{
    $file = abs_path($relFile);
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/** Atomický zápis JSON (dočasný soubor + rename). */
function json_write_file(string $relFile, array $data): void
{
    $file = abs_path($relFile);
    ensure_dir(dirname($file));
    $tmp = $file . '.' . random_hex(4) . '.tmp';
    if (file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) === false) {
        throw new RuntimeException('Zápis souboru selhal.');
    }
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Přejmenování souboru selhalo.');
    }
}

/**
 * Úprava jednoho sdíleného JSON souboru pod zámkem (rate-limit, fronta e-mailů, ...).
 * Callback dostane pole odkazem a vrací libovolný výsledek.
 */
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

function bucket_of(int $id): int
{
    return intdiv($id, max(1, (int)cfg('img.folder_divisor')));
}

/** Relativní cesta JSON detailu: <json_buckets.typ>/<bucket>/<id>.json */
function json_rel(string $type, int $id): string
{
    $dir = cfg('storage.json_buckets.' . $type);
    if (!is_string($dir) || $id < 1) {
        throw new InvalidArgumentException('Neplatný typ nebo ID JSON záznamu.');
    }
    return $dir . '/' . bucket_of($id) . '/' . $id . '.json';
}

function json_get(string $type, int $id): array
{
    return json_read_file(json_rel($type, $id));
}

function json_put(string $type, int $id, array $data): void
{
    json_write_file(json_rel($type, $id), $data);
}

function json_remove(string $type, int $id): void
{
    @unlink(abs_path(json_rel($type, $id)));
}

/** Relativní cesta obrázku: <img.subdirs.typ>/<bucket>/<id>_<s|l>.<format> */
function img_rel(string $type, int $id, string $size): string
{
    $dir = cfg('img.subdirs.' . $type);
    if (!is_string($dir) || $id < 1 || !in_array($size, ['s', 'l'], true)) {
        throw new InvalidArgumentException('Neplatný typ, ID nebo velikost obrázku.');
    }
    return $dir . '/' . bucket_of($id) . '/' . $id . '_' . $size . '.' . cfg('img.format');
}

function img_url(string $type, int $id, string $size): string
{
    $rel = img_rel($type, $id, $size);
    $mt = @filemtime(abs_path($rel));
    return url($rel) . ($mt ? '?v=' . $mt : '');
}

/** Smaže JSON detail a oba obrázky záznamu (volá se při smazání záznamu). */
function entity_files_delete(string $type, int $id): void
{
    json_remove($type, $id);
    foreach (['s', 'l'] as $size) {
        $f = abs_path(img_rel($type, $id, $size));
        if (is_file($f)) {
            @unlink($f);
        }
    }
}

/** Profil uživatele (e-mail, avatar, jazyk, uložené filtry): data/users/<bucket>/<id>.json */
function user_profile_rel(int $id): string
{
    if ($id < 1) {
        throw new InvalidArgumentException('Neplatné ID uživatele.');
    }
    return cfg('storage.users_dir') . '/' . bucket_of($id) . '/' . $id . '.json';
}

function user_profile_get(int $id): array
{
    return json_read_file(user_profile_rel($id));
}

function user_profile_put(int $id, array $data): void
{
    json_write_file(user_profile_rel($id), $data);
}

function user_profile_delete(int $id): void
{
    @unlink(abs_path(user_profile_rel($id)));
}

/** Soubor rozpracovaného formuláře (jeden na uživatele). */
function draft_rel(int $userId): string
{
    return sprintf((string)cfg('storage.draft_file'), $userId);
}

/**
 * Smaže obsah adresáře (rekurzivně), ponechá soubory z $keep (podle názvu).
 * Chrání před výmazem mimo aplikaci. Vrací počet smazaných souborů.
 */
function dir_delete_contents(string $absDir, array $keep = []): int
{
    $real = realpath($absDir);
    $root = realpath(KF_ROOT);
    if ($real === false || $root === false || $real === $root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        return 0;
    }
    $count = 0;
    foreach (scandir($real) ?: [] as $name) {
        if ($name === '.' || $name === '..' || in_array($name, $keep, true)) {
            continue;
        }
        $path = $real . DIRECTORY_SEPARATOR . $name;
        if (is_dir($path) && !is_link($path)) {
            $count += dir_delete_contents($path, $keep);
            @rmdir($path);
        } elseif (@unlink($path)) {
            $count++;
        }
    }
    return $count;
}

/** Počet souborů v adresáři (rekurzivně) bez ponechaných souborů. */
function dir_count_files(string $absDir, array $keep = []): int
{
    if (!is_dir($absDir)) {
        return 0;
    }
    $n = 0;
    foreach (scandir($absDir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || in_array($name, $keep, true)) {
            continue;
        }
        $p = $absDir . DIRECTORY_SEPARATOR . $name;
        $n += is_dir($p) ? dir_count_files($p, $keep) : 1;
    }
    return $n;
}

/** Cron: smaže dočasné soubory (náhledy obrázků, drafty) starší než storage.tmp_ttl_sec. */
function tmp_cleanup(): int
{
    $dir = abs_path((string)cfg('storage.tmp_dir'));
    if (!is_dir($dir)) {
        return 0;
    }
    $keep = (array)cfg('install.wipe_keep');
    $limit = time() - (int)cfg('storage.tmp_ttl_sec');
    $n = 0;
    foreach (scandir($dir) ?: [] as $name) {
        $p = $dir . '/' . $name;
        if ($name === '.' || $name === '..' || in_array($name, $keep, true) || !is_file($p)) {
            continue;
        }
        if ((int)filemtime($p) < $limit && @unlink($p)) {
            $n++;
        }
    }
    return $n;
}
