<?php
// src/debug.php
declare(strict_types=1);

/** Jednotné strukturované logování PHP chyb a diagnostických událostí. */
function kf_debug_log(string $type, array $context = []): void
{
    try {
        $uri = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $record = [
            'time' => date('c'),
            'request_id' => kf_debug_request_id(),
            'type' => $type,
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'path' => substr($uri, 0, 300),
            'context' => $context,
        ];
        log_append((string)cfg('storage.error_log_file'), (string)json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } catch (Throwable) {
        // Diagnostika nesmí způsobit další chybu.
    }
}

function kf_debug_request_id(): string
{
    static $id = null;
    if ($id === null) {
        try {
            $id = bin2hex(random_bytes(6));
        } catch (Throwable) {
            $id = (string)mt_rand(100000, 999999);
        }
    }
    return $id;
}

/** DEBUG mohou používat hlavní účet (ID 2) a administrátoři. */
function kf_debug_user_allowed(?array $user): bool
{
    return $user !== null
        && ((int)($user['id'] ?? 0) === 2 || (int)($user['role'] ?? 0) >= role_value('admin'));
}

/** DEBUG je aktivní pouze tehdy, když si jej oprávněný uživatel zapnul v nastavení. */
function kf_debug_user_active(?array $user): bool
{
    if (!kf_debug_user_allowed($user)) {
        return false;
    }
    $profile = user_profile_get((int)$user['id']);
    return !empty($profile['debug_enabled']);
}

/** Skript plovoucího diagnostického panelu připojí jen při aktivním nastavení. */
function kf_debug_script(?array $user): string
{
    if (!kf_debug_user_active($user)) {
        return '';
    }

    return '<link rel="stylesheet" href="' . e(asset('css/debug.css')) . '">'
        . '<script src="' . e(asset('js/debug.js')) . '" defer></script>';
}

/** Poslední diagnostické záznamy pro ověřeného správce/debug klienta. */
function kf_debug_log_tail(int $limit = 80): array
{
    $file = abs_path((string)cfg('storage.error_log_file'));
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }
    $out = [];
    foreach (array_slice($lines, -max(1, min($limit, 150))) as $line) {
        $decoded = json_decode((string)$line, true);
        $out[] = is_array($decoded)
            ? $decoded
            : ['time' => '', 'type' => 'PHP LOG', 'context' => ['message' => (string)$line]];
    }
    return $out;
}
