<?php
// src/compat.php
declare(strict_types=1);

// Pomocné funkce, které se definují jen tehdy, když je ještě nedefinoval jiný modul.

if (!function_exists('base_path')) {
    function base_path(): string
    {
        $p = (string)(parse_url((string)env('BASE_URL', '/'), PHP_URL_PATH) ?: '/');
        $p = '/' . trim($p, '/') . '/';
        return $p === '//' ? '/' : $p;
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        return rtrim((string)env('BASE_URL', ''), '/') . '/';
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return base_url() . ltrim($path, '/');
    }
}

if (!function_exists('e')) {
    function e(?string $s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('random_hex')) {
    function random_hex(int $bytes): string
    {
        return bin2hex(random_bytes(max(1, $bytes)));
    }
}

if (!function_exists('req_path')) {
    /** Cesta požadavku relativně k BASE_URL, bez lomítek na okrajích. */
    function req_path(): string
    {
        $uri = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $base = base_path();
        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        return trim(rawurldecode($uri), '/');
    }
}

if (!function_exists('safe_next')) {
    /** Návratová cesta po přihlášení: jen relativní cesta v rámci aplikace. */
    function safe_next(string $n, string $default = ''): string
    {
        $n = ltrim(trim($n), '/');
        if ($n === '' || strlen($n) > 300 || !preg_match('~^[A-Za-z0-9_\-./?=&%]+$~', $n)
            || str_contains($n, '..') || str_contains($n, '//')) {
            return $default;
        }
        return $n;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path = '', int $code = 303): never
    {
        header('Location: ' . url($path), true, $code);
        exit;
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        render_error_page($code, $message);
    }
}

// ---------- JSON soubory ----------

if (!function_exists('json_read_file')) {
    function json_read_file(string $rel): array
    {
        $f = abs_path($rel);
        if (!is_file($f)) {
            return [];
        }
        $d = json_decode((string)file_get_contents($f), true);
        return is_array($d) ? $d : [];
    }
}

if (!function_exists('json_write_file')) {
    function json_write_file(string $rel, array $data, bool $pretty = false): void
    {
        $f = abs_path($rel);
        ensure_dir(dirname($f));
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        $json = json_encode($data, $flags) . ($pretty ? "\n" : '');
        $written = file_put_contents($f, $json, LOCK_EX);
        if ($written === false || $written !== strlen($json)) {
            throw new RuntimeException('Nelze uložit JSON soubor: ' . $rel);
        }
    }
}

if (!function_exists('json_bucket_rel')) {
    /** data/<bucket>/<id div divisor>/<id>.json */
    function json_bucket_rel(string $bucket, int $id): string
    {
        $dir = (string)cfg('storage.json_buckets.' . $bucket);
        $div = max(1, (int)cfg('img.folder_divisor'));
        return $dir . '/' . intdiv($id, $div) . '/' . $id . '.json';
    }
}

if (!function_exists('json_remove')) {
    function json_remove(string $bucket, int $id): void
    {
        @unlink(abs_path(json_bucket_rel($bucket, $id)));
    }
}

// ---------- profil uživatele (data/users) ----------

if (!function_exists('user_profile_rel')) {
    function user_profile_rel(int $id): string
    {
        $div = max(1, (int)cfg('img.folder_divisor'));
        return (string)cfg('storage.users_dir') . '/' . intdiv($id, $div) . '/' . $id . '.json';
    }
}

if (!function_exists('user_profile_get')) {
    function user_profile_get(int $id): array
    {
        static $cache = [];
        return $cache[$id] ??= json_read_file(user_profile_rel($id));
    }
}

if (!function_exists('user_profile_put')) {
    function user_profile_put(int $id, array $data): void
    {
        json_write_file(user_profile_rel($id), $data, true);
    }
}

if (!function_exists('user_profile_delete')) {
    function user_profile_delete(int $id): void
    {
        @unlink(abs_path(user_profile_rel($id)));
    }
}

if (!function_exists('draft_rel')) {
    function draft_rel(int $userId): string
    {
        return sprintf((string)cfg('storage.draft_file'), $userId);
    }
}

// ---------- cron úlohy ----------

if (!function_exists('tmp_cleanup')) {
    function tmp_cleanup(): void
    {
        $dir = abs_path((string)cfg('storage.tmp_dir'));
        if (!is_dir($dir)) {
            return;
        }
        $limit = time() - (int)cfg('storage.tmp_ttl_sec');
        foreach (scandir($dir) ?: [] as $name) {
            $p = $dir . '/' . $name;
            if ($name === '.' || $name === '..' || $name === '.htaccess' || $name === 'index.html' || !is_file($p)) {
                continue;
            }
            if ((int)filemtime($p) < $limit) {
                @unlink($p);
            }
        }
    }
}

if (!function_exists('ratelimit_cleanup')) {
    function ratelimit_cleanup(): void
    {
        $cap = (int)cfg('limits.rate_limit_max_entries');
        json_locked((string)cfg('storage.ratelimit_file'), static function (array &$d) use ($cap): bool {
            ratelimit_purge($d, time(), $cap);
            return true;
        });
    }
}
