<?php
// bootstrap.php
declare(strict_types=1);

define('KF_ROOT', __DIR__);

$kfCfg = require KF_ROOT . '/config.php';
$kfSrc = KF_ROOT . '/' . $kfCfg['paths']['src'];
unset($kfCfg);
foreach (['core', 'db', 'storage', 'security', 'session', 'http', 'i18n', 'mail'] as $kfModule) {
    require_once $kfSrc . '/' . $kfModule . '.php';
}
unset($kfSrc, $kfModule);

mb_internal_encoding('UTF-8');
date_default_timezone_set((string)cfg('app.timezone'));
error_reporting(E_ALL);
ini_set('display_errors', cfg('app.debug') ? '1' : '0');
ini_set('log_errors', '0');

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    try {
        log_append((string)cfg('storage.error_log_file'), get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    } catch (Throwable) {
        // logování nesmí způsobit další chybu
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo cfg('app.debug') ? (string)$e : 'Chyba serveru (500).';
});
