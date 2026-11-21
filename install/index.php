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
} elseif ($action !== '') {
    $posted = (string)($_POST['token'] ?? '');
    if (!inst_token_valid($posted)) {
        http_response_code(403);
        inst_login('Odemknutí vypršelo. Zadej instalační klíč znovu.');
    }
    if (cfg('security.csrf_origin_check') && !origin_ok()) {
        http_response_code(403);
        inst_login('Požadavek neprošel bezpečnostní kontrolou.');
    }
    $token = inst_token(time() + (int)cfg('install.token_ttl_sec'));
} else {
    inst_login();
}

if ($action === 'run') {
    $result = inst_execute_selection($_POST);
    inst_render('Výsledek instalace', inst_result_html($result, $token));
}

$checks = inst_environment_checks();
$tables = inst_database_tables();
$schemaFiles = inst_sql_files((string)cfg('install.sql_tables_dir'));
$seedFiles = inst_sql_files((string)cfg('install.sql_seed_dir'));
$usersState = inst_user_state();
$errors = array_filter($checks, static fn(array $x): bool => $x['state'] === 'err');
$warnings = array_filter($checks, static fn(array $x): bool => $x['state'] === 'warn');

ob_start();
?>
<div class="hero">
  <div class="eyebrow">K&F / SYSTEM SETUP</div>
  <h1>Instalační centrum</h1>
  <p>Kontrola prostředí, databáze a řízené spuštění SQL. Každý krok je samostatně volitelný.</p>
  <div class="hero-meta"><span>PHP <?= e(PHP_VERSION) ?></span><span>DB <?= e((string)env('DB_NAME', '')) ?></span><span>Prefix <?= e((string)cfg('db.prefix')) ?></span></div>
</div>

<?php if ($errors): ?>
<section class="notice danger"><strong>Instalaci nelze bezpečně spustit.</strong><p>Nejdřív oprav chyby označené červeně.</p></section>
<?php elseif ($warnings): ?>
<section class="notice warning"><strong>Kontrola dokončena s upozorněními.</strong><p>Pokračování je možné, ale zkontroluj uvedené položky.</p></section>
<?php else: ?>
<section class="notice success"><strong>Prostředí je připravené.</strong><p>Nebyly nalezeny blokující problémy.</p></section>
<?php endif; ?>

<section class="panel">
  <div class="section-head"><div><span class="step">01</span><h2>Kontrola prostředí</h2></div><span class="muted"><?= count($checks) ?> kontrol</span></div>
  <div class="check-grid">
  <?php foreach ($checks as $check): ?>
    <div class="check-row"><span class="status <?= e($check['state']) ?>"></span><div><strong><?= e($check['label']) ?></strong><small><?= e($check['detail']) ?></small></div><span class="status-text"><?= e($check['state'] === 'ok' ? 'OK' : ($check['state'] === 'warn' ? 'POZOR' : 'CHYBA')) ?></span></div>
  <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="section-head"><div><span class="step">02</span><h2>Aktuální databáze</h2></div><span class="muted"><?= count($tables) ?> tabulek v konfiguraci</span></div>
  <div class="table-wrap"><table><thead><tr><th>Tabulka</th><th>Stav</th><th class="num">Záznamy</th></tr></thead><tbody>
  <?php foreach ($tables as $table): ?>
    <tr><td><code><?= e($table['name']) ?></code></td><td><span class="pill <?= $table['exists'] ? 'present' : 'missing' ?>"><?= $table['exists'] ? 'Existuje' : 'Chybí' ?></span></td><td class="num"><?= $table['exists'] ? number_format($table['rows'], 0, ',', ' ') : '—' ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="user-state"><div><strong>Uživatelské účty</strong><small>Počet běžných účtů, bez systémového AI účtu</small></div><strong><?= $usersState['regular'] ?></strong></div>
  <?php if ($usersState['regular'] === 0): ?><p class="hint">Po úspěšném spuštění může první člověk přihlášený přes OAuth získat roli administrátora.</p><?php else: ?><p class="hint">Běžný uživatelský účet již existuje. Instalátor dalšího administrátora automaticky nevytvoří ani nepovýší.</p><?php endif; ?>
</section>

<form method="post" id="installer-form">
<input type="hidden" name="action" value="run"><input type="hidden" name="token" value="<?= e($token) ?>">
<section class="panel">
  <div class="section-head"><div><span class="step">03</span><h2>Výběr tabulek ke smazání</h2></div><button type="button" class="text-button" data-check-all="drop_tables">Vybrat vše</button></div>
  <div class="callout danger-lite"><strong>Pozor: nevratná operace.</strong> Označené tabulky budou smazány včetně všech dat. Neoznačených tabulek se mazání nedotkne.</div>
  <div class="choice-list">
  <?php foreach ($tables as $table): ?>
    <label class="choice"><input type="checkbox" name="drop_tables[]" value="<?= e($table['key']) ?>"><span class="choice-main"><strong><?= e($table['name']) ?></strong><small><?= $table['exists'] ? number_format($table['rows'], 0, ',', ' ') . ' záznamů' : 'Tabulka neexistuje' ?></small></span><span class="choice-tag"><?= $table['exists'] ? 'SMAZAT' : '—' ?></span></label>
  <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="section-head"><div><span class="step">04</span><h2>SQL – struktura tabulek</h2></div><div class="inline-actions"><button type="button" class="text-button" data-check-all="schema_files">Vybrat vše</button><button type="button" class="text-button" data-uncheck-all="schema_files">Zrušit výběr</button></div></div>
  <p class="muted">Vybrané soubory vytvoří chybějící tabulky. Pokud tabulka existuje a není vybraná ke smazání, její CREATE se přeskočí.</p>
  <div class="choice-list">
  <?php foreach ($schemaFiles as $file): ?>
    <?php $tableKey = inst_table_key_from_file($file); $exists = isset($tablesByKey[$tableKey]) ? $tablesByKey[$tableKey]['exists'] : false; ?>
    <label class="choice"><input type="checkbox" name="schema_files[]" value="<?= e($file) ?>" checked><span class="choice-main"><strong><?= e($file) ?></strong><small>Tabulka <?= e($tableKey) ?> · <?= $exists ? 'již existuje' : 'bude vytvořena' ?></small></span><span class="choice-tag">CREATE</span></label>
  <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <div class="section-head"><div><span class="step">05</span><h2>SQL – počáteční data</h2></div><div class="inline-actions"><button type="button" class="text-button" data-check-all="seed_files">Vybrat vše</button><button type="button" class="text-button" data-uncheck-all="seed_files">Zrušit výběr</button></div></div>
  <p class="muted">Seed soubory vkládají základní data. Spouštěj je jen tehdy, když víš, že jejich data v databázi ještě nejsou.</p>
  <div class="choice-list">
  <?php foreach ($seedFiles as $file): ?>
    <label class="choice"><input type="checkbox" name="seed_files[]" value="<?= e($file) ?>"><span class="choice-main"><strong><?= e($file) ?></strong><small><?= $file === '10_users.sql' ? 'Systémový účet AI' : ($file === '20_countries.sql' ? 'Seznam států' : 'Počáteční data') ?></small></span><span class="choice-tag">INSERT</span></label>
  <?php endforeach; ?>
  </div>
</section>

<section class="panel final-panel">
  <div class="section-head"><div><span class="step">06</span><h2>Kontrola a spuštění</h2></div></div>
  <div class="summary" id="selection-summary">Zatím není vybráno žádné mazání. Struktura a seed SQL se spustí podle zaškrtnutých souborů.</div>
  <label class="confirm"><input type="checkbox" id="confirm-run" required><span>Rozumím tomu, že smazání vybraných tabulek odstraní jejich data a tuto akci nelze vrátit.</span></label>
  <button type="submit" class="primary-button" <?= $errors ? 'disabled' : '' ?>>Spustit vybrané operace <span>→</span></button>
  <p class="footnote">Instalační klíč je platný <?= (int)cfg('install.token_ttl_sec') / 60 ?> minut. Výsledek každého SQL souboru se zobrazí níže po spuštění.</p>
</section>
</form>
<?php
$body = (string)ob_get_clean();
inst_render('Instalační centrum', $body, true);
