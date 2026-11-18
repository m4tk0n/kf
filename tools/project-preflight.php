<?php
declare(strict_types=1);

/**
 * Read-only K&F preflight.
 * Run: php tools/project-preflight.php
 * Does not connect to the live server or database and never reads .env.
 */

$root = dirname(__DIR__);
$checks = [];
$failures = [];

function inspect(string $label, bool $ok, string $detail, array &$checks, array &$failures): void
{
    $checks[] = [$label, $ok, $detail];
    if (!$ok) {
        $failures[] = $label . ': ' . $detail;
    }
}

$required = [
    'config.php',
    'src/bootstrap.php',
    'src/helpers.php',
    'src/google_auth.php',
    'index.php',
    'install/index.php',
    'install/sql/001_schema.sql',
    'templates/layout.php',
    'lang/cs/common.ini',
    'lang/en/common.ini',
    '.github/workflows/deploy-to-ftp.yml',
    '.github/workflows/project-verification.yml',
];

foreach ($required as $relative) {
    inspect('File ' . $relative, is_file($root . '/' . $relative), 'required file exists', $checks, $failures);
}

$config = is_file($root . '/config.php') ? (string)file_get_contents($root . '/config.php') : '';
$bootstrap = is_file($root . '/src/bootstrap.php') ? (string)file_get_contents($root . '/src/bootstrap.php') : '';
$helpers = is_file($root . '/src/helpers.php') ? (string)file_get_contents($root . '/src/helpers.php') : '';
$deploy = is_file($root . '/.github/workflows/deploy-to-ftp.yml') ? (string)file_get_contents($root . '/.github/workflows/deploy-to-ftp.yml') : '';

inspect('PHP target', str_contains($config, "'min_php'    => '8.3.0'"), 'config declares PHP 8.3 minimum', $checks, $failures);
inspect('Dynamic DB prefix', str_contains($config, "env('TABLE_PREFIX')") && str_contains($helpers, "cfg('db.prefix')"), 'table prefix comes from TABLE_PREFIX', $checks, $failures);
inspect('No putenv', !str_contains($helpers . $bootstrap, 'putenv('), 'environment loader does not call putenv()', $checks, $failures);
inspect('Database sessions', str_contains($bootstrap, 'KfDatabaseSessionHandler') && str_contains($bootstrap, "session_name('KFSESSID')"), 'sessions use the database handler and KFSESSID cookie', $checks, $failures);
inspect('Admin role', (bool)preg_match("/'role'\\s*=>\\s*\\[[^]]*4\\s*=>\\s*'admin'/s", $config), 'role 4 is admin', $checks, $failures);
inspect('.env excluded from deploy', (bool)preg_match('/\\.env/', $deploy), 'FTP workflow contains an explicit .env exclusion', $checks, $failures);

if ($failures !== []) {
    fwrite(STDERR, "K&F preflight FAILED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

foreach ($checks as [$label, $ok, $detail]) {
    echo '[OK] ' . $label . ' — ' . $detail . PHP_EOL;
}
echo 'K&F preflight passed (' . count($checks) . ' checks).' . PHP_EOL;
echo "Scope: repository files only; no live DB, FTP, server or browser checks.\n";
