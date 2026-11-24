<?php
// src/db.php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $charset = (string)cfg('db.charset');
        $collation = (string)cfg('db.collation');
        if (!preg_match('/^[a-z0-9_]+$/', $charset) || !preg_match('/^[a-z0-9_]+$/', $collation)) {
            throw new RuntimeException('Neplatná db.charset nebo db.collation.');
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            (string)env('DB_HOST', 'localhost'),
            (string)env('DB_PORT', '3306'),
            (string)env('DB_NAME', ''),
            $charset
        );
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => (bool)cfg('db.emulate_prepares'),
        ];
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $opts[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        }
        $pdo = new PDO($dsn, (string)env('DB_USER', ''), (string)env('DB_PASS', ''), $opts);
        $pdo->exec('SET NAMES ' . $charset . ' COLLATE ' . $collation);
    }
    return $pdo;
}

/** Název tabulky včetně prefixu a zpětných apostrofů (klíč z cfg('db.tables')). */
function tbl(string $key): string
{
    $name = cfg('db.tables.' . $key);
    if (!is_string($name) || $name === '') {
        throw new InvalidArgumentException('Neznámá tabulka: ' . $key);
    }
    return '`' . cfg('db.prefix') . $name . '`';
}

/** Prepared statement: vrací provedený PDOStatement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
