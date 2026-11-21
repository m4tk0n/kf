<?php
// src/core.php
declare(strict_types=1);

/** Hodnota z .env (soubor se načte jednou). */
function env(string $key, ?string $default = null): ?string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = KF_ROOT . '/.env';
        if (is_readable($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $i => $line) {
                if ($i === 0) {
                    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
                }
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if ($v !== '' && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0] && strlen($v) >= 2) {
                    $q = $v[0];
                    $v = substr($v, 1, -1);
                    if ($q === '"') {
                        $v = str_replace(['\\"', '\\\\'], ['"', '\\'], $v);
                    }
                } else {
                    $v = trim(preg_replace('/\s+#.*$/', '', $v) ?? $v);
                }
                if (preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) {
                    $vars[$k] = $v;
                }
            }
        }
    }
    return array_key_exists($key, $vars) ? $vars[$key] : $default;
}

/** Hodnota z config.php podle cesty „sekce.klic“ (prefix tabulek, ladění a e-mail se přepisují z .env). */
function cfg(string $path, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require KF_ROOT . '/config.php';
        $prefix = (string)env('TABLE_PREFIX', (string)($cfg['db']['prefix'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_]*$/', $prefix)) {
            throw new RuntimeException('TABLE_PREFIX smí obsahovat jen písmena, číslice a podtržítko.');
        }
        $cfg['db']['prefix'] = $prefix;
        if (env('APP_ENV') === 'development') {
            $cfg['app']['debug'] = true;
        }
        $transport = (string)env('MAIL_TRANSPORT', '');
        if ($transport !== '') {
            $cfg['mail']['transport'] = $transport;
        }
    }
    $node = $cfg;
    foreach (explode('.', $path) as $seg) {
        if (!is_array($node) || !array_key_exists($seg, $node)) {
            return $default;
        }
        $node = $node[$seg];
    }
    return $node;
}

function base_url(): string
{
    $b = trim((string)env('BASE_URL', ''));
    if ($b === '') {
        throw new RuntimeException('BASE_URL chybí v .env.');
    }
    return rtrim($b, '/') . '/';
}

function base_path(): string
{
    return (string)(parse_url(base_url(), PHP_URL_PATH) ?: '/');
}

function url(string $path = ''): string
{
    return base_url() . ltrim($path, '/');
}

/** Absolutní cesta v rámci aplikace; zakazuje „..“. */
function abs_path(string $rel): string
{
    if (str_contains($rel, '..')) {
        throw new InvalidArgumentException('Neplatná cesta.');
    }
    return KF_ROOT . '/' . ltrim($rel, '/');
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function random_hex(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

/** Cesta požadavku relativně k BASE_URL (bez lomítek na okrajích). */
function req_path(): string
{
    static $p = null;
    if ($p === null) {
        $uri = rawurldecode((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
        $base = base_path();
        if (str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        } elseif (rtrim($uri, '/') . '/' === $base) {
            $uri = '';
        }
        $uri = trim($uri, '/');
        if ($uri === 'index.php' || str_starts_with($uri, 'index.php/')) {
            $uri = trim(substr($uri, 9), '/');
        }
        $p = $uri;
    }
    return $p;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function wants_json(): bool
{
    $ep = trim((string)cfg('ai_import.endpoint'), '/');
    $p = req_path();
    return $ep !== '' && ($p === $ep || str_starts_with($p, $ep . '/'));
}

function json_out(array $data, int $status = 200): never
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code, string $message = ''): never
{
    if (wants_json()) {
        json_out(['error' => $message !== '' ? $message : 'error', 'status' => $code], $code);
    }
    if (function_exists('render_error_page') && !headers_sent()) {
        try {
            render_error_page($code, $message);
        } catch (Throwable) {
            // záložní prostá stránka níže
        }
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
    }
    $title = $code . ($message !== '' ? ' – ' . $message : '');
    echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title><h1>' . e($title) . '</h1>';
    exit;
}

/** Přesměrování jen v rámci aplikace (relativní cesta nebo URL pod BASE_URL). */
function redirect(string $target, int $status = 303): never
{
    $target = safe_next($target, '');
    header('Location: ' . (str_starts_with($target, 'http') ? $target : url($target)), true, $status);
    exit;
}

/** Ověří návratový parametr: smí být jen relativní cesta uvnitř aplikace, jinak $fallback. */
function safe_next(string $next, string $fallback = ''): string
{
    $next = trim($next);
    if ($next === '') {
        return $fallback;
    }
    if (str_starts_with($next, base_url())) {
        return $next;
    }
    if (preg_match('~^[A-Za-z][A-Za-z0-9+.-]*:~', $next) || str_starts_with($next, '//') || str_contains($next, '\\')
        || preg_match('/[\x00-\x1F]/', $next)) {
        return $fallback;
    }
    return ltrim($next, '/');
}

/** Název bez diakritiky malými písmeny (pro name_ascii a našeptávač). */
function ascii_fold(string $s): string
{
    static $tr = false;
    if ($tr === false) {
        $tr = class_exists('Transliterator') ? Transliterator::create('Any-Latin; Latin-ASCII; Lower()') : null;
    }
    if ($tr instanceof Transliterator) {
        $out = $tr->transliterate($s);
        $s = is_string($out) ? $out : $s;
    } else {
        $s = strtr(mb_strtolower($s), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ą' => 'a', 'ā' => 'a',
            'č' => 'c', 'ć' => 'c', 'ç' => 'c', 'ď' => 'd', 'đ' => 'd',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ě' => 'e', 'ę' => 'e', 'ē' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
            'ĺ' => 'l', 'ľ' => 'l', 'ł' => 'l', 'ň' => 'n', 'ń' => 'n', 'ñ' => 'n',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ő' => 'o', 'ø' => 'o', 'ō' => 'o',
            'ř' => 'r', 'ŕ' => 'r', 'š' => 's', 'ś' => 's', 'ş' => 's', 'ß' => 'ss', 'ť' => 't', 'ţ' => 't',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ů' => 'u', 'ű' => 'u', 'ū' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ž' => 'z', 'ź' => 'z', 'ż' => 'z', 'æ' => 'ae', 'œ' => 'oe',
        ]);
    }
    return trim(preg_replace('/\s+/u', ' ', mb_strtolower($s)) ?? $s);
}

function slugify(string $s, int $max = 100): string
{
    $s = preg_replace('/[^a-z0-9]+/', '-', ascii_fold($s)) ?? '';
    $s = trim(substr(trim($s, '-'), 0, $max), '-');
    return $s !== '' ? $s : 'item';
}
