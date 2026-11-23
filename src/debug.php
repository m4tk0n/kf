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

/**
 * Debug je dostupný adminovi, nebo hostovi s tajným DEBUG_KEY z .env.
 * Aktivuje se vždy výslovným parametrem ?debug=1.
 */
function kf_debug_enabled(?array $user): bool
{
    if ((string)($_GET['debug'] ?? '') !== '1') {
        return false;
    }

    if ($user !== null && (int)($user['role'] ?? 0) >= role_value('admin')) {
        return true;
    }

    $key = trim((string)env('DEBUG_KEY', ''));
    if ($key === '') {
        return false;
    }

    $cookieName = 'KF_DEBUG';
    $cookieValue = (string)($_COOKIE[$cookieName] ?? '');
    $expected = hash('sha256', $key);
    if ($cookieValue !== '' && hash_equals($expected, $cookieValue)) {
        return true;
    }

    $provided = (string)($_GET['debug_key'] ?? '');
    if ($provided === '' || !hash_equals($key, $provided)) {
        return false;
    }

    $basePath = (string)(parse_url(url(''), PHP_URL_PATH) ?: '/');
    if (!headers_sent()) {
        setcookie($cookieName, $expected, [
            'expires' => time() + 1800,
            'path' => $basePath,
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    return true;
}
