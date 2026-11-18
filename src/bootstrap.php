<?php
declare(strict_types=1);

define('KF_ROOT', dirname(__DIR__));
require __DIR__ . '/helpers.php';
require __DIR__ . '/moderation.php';

load_env(KF_ROOT . '/.env');
$GLOBALS['KF_CFG'] = require KF_ROOT . '/config.php';

if (!preg_match('/^[a-z0-9]{1,10}_$/', cfg('db.prefix'))) {
    throw new RuntimeException('Invalid TABLE_PREFIX (a-z, 0-9, trailing underscore)');
}
date_default_timezone_set(cfg('app.timezone'));
error_reporting(E_ALL);
ini_set('display_errors', cfg('app.debug') ? '1' : '0');

// Security headers are sent before any application output.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Permitted-Cross-Domain-Policies: none');
if (strtolower((string)parse_url(cfg('app.base_url'), PHP_URL_SCHEME)) === 'https') {
    header('Strict-Transport-Security: max-age=31536000');
}

// Sessions are persisted in the prefixed database table; only the random ID is a cookie.
$https = strtolower((string)parse_url(cfg('app.base_url'), PHP_URL_SCHEME)) === 'https';
$cookiePath = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/') . '/';
session_name('KFSESSID');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $https ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.sid_length', '48');
ini_set('session.sid_bits_per_character', '6');
ini_set('session.gc_maxlifetime', (string)KF_SESSION_ABSOLUTE_TTL);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookiePath,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $sessionTable = tbl('sessions');
    $tableCheck = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $tableCheck->execute([cfg('db.prefix') . 'sessions']);
    $hasSessionTable = (int)$tableCheck->fetchColumn() === 1;
    $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $isInstaller = str_contains($requestPath, '/install');

    if ($hasSessionTable) {
        session_set_save_handler(new KfDatabaseSessionHandler(db(), $sessionTable), true);
    } elseif (!$isInstaller) {
        throw new RuntimeException('Database sessions table is missing. Run the K&F installer.');
    }

    session_start();
}

