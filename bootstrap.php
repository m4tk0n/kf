<?php
// bootstrap.php
declare(strict_types=1);

define('KF_ROOT', __DIR__);

// core musí být první (cfg(), env()); ostatní moduly se načítají podle cfg('paths.src')
require_once KF_ROOT . '/src/core.php';

foreach (['lang', 'catalog'] as $kfModule) {
    require_once KF_ROOT . '/' . cfg('paths.src') . '/' . $kfModule . '.php';
}
unset($kfModule);

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
        @file_put_contents(
            abs_path((string)cfg('storage.error_log_file')),
            date('c') . ' ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n",
            FILE_APPEND | LOCK_EX
        );
    } catch (Throwable) {
        // logování nesmí způsobit další chybu
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo cfg('app.debug') ? (string)$e : 'Chyba serveru (500).';
});
