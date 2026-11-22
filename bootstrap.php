<?php
// bootstrap.php
declare(strict_types=1);

define('KF_ROOT', __DIR__);

// Aktuální aplikace používá core + storage/security + lang/catalog.
// db.php a session.php jsou starší/alternativní moduly s duplicitními funkcemi;
// core.php už obsahuje db(), q() a tbl(), security.php obsahuje společné
// bezpečnostní helpery. Proto je zde nenačítáme.
require_once KF_ROOT . '/src/core.php';

$kfSrc = KF_ROOT . '/' . cfg('paths.src');
foreach (['storage', 'security', 'lang', 'catalog'] as $kfModule) {
    require_once $kfSrc . '/' . $kfModule . '.php';
}
unset($kfSrc, $kfModule);

/**
 * Instalátor po úspěšném smazání dat zneplatní přihlašovací cookie.
 * Funkce je v bootstrapu, protože starý session.php není kompatibilní
 * s aktuální sadou modulů a jeho načtení by způsobilo redeklarace funkcí.
 */
if (!function_exists('session_clear_cookie')) {
    function session_clear_cookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $cookiePath = cfg('session.cookie_path');
        if (!is_string($cookiePath) || $cookiePath === '') {
            $basePath = (string)(parse_url((string)env('BASE_URL', '/'), PHP_URL_PATH) ?: '/');
            $cookiePath = '/' . trim($basePath, '/') . '/';
            if ($cookiePath === '//') {
                $cookiePath = '/';
            }
        }

        setcookie((string)cfg('session.name'), '', [
            'expires' => time() - 86400,
            'path' => $cookiePath,
            'secure' => (bool)cfg('session.cookie_secure_auto') ? request_is_https() : true,
            'httponly' => (bool)cfg('session.cookie_httponly'),
            'samesite' => (string)cfg('session.cookie_samesite'),
        ]);
    }
}

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
        log_append(
            (string)cfg('storage.error_log_file'),
            date('c') . ' ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()
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
