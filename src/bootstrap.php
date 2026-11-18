<?php
declare(strict_types=1);

define('KF_ROOT', dirname(__DIR__));
require __DIR__ . '/helpers.php';

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

// Browser stores only the opaque PHP session ID. All session data stays server-side in MySQL.
if (!extension_loaded('sodium')) {
    throw new RuntimeException('The sodium PHP extension is required for encrypted database sessions.');
}
require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/auth.php';

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
ini_set('session.lazy_write', '0');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
$sessionHandler = new KfDatabaseSessionHandler();
session_set_save_handler($sessionHandler, true);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
