<?php
// src/core.php
declare(strict_types=1);

if (!defined('KF_ROOT')) define('KF_ROOT', dirname(__DIR__));

function env(string $key, mixed $default = null): mixed
{
    static $env = null;
    if ($env === null) {
        $env = [];
        $file = KF_ROOT . '/.env';
        $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) $v = substr($v, 1, -1);
            $env[trim($k)] = $v;
        }
    }
    return $env[$key] ?? $default;
}

function cfg(string $key, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require KF_ROOT . '/config.php';
        $cfg['db']['prefix'] = (string)env('TABLE_PREFIX', '');
    }
    $v = $cfg;
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) return $default;
        $v = $v[$part];
    }
    return $v;
}

function abs_path(string $rel): string
{
    return KF_ROOT . '/' . ltrim($rel, '/');
}

function url(string $path = ''): string
{
    return rtrim((string)env('BASE_URL', '/'), '/') . '/' . ltrim($path, '/');
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_security_headers(bool $noStore = false): void
{
    foreach ((array)cfg('security.headers') as $name => $value) header($name . ': ' . $value);
    header('Content-Security-Policy: ' . cfg('security.csp'));
    if ($noStore) header('Cache-Control: no-store');
}

/** Sjednotí typografické pomlčky ve všech textových hodnotách aplikace. */
function kf_text_normalize(string $value): string
{
    return strtr($value, ['–' => '-', '—' => '-', '‑' => '-', '−' => '-']);
}
