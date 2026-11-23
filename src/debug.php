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
    $mode = (string)($_GET['debug'] ?? '');
    $cookieName = 'KF_DEBUG';

    if ($mode === '0') {
        if (!headers_sent()) {
            $path = (string)(parse_url(url(''), PHP_URL_PATH) ?: '/');
            setcookie($cookieName, '', [
                'expires' => time() - 3600,
                'path' => $path,
                'secure' => request_is_https(),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }
        return false;
    }

    $key = trim((string)env('DEBUG_KEY', ''));
    $keyCookie = $key !== '' ? hash('sha256', $key) : '';
    $appKey = (string)env('APP_KEY', '');
    $adminCookie = $appKey !== '' ? hash_hmac('sha256', 'kf-independent-debug-admin', $appKey) : '';
    $cookieValue = (string)($_COOKIE[$cookieName] ?? '');
    $isAdmin = $user !== null && (int)($user['role'] ?? 0) >= role_value('admin');

    $authorized = false;
    $cookieToSet = '';

    if ($mode === '1' && $isAdmin && $adminCookie !== '') {
        $authorized = true;
        $cookieToSet = $adminCookie;
    } elseif ($mode === '1' && $key !== '') {
        $provided = (string)($_GET['debug_key'] ?? '');
        if ($provided !== '' && hash_equals($key, $provided)) {
            $authorized = true;
            $cookieToSet = $keyCookie;
        }
    }

    if (!$authorized && $isAdmin && $adminCookie !== '' && $cookieValue !== '' && hash_equals($adminCookie, $cookieValue)) {
        $authorized = true;
    }
    if (!$authorized && $keyCookie !== '' && $cookieValue !== '' && hash_equals($keyCookie, $cookieValue)) {
        $authorized = true;
    }

    if (!$authorized) {
        return false;
    }

    if ($cookieToSet !== '' && !headers_sent()) {
        $path = (string)(parse_url(url(''), PHP_URL_PATH) ?: '/');
        setcookie($cookieName, $cookieToSet, [
            'expires' => time() + 1800,
            'path' => $path,
            'secure' => request_is_https(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    if (!headers_sent()) {
        header('X-KF-Request-ID: ' . kf_debug_request_id());
    }
    return true;
}
