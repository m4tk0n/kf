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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => str_starts_with(cfg('app.base_url'), 'https'),
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}
