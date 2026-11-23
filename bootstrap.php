<?php
// bootstrap.php
declare(strict_types=1);

define('KF_ROOT', __DIR__);

require_once KF_ROOT . '/src/core.php';

$kfSrc = KF_ROOT . '/' . cfg('paths.src');
// compat.php je poslední: doplňuje jen funkce, které ještě neexistují
foreach (['storage', 'security', 'debug', 'lang', 'catalog', 'i18n', 'session', 'oauth', 'users', 'views', 'detail', 'routes', 'compat'] as $kfModule) {
    require_once $kfSrc . '/' . $kfModule . '.php';
}
unset($kfSrc, $kfModule);

mb_internal_encoding('UTF-8');
date_default_timezone_set((string)cfg('app.timezone'));
error_reporting(E_ALL);
ini_set('display_errors', cfg('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', abs_path((string)cfg('storage.error_log_file')));

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    kf_debug_log('PHP EXCEPTION', [
        'class' => get_class($e),
        'message' => $e->getMessage(),
        'file' => str_replace(KF_ROOT . '/', '', $e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo cfg('app.debug') ? (string)$e : 'Chyba serveru (500).';
});

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        kf_debug_log('PHP FATAL', [
            'message' => $last['message'] ?? 'Fatal error',
            'file' => isset($last['file']) ? str_replace(KF_ROOT . '/', '', (string)$last['file']) : '',
            'line' => (int)($last['line'] ?? 0),
        ]);
    }
});
