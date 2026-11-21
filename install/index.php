<?php
// install/index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
send_security_headers(true);

function inst_token_make(int $exp): string
{
    return $exp . '.' . hash_hmac('sha256', 'install|' . $exp . '|' . ua_hash_hex(), (string)env('APP_KEY', ''));
}

function inst_token_ok(string $t): bool
{
    $parts = explode('.', $t, 2);
    if (count($parts) !== 2 || !ctype_digit($parts[0]) || (int)$parts[0] < time()) {
        return false;
    }
    return hash_equals(inst_token_make((int)$parts[0]), $t);
}

/** @return array<string,string> */
function inst_sql_files(string $relDir): array
{
    $dir = abs_path($relDir);
    $out = [];
    if (!is_dir($dir)) {
        return $out;
    }
    foreach (glob($dir . '/*.sql') ?: [] as $f) {
        $out[$f] = basename($f);
    }
    asort($out, SORT_NATURAL);
    return $out;
}

/** @return string[] */
function inst_split(string $sql): array
{
    $out = [];
    $buf = '';
    foreach (preg_split('/\r\n|\r|\n/', $sql) ?: [] as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#')) {
            continue;
        }
        $buf .= $line . "\n";
        if (str_ends_with($t, ';')) {
            $out[] = trim($buf);
            $buf = '';
        }
    }
    if (trim($buf) !== '') {
        $out[] = trim($buf);
    }
    return $out;
}

function inst_parse_table_sql(string $filledSql, string $fallbackName): array
{
    $dropSql = null;
    $createSql = null;
    $tableName = $fallbackName;

    foreach (inst_split($filledSql) as $stmt) {
        if ($dropSql === null && preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?\s*;$/is', $stmt, $m)) {
            $dropSql = $stmt;
            $tableName = $m[1];
            continue;
        }
        if ($createSql === null && preg_match('/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?\s*\(/is', $stmt, $m)) {
            $createSql = $stmt;
            $tableName = $m[1];
        }
    }

    return [
        'table' => $tableName,
        'drop' => $dropSql,
        'create' => $createSql,
    ];
}

function inst_exec_retry(PDO $pdo, string $sql): void
{
    $maxAttempts = 5;
    for ($attempt = 1; ; $attempt++) {
        try {
            $pdo->exec($sql);
            return;
        } catch (PDOException $e) {
            $driverCode = (int)($e->errorInfo[1] ?? 0);
            $retryable = $driverCode === 1213 || $driverCode === 1205 || (string)$e->getCode() === '40001';
            if (!$retryable || $attempt >= $maxAttempts) {
                throw $e;
            }
            usleep(200000 * $attempt);
        }
    }
}

function inst_fill(string $sql, PDO $pdo): string
{
    $esc = static fn(string $v): string => substr($pdo->quote($v), 1, -1);
    $sql = strtr($sql, [
        '{prefix}' => (string)cfg('db.prefix'),
        '{charset}' => (string)cfg('db.charset'),
        '{collation}' => (string)cfg('db.collation'),
        '{ai_user_id}' => (string)(int)cfg('users.ai_user_id'),
        '{ai_name}' => $esc((string)cfg('users.ai_name')),
    ]);
    if (preg_match('/\{[a-z_]+\}/', $sql, $m)) {
        throw new RuntimeException('Nevyřešený zástupný znak ' . $m[0]);
    }
    return $sql;
}

function inst_table_of(string $file): string
{
    return (string)preg_replace('/^\d+_(.+)\.sql$/', '$1', $file);
}

function inst_oauth_providers(): array
{
    $ready = [];
    $pairs = [
        'google' => ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'],
        'facebook' => ['FACEBOOK_APP_ID', 'FACEBOOK_APP_SECRET'],
    ];
    foreach ($pairs as $name => [$id, $secret]) {
        if (cfg('oauth.' . $name . '.enabled') && (string)env($id, '') !== '' && (string)env($secret, '') !== '') {
            $ready[] = $name;
        }
    }
    return $ready;
}

function inst_login_url(string $provider): string
{
    return url(str_replace('{provider}', $provider, (string)cfg('oauth.login_path')));
}

/** @return array<int,array{0:string,1:string,2:string}> */
function inst_checks(): array
{
    $c = [];
    $min = (string)cfg('install.min_php');
    $c[] = ['PHP verze', version_compare(PHP_VERSION, $min, '>=') ? 'ok' : 'err', PHP_VERSION . ' (min. ' . $min . ')'];
    foreach ((array)cfg('install.extensions') as $ext) {
        $c[] = ['Rozšíření ' . $ext, extension_loaded((string)$ext) ? 'ok' : 'err', extension_loaded((string)$ext) ? 'načteno' : 'chybí'];
    }
    $c[] = ['Rozšíření imagick', extension_loaded('imagick') ? 'ok' : 'warn', extension_loaded('imagick') ? 'načteno' : 'nepovinné, použije se GD'];
    $c[] = ['Soubor .env', is_file(KF_ROOT . '/.env') ? 'ok' : 'err', is_file(KF_ROOT . '/.env') ? 'nalezen' : 'chybí (zkopíruj env.example)'];
    $key = (string)env('APP_KEY', '');
    $c[] = ['APP_KEY', strlen($key) >= 32 && !str_starts_with($key, 'zmen-') ? 'ok' : 'err', 'min. 32 znaků'];
    $c[] = ['BASE_URL', (string)env('BASE_URL', '') !== '' ? 'ok' : 'err', (string)env('BASE_URL', '(prázdné)')];
    foreach ((array)cfg('install.writable_dirs') as $d) {
        try {
            ensure_dir(abs_path((string)$d));
            $w = is_writable(abs_path((string)$d));
        } catch (Throwable) {
            $w = false;
        }
        $c[] = ['Zapisovatelná složka ' . $d, $w ? 'ok' : 'err', $w ? 'ano' : 'ne'];
    }
    try {
        $v = (string)db()->query('SELECT VERSION()')->fetchColumn();
        $c[] = ['Spojení s DB', 'ok', $v . ' (' . (string)env('DB_NAME', '') . ')'];
    } catch (Throwable $e) {
        $c[] = ['Spojení s DB', 'err', substr($e->getMessage(), 0, 160)];
    }
    $c[] = ['IP klienta', 'ok', client_ip()];
    $c[] = ['HTTPS', request_is_https() ? 'ok' : 'warn', request_is_https() ? 'detekováno' : 'nedetekováno'];
    $prov = inst_oauth_providers();
    $c[] = ['OAuth poskytovatelé', $prov ? 'ok' : 'warn', $prov ? implode(', ', $prov) : 'není nastaven v .env'];
    return $c;
}

/** @return array<int,array{0:string,1:bool,2:int}> */
function inst_table_status(): array
{
    $out = [];
    foreach ((array)cfg('db.tables') as $key => $name) {
        $full = (string)cfg('db.prefix') . $name;
        $exists = false;
        $rows = 0;
        try {
            $exists = (bool)q('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$full])->fetchColumn();
            if ($exists) {
                $rows = (int)db()->query('SELECT COUNT(*) FROM ' . tbl((string)$key))->fetchColumn();
            }
        } catch (Throwable) {
        }
        $out[] = [$full, $exists, $rows];
    }
    return $out;
}

function inst_sql_consistency(): array
{
    $issues = [];
    $files = array_map('inst_table_of', array_values(inst_sql_files((string)cfg('install.sql_tables_dir'))));
    $cfgTables = array_values((array)cfg('db.tables'));
    foreach (array_diff($cfgTables, $files) as $t) {
        $issues[] = 'Tabulka „' . $t . '“ z configu nemá SQL soubor.';
    }
    foreach (array_diff($files, $cfgTables) as $t) {
        $issues[] = 'SQL soubor „' . $t . '“ nemá tabulku v configu.';
    }
    return $issues;
}

function inst_wipe_plan(): array
{
    $keep = (array)cfg('install.wipe_keep');
    $plan = [];
    foreach ((array)cfg('install.wipe_dirs') as $d) {
        $plan[] = [(string)$d . '/', dir_count_files(abs_path((string)$d), $keep) . ' souborů'];
    }
    foreach ((array)cfg('install.wipe_files') as $f) {
        $plan[] = [(string)$f, is_file(abs_path((string)$f)) ? 'existuje' : 'nenalezen'];
    }
    return $plan;
}

/**
 * Rekurzivně projde složku, zaloguje každý soubor/ponechaný soubor a smaže soubory mimo $keep.
 * Vrací ['deleted' => int, 'ok' => bool].
 */
function inst_process_dir_files(string $absDir, string $relPrefix, array $keep, callable $logStep): array
{
    $real = realpath($absDir);
    $root = realpath(KF_ROOT);
    if ($real === false || $root === false || $real === $root || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        $logStep('Mazu soubory', $relPrefix !== '' ? $relPrefix : $absDir, 'err', 'chyba');
        return ['deleted' => 0, 'ok' => false];
    }

    $deleted = 0;
    $allOk = true;
    $hasRemaining = false;

    foreach (scandir($real) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = $real . DIRECTORY_SEPARATOR . $name;
        $rel = $relPrefix !== '' ? $relPrefix . '/' . $name : $name;

        if (in_array($name, $keep, true)) {
            $hasRemaining = true;
            $logStep('Mazu soubory', $rel, 'info', 'ponechano');
            continue;
        }

        if (is_dir($path) && !is_link($path)) {
            $sub = inst_process_dir_files($path, $rel, $keep, $logStep);
            $deleted += $sub['deleted'];
            if (!$sub['ok']) {
                $allOk = false;
                $hasRemaining = true;
            } elseif (@rmdir($path)) {
                // prázdný podadresář odstraněn
            } else {
                $hasRemaining = true;
            }
        } elseif (is_file($path) || is_link($path)) {
            if (@unlink($path)) {
                $deleted++;
                $logStep('Mazu soubory', $rel, 'ok', 'ok');
            } else {
                $allOk = false;
                $hasRemaining = true;
                $logStep('Mazu soubory', $rel, 'err', 'chyba');
            }
        }
    }

    if (!$hasRemaining && $real !== realpath(abs_path($relPrefix))) {
        @rmdir($real);
    }

    return ['deleted' => $deleted, 'ok' => $allOk];
}

function inst_load_table_defs(PDO $pdo): array
{
    $defs = [];
    foreach (inst_sql_files((string)cfg('install.sql_tables_dir')) as $path => $name) {
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('Nelze přečíst SQL soubor: ' . $name);
        }
        $filled = inst_fill($raw, $pdo);
        $parsed = inst_parse_table_sql($filled, inst_table_of($name));
        $defs[] = [
            'file' => $name,
            'table' => $parsed['table'],
            'drop' => $parsed['drop'] ?? ('DROP TABLE IF EXISTS `' . $parsed['table'] . '`;'),
            'create' => $parsed['create'],
            'all_stmts' => inst_split($filled),
        ];
    }
    return $defs;
}

function inst_run_wipe(bool $wipeOptional, callable $logStep): array
{
    $ok = true;
    $pdo = db();
    $lockName = 'kf_install_' . substr(hash('sha256', (string)env('DB_NAME', '')), 0, 32);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        $logStep('Mazu tabulky', 'Instalace', 'err', 'chyba');
        $logStep('Mazu tabulky', 'Smazano 0 tabulek', 'err', 'chyba');
        return ['ok' => false];
    }

    $dropped = 0;
    $deletedFiles = 0;

    try {
        $tableDefs = inst_load_table_defs($pdo);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tableDefs as $tDef) {
            try {
                inst_exec_retry($pdo, $tDef['drop']);
                $dropped++;
                $logStep('Mazu tabulky', $tDef['table'], 'ok', 'ok');
            } catch (Throwable) {
                $ok = false;
                $logStep('Mazu tabulky', $tDef['table'], 'err', 'chyba');
                break;
            }
        }
        $logStep('Mazu tabulky', 'Smazano ' . $dropped . ' tabulek', $ok ? 'ok' : 'err', $ok ? 'ok' : 'chyba');

        if ($ok) {
            $keep = (array)cfg('install.wipe_keep');
            $filesOk = true;

            foreach ((array)cfg('install.wipe_dirs') as $d) {
                $relDir = trim((string)$d, '/');
                $absDir = abs_path($relDir);
                if (!is_dir($absDir)) {
                    $logStep('Mazu soubory', $relDir . '/', 'info', 'nenalezen');
                    continue;
                }
                $dirRes = inst_process_dir_files($absDir, $relDir, $keep, $logStep);
                $deletedFiles += $dirRes['deleted'];
                if (!$dirRes['ok']) {
                    $filesOk = false;
                }
            }

            $files = (array)cfg('install.wipe_files');
            if ($wipeOptional) {
                $files = array_merge($files, (array)cfg('install.wipe_optional_files'));
            }
            foreach ($files as $f) {
                $relF = trim((string)$f, '/');
                if (in_array(basename($relF), $keep, true)) {
                    $logStep('Mazu soubory', $relF, 'info', 'ponechano');
                    continue;
                }
                $absF = abs_path($relF);
                if (!is_file($absF) && !is_link($absF)) {
                    $logStep('Mazu soubory', $relF, 'info', 'nenalezen');
                    continue;
                }
                if (@unlink($absF)) {
                    $deletedFiles++;
                    $logStep('Mazu soubory', $relF, 'ok', 'ok');
                } else {
                    $filesOk = false;
                    $logStep('Mazu soubory', $relF, 'err', 'chyba');
                }
            }

            foreach (array_merge((array)cfg('install.wipe_dirs'), (array)cfg('install.writable_dirs')) as $d) {
                ensure_dir(abs_path((string)$d));
            }
            session_clear_cookie();

            if (!$filesOk) {
                $ok = false;
            }
            $logStep('Mazu soubory', 'Smazano ' . $deletedFiles . ' souboru', $filesOk ? 'ok' : 'err', $filesOk ? 'ok' : 'chyba');
        }
    } finally {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable) {
        }
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable) {
        }
    }

    return ['ok' => $ok];
}

function inst_run_create_and_seed(callable $logStep): array
{
    $ok = true;
    $pdo = db();
    $lockName = 'kf_install_' . substr(hash('sha256', (string)env('DB_NAME', '')), 0, 32);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        $logStep('Vytvarim tabulky', 'Instalace', 'err', 'chyba');
        $logStep('Vytvarim tabulky', 'Vytvoreno 0 tabulek', 'err', 'chyba');
        return ['ok' => false];
    }

    $created = 0;
    $seeded = 0;

    try {
        $tableDefs = inst_load_table_defs($pdo);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tableDefs as $tDef) {
            try {
                if ($tDef['create'] !== null) {
                    inst_exec_retry($pdo, $tDef['create']);
                    foreach ($tDef['all_stmts'] as $stmt) {
                        if ($stmt !== $tDef['drop'] && $stmt !== $tDef['create']) {
                            inst_exec_retry($pdo, $stmt);
                        }
                    }
                } else {
                    foreach ($tDef['all_stmts'] as $stmt) {
                        if ($stmt !== $tDef['drop']) {
                            inst_exec_retry($pdo, $stmt);
                        }
                    }
                }
                $created++;
                $logStep('Vytvarim tabulky', $tDef['table'], 'ok', 'ok');
            } catch (Throwable) {
                $ok = false;
                $logStep('Vytvarim tabulky', $tDef['table'], 'err', 'chyba');
                break;
            }
        }
        $logStep('Vytvarim tabulky', 'Vytvoreno ' . $created . ' tabulek', $ok ? 'ok' : 'err', $ok ? 'ok' : 'chyba');

        if ($ok) {
            foreach (inst_sql_files((string)cfg('install.sql_seed_dir')) as $path => $name) {
                try {
                    $raw = @file_get_contents($path);
                    if (!is_string($raw)) {
                        throw new RuntimeException('Nelze přečíst seed soubor: ' . $name);
                    }
                    $stmts = inst_split(inst_fill($raw, $pdo));
                    foreach ($stmts as $stmt) {
                        inst_exec_retry($pdo, $stmt);
                    }
                    $seeded++;
                    $logStep('Vytvarim seed', $name, 'ok', 'ok');
                } catch (Throwable) {
                    $ok = false;
                    $logStep('Vytvarim seed', $name, 'err', 'chyba');
                    break;
                }
            }
            $logStep('Vytvarim seed', 'Vlozeno ' . $seeded . ' seed souboru', $ok ? 'ok' : 'err', $ok ? 'ok' : 'chyba');
        }
    } finally {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } catch (Throwable) {
        }
        try {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        } catch (Throwable) {
        }
    }

    return ['ok' => $ok];
}

function inst_badge(string $state): string
{
    return '<span class="b ' . e($state) . '">' . e(['ok' => 'OK', 'warn' => 'pozor', 'err' => 'chyba'][$state] ?? $state) . '</span>';
}

function inst_page(string $title, string $body): never
{
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . e($title) . '</title><style>'
        . ':root{color-scheme:light dark;--bg:#f5f6f8;--fg:#1c2330;--card:#fff;--bd:#d9dde5;--ok:#1a7f37;--warn:#9a6700;--err:#cf222e;--info:#57606a;--ac:#0b5fff}'
        . '@media(prefers-color-scheme:dark){:root{--bg:#12151b;--fg:#e6e9ef;--card:#1b2029;--bd:#2f3745;--ok:#3fb950;--warn:#d29922;--err:#f85149;--info:#8b949e;--ac:#5b9bff}}'
        . '*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,sans-serif;background:var(--bg);color:var(--fg)}'
        . 'main{max-width:860px;margin:0 auto;padding:16px}h1{font-size:1.4rem;margin:.2em 0 .6em}h2{font-size:1.05rem;margin:0 0 .5em}'
        . '.card{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:14px 16px;margin:0 0 14px}'
        . 'table{width:100%;border-collapse:collapse}td,th{padding:5px 6px;border-bottom:1px solid var(--bd);text-align:left;vertical-align:top;font-size:.92rem}'
        . 'th{font-weight:600}.b{display:inline-block;min-width:3.4em;text-align:center;border-radius:6px;padding:0 6px;font-size:.8rem;font-weight:600;color:#fff}'
        . '.b.ok{background:var(--ok)}.b.warn{background:var(--warn)}.b.err{background:var(--err)}.r{text-align:right}'
        . 'input[type=password],input[type=text]{width:100%;padding:9px;border:1px solid var(--bd);border-radius:8px;background:var(--bg);color:var(--fg);font:inherit}'
        . 'button,.btn{display:inline-block;padding:10px 16px;border:0;border-radius:8px;background:var(--ac);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;margin:4px 6px 4px 0}'
        . 'button.danger{background:var(--err)}label{display:block;margin:8px 0}code{font-size:.88em}.warnbox{border-left:4px solid var(--err);padding-left:10px}'
        . '.ok{color:var(--ok);font-weight:700}.err{color:var(--err);font-weight:700}.info{color:var(--info);font-weight:600}'
        . '</style></head><body><main><h1>' . e($title) . '</h1>' . $body . '</main></body></html>';
    exit;
}

function inst_key_form(string $error = ''): never
{
    $body = '<div class="card"><h2>Přihlášení do instalátoru</h2><form method="post" autocomplete="off"><input type="hidden" name="action" value="unlock">'
        . ($error !== '' ? '<p class="warnbox">' . e($error) . '</p>' : '')
        . '<label>INSTALL_KEY z .env<input type="password" name="key" autofocus required></label>'
        . '<button type="submit">Pokračovat</button></form></div>';
    inst_page('Instalace – ' . (string)cfg('app.name'), $body);
}

$installKey = (string)env('INSTALL_KEY', '');
if ($installKey === '') {
    http_response_code(403);
    inst_page('Instalátor je vypnutý', '<div class="card"><p>V <code>.env</code> je prázdný <code>INSTALL_KEY</code>.</p></div>');
}

$maxTries = (int)cfg('limits.install_key_max_hits');
$action = is_post() ? (string)($_POST['action'] ?? '') : '';

if ($action === '') {
    inst_key_form();
}
if (ratelimit_hit('install', $maxTries, false)) {
    security_log('install_rate_limit');
    http_response_code(429);
    inst_page('Příliš mnoho pokusů', '<div class="card"><p>Zkus to později.</p></div>');
}

$token = '';
if ($action === 'unlock') {
    $sent = (string)($_POST['key'] ?? '');
    if (!hash_equals($installKey, $sent)) {
        ratelimit_hit('install', $maxTries, true);
        security_log('install_key_invalid');
        http_response_code(403);
        inst_key_form('Neplatný klíč.');
    }
    $token = inst_token_make(time() + (int)cfg('install.token_ttl_sec'));
} elseif ($action === 'install_wipe' || $action === 'install_create_seed') {
    $sentToken = (string)($_POST['token'] ?? '');
    if (!inst_token_ok($sentToken)) {
        ratelimit_hit('install', $maxTries, true);
        security_log('install_token_invalid');
        http_response_code(403);
        inst_key_form('Platnost odemknutí vypršela, zadej klíč znovu.');
    }
    if (cfg('security.csrf_origin_check') && !origin_ok()) {
        security_log('install_origin_mismatch');
        http_response_code(403);
        inst_key_form('Neplatný původ požadavku.');
    }
    $token = inst_token_make(time() + (int)cfg('install.token_ttl_sec'));
} else {
    security_log('install_bad_action');
    http_response_code(400);
    inst_key_form();
}

if ($action === 'install_wipe' || $action === 'install_create_seed') {
    $sections = [];
    $logStep = static function (string $section, string $label, string $status, string $detail) use (&$sections): void {
        if (!isset($sections[$section])) {
            $sections[$section] = [];
        }
        $sections[$section][] = [$label, $status, $detail];
    };

    try {
        if ($action === 'install_wipe') {
            $res = inst_run_wipe(!empty($_POST['wipe_optional']), $logStep);
        } else {
            $res = inst_run_create_and_seed($logStep);
        }
    } catch (Throwable) {
        $res = ['ok' => false];
        $secName = $action === 'install_wipe' ? 'Mazu tabulky' : 'Vytvarim tabulky';
        $logStep($secName, 'Instalace', 'err', 'chyba');
    }

    $html = '';
    foreach ($sections as $secTitle => $rows) {
        $html .= '<div class="card"><h2>' . e($secTitle) . '</h2><table>';
        foreach ($rows as [$label, $status, $detail]) {
            $cls = $status === 'ok' ? 'ok' : ($status === 'err' ? 'err' : 'info');
            $txt = $detail !== '' ? $detail : ($status === 'ok' ? 'ok' : 'chyba');
            $html .= '<tr><td>' . e($label) . '</td><td class="' . $cls . '">' . e($txt) . '</td></tr>';
        }
        $html .= '</table></div>';
    }

    if ($action === 'install_wipe') {
        if (!empty($res['ok'])) {
            $html .= '<div class="card"><form method="post">'
                . '<input type="hidden" name="action" value="install_create_seed">'
                . '<input type="hidden" name="token" value="' . e($token) . '">'
                . '<button type="submit">Pokračovat</button></form></div>';
        } else {
            $html .= '<div class="card warnbox"><p>chyba</p>'
                . '<a class="btn" href="' . e(url('install/')) . '">Zpět</a></div>';
        }
    } elseif ($action === 'install_create_seed') {
        if (!empty($res['ok'])) {
            $prov = inst_oauth_providers();
            $html .= '<div class="card"><h2>Vytvoření prvního admina</h2>';
            if ($prov) {
                $html .= '<p>Přihlaste se – první běžný uživatel se stane administrátorem.</p>';
                foreach ($prov as $p) {
                    $html .= '<a class="btn" href="' . e(inst_login_url($p)) . '">Přihlásit přes ' . e(ucfirst($p)) . '</a>';
                }
            } else {
                $html .= '<p class="warnbox">V <code>.env</code> není nastaven žádný OAuth poskytovatel.</p>'
                    . '<a class="btn" href="' . e(url('')) . '">Otevřít aplikaci</a>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="card warnbox"><p>chyba</p>'
                . '<a class="btn" href="' . e(url('install/')) . '">Zpět</a></div>';
        }
    }

    inst_page('Instalace – průběh', $html);
}

$checks = '';
$blocking = false;
foreach (inst_checks() as [$label, $state, $detail]) {
    $blocking = $blocking || $state === 'err';
    $checks .= '<tr><td>' . e($label) . '</td><td>' . inst_badge($state) . '</td><td>' . e($detail) . '</td></tr>';
}
$tables = '';
foreach (inst_table_status() as [$name, $exists, $rows]) {
    $tables .= '<tr><td><code>' . e($name) . '</code></td><td>' . ($exists ? 'existuje' : 'neexistuje') . '</td><td class="r">' . ($exists ? $rows : '–') . '</td></tr>';
}
$issues = inst_sql_consistency();
$files = '';
foreach ([(string)cfg('install.sql_tables_dir'), (string)cfg('install.sql_seed_dir')] as $dir) {
    foreach (inst_sql_files($dir) as $name) {
        $files .= '<tr><td><code>' . e($dir . '/' . $name) . '</code></td></tr>';
    }
}
$wipe = '';
foreach (inst_wipe_plan() as [$what, $info]) {
    $wipe .= '<tr><td><code>' . e($what) . '</code></td><td>' . e($info) . '</td></tr>';
}
$optional = implode(', ', array_map('strval', (array)cfg('install.wipe_optional_files')));

$body = '<div class="card"><h2>Prostředí</h2><table>' . $checks . '</table></div>'
    . '<div class="card"><h2>Tabulky v databázi</h2><table><tr><th>Tabulka</th><th>Stav</th><th class="r">Řádků</th></tr>' . $tables . '</table></div>'
    . '<div class="card"><h2>SQL soubory</h2>'
    . ($issues ? '<p class="warnbox">' . implode('<br>', array_map('e', $issues)) . '</p>' : '') . '<table>' . $files . '</table></div>'
    . '<div class="card"><h2>Co se při instalaci smaže</h2><table>' . $wipe . '</table></div>'
    . '<div class="card"><form method="post"><input type="hidden" name="action" value="install_wipe"><input type="hidden" name="token" value="' . e($token) . '">'
    . '<label><input type="checkbox" name="wipe_optional" value="1" checked> Smazat i logy (<code>' . e($optional) . '</code>)</label>'
    . ($blocking || $issues ? '<p class="warnbox">Nejdřív oprav chyby výše.</p>' : '<button type="submit" class="danger">Nainstalovat (smaže všechna data)</button>')
    . '</form></div>';
inst_page('Instalace – ' . (string)cfg('app.name'), $body);
