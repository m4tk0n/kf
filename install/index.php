<?php
// install/index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

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

/** Krátce opakuje příkaz po přechodném deadlocku nebo timeoutu InnoDB. */
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
            // InnoDB po deadlocku jednu transakci zruší; krátká prodleva umožní
            // dokončení souběžného požadavku před opakováním stejného DDL.
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
function inst_run(bool $wipeOptional, ?callable $progress = null): array
{
    $log = [];
    $ok = true;
    $pdo = db();

    // Zabrání souběžnému spuštění dvou instalací nad stejnou databází.
    $lockName = 'kf_install_' . substr(hash('sha256', (string)env('DB_NAME', '')), 0, 32);
    $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) {
        return ['ok' => false, 'log' => [['Instalace', false, 'Jiná instalace právě pracuje s databází. Zkus to znovu za chvíli.']]];
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $groups = [
        'Tabulka' => (string)cfg('install.sql_tables_dir'),
        'Seed' => (string)cfg('install.sql_seed_dir'),
    ];
    foreach ($groups as $label => $dir) {
        foreach (inst_sql_files($dir) as $path => $name) {
            $item = $label . ' ' . $name;
            if ($progress) {
                $progress($item, 'running', 'Připravuji SQL soubor');
            }
            try {
                $stmts = inst_split(inst_fill((string)file_get_contents($path), $pdo));
                $total = count($stmts);
                foreach ($stmts as $index => $s) {
                    if ($progress) {
                        $progress($item, 'running', 'Příkaz ' . ($index + 1) . '/' . $total);
                    }
                    inst_exec_retry($pdo, $s);
                }
                $detail = $total . ' příkazů – dokončeno';
                $log[] = [$item, true, $detail];
                if ($progress) {
                    $progress($item, 'ok', $detail);
                }
            } catch (Throwable $e) {
                $detail = substr($e->getMessage(), 0, 300);
                $log[] = [$item, false, $detail];
                if ($progress) {
                    $progress($item, 'err', $detail);
                }
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
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lockName]);

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
if ($installKey === '') {
    http_response_code(403);
    inst_page('Instalátor je vypnutý', '<div class="card"><p>V <code>.env</code> je prázdný <code>INSTALL_KEY</code>. Pro spuštění instalátoru ho vyplň.</p></div>');
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
} else {
    security_log('install_bad_action');
    http_response_code(400);
    inst_key_form();
}

// ---------- instalace ----------

if ($action === 'install') {
    // Výstup posíláme průběžně, aby uživatel viděl skutečný postup instalace.
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instalace – průběh</title><style>'
        . ':root{color-scheme:light dark;--bg:#f5f6f8;--fg:#1c2330;--card:#fff;--bd:#d9dde5;--ok:#1a7f37;--err:#cf222e;--ac:#0b5fff}'
        . '@media(prefers-color-scheme:dark){:root{--bg:#12151b;--fg:#e6e9ef;--card:#1b2029;--bd:#2f3745;--ok:#3fb950;--err:#f85149}}'
        . '*{box-sizing:border-box}body{margin:0;padding:16px;background:var(--bg);color:var(--fg);font:15px/1.5 system-ui,sans-serif}main{max-width:860px;margin:auto}.card{background:var(--card);border:1px solid var(--bd);border-radius:10px;padding:14px;margin:0 0 12px}table{width:100%;border-collapse:collapse}td{padding:7px 5px;border-bottom:1px solid var(--bd);vertical-align:top}.state{font-weight:700;white-space:nowrap}.ok{color:var(--ok)}.err{color:var(--err)}.muted{opacity:.75}button,.btn{display:inline-block;padding:10px 16px;border:0;border-radius:8px;background:var(--ac);color:#fff;font:inherit;text-decoration:none}</style></head><body><main><h1>Instalace – průběh</h1><div class="card"><p id="current">Připravuji instalaci…</p><table id="progress"><tr><td>Čekám na zahájení</td><td class="state">…</td></tr></table></div>';
    echo str_repeat(' ', 4096);
    @flush();

    $progress = static function (string $item, string $state, string $detail): void {
        $safeItem = e($item);
        $safeDetail = e($detail);
        $safeState = e($state === 'ok' ? 'HOTOVO' : ($state === 'err' ? 'CHYBA' : 'PRACUJI'));
        $class = $state === 'ok' ? 'ok' : ($state === 'err' ? 'err' : 'muted');
        echo '<script>document.getElementById("current").textContent=' . json_encode($item . ' – ' . $detail, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
        echo '<tr><td>' . $safeItem . '<div class="muted">' . $safeDetail . '</div></td><td class="state ' . $class . '">' . $safeState . '</td></tr>';
        echo str_repeat(' ', 1024);
        @flush();
    };

    try {
        $res = inst_run(!empty($_POST['wipe_optional']), $progress);
    } catch (Throwable $e) {
        $res = ['ok' => false, 'log' => [['Instalace', false, substr($e->getMessage(), 0, 300)]]];
        $progress('Instalace', 'err', substr($e->getMessage(), 0, 300));
    }
    if ($res['ok']) {
        $prov = inst_oauth_providers();
        if ($prov) {
            // Po čisté instalaci vždy pokračovat přihlášením; pokud je dostupný Google,
            // použít ho přednostně i v případě, že jsou nakonfigurovaní další poskytovatelé.
            $provider = in_array('google', $prov, true) ? 'google' : $prov[0];
            header('Location: ' . inst_login_url($provider), true, 303);
            exit;
        }
    }
    $rows = '';
    foreach ($res['log'] as [$label, $okRow, $msg]) {
        $rows .= '<tr><td>' . e($label) . '</td><td>' . inst_badge($okRow ? 'ok' : 'err') . '</td><td>' . e($msg) . '</td></tr>';
    }
    $body = '<div class="card"><h2>Výsledek</h2><table>' . $rows . '</table></div>';
    if ($res['ok']) {
        $body .= '<div class="card"><h2>Vytvoření admina</h2>';
        $prov = inst_oauth_providers();
        if ($prov) {
            $body .= '<p>Přihlas se – první běžný uživatel se automaticky stane administrátorem.</p>';
            foreach ($prov as $p) {
                $body .= '<a class="btn" href="' . e(inst_login_url($p)) . '">Vytvořit admina přihlášením přes ' . e(ucfirst($p)) . '</a>';
            }
        } else {
            $body .= '<p class="warnbox">V <code>.env</code> není nastaven žádný OAuth poskytovatel. Doplň klíče a přihlas se – první přihlášený se stane administrátorem.</p>'
                . '<a class="btn" href="' . e(url('')) . '">Otevřít aplikaci</a>';
        }
        $body .= '</div>';
    } else {
        $body .= '<div class="card warnbox"><p>Instalace skončila chybou. Oprav SQL soubor nebo nastavení a spusť ji znovu.</p></div>';
    }
    echo '</table></div>' . $body . '</main></body></html>';
    if ($res['ok'] && ($prov = inst_oauth_providers())) {
        $provider = in_array('google', $prov, true) ? 'google' : $prov[0];
        echo '<p>Přesměrování na přihlášení… <a href="' . e(inst_login_url($provider)) . '">Pokračovat</a></p><script>setTimeout(function(){location.replace('
            . json_encode(inst_login_url($provider), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            . ')},1800);</script>';
    } else {
        echo '<p><a class="btn" href="' . e(url('install/')) . '">Zpět na přehled instalátoru</a></p>';
    }
    echo '</main></body></html>';
    exit;
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
    . '<div class="card"><h2>Tabulky v databázi</h2><table><tr><th>Tabulka</th><th>Stav</th><th class="r">Řádků</th></tr>' . $tables . '</table></div>'
    . '<div class="card"><h2>SQL soubory (spouští se v tomto pořadí)</h2>'
    . ($issues ? '<p class="warnbox">' . implode('<br>', array_map('e', $issues)) . '</p>' : '') . '<table>' . $files . '</table></div>'
    . '<div class="card"><h2>Co se při instalaci smaže</h2><p>Všechny tabulky se zahodí a vytvoří znovu (bez migrací). Protože se ID číslují od 1, smaže se i obsah těchto složek a souborů (ochranné soubory <code>' . e(implode(', ', (array)cfg('install.wipe_keep'))) . '</code> zůstanou):</p><table>' . $wipe . '</table></div>'
    . '<div class="card"><form method="post"><input type="hidden" name="action" value="install"><input type="hidden" name="token" value="' . e($token) . '">'
    . '<label><input type="checkbox" name="wipe_optional" value="1" checked> Smazat i logy (<code>' . e($optional) . '</code>)</label>'
    . ($blocking || $issues ? '<p class="warnbox">Nejdřív oprav chyby výše.</p>' : '<button type="submit" class="danger">Nainstalovat (smaže všechna data)</button>')
    . '</form></div>';
inst_page('Instalace – ' . (string)cfg('app.name'), $body);
