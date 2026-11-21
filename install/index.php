<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/lib.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
send_security_headers(true);

$installKey = trim((string)env('INSTALL_KEY', ''));
if ($installKey === '') {
    http_response_code(403);
    inst_render('Instalátor vypnutý', '<section class="panel"><p>V souboru <code>.env</code> není nastaven klíč <code>INSTALL_KEY</code>.</p></section>');
}

$action = is_post() ? (string)($_POST['action'] ?? '') : '';
$token = '';
if ($action === 'unlock') {
    if (ratelimit_hit('install', (int)cfg('limits.install_key_max_hits'), false)) {
        http_response_code(429);
        inst_login('Příliš mnoho pokusů. Zkus to později.');
    }
    $sent = (string)($_POST['key'] ?? '');
    if (!hash_equals($installKey, $sent)) {
        ratelimit_hit('install', (int)cfg('limits.install_key_max_hits'), true);
        security_log('install_key_invalid');
        http_response_code(403);
        inst_login('Zadaný klíč není správný.');
    }
    $token = inst_token(time() + (int)cfg('install.token_ttl_sec'));
} elseif ($action !== '' || isset($_GET['token'])) {
    $posted = (string)($_POST['token'] ?? $_GET['token'] ?? '');
    if (!inst_token_valid($posted)) {
        http_response_code(403);
        inst_login('Odemknutí vypršelo. Zadej instalační klíč znovu.');
    }
    if ($action !== '' && cfg('security.csrf_origin_check') && !origin_ok()) {
        http_response_code(403);
        inst_login('Požadavek neprošel bezpečnostní kontrolou.');
    }
    $token = inst_token(time() + (int)cfg('install.token_ttl_sec'));
} else {
    inst_login();
}

if ($action === 'run_full' || $action === 'run_manual') {
    $post = $_POST;
    if ($action === 'run_full') {
        $post['_full_install'] = '1';
        $post['drop_tables'] = array_keys((array)cfg('db.tables'));
        $post['schema_files'] = inst_sql_files((string)cfg('install.sql_tables_dir'));
        $post['seed_files'] = inst_sql_files((string)cfg('install.sql_seed_dir'));
    }
    $result = inst_execute_selection($post);
    inst_render('Protokol instalace', inst_result_html($result, $token));
}

$page = (string)($_GET['page'] ?? 'overview');
if (!in_array($page, ['overview', 'full', 'manual'], true)) $page = 'overview';

$checks = inst_environment_checks();
$tables = inst_database_tables();
$schemaFiles = inst_sql_files((string)cfg('install.sql_tables_dir'));
$seedFiles = inst_sql_files((string)cfg('install.sql_seed_dir'));
$usersState = inst_user_state();
$errors = array_filter($checks, static fn(array $x): bool => $x['state'] === 'err');
$warnings = array_filter($checks, static fn(array $x): bool => $x['state'] === 'warn');
$wipe = inst_wipe_preview();
$link = static fn(string $p): string => e(url('install/?page='.$p.'&token='.rawurlencode($token)));

ob_start();
?>
<div class="topbar"><a class="brand" href="<?= $link('overview') ?>">K&amp;F <span>INSTALLER</span></a><div class="top-meta">PHP <?= e(PHP_VERSION) ?> <i></i> DB <?= e((string)env('DB_NAME', '')) ?></div></div>
<div class="hero">
  <div class="eyebrow">SYSTEM SETUP / <?= e(strtoupper($page)) ?></div>
  <h1><?= $page === 'overview' ? 'Přehled instalace' : ($page === 'full' ? 'Kompletní instalace' : 'Manuální instalace') ?></h1>
  <p><?= $page === 'overview' ? 'Nejdřív zkontroluj prostředí, databázi a soubory určené k vyčištění.' : ($page === 'full' ? 'Čistá instalace aplikace. Smažou se aplikační tabulky i provozní data a znovu se vytvoří databázová struktura.' : 'Vyber přesně, které tabulky chceš vyčistit a které SQL soubory spustit.') ?></p>
</div>
<nav class="steps-nav">
  <a class="<?= $page === 'overview' ? 'active' : '' ?>" href="<?= $link('overview') ?>"><b>01</b><span>Přehled</span></a>
  <a class="<?= $page === 'full' ? 'active' : '' ?>" href="<?= $link('full') ?>"><b>02</b><span>Kompletní</span></a>
  <a class="<?= $page === 'manual' ? 'active' : '' ?>" href="<?= $link('manual') ?>"><b>03</b><span>Manuální</span></a>
</nav>

<?php if ($page === 'overview'): ?>
  <?php if ($errors): ?><section class="notice danger"><strong>Prostředí není připravené.</strong> Oprav červeně označené kontroly před instalací.</section>
  <?php elseif ($warnings): ?><section class="notice warning"><strong>Kontrola dokončena s upozorněními.</strong> Před pokračováním zkontroluj varování.</section>
  <?php else: ?><section class="notice success"><strong>Prostředí je připravené.</strong> Nebyly nalezeny blokující problémy.</section><?php endif; ?>

  <section class="panel">
    <div class="section-head"><div><span class="step">01</span><h2>Kontrola prostředí</h2></div><span class="muted"><?= count($checks) ?> kontrol</span></div>
    <div class="check-grid"><?php foreach ($checks as $check): ?>
      <div class="check-row"><span class="status <?= e($check['state']) ?>"></span><div><strong><?= e($check['label']) ?></strong><small><?= e($check['detail']) ?></small></div><span class="status-text"><?= e($check['state'] === 'ok' ? 'OK' : ($check['state'] === 'warn' ? 'POZOR' : 'CHYBA')) ?></span></div>
    <?php endforeach; ?></div>
  </section>
  <section class="panel">
    <div class="section-head"><div><span class="step">02</span><h2>Aktuální databáze</h2></div><span class="muted"><?= count($tables) ?> tabulek</span></div>
    <div class="db-summary"><span>Databáze <b><?= e((string)env('DB_NAME', '')) ?></b></span><span>Prefix <b><?= e((string)cfg('db.prefix')) ?: '—' ?></b></span><span>Existuje <b><?= count(array_filter($tables, static fn($t) => $t['exists'])) ?>/<?= count($tables) ?></b></span></div>
    <div class="table-wrap"><table><thead><tr><th>Tabulka</th><th>Stav</th><th class="num">Záznamy</th></tr></thead><tbody><?php foreach ($tables as $table): ?>
      <tr><td><code><?= e($table['name']) ?></code></td><td><span class="pill <?= $table['exists'] ? 'present' : 'missing' ?>"><?= $table['exists'] ? 'Existuje' : 'Chybí' ?></span></td><td class="num"><?= $table['exists'] ? number_format($table['rows'], 0, ',', ' ') : '—' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <div class="user-state"><div><strong>Uživatelské účty</strong><small>Běžné účty bez systémového AI účtu</small></div><strong><?= $usersState['regular'] ?></strong></div>
    <p class="hint"><?= $usersState['regular'] === 0 ? 'Po čisté instalaci může první běžný OAuth účet získat roli administrátora.' : 'Běžný účet již existuje. Automatické vytvoření prvního administrátora se neprovede.' ?></p>
  </section>
  <section class="panel">
    <div class="section-head"><div><span class="step">03</span><h2>Soubory určené k vyčištění</h2></div><span class="muted"><?= $wipe['file_count'] ?> souborů</span></div>
    <p class="muted">Při kompletní instalaci se smaže obsah uvedených datových složek a níže uvedené provozní soubory. Samotné složky zůstanou. Ochranné soubory <code>.htaccess</code> a <code>index.html</code> se zachovají.</p>
    <div class="wipe-list">
      <?php foreach ($wipe['dirs'] as $dir): ?><div class="wipe-row"><span class="file-icon">DIR</span><div><strong><?= e($dir['path']) ?>/</strong><small><?= $dir['count'] ?> souborů · <?= e($dir['size']) ?></small></div><span class="pill"><?= $dir['count'] ? 'VYČISTIT' : 'PRÁZDNÉ' ?></span></div><?php endforeach; ?>
      <?php foreach ($wipe['files'] as $file): ?><div class="wipe-row"><span class="file-icon"><?= e(strtoupper(pathinfo($file['path'], PATHINFO_EXTENSION) ?: 'FILE')) ?></span><div><strong><?= e($file['path']) ?></strong><small><?= $file['exists'] ? e($file['size']) : 'Soubor neexistuje' ?></small></div><span class="pill"><?= $file['exists'] ? 'SMAZAT' : '—' ?></span></div><?php endforeach; ?>
      <?php foreach ($wipe['optional'] as $file): ?><div class="wipe-row optional"><span class="file-icon">LOG</span><div><strong><?= e($file['path']) ?></strong><small>Volitelný logovací soubor</small></div><span class="pill"><?= $file['exists'] ? 'VOLITELNĚ' : '—' ?></span></div><?php endforeach; ?>
    </div>
    <p class="hint">Soubory aplikace (.php, .css, .js), konfigurace a <code>.env</code> nejsou součástí seznamu a instalátor je nemaže.</p>
  </section>
  <div class="action-grid"><a class="action-card primary" href="<?= $link('full') ?>"><span class="action-no">02</span><strong>Kompletní instalace</strong><small>Vyčistit databázi a provozní data, znovu nainstalovat vše</small><b>Pokračovat →</b></a><a class="action-card" href="<?= $link('manual') ?>"><span class="action-no">03</span><strong>Manuální instalace</strong><small>Vybrat jen konkrétní tabulky a SQL soubory</small><b>Otevřít výběr →</b></a></div>

<?php elseif ($page === 'full'): ?>
  <section class="notice danger"><strong>Kompletní instalace smaže všechna aplikační data.</strong> Budou vyčištěny všechny aplikační tabulky, obsah datových složek a provozní soubory uvedené v přehledu. Poté se spustí všechny SQL soubory.</section>
  <section class="panel">
    <div class="section-head"><div><span class="step">01</span><h2>Tabulky, které budou smazány</h2></div><span class="muted"><?= count($tables) ?> tabulek</span></div>
    <div class="table-wrap"><table><thead><tr><th>Tabulka</th><th>Aktuální stav</th><th class="num">Záznamy</th></tr></thead><tbody><?php foreach ($tables as $table): ?><tr><td><code><?= e($table['name']) ?></code></td><td><?= $table['exists'] ? '<span class="pill present">SMAZAT</span>' : '<span class="pill missing">NENÍ</span>' ?></td><td class="num"><?= $table['exists'] ? number_format($table['rows'], 0, ',', ' ') : '—' ?></td></tr><?php endforeach; ?></tbody></table></div>
  </section>
  <section class="panel">
    <div class="section-head"><div><span class="step">02</span><h2>SQL soubory ke spuštění</h2></div><span class="muted"><?= count($schemaFiles)+count($seedFiles) ?> souborů</span></div>
    <h3>Struktura tabulek</h3><div class="file-list"><?php foreach ($schemaFiles as $f): ?><div class="file-row"><code><?= e($f) ?></code><span>CREATE TABLE</span></div><?php endforeach; ?></div>
    <h3>Počáteční data</h3><div class="file-list"><?php foreach ($seedFiles as $f): ?><div class="file-row"><code><?= e($f) ?></code><span>INSERT DATA</span></div><?php endforeach; ?></div>
  </section>
  <section class="panel">
    <div class="section-head"><div><span class="step">03</span><h2>Potvrzení</h2></div></div>
    <p class="muted">Před spuštěním zkontroluj ještě jednou, že jde o správnou databázi. Tato akce není vratná.</p>
    <form method="post" class="run-form"><input type="hidden" name="action" value="run_full"><input type="hidden" name="token" value="<?= e($token) ?>">
      <label class="confirm"><input type="checkbox" required><span>Potvrzuji kompletní vymazání aplikačních dat a opětovnou instalaci databáze.</span></label>
      <button class="primary-button" type="submit" <?= $errors ? 'disabled' : '' ?>>Spustit kompletní instalaci <span>→</span></button>
    </form>
  </section>

<?php else: ?>
  <section class="notice warning"><strong>Manuální režim.</strong> Provedou se pouze označené operace. Neoznačené tabulky ani soubory zůstanou beze změny. Propojené tabulky musíš při mazání vybrat společně.</section>
  <form method="post" class="run-form" id="manual-form"><input type="hidden" name="action" value="run_manual"><input type="hidden" name="token" value="<?= e($token) ?>">
    <section class="panel">
      <div class="section-head"><div><span class="step">01</span><h2>Tabulky ke smazání</h2></div><button type="button" class="text-button" data-check-all="drop_tables">Vybrat vše</button></div>
      <div class="choice-list"><?php foreach ($tables as $table): ?><label class="choice"><input type="checkbox" name="drop_tables[]" value="<?= e($table['key']) ?>" <?= !$table['exists'] ? 'disabled' : '' ?>><span class="choice-main"><strong><?= e($table['name']) ?></strong><small><?= $table['exists'] ? number_format($table['rows'], 0, ',', ' ') . ' záznamů' : 'Tabulka neexistuje' ?></small></span><span class="choice-tag"><?= $table['exists'] ? 'SMAZAT' : '—' ?></span></label><?php endforeach; ?></div>
    </section>
    <section class="panel">
      <div class="section-head"><div><span class="step">02</span><h2>SQL – struktura tabulek</h2></div><div class="inline-actions"><button type="button" class="text-button" data-check-all="schema_files">Vybrat vše</button><button type="button" class="text-button" data-uncheck-all="schema_files">Zrušit</button></div></div>
      <p class="muted">Vytvoří chybějící tabulky. Existující tabulky se nepřepisují.</p>
      <div class="choice-list"><?php foreach ($schemaFiles as $file): $key=inst_table_key_from_file($file); $exists=isset($tables[$key])&&$tables[$key]['exists']; ?><label class="choice"><input type="checkbox" name="schema_files[]" value="<?= e($file) ?>" checked><span class="choice-main"><strong><?= e($file) ?></strong><small><?= $exists ? 'Tabulka existuje – CREATE bude přeskočeno' : 'Tabulka bude vytvořena' ?></small></span><span class="choice-tag">CREATE</span></label><?php endforeach; ?></div>
    </section>
    <section class="panel">
      <div class="section-head"><div><span class="step">03</span><h2>SQL – počáteční data</h2></div><div class="inline-actions"><button type="button" class="text-button" data-check-all="seed_files">Vybrat vše</button><button type="button" class="text-button" data-uncheck-all="seed_files">Zrušit</button></div></div>
      <p class="muted">Seed soubory se spustí pouze tehdy, pokud jsou označené. Již naplněné základní tabulky se přeskočí podle ochranné kontroly.</p>
      <div class="choice-list"><?php foreach ($seedFiles as $file): ?><label class="choice"><input type="checkbox" name="seed_files[]" value="<?= e($file) ?>" checked><span class="choice-main"><strong><?= e($file) ?></strong><small><?= $file === '10_users.sql' ? 'Systémový účet AI' : ($file === '20_countries.sql' ? 'Seznam států' : 'Počáteční data') ?></small></span><span class="choice-tag">INSERT</span></label><?php endforeach; ?></div>
    </section>
    <section class="panel final-panel"><div class="section-head"><div><span class="step">04</span><h2>Spuštění výběru</h2></div></div><div class="summary" id="selection-summary">Načítám výběr…</div><label class="confirm"><input type="checkbox" id="confirm-run" required><span>Rozumím tomu, že smazání vybraných tabulek odstraní jejich data a tuto akci nelze vrátit.</span></label><button type="submit" class="primary-button" <?= $errors ? 'disabled' : '' ?>>Provést vybrané operace <span>→</span></button></section>
  </form>
<?php endif; ?>
<?php
$body = (string)ob_get_clean();
inst_render('Instalační centrum', $body, true);
