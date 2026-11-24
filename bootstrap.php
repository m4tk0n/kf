<?php
// bootstrap.php
declare(strict_types=1);

define('KF_ROOT', __DIR__);

require_once KF_ROOT . '/src/core.php';

$kfSrc = KF_ROOT . '/' . cfg('paths.src');
// compat.php je poslední: doplňuje jen funkce, které ještě neexistují
foreach (['storage', 'security', 'lang', 'catalog', 'i18n', 'session', 'oauth', 'users', 'views', 'detail', 'routes', 'compat'] as $kfModule) {
    require_once $kfSrc . '/' . $kfModule . '.php';
}
unset($kfSrc, $kfModule);

mb_internal_encoding('UTF-8');
date_default_timezone_set((string)cfg('app.timezone'));
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', abs_path((string)cfg('storage.error_log_file')));
