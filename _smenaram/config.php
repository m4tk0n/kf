<?php
// Samostatná konfigurace aplikace SměnářAM.
// Načítá pouze vlastní soubor _smenaram/.env.
declare(strict_types=1);

$envFile = __DIR__ . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[-1] === '"') ||
            ($value[0] === "'" && $value[-1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
    }
}

define('DB_HOST', (string)($_ENV['DB_HOST'] ?? 'localhost'));
define('DB_PORT', (string)($_ENV['DB_PORT'] ?? '3306'));
define('DB_NAME', (string)($_ENV['DB_NAME'] ?? ''));
define('DB_USER', (string)($_ENV['DB_USER'] ?? ''));
define('DB_PASS', (string)($_ENV['DB_PASS'] ?? ''));
define('DB_PREFIX', (string)($_ENV['DB_PREFIX'] ?? ''));

function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        if (DB_NAME === '' || DB_USER === '') {
            throw new RuntimeException('V _smenaram/.env chybí DB_NAME nebo DB_USER.');
        }

        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}
