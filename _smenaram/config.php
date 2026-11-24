<?php
// SměnářAM používá stejné připojení a .env jako hlavní aplikace K&F.
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/core.php';

if (!defined('DB_PREFIX')) {
    define('DB_PREFIX', (string)cfg('db.prefix', ''));
}

if (!function_exists('getDbConnection')) {
    function getDbConnection(): PDO
    {
        return db();
    }
}
