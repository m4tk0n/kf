<?php
declare(strict_types=1);

try {
    require dirname(__DIR__) . '/src/bootstrap.php';
} catch (Throwable $ex) {
    http_response_code(500);
    exit('Config error: ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

lang_load('install');
$want = $_GET['lang'] ?? cfg('app.default_lang');
lang_code(in_array($want, cfg('app.languages'), true) ? $want : cfg('app.default_lang'));

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

$prefix = cfg('db.prefix');
$lock = __DIR__ . '/installed.lock';
$token = hash_hmac('sha256', 'kf-install-maintenance', cfg('app.key'));
$errors = [];
$done = false;
$dbAvailable = false;
$installed = false;
$tableCount = 0;
$checks = [];

function install_table_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$name]);
    return (int)$stmt->fetchColumn() > 0;
}

function install_run_sql_file(PDO $pdo, string $file, string $prefix): void
{
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Nelze přečíst SQL soubor: ' . basename($file));
    }
    $sql = str_replace('{p}', $prefix, $sql);
    $sql = (string)preg_replace('/^\\s*--.*$/m', '', $sql);
    $statements = preg_split('/;\\s*(?:\\r?\\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
}

try {
    $pdo = db();
    $dbAvailable = true;
    $metaTable = $prefix . 'meta';
    $installed = install_table_exists($pdo, $metaTable);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?');
    $stmt->execute([$prefix . '%']);
    $tableCount = (int)$stmt->fetchColumn();
} catch (Throwable $ex) {
    $errors[] = L('inst-db-unavailable') . ' ' . $ex->getMessage();
}

$checks['PHP ' . cfg('install.min_php')] = version_compare(PHP_VERSION, cfg('install.min_php'), '>=');
foreach (cfg('install.extensions') as $extension) {
    $checks['ext ' . $extension] = extension_loaded($extension);
}
$checks['gd webp'] = function_exists('imagewebp');
foreach (cfg('install.writable') as $dir) {
    $path = path($dir);
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $checks['dir ' . cfg("paths.$dir")] = is_dir($path) && is_writable($path);
}
$checks['DB connection'] = $dbAvailable;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) {
        $errors[] = L('inst-invalid-token');
    }
    if (!$dbAvailable) {
        $errors[] = L('inst-db-unavailable');
    }
    if (in_array(false, $checks, true)) {
        $errors[] = L('inst-fail');
    }

    if (!$errors) {
        try {
            // Všechny základní SQL soubory jsou opakovatelně bezpečné:
            // CREATE TABLE IF NOT EXISTS a INSERT IGNORE. Žádná tabulka ani záznam se nemaže.
            $files = glob(__DIR__ . '/sql/*.sql') ?: [];
            sort($files, SORT_NATURAL);
            foreach ($files as $file) {
                install_run_sql_file($pdo, $file, $prefix);
            }

            // Budoucí automatické migrace musí mít příponu .migration.sql.
            // Starší ruční migrační soubory se záměrně nespouštějí.
            $migrationTable = $prefix . 'schema_migrations';
            $pdo->exec("CREATE TABLE IF NOT EXISTS `{$migrationTable}` (
                `migration` VARCHAR(190) NOT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");

            $migrations = glob(__DIR__ . '/migrations/*.migration.sql') ?: [];
            sort($migrations, SORT_NATURAL);
            foreach ($migrations as $migration) {
                $name = basename($migration);
                $stmt = $pdo->prepare("SELECT 1 FROM `{$migrationTable}` WHERE migration = ? LIMIT 1");
                $stmt->execute([$name]);
                if ($stmt->fetchColumn()) {
                    continue;
                }
                install_run_sql_file($pdo, $migration, $prefix);
                $stmt = $pdo->prepare("INSERT INTO `{$migrationTable}` (migration) VALUES (?)");
                $stmt->execute([$name]);
            }

            if (file_put_contents($lock, date('c') . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException(L('inst-lock-write-fail'));
            }
            $installed = install_table_exists($pdo, $metaTable);
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = L('inst-update-failed') . ' ' . $ex->getMessage();
        }
    }
}

$allChecksOk = !in_array(false, $checks, true);
?>
<!doctype html>
<html lang="<?= e(lang_code()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(L('inst-title')) ?></title>
<style>
:root{color-scheme:light dark;--bg:#10151d;--panel:#19212c;--line:#303b49;--muted:#aab6c5;--ok:#39b978;--bad:#e16b6b;--accent:#5798e8}
*{box-sizing:border-box}body{font:16px/1.5 system-ui,sans-serif;margin:0;padding:20px;background:var(--bg);color:#f2f5f8}
main{width:min(100%,620px);margin:0 auto}.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px}
h1{font-size:1.45rem;margin:0 0 6px}h2{font-size:1.05rem;margin:22px 0 10px}.muted{color:var(--muted)}
.status{display:flex;align-items:center;gap:10px;padding:13px;border:1px solid var(--line);border-radius:10px;margin:18px 0}
.dot{width:11px;height:11px;border-radius:50%;background:var(--ok);flex:none}.bad .dot{background:var(--bad)}
ul{list-style:none;margin:0;padding:0;display:grid;gap:7px}li{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px solid var(--line)}
.ok{color:var(--ok)}.fail{color:var(--bad)}button{width:100%;border:0;border-radius:9px;padding:13px 16px;background:var(--accent);color:white;font:inherit;font-weight:650;margin-top:18px;cursor:pointer}
button:disabled{opacity:.45;cursor:not-allowed}.error{padding:11px;border:1px solid var(--bad);border-radius:8px;color:var(--bad);margin:10px 0;overflow-wrap:anywhere}
.lang{display:flex;justify-content:flex-end;gap:14px;margin-bottom:12px}a{color:var(--accent)}.note{font-size:.9rem;color:var(--muted);margin-top:12px}
</style>
</head>
<body>
<main>
  <nav class="lang"><a href="?lang=cs">CZ</a><a href="?lang=en">EN</a></nav>
  <section class="panel">
    <h1><?= e(L('inst-title')) ?></h1>
    <p class="muted"><?= e(L('inst-subtitle')) ?></p>

    <?php if ($installed || is_file($lock)): ?>
      <div class="status"><span class="dot"></span><div><strong><?= e(L('inst-already-done')) ?></strong><br><span class="muted"><?= e(L('inst-already-desc')) ?></span></div></div>
    <?php else: ?>
      <div class="status <?= $tableCount > 0 ? 'bad' : '' ?>"><span class="dot"></span><div><strong><?= e(L('inst-not-installed')) ?></strong><br><span class="muted"><?= e(L('inst-not-installed-desc')) ?></span></div></div>
    <?php endif; ?>

    <h2><?= e(L('inst-status')) ?></h2>
    <ul>
      <li><span><?= e(L('inst-db-status')) ?></span><strong class="<?= $dbAvailable ? 'ok' : 'fail' ?>"><?= e($dbAvailable ? L('inst-db-connected') : L('inst-db-error')) ?></strong></li>
      <li><span><?= e(L('inst-prefix-tables')) ?></span><strong><?= (int)$tableCount ?></strong></li>
      <li><span><?= e(L('inst-lock-status')) ?></span><strong><?= e(is_file($lock) ? L('inst-lock-present') : L('inst-lock-missing')) ?></strong></li>
    </ul>

    <h2><?= e(L('inst-checks')) ?></h2>
    <ul>
      <?php foreach ($checks as $name => $ok): ?>
        <li><span><?= e($name) ?></span><strong class="<?= $ok ? 'ok' : 'fail' ?>"><?= e($ok ? L('inst-check-ok') : L('inst-check-fail')) ?></strong></li>
      <?php endforeach; ?>
    </ul>

    <?php foreach ($errors as $error): ?><p class="error"><?= e($error) ?></p><?php endforeach; ?>
    <?php if ($done): ?><p class="ok"><?= e(L('inst-update-done')) ?></p><?php endif; ?>

    <form method="post">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <button type="submit" <?= (!$dbAvailable || !$allChecksOk) ? 'disabled' : '' ?>><?= e($installed || is_file($lock) ? L('inst-run-again') : L('inst-run-first')) ?></button>
    </form>
    <p class="note"><?= e(L('inst-safe-note')) ?></p>
    <p><a href="<?= e(rtrim(cfg('app.base_url'), '/') . '/') ?>"><?= e(L('inst-open')) ?></a></p>
  </section>
</main>
</body>
</html>
