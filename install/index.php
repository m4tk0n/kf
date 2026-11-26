<?php
// install/index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

// Ověření přihlášeného správce pro bezpečný vstup do instalátoru.
$instUser = auth_resolve();
$instSuperAdmin = $instUser !== null && (int)($instUser['id'] ?? 0) === 2;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
send_security_headers(true);

// ---------- pomocné funkce ----------

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

/** @return array<string,string> cesta => název souboru, seřazeno podle číselného prefixu */
function inst_sql_files(string $relDir): array
{
    $dir = abs_path($relDir);
    $out = [];
    foreach (glob($dir . '/*.sql') ?: [] as $f) {
        $out[$f] = basename($f);
    }
    asort($out, SORT_NATURAL);
    return $out;
}

/** Rozdělí soubor na příkazy: komentáře `--` se přeskočí, příkaz končí `;` na konci řádku. */
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

/** Název tabulky z NN_<tabulka>.sql */
function inst_table_of(string $file): string
{
    return (string)preg_replace('/^\d+_(.+)\.sql$/', '$1', $file);
}

function inst_oauth_providers(): array
{
    $ready = [];
    $pairs = ['google' => ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'], 'facebook' => ['FACEBOOK_APP_ID', 'FACEBOOK_APP_SECRET']];
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

/** @return array<int,array{0:string,1:string,2:string}> [popisek, stav ok|warn|err, detail] */
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
    $c[] = ['APP_KEY', strlen($key) >= 32 && !str_starts_with($key, 'zmen-') ? 'ok' : 'err', 'min. 32 znaků a nesmí zůstat vzorová hodnota'];
    $prefix = (string)cfg('db.prefix');
    $c[] = ['Prefix tabulek (TABLE_PREFIX)', $prefix !== '' ? 'ok' : 'err', $prefix !== '' ? $prefix : 'prázdný prefix je zakázán, instalátor by mohl zahodit cizí tabulky stejného názvu'];
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
    $c[] = ['IP klienta', 'ok', client_ip() . ' (hlavička ' . (string)cfg('security.ip_header') . ': ' . ((string)($_SERVER[(string)cfg('security.ip_header')] ?? '') ?: 'není') . ')'];
    $c[] = ['HTTPS', request_is_https() ? 'ok' : 'warn', request_is_https() ? 'detekováno' : 'nedetekováno, cookie Secure by nefungovala'];
    $prov = inst_oauth_providers();
    $c[] = ['OAuth poskytovatelé', $prov ? 'ok' : 'warn', $prov ? implode(', ', $prov) : 'žádný není nastaven v .env – nepůjde vytvořit admina přihlášením'];
    return $c;
}

/** @return array<int,array{0:string,1:bool,2:int}> [tabulka, existuje, řádků] */
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

/** Počet tabulek v databázi, které aplikaci nepatří (jiné projekty) a instalátor se jich nedotkne. */
function inst_other_tables(): int
{
    try {
        $mine = array_map(static fn($n): string => strtolower((string)cfg('db.prefix') . $n), array_values((array)cfg('db.tables')));
        $all = q('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
        return count(array_diff(array_map('strtolower', array_map('strval', $all)), $mine));
    } catch (Throwable) {
        return 0;
    }
}

/** Kontrola, že každá tabulka z configu má SQL soubor a naopak. */
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
        $plan[] = [(string)$f, is_file(abs_path((string)$f)) ? 'existuje' : '–'];
    }
    return $plan;
}

/** Provede instalaci. @return array{ok:bool,log:array<int,array{0:string,1:bool,2:string}>} */
function inst_run(bool $wipeOptional): array
{
    if ((string)cfg('db.prefix') === '') {
        throw new RuntimeException('TABLE_PREFIX je prázdný. Instalace by mohla zahodit cizí tabulky, proto se nespustí.');
    }
    $log = [];
    $ok = true;
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $groups = [
        'Tabulka' => (string)cfg('install.sql_tables_dir'),
        'Seed' => (string)cfg('install.sql_seed_dir'),
    ];
    foreach ($groups as $label => $dir) {
        foreach (inst_sql_files($dir) as $path => $name) {
            try {
                $stmts = inst_split(inst_fill((string)file_get_contents($path), $pdo));
                foreach ($stmts as $s) {
                    $pdo->exec($s);
                }
                $log[] = [$label . ' ' . $name, true, count($stmts) . ' příkazů'];
            } catch (Throwable $e) {
                $log[] = [$label . ' ' . $name, false, substr($e->getMessage(), 0, 300)];
                $ok = false;
                break 2; // při první chybě se končí, další soubory by selhaly kvůli cizím klíčům
            }
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    if ($ok) {
        $keep = (array)cfg('install.wipe_keep');
        foreach ((array)cfg('install.wipe_dirs') as $d) {
            $n = dir_delete_contents(abs_path((string)$d), $keep);
            $log[] = ['Smazáno ' . $d . '/', true, $n . ' souborů'];
        }
        $files = (array)cfg('install.wipe_files');
        if ($wipeOptional) {
            $files = array_merge($files, (array)cfg('install.wipe_optional_files'));
        }
        foreach ($files as $f) {
            $abs = abs_path((string)$f);
            $done = !is_file($abs) || @unlink($abs);
            $log[] = ['Smazán soubor ' . $f, $done, $done ? 'OK' : 'nelze smazat'];
            $ok = $ok && $done;
        }
        foreach (array_merge((array)cfg('install.wipe_dirs'), (array)cfg('install.writable_dirs')) as $d) {
            ensure_dir(abs_path((string)$d));
        }
        session_clear_cookie();
    }
    return ['ok' => $ok, 'log' => $log];
}

function inst_badge(string $state): string
{
    return '<span class="b ' . e($state) . '">' . e(['ok' => 'OK', 'warn' => 'pozor', 'err' => 'chyba'][$state] ?? $state) . '</span>';
}

function inst_page(string $title, string $body): never
{
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . e($title) . '</title><style>'
        . ':root{color-scheme:light dark;--bg:#f5f6f8;--fg:#1c2330;--card:#fff;--bd:#d9dde5;--ok:#1a7f37;--warn:#9a6700;--err:#cf222e;--ac:#0b5fff}'
        . '@media(prefers-color-scheme:dark){:root{--bg:#12151b;--fg:#e6e9ef;--card:#1b2029;--bd:#2f3745;--ok:#3fb950;--warn:#d29922;--err:#f85149;--ac:#5b9bff}}'
        . '*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,sans-serif;background:var(--bg);color:var(--fg)}'
        . 'main{max-width:860px;margin:0 auto;padding:16px}h1{font-size:1.4rem;margin:.2em 0 .6em}h2{font-size:1.05rem;margin:0 0 .5em}'
        . '.card{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:14px 16px;margin:0 0 14px}'
        . 'table{width:100%;border-collapse:collapse}td,th{padding:5px 6px;border-bottom:1px solid var(--bd);text-align:left;vertical-align:top;font-size:.92rem}'
        . 'th{font-weight:600}.b{display:inline-block;min-width:3.4em;text-align:center;border-radius:6px;padding:0 6px;font-size:.8rem;font-weight:600;color:#fff}'
        . '.b.ok{background:var(--ok)}.b.warn{background:var(--warn)}.b.err{background:var(--err)}.mut{color:var(--warn)}.r{text-align:right}'
        . 'input[type=password],input[type=text]{width:100%;padding:9px;border:1px solid var(--bd);border-radius:8px;background:var(--bg);color:var(--fg);font:inherit}'
        . 'button,.btn{display:inline-block;padding:10px 16px;border:0;border-radius:8px;background:var(--ac);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;margin:4px 6px 4px 0}'
        . 'button.danger{background:var(--err)}label{display:block;margin:8px 0}code{font-size:.88em}.warnbox{border-left:4px solid var(--err);padding-left:10px}'
        . '</style></head><body><main><h1>' . e($title) . '</h1>' . $body . '</main></body></html>';
    exit;
}

function inst_key_form(string $error = ''): never
{
    $body = '<div class="card"><form method="post" autocomplete="off"><input type="hidden" name="action" value="unlock">'
        . ($error !== '' ? '<p class="warnbox">' . e($error) . '</p>' : '')
        . '<label>INSTALL_KEY z .env<input type="password" name="key" autofocus required></label>'
        . '<button type="submit">Pokračovat</button></form></div>';
    inst_page('Instalace – ' . (string)cfg('app.name'), $body);
}

// ---------- řízení ----------

$installKey = (string)env('INSTALL_KEY', '');
if ($installKey === '' && !$instSuperAdmin) {
    http_response_code(403);
    inst_page('Instalátor je vypnutý', '<div class="card"><p>V <code>.env</code> je prázdný <code>INSTALL_KEY</code>. Pro spuštění instalátoru ho vyplň.</p></div>');
}

$maxTries = (int)cfg('limits.install_key_max_hits');
$action = is_post() ? (string)($_POST['action'] ?? '') : '';

if ($action === '') {
    if ($instSuperAdmin) {
        $token = inst_token_make(time() + (int)cfg('install.token_ttl_sec'));
        $action = 'admin_view';
    } else {
        inst_key_form();
    }
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
} elseif ($action === 'install') {
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
} elseif ($action === 'create_test_profiles') {
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
} elseif ($action === 'admin_view' && $instSuperAdmin) {
    $token = inst_token_make(time() + (int)cfg('install.token_ttl_sec'));
} else {
    security_log('install_bad_action');
    http_response_code(400);
    inst_key_form();
}

// ---------- instalace ----------

if ($action === 'install') {
    try {
        $res = inst_run(!empty($_POST['wipe_optional']));
    } catch (Throwable $e) {
        $res = ['ok' => false, 'log' => [['Instalace', false, substr($e->getMessage(), 0, 300)]]];
    }
    $rows = '';
    foreach ($res['log'] as [$label, $okRow, $msg]) {
        $rows .= '<tr><td>' . e($label) . '</td><td>' . inst_badge($okRow ? 'ok' : 'err') . '</td><td>' . e($msg) . '</td></tr>';
    }
    $body = '<div class="card"><h2>' . ($res['ok'] ? 'Instalace dokončena' : 'Instalace nedokončena') . '</h2>'
        . '<p>' . ($res['ok'] ? 'Instalace proběhla úspěšně. Níže je přehled skutečně provedených operací.' : 'Instalace skončila chybou. Zkontroluj neúspěšné položky a spusť instalaci znovu.') . '</p>'
        . '<table><thead><tr><th>Operace</th><th>Stav</th><th>Podrobnosti</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
    if ($res['ok']) {
        $body .= '<div class="card"><h2>První administrátor</h2>';
        $prov = inst_oauth_providers();
        if ($prov) {
            $body .= '<p>První běžný uživatel, který se přihlásí, získá roli administrátora.</p>';
            foreach ($prov as $p) {
                $body .= '<a class="btn" href="' . e(inst_login_url($p)) . '">Vytvořit prvního administrátora přes ' . e(ucfirst($p)) . '</a>';
            }
        } else {
            $body .= '<p class="warnbox">Není nastaven žádný OAuth poskytovatel. Nejdříve doplň přihlašovací údaje Google nebo Facebook do <code>.env</code>. Poté se vrať do aplikace a přihlas prvního uživatele; ten získá roli administrátora.</p>';
        }
        $body .= '</div>';
    }
    $body .= '<div class="card"><a class="btn" href="' . e(url('install/')) . '">Zpět na úvod instalátoru</a>'
        . (!$res['ok'] ? '<a class="btn" href="' . e(url('install/')) . '">Spustit instalaci znovu</a>' : '')
        . '</div>';
    inst_page('Instalace – výsledek', $body);
}

if ($action === 'create_test_profiles') {
    try {
        $res = debug_test_profiles_create();
        $message = '<p>' . e($res['message']) . ' (' . (int)$res['count'] . '/5)</p>';
        $state = 'ok';
    } catch (Throwable $e) {
        $message = '<p class="warnbox">' . e(substr($e->getMessage(), 0, 300)) . '</p>';
        $state = 'err';
    }
    $body = '<div class="card"><h2>Testovací profily</h2>' . $message
        . '<p>' . inst_badge($state) . '</p></div>'
        . '<div class="card"><a class="btn" href="' . e(url('install/')) . '">Zpět na instalátor</a></div>';
    inst_page('Testovací profily – výsledek', $body);
}

// ---------- přehled (po odemknutí) ----------

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
    . '<div class="card"><h2>Tabulky aplikace v databázi</h2><table><tr><th>Tabulka</th><th>Stav</th><th class="r">Řádků</th></tr>' . $tables . '</table></div>'
    . '<div class="card"><h2>SQL soubory (spouští se v tomto pořadí)</h2>'
    . ($issues ? '<p class="warnbox">' . implode('<br>', array_map('e', $issues)) . '</p>' : '') . '<table>' . $files . '</table></div>'
    . '<div class="card"><h2>Co se při instalaci smaže</h2><p>Zahodí se a znovu vytvoří <strong>jen tabulky této aplikace</strong> (s prefixem <code>' . e((string)cfg('db.prefix')) . '</code>, viz seznam výše), bez migrací. Ostatní tabulky v téže databázi (' . inst_other_tables() . ') se nedotknou. Protože se ID číslují od 1, smaže se i obsah těchto složek a souborů (ochranné soubory <code>' . e(implode(', ', (array)cfg('install.wipe_keep'))) . '</code> zůstanou):</p><table>' . $wipe . '</table></div>'
    . '<div class="card"><form method="post"><input type="hidden" name="action" value="install"><input type="hidden" name="token" value="' . e($token) . '">'
    . '<label><input type="checkbox" name="wipe_optional" value="1" checked> Smazat i logy (<code>' . e($optional) . '</code>)</label>'
    . ($blocking || $issues ? '<p class="warnbox">Nejdřív oprav chyby výše.</p>' : '<button type="submit" class="danger">Nainstalovat (smaže data aplikace)</button>')
    . '</form></div>'
    . '<div class="card"><h2>Testovací profily</h2>'
    . '<p>Profily se nevytvářejí při instalaci. Vytvoří se až po samostatném kliknutí a pouze pokud už v databázi existuje hlavní účet ID 2.</p>'
    . '<form method="post"><input type="hidden" name="action" value="create_test_profiles"><input type="hidden" name="token" value="' . e($token) . '">'
    . '<button type="submit">Vytvořit testovací profily</button></form></div>';
inst_page('Instalace – ' . (string)cfg('app.name'), $body);
