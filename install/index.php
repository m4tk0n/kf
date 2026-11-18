<?php
declare(strict_types=1);
try { require dirname(__DIR__) . '/src/bootstrap.php'; }
catch (Throwable $ex) { http_response_code(500); exit('Konfiguraci instalátoru se nepodařilo načíst.'); }

lang_load('install');
$want = $_GET['lang'] ?? detect_lang();
lang_code(in_array($want, cfg('app.languages'), true) ? $want : cfg('app.default_lang'));
$https = strtolower((string)parse_url(cfg('app.base_url'), PHP_URL_SCHEME)) === 'https';
$installPath = rtrim((string)parse_url(cfg('app.base_url'), PHP_URL_PATH), '/') . '/install/';
session_name('KFINSTALL');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_set_cookie_params(['lifetime'=>0,'path'=>$installPath,'secure'=>$https,'httponly'=>true,'samesite'=>'Strict']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['install_csrf'];

$prefix = (string)cfg('db.prefix');
$lock = __DIR__ . '/installed.lock';
$installed = is_file($lock);
$errors = [];
$notice = '';
$done = false;
$pdo = null;
$dbConnected = false;
$tables = ['meta'=>false,'users'=>false,'user_identities'=>false,'sessions'=>false,'schema_migrations'=>false];
$columns = [];
$migrationApplied = false;

function inst_table_exists(PDO $pdo, string $table): bool {
    $q=$pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
    $q->execute([$table]); return (bool)$q->fetchColumn();
}
function inst_column_exists(PDO $pdo, string $table, string $column): bool {
    $q=$pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1');
    $q->execute([$table,$column]); return (bool)$q->fetchColumn();
}
function inst_run_sql(PDO $pdo, string $file, string $prefix): void {
    if (!is_file($file) || !is_readable($file)) throw new RuntimeException('SQL migration unavailable');
    $sql=str_replace('{p}',$prefix,(string)file_get_contents($file));
    $sql=(string)preg_replace('/^\\s*--.*$/m','',$sql);
    foreach (preg_split('/;\\s*(?:\\r?\\n|$)/',$sql) ?: [] as $statement) {
        if (trim($statement)!=='') $pdo->exec($statement);
    }
}
function inst_create_migration_table(PDO $pdo, string $prefix): void {
    $name=chr(96).$prefix.'schema_migrations'.chr(96);
    $pdo->exec("CREATE TABLE IF NOT EXISTS {$name} (version VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
}
function inst_mark_migration(PDO $pdo, string $prefix, string $version): void {
    $q=$pdo->prepare('INSERT IGNORE INTO '.chr(96).$prefix.'schema_migrations'.chr(96).' (version) VALUES (?)');
    $q->execute([$version]);
}

try {
    $pdo=db(); $dbConnected=true;
    foreach (array_keys($tables) as $table) $tables[$table]=inst_table_exists($pdo,$prefix.$table);
    if ($tables['users']) foreach (['id','email','role','state','confirmed','pass'] as $column) $columns[$column]=inst_column_exists($pdo,$prefix.'users',$column);
    if ($tables['schema_migrations']) {
        $q=$pdo->prepare('SELECT 1 FROM '.chr(96).$prefix.'schema_migrations'.chr(96).' WHERE version=? LIMIT 1');
        $q->execute(['001_auth']); $migrationApplied=(bool)$q->fetchColumn();
    }
} catch (Throwable $ex) { error_log('K&F installer database check: '.$ex->getMessage()); }

$checks=['PHP '.cfg('install.min_php')=>version_compare(PHP_VERSION,cfg('install.min_php'),'>=')];
foreach(cfg('install.extensions') as $extension) $checks['PHP extension '.$extension]=extension_loaded($extension);
$checks['GD WebP']=function_exists('imagewebp');
foreach(cfg('install.writable') as $directory) {
    $target=path($directory); if(!is_dir($target)) @mkdir($target,0755,true);
    $checks['Writable: '.cfg('paths.'.$directory)]=is_dir($target)&&is_writable($target);
}
$checks['Database connection']=$dbConnected;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $posted=(string)($_POST['csrf']??'');
    if($posted===''||!hash_equals($csrf,$posted)) $errors[]=L('inst-invalid-request');
    elseif(in_array(false,$checks,true)) $errors[]=L('inst-fail');
    else {
        try {
            if(!$pdo instanceof PDO) throw new RuntimeException('Database unavailable');
            $action=(string)($_POST['action']??'');
            if($action==='install'&&!$installed) {
                if($tables['meta']||$tables['users']) throw new RuntimeException('Database already contains K&F tables');
                $files=glob(__DIR__.'/sql/*.sql')?:[]; sort($files,SORT_STRING);
                if(!$files) throw new RuntimeException('Installation SQL not found');
                foreach($files as $file) inst_run_sql($pdo,$file,$prefix);
                inst_create_migration_table($pdo,$prefix);
                inst_mark_migration($pdo,$prefix,'001_auth');
                if(file_put_contents($lock,date('c'),LOCK_EX)===false) throw new RuntimeException('Cannot write installation lock');
                $done=true; $notice=L('inst-installed');
            } elseif($action==='upgrade'&&$installed) {
                $submitted=(string)($_POST['install_key']??''); $appKey=(string)cfg('app.key');
                if(strlen($appKey)<32||$submitted===''||!hash_equals($appKey,$submitted)) throw new RuntimeException('Invalid installation key');
                if(!$tables['users']) throw new RuntimeException('Users table missing');
                foreach(['id','email','role','state','confirmed'] as $required) if(empty($columns[$required])) throw new RuntimeException('Unexpected users schema');
                inst_create_migration_table($pdo,$prefix);
                $q=$pdo->prepare('SELECT 1 FROM '.chr(96).$prefix.'schema_migrations'.chr(96).' WHERE version=? LIMIT 1');
                $q->execute(['001_auth']); $applied=(bool)$q->fetchColumn();
                if(!$applied) {
                    inst_run_sql($pdo,__DIR__.'/migrations/001_auth.sql',$prefix);
                    if(!inst_table_exists($pdo,$prefix.'user_identities')||!inst_table_exists($pdo,$prefix.'sessions')) throw new RuntimeException('Authentication tables missing after migration');
                    if(inst_column_exists($pdo,$prefix.'users','pass')) $pdo->exec('ALTER TABLE '.chr(96).$prefix.'users'.chr(96).' DROP COLUMN '.chr(96).'pass'.chr(96));
                    inst_mark_migration($pdo,$prefix,'001_auth');
                }
                $done=true; $notice=$applied?L('inst-up-to-date'):L('inst-upgrade-done');
            } else throw new RuntimeException('Invalid installer action');
        } catch(Throwable $ex) {
            error_log('K&F installer action failed: '.$ex->getMessage());
            $errors[]=L('inst-action-failed');
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
?>
<!doctype html>
<html lang="<?=e(lang_code())?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="light"><title><?=e(L('inst-title'))?></title>
<style>
:root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#20252b;background:#f3f5f7}
*{box-sizing:border-box}body{margin:0;padding:20px 12px;min-height:100vh;display:grid;place-items:center}
main{width:min(100%,680px);padding:clamp(20px,5vw,34px);background:#fff;border:1px solid #e1e5e9;border-radius:15px;box-shadow:0 8px 30px #17212b0d}
header{display:flex;justify-content:space-between;gap:14px;margin-bottom:22px}h1{font-size:clamp(24px,5vw,30px);line-height:1.15;margin:0 0 7px}
h2{font-size:18px;margin:24px 0 12px}p{line-height:1.55;margin:8px 0 14px}.muted,small{color:#65717d;font-size:14px}
.lang{white-space:nowrap;font-size:14px}.lang a{margin-left:8px;color:#35495e}
.status{padding:13px 14px;border:1px solid #e1e5e9;border-radius:9px}.status p{margin:3px 0 0;color:#65717d;font-size:14px}
ul{list-style:none;padding:0;margin:0;display:grid;gap:7px}li{display:flex;justify-content:space-between;gap:12px;padding:9px 11px;border-radius:7px;background:#f7f8f9;font-size:14px}
.good{color:#187044;font-weight:650}.bad{color:#b42318;font-weight:650}
.notice,.error,.warning{padding:12px 14px;border-radius:9px;margin:14px 0;font-size:14px;line-height:1.5}
.notice{background:#edf8f1;color:#175c37}.error{background:#fff0ef;color:#922b24}.warning{background:#fff8e8;color:#78520b}
label{display:block;font-size:14px;font-weight:600;margin:17px 0 7px}
input{width:100%;min-height:48px;padding:11px 12px;border:1px solid #cbd2d9;border-radius:8px;font:inherit;font-size:16px}
button{width:100%;min-height:48px;margin-top:14px;border:0;border-radius:8px;background:#263b50;color:#fff;font:inherit;font-weight:650}
button:disabled{opacity:.45}small{display:block;line-height:1.5;margin-top:8px}hr{border:0;border-top:1px solid #e8ebee;margin:23px 0}
footer{margin-top:22px;text-align:center;color:#7b858e;font-size:12px}
@media(max-width:420px){body{padding:9px}main{padding:19px 15px;border-radius:11px}}
</style></head><body><main>
<header><div><h1><?=e(L('inst-title'))?></h1><p class="muted"><?=e($installed?L('inst-upgrade-subtitle'):L('inst-subtitle'))?></p></div>
<nav class="lang"><?php foreach(cfg('app.languages') as $language):?><a href="?lang=<?=e($language)?>"><?=e(strtoupper($language))?></a><?php endforeach;?></nav></header>
<?php if($done):?>
<div class="notice"><strong><?=e($notice)?></strong></div>
<p><?=e($installed?L('inst-upgrade-next'):L('inst-remove-folder'))?></p><a href="<?=e(url())?>"><?=e(L('inst-open'))?></a>
<?php else:?>
<section class="status"><strong><?=e($installed?L('inst-mode-upgrade'):L('inst-mode-install'))?></strong><p><?=e($installed?L('inst-mode-upgrade-desc'):L('inst-mode-install-desc'))?></p></section>
<h2><?=e(L('inst-checks'))?></h2><ul><?php foreach($checks as $name=>$ok):?><li><span><?=e($name)?></span><span class="<?=$ok?'good':'bad'?>"><?=$ok?'OK':'CHYBÍ'?></span></li><?php endforeach;?></ul>
<?php if($installed):?>
<h2><?=e(L('inst-db-status'))?></h2><ul>
<li><span>users</span><span class="<?=$tables['users']?'good':'bad'?>"><?=$tables['users']?'OK':'CHYBÍ'?></span></li>
<li><span>user_identities</span><span class="<?=$tables['user_identities']?'good':'bad'?>"><?=$tables['user_identities']?'OK':'CHYBÍ'?></span></li>
<li><span>sessions</span><span class="<?=$tables['sessions']?'good':'bad'?>"><?=$tables['sessions']?'OK':'CHYBÍ'?></span></li>
<li><span>users.pass</span><span class="<?=empty($columns['pass'])?'good':'bad'?>"><?=empty($columns['pass'])?L('inst-removed'):L('inst-present')?></span></li>
<li><span><?=e(L('inst-migration'))?></span><span class="<?=$migrationApplied?'good':'bad'?>"><?=$migrationApplied?L('inst-applied'):L('inst-pending')?></span></li>
</ul><div class="warning"><?=e(L('inst-backup-warning'))?></div><?php endif;?>
<?php foreach($errors as $error):?><div class="error"><?=e($error)?></div><?php endforeach;?>
<?php if(!$installed):?><form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="install"><button type="submit" <?=$dbConnected&&count(array_filter($checks))===count($checks)?'':'disabled'?>><?=e(L('inst-submit'))?></button></form>
<?php else:?><hr><h2><?=e(L('inst-upgrade-title'))?></h2><p><?=e(L('inst-key-help'))?></p>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="upgrade">
<label for="install-key"><?=e(L('inst-key-label'))?></label><input id="install-key" name="install_key" type="password" autocomplete="off" required>
<small><?=e(L('inst-key-note'))?></small><button type="submit" <?=(!$dbConnected||!$tables['users']||$migrationApplied||count(array_filter($checks))!==count($checks))?'disabled':''?>><?=e(L('inst-upgrade-submit'))?></button></form><?php endif;?>
<?php endif;?><footer>K&amp;F · <?=e(L('inst-secure-footer'))?></footer>
</main></body></html>
