<?php
declare(strict_types=1);

try {
    require dirname(__DIR__) . '/src/bootstrap.php';
} catch (Throwable $ex) {
    http_response_code(500);
    exit('Config error: ' . htmlspecialchars($ex->getMessage()));
}
lang_load('install');

$want = $_GET['lang'] ?? cfg('app.default_lang');
lang_code(in_array($want, cfg('app.languages'), true) ? $want : cfg('app.default_lang'));

$lock = __DIR__ . '/installed.lock';
if (is_file($lock)) {
    http_response_code(403);
    exit(e(L('inst-locked')));
}

// ---- kontrola prostředí ----
$checks = ['PHP ' . cfg('install.min_php') => version_compare(PHP_VERSION, cfg('install.min_php'), '>=')];
foreach (cfg('install.extensions') as $x) {
    $checks['ext ' . $x] = extension_loaded($x);
}
$checks['gd webp'] = function_exists('imagewebp');
foreach (cfg('install.writable') as $d) {
    $p = path($d);
    if (!is_dir($p)) {
        @mkdir($p, 0755, true);
    }
    $checks['dir ' . cfg("paths.$d")] = is_dir($p) && is_writable($p);
}

$token  = hash_hmac('sha256', 'install', cfg('app.key'));
$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($token, (string)($_POST['token'] ?? ''))) {
        $errors[] = 'Invalid token';
    }
    if (in_array(false, $checks, true)) {
        $errors[] = L('inst-fail');
    }

    if (!$errors) {
        try {
            $pdo  = db();
            $meta = cfg('db.prefix') . 'meta';
            if ($pdo->query('SHOW TABLES LIKE ' . $pdo->quote(addcslashes($meta, '_%')))->fetchColumn()) {
                throw new RuntimeException(L('inst-exists'));
            }
            $files = glob(__DIR__ . '/sql/*.sql') ?: [];
            sort($files);
            foreach ($files as $f) {
                $sql = str_replace('{p}', cfg('db.prefix'), (string)file_get_contents($f));
                $sql = (string)preg_replace('/^\\s*--.*$/m', '', $sql);
                foreach (preg_split('/;\\s*[\\r\\n]+/', $sql) ?: [] as $stmt) {
                    if (trim($stmt) !== '') {
                        $pdo->exec($stmt);
                    }
                }
            }
            file_put_contents($lock, date('c'));
            $done = true;
        } catch (Throwable $ex) {
            $errors[] = L('inst-sql-fail') . ' ' . $ex->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="<?= e(lang_code()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(L('inst-title')) ?></title>
<style>
body{font:16px/1.5 system-ui,sans-serif;max-width:32rem;margin:1rem auto;padding:0 1rem}
input,button{width:100%;padding:.6rem;margin:.25rem 0 1rem;box-sizing:border-box;font:inherit}
.ok{color:#080}.bad{color:#b00}
</style>
</head>
<body>
<h1><?= e(L('inst-title')) ?></h1>
<p><?php foreach (cfg('app.languages') as $l): ?><a href="?lang=<?= e($l) ?>"><?= e(strtoupper($l)) ?></a> <?php endforeach; ?></p>

<?php if ($done): ?>
  <p class="ok"><?= e(L('inst-done')) ?></p>
  <p><a href="<?= e(url()) ?>"><?= e(L('inst-open')) ?></a></p>
<?php else: ?>
  <h2><?= e(L('inst-checks')) ?></h2>
  <ul>
  <?php foreach ($checks as $name => $ok): ?>
    <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✓' : '✗' ?> <?= e($name) ?></li>
  <?php endforeach; ?>
  </ul>
  <?php foreach ($errors as $err): ?><p class="bad"><?= e($err) ?></p><?php endforeach; ?>
  <form method="post">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <button type="submit"><?= e(L('inst-submit')) ?></button>
  </form>
<?php endif; ?>
</body>
</html>
