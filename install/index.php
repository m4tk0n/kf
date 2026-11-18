<?php
declare(strict_types=1);
try { require dirname(__DIR__) . '/src/bootstrap.php'; }
catch (Throwable $ex) { http_response_code(500); exit('Config error: ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')); }
lang_load('install');
$want=$_GET['lang']??cfg('app.default_lang');
lang_code(in_array($want,cfg('app.languages'),true)?$want:cfg('app.default_lang'));
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow');
$prefix=cfg('db.prefix'); $lock=__DIR__.'/installed.lock'; $token=hash_hmac('sha256','kf-install-maintenance',cfg('app.key'));
$errors=[]; $done=false; $dbAvailable=false; $pdo=null; $tableCount=0; $installed=false;
$plan=['create'=>[],'columns'=>[],'indexes'=>[],'foreign'=>[],'mismatches'=>[],'seeds'=>[],'migrations'=>[],'manual'=>[]];
function inst_id(string $s):string{return chr(96).str_replace(chr(96),chr(96).chr(96),$s).chr(96);}
function inst_split(string $sql):array{
 $out=[];$buf='';$q='';$line=false;$block=false;$n=strlen($sql);
 for($i=0;$i<$n;$i++){ $c=$sql[$i];$nx=$i+1<$n?$sql[$i+1]:'';
  if($line){if($c==="\n"){$line=false;$buf.=$c;}continue;}
  if($block){if($c==='*'&&$nx==='/'){$block=false;$i++;}continue;}
  if($q!==''){$buf.=$c;if($c==='\\'&&$i+1<$n){$buf.=$sql[++$i];continue;}if($c===$q){if($nx===$q)$buf.=$sql[++$i];else $q='';}continue;}
  if(($c==='-'&&$nx==='-'&&($i+2>=$n||ctype_space($sql[$i+2])))||$c==='#'){$line=true;if($c==='-')$i++;continue;}
  if($c==='/'&&$nx==='*'){$block=true;$i++;continue;}
  if($c==="'"||$c==='"'||ord($c)===96){$q=$c;$buf.=$c;continue;}
  if($c===';'){if(trim($buf)!=='')$out[]=trim($buf);$buf='';}else $buf.=$c;
 }
 if(trim($buf)!=='')$out[]=trim($buf);return $out;
}
function inst_parts(string $s):array{
 $out=[];$b='';$d=0;$q='';$n=strlen($s);
 for($i=0;$i<$n;$i++){ $c=$s[$i];$nx=$i+1<$n?$s[$i+1]:'';
  if($q!==''){$b.=$c;if($c==='\\'&&$i+1<$n){$b.=$s[++$i];continue;}if($c===$q){if($nx===$q)$b.=$s[++$i];else $q='';}continue;}
  if($c==="'"||$c==='"'||ord($c)===96){$q=$c;$b.=$c;continue;}
  if($c==='(')$d++;if($c===')')$d--;
  if($c===','&&$d===0){$out[]=trim($b);$b='';}else $b.=$c;
 }if(trim($b)!=='')$out[]=trim($b);return $out;
}
function inst_safe_base(string $s):bool{return (bool)preg_match('/^(CREATE\\s+TABLE\\s+IF\\s+NOT\\s+EXISTS|INSERT\\s+IGNORE\\s+INTO)\\b/i',ltrim($s));}
function inst_safe_migration(string $s):bool{return (bool)preg_match('/^(CREATE\\s+TABLE\\s+IF\\s+NOT\\s+EXISTS|ALTER\\s+TABLE\\s+.+\\s+ADD\\s+(?:COLUMN|INDEX|UNIQUE\\s+INDEX|CONSTRAINT)\\b|CREATE\\s+(?:UNIQUE\\s+)?INDEX)\\b/i',ltrim($s));}
function inst_sql(string $f,string $p):string{$s=file_get_contents($f);if($s===false)throw new RuntimeException('Nelze přečíst '.basename($f));return str_replace('{p}',$p,$s);}
function inst_create_def(string $s):?array{
 if(!preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?\x60([^\x60]+)\x60\s*\((.*)\)\s*(?:ENGINE|DEFAULT|;|$)/is',$s,$m))return null;
 $r=['table'=>$m[1],'columns'=>[],'indexes'=>[],'foreign'=>[],'sql'=>$s];
 foreach(inst_parts($m[2]) as $d){
  if(preg_match('/^\x60([^\x60]+)\x60\s+(.+)$/s',$d,$x)){$r['columns'][$x[1]]=$x[2];continue;}
  if(preg_match('/^(PRIMARY\s+KEY|UNIQUE\s+(?:KEY|INDEX)|KEY|INDEX)\s*(?:\x60([^\x60]+)\x60)?\s*\(([^)]+)\)/i',$d,$x)){
   $k=strtoupper(preg_replace('/\s+/',' ',trim($x[1])));$name=str_starts_with($k,'PRIMARY')?'PRIMARY':($x[2]??'');
   $r['indexes'][$name]=['kind'=>$k,'columns'=>str_replace(' ','',str_replace(chr(96),'',$x[3]))];continue;
  }
  if(preg_match('/^CONSTRAINT\s+\x60([^\x60]+)\x60\s+FOREIGN\s+KEY\s*\(([^)]+)\)\s+REFERENCES\s+\x60([^\x60]+)\x60\s*\(([^)]+)\)(.*)$/is',$d,$x))
   $r['foreign'][$x[1]]=['columns'=>str_replace(' ','',str_replace(chr(96),'',$x[2])),'ref_table'=>$x[3],'ref_columns'=>str_replace(' ','',str_replace(chr(96),'',$x[4])),'tail'=>trim($x[5])];
 }return $r;
}
function inst_expected(string $p):array{$out=[];$fs=glob(__DIR__.'/sql/*.sql')?:[];sort($fs,SORT_NATURAL);foreach($fs as $f)foreach(inst_split(inst_sql($f,$p)) as $s){$d=inst_create_def($s);if($d)$out[$d['table']]=$d;}return $out;}
function inst_cols(PDO $db,string $t):array{$s=$db->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$t]);$a=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r)$a[$r['COLUMN_NAME']]=$r;return $a;}
function inst_indexes(PDO $db,string $t):array{$s=$db->prepare('SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');$s->execute([$t]);$a=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$n=$r['INDEX_NAME'];$a[$n]??=['unique'=>(int)$r['NON_UNIQUE']===0,'columns'=>[]];$a[$n]['columns'][]=$r['COLUMN_NAME'];}return $a;}
function inst_fks(PDO $db,string $t):array{$s=$db->prepare('SELECT CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION');$s->execute([$t]);$a=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){$n=$r['CONSTRAINT_NAME'];$a[$n]??=['columns'=>[],'ref_table'=>$r['REFERENCED_TABLE_NAME'],'ref_columns'=>[]];$a[$n]['columns'][]=$r['COLUMN_NAME'];$a[$n]['ref_columns'][]=$r['REFERENCED_COLUMN_NAME'];}return $a;}
function inst_plan(PDO $db,string $p):array{
 $r=['create'=>[],'columns'=>[],'indexes'=>[],'foreign'=>[],'mismatches'=>[],'seeds'=>[],'migrations'=>[],'manual'=>[],'inventory'=>[]];$schema=inst_expected($p);
 foreach($schema as $t=>$d){$cols=inst_cols($db,$t);if(!$cols){$r['create'][]=['table'=>$t,'sql'=>$d['sql']];continue;}
  foreach($d['columns'] as $c=>$def){if(!isset($cols[$c])){$required=(bool)preg_match('/\\bNOT\\s+NULL\\b/i',$def);$hasDefault=(bool)preg_match('/\\bDEFAULT\\b/i',$def);if($required&&!$hasDefault&&(int)$db->query('SELECT COUNT(*) FROM '.inst_id($t))->fetchColumn()>0){$r['manual'][]=['name'=>$t.'.'.$c,'reason'=>'Nový povinný sloupec bez výchozí hodnoty v tabulce s daty; je nutné určit hodnotu pro existující řádky.'];}else $r['columns'][]=['table'=>$t,'column'=>$c,'definition'=>$def];}else{$want=strtolower(strtok(trim($def),' '));$have=strtolower($cols[$c]['COLUMN_TYPE']);if($want!==''&&!str_starts_with($have,$want)&&!str_starts_with($want,$have))$r['mismatches'][]=['table'=>$t,'name'=>$c,'actual'=>$have,'expected'=>$want];$expectedNotNull=(bool)preg_match('/\\bNOT\\s+NULL\\b/i',$def);$actualNotNull=$cols[$c]['IS_NULLABLE']==='NO';if($expectedNotNull!==$actualNotNull)$r['mismatches'][]=['table'=>$t,'name'=>$c.' NULL','actual'=>$actualNotNull?'NOT NULL':'NULL','expected'=>$expectedNotNull?'NOT NULL':'NULL'];}}
  $ix=inst_indexes($db,$t);foreach($d['indexes'] as $n=>$v){$want=array_map('trim',explode(',',$v['columns']));if(!isset($ix[$n])){$unique=str_starts_with($v['kind'],'UNIQUE')||$n==='PRIMARY';$columnSql=implode(',',array_map(static fn($z)=>inst_id(trim($z)),explode(',',$v['columns'])));$dupes=$unique?(int)$db->query('SELECT COUNT(*) FROM (SELECT '.$columnSql.' FROM '.inst_id($t).' GROUP BY '.$columnSql.' HAVING COUNT(*)>1) AS dup_rows')->fetchColumn():0;if($dupes>0)$r['manual'][]=['name'=>$t.'.'.$n,'reason'=>'Nelze bezpečně přidat unikátní index: existují duplicitní hodnoty.'];else $r['indexes'][]=['table'=>$t,'name'=>$n,'definition'=>$v];}elseif($ix[$n]['columns']!==$want||$ix[$n]['unique']!==str_starts_with($v['kind'],'UNIQUE')&&$n!=='PRIMARY')$r['mismatches'][]=['table'=>$t,'name'=>'index '.$n,'actual'=>implode(',',$ix[$n]['columns']).($ix[$n]['unique']?' UNIQUE':''),'expected'=>implode(',',$want).(str_starts_with($v['kind'],'UNIQUE')?' UNIQUE':'')];}
  $fk=inst_fks($db,$t);foreach($d['foreign'] as $n=>$v)if(!isset($fk[$n])){$refExists=inst_cols($db,$v['ref_table']);if($refExists){$local=explode(',',$v['columns']);$remote=explode(',',$v['ref_columns']);$join=[];foreach($local as $i=>$col)$join[]='src.'.inst_id(trim($col)).'=dst.'.inst_id(trim($remote[$i]));$first=trim($local[0]);$orphans=(int)$db->query('SELECT COUNT(*) FROM '.inst_id($t).' src LEFT JOIN '.inst_id($v['ref_table']).' dst ON '.implode(' AND ',$join).' WHERE src.'.inst_id($first).' IS NOT NULL AND dst.'.inst_id(trim($remote[0])).' IS NULL')->fetchColumn();if($orphans>0)$r['manual'][]=['name'=>$t.'.'.$n,'reason'=>'Nelze bezpečně přidat cizí klíč: existují osiřelé odkazy.'];else $r['foreign'][]=['table'=>$t,'name'=>$n,'definition'=>$v];}else $r['foreign'][]=['table'=>$t,'name'=>$n,'definition'=>$v];}
 }
 $fs=glob(__DIR__.'/sql/*.sql')?:[];sort($fs,SORT_NATURAL);
 foreach($fs as $file)foreach(inst_split(inst_sql($file,$p)) as $statement){
  if(!inst_safe_base($statement))$r['manual'][]=['name'=>basename($file),'reason'=>'SQL příkaz není v bezpečném automatickém seznamu a nebude spuštěn.'];
  if(preg_match('/^INSERT\\s+IGNORE\\s+INTO\\s+\\x60([^\\x60]+)\\x60/i',$statement,$m)){
   $count=null;
   if(preg_match('/\\bVALUES\\s*(.*)$/is',$statement,$v))$count=count(inst_parts(trim($v[1])));
   elseif(($pos=stripos($statement,'SELECT'))!==false){try{$q=$db->query('SELECT COUNT(*) FROM ('.substr($statement,$pos).') AS preview_rows');$count=(int)$q->fetchColumn();}catch(Throwable $e){}}
   $r['seeds'][]=['file'=>basename($file),'table'=>$m[1],'count'=>$count];
  }
 }
 $mt=$p.'schema_migrations';$exists=(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=".$db->quote($mt))->fetchColumn();
 foreach(glob(__DIR__.'/migrations/*.sql')?:[] as $f){
  if(str_ends_with($f,'.migration.sql'))continue;
  $name=basename($f);
  if($name==='001_auth.sql'){
   $users=$p.'users';$identity=$p.'user_identities';$sessions=$p.'sessions';
   $passExists=isset(inst_cols($db,$users)['pass']);
   $identityExists=(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=".$db->quote($identity))->fetchColumn();
   $sessionsExists=(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=".$db->quote($sessions))->fetchColumn();
   $applied=!$passExists&&$identityExists&&$sessionsExists;
   $r['migrations'][]=['name'=>$name,'status'=>$applied?'applied':'pending','destructive'=>$passExists,'file'=>$f,'legacy_auth'=>true];
   continue;
  }
  $s=inst_sql($f,$p);$destructive=(bool)preg_match('/\\b(DROP\\s+(?:TABLE|COLUMN|INDEX)|TRUNCATE|DELETE\\s+FROM)\\b/i',$s);
  $r['migrations'][]=['name'=>$name,'status'=>'manual','destructive'=>$destructive];
  if($destructive)$r['manual'][]=['name'=>$name,'reason'=>'Obsahuje destruktivní SQL; automaticky se nespustí.'];
 }
 foreach(glob(__DIR__.'/migrations/*.migration.sql')?:[] as $f){
  $n=basename($f);$applied=false;
  if($exists){$q=$db->prepare('SELECT 1 FROM '.inst_id($mt).' WHERE migration=?');$q->execute([$n]);$applied=(bool)$q->fetchColumn();}
  $migrationSql=inst_split(inst_sql($f,$p));$unsafe=(bool)preg_match('/\\b(DROP\\s+(?:TABLE|COLUMN|INDEX)|TRUNCATE|DELETE\\s+FROM|UPDATE\\s+|RENAME\\s+TABLE|MODIFY\\s+COLUMN|CHANGE\\s+COLUMN)\\b/i',inst_sql($f,$p));
  foreach($migrationSql as $statement)if(!inst_safe_migration($statement))$unsafe=true;
  $status=$applied?'applied':($unsafe?'blocked':'pending');
  $r['migrations'][]=['name'=>$n,'status'=>$status,'destructive'=>$unsafe,'file'=>$f];
  if($unsafe)$r['manual'][]=['name'=>$n,'reason'=>'Obsahuje nepodporovaný nebo rizikový SQL příkaz; automaticky se nespustí.'];
 }

 $list=$db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,CHAR_LENGTH(?))=? ORDER BY TABLE_NAME');$list->execute([$p,$p]);foreach($list->fetchAll(PDO::FETCH_COLUMN) as $table){$cols=inst_cols($db,$table);$r['inventory'][]=['table'=>$table,'columns'=>array_keys($cols),'expected'=>isset($schema[$table])?count($schema[$table]['columns']):null];}
 return $r;
}
function inst_apply(PDO $db,string $p,array $plan):void{
 foreach($plan['create'] as $x)$db->exec($x['sql']);
 foreach($plan['columns'] as $x)$db->exec('ALTER TABLE '.inst_id($x['table']).' ADD COLUMN '.inst_id($x['column']).' '.$x['definition']);
 foreach($plan['indexes'] as $x){$v=$x['definition'];$cols=implode(',',array_map('trim',explode(',',$v['columns'])));$u=str_starts_with($v['kind'],'UNIQUE')?'UNIQUE ':'';$db->exec('ALTER TABLE '.inst_id($x['table']).' ADD '.($x['name']==='PRIMARY'?'PRIMARY KEY':$u.'INDEX '.inst_id($x['name'])).' ('.$cols.')');}
 foreach($plan['foreign'] as $x){$v=$x['definition'];$db->exec('ALTER TABLE '.inst_id($x['table']).' ADD CONSTRAINT '.inst_id($x['name']).' FOREIGN KEY ('.$v['columns'].') REFERENCES '.inst_id($v['ref_table']).' ('.$v['ref_columns'].') '.$v['tail']);}
 $fs=glob(__DIR__.'/sql/*.sql')?:[];sort($fs,SORT_NATURAL);foreach($fs as $f)foreach(inst_split(inst_sql($f,$p)) as $s)if(inst_safe_base($s))$db->exec($s);
 $mt=$p.'schema_migrations';$db->exec('CREATE TABLE IF NOT EXISTS '.inst_id($mt).' (migration VARCHAR(190) NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(migration)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
 foreach($plan['migrations'] as $m)if(($m['status']??'')==='pending'&&!empty($m['file'])){
  if(($m['legacy_auth']??false)===true){
   $users=$p.'users';
   if(isset(inst_cols($db,$users)['pass']))$db->exec('ALTER TABLE '.inst_id($users).' DROP COLUMN '.inst_id('pass'));
   foreach(inst_split(inst_sql($m['file'],$p)) as $statement)if(preg_match('/^CREATE\\s+TABLE\\s+IF\\s+NOT\\s+EXISTS\\b/i',ltrim($statement)))$db->exec($statement);
  }else{
   foreach(inst_split(inst_sql($m['file'],$p)) as $s)if(inst_safe_migration($s))$db->exec($s);
  }
  $q=$db->prepare('INSERT INTO '.inst_id($mt).' (migration) VALUES (?) ON DUPLICATE KEY UPDATE applied_at=CURRENT_TIMESTAMP');$q->execute([$m['name']]);
 }
 if(file_put_contents(__DIR__.'/installed.lock',date('c').PHP_EOL,LOCK_EX)===false)throw new RuntimeException(L('inst-lock-write-fail'));
}
try{$pdo=db();$dbAvailable=true;$installed=(bool)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.$pdo->quote($prefix.'meta'))->fetchColumn();$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,CHAR_LENGTH(?))=?');$q->execute([$prefix,$prefix]);$tableCount=(int)$q->fetchColumn();$plan=inst_plan($pdo,$prefix);}catch(Throwable $ex){$errors[]=L('inst-db-unavailable').' '.$ex->getMessage();}
$checks=['PHP '.cfg('install.min_php')=>version_compare(PHP_VERSION,cfg('install.min_php'),'>=')];foreach(cfg('install.extensions') as $x)$checks['ext '.$x]=extension_loaded($x);$checks['gd webp']=function_exists('imagewebp');foreach(cfg('install.writable') as $d){$v=path($d);if(!is_dir($v))@mkdir($v,0755,true);$checks['dir '.cfg("paths.$d")]=is_dir($v)&&is_writable($v);}$checks['DB connection']=$dbAvailable;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){if(!hash_equals($token,(string)($_POST['token']??'')))$errors[]=L('inst-invalid-token');if(!$dbAvailable)$errors[]=L('inst-db-unavailable');if(in_array(false,$checks,true))$errors[]=L('inst-fail');if(!$errors){try{if($installed){$actor=refresh_authenticated_user();if(!$actor||(int)$actor['role']<4){http_response_code(403);exit(e(L('inst-admin-required')));}}$plan=inst_plan($pdo,$prefix);if(($_POST['action']??'')!=='apply')throw new RuntimeException('Invalid action');inst_apply($pdo,$prefix,$plan);$done=true;$installed=true;$plan=inst_plan($pdo,$prefix);}catch(Throwable $ex){$errors[]=L('inst-update-failed').' '.$ex->getMessage();}}}
$ok=!in_array(false,$checks,true);$seedChanges=0;foreach($plan['seeds'] as $seed)if(($seed['count']??0)>0)$seedChanges+=(int)$seed['count'];$changes=count($plan['create'])+count($plan['columns'])+count($plan['indexes'])+count($plan['foreign'])+count(array_filter($plan['migrations'],static fn($m)=>$m['status']==='pending'))+$seedChanges;
?>
<!doctype html><html lang="<?= e(lang_code()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e(L('inst-title')) ?></title>
<style>
:root{color-scheme:light dark;--bg:#10151d;--panel:#19212c;--line:#303b49;--muted:#aab6c5;--ok:#39b978;--bad:#e16b6b;--accent:#5798e8;--warn:#e3b45c}*{box-sizing:border-box}body{font:15px/1.5 system-ui,sans-serif;margin:0;padding:16px;background:var(--bg);color:#f2f5f8}main{width:min(100%,760px);margin:auto}.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px}h1{font-size:1.4rem;margin:0 0 5px}h2{font-size:1.05rem;margin:22px 0 8px}.muted{color:var(--muted)}.lang{text-align:right;margin-bottom:10px}a{color:var(--accent)}.status{padding:12px;border:1px solid var(--line);border-radius:9px;margin:15px 0}.ok{color:var(--ok)}.fail{color:var(--bad)}.warn{color:var(--warn)}ul{list-style:none;padding:0;margin:0}li{padding:7px 0;border-bottom:1px solid var(--line);overflow-wrap:anywhere}.tag{display:inline-block;min-width:82px;color:var(--muted);font-size:.82rem}.error{padding:10px;border:1px solid var(--bad);border-radius:8px;color:var(--bad);overflow-wrap:anywhere}button{width:100%;border:0;border-radius:9px;padding:13px;background:var(--accent);color:white;font:inherit;font-weight:700;margin-top:16px;cursor:pointer}button:disabled{opacity:.4}.note{font-size:.88rem;color:var(--muted)}
</style></head><body><main><nav class="lang"><a href="?lang=cs">CZ</a> · <a href="?lang=en">EN</a></nav><section class="panel">
<h1><?= e(L('inst-title')) ?></h1><p class="muted"><?= e(L('inst-subtitle')) ?></p>
<div class="status"><strong><?= e($installed?L('inst-already-done'):L('inst-not-installed')) ?></strong><br><span class="muted"><?= (int)$tableCount ?> <?= e(L('inst-prefix-tables')) ?> · <?= e(is_file($lock)?L('inst-lock-present'):L('inst-lock-missing')) ?></span></div>
<h2><?= e(L('inst-checks')) ?></h2><ul><?php foreach($checks as $n=>$v): ?><li><?= e($n) ?> <strong class="<?= $v?'ok':'fail' ?>"><?= e($v?L('inst-check-ok'):L('inst-check-fail')) ?></strong></li><?php endforeach; ?></ul>
<h2><?= e(L('inst-plan')) ?></h2>
<details><summary><?= e(L('inst-current-schema')) ?> (<?= count($plan['inventory']) ?>)</summary><ul><?php foreach($plan['inventory'] as $x): ?><li><strong><?= e($x['table']) ?></strong> — <?= count($x['columns']) ?>: <?= e(implode(', ',$x['columns'])) ?><?php if($x['expected']===null): ?> <span class="warn">(<?= e(L('inst-extra-table')) ?>)</span><?php endif; ?></li><?php endforeach; ?></ul></details>
<?php if($plan['create']||$plan['columns']||$plan['indexes']||$plan['foreign']): ?><ul>
<?php foreach($plan['create'] as $x): ?><li><span class="tag"><?= e(L('inst-new-table')) ?></span><?= e($x['table']) ?></li><?php endforeach; ?>
<?php foreach($plan['columns'] as $x): ?><li><span class="tag"><?= e(L('inst-new-column')) ?></span><?= e($x['table'].'.'.$x['column']) ?> <span class="muted"><?= e($x['definition']) ?></span></li><?php endforeach; ?>
<?php foreach($plan['indexes'] as $x): ?><li><span class="tag"><?= e(L('inst-new-index')) ?></span><?= e($x['table'].'.'.$x['name']) ?></li><?php endforeach; ?>
<?php foreach($plan['foreign'] as $x): ?><li><span class="tag"><?= e(L('inst-new-fk')) ?></span><?= e($x['table'].'.'.$x['name']) ?></li><?php endforeach; ?>
</ul><?php else: ?><p class="ok"><?= e(L('inst-no-schema-changes')) ?></p><?php endif; ?>
<h2><?= e(L('inst-data')) ?></h2><ul><?php foreach($plan['seeds'] as $x): ?><li><?= e($x['file']) ?> → <?= e($x['table']) ?>: <?= $x['count']===null?e(L('inst-count-unknown')):((int)$x['count'].' '.e(L('inst-candidate-rows'))) ?></li><?php endforeach; ?></ul>
<h2><?= e(L('inst-migrations')) ?></h2><ul><?php foreach($plan['migrations'] as $x): ?><li><strong><?= e($x['name']) ?></strong> — <span class="<?= $x['status']==='pending'?'warn':($x['status']==='applied'?'ok':'muted') ?>"><?= e(L('inst-migration-'.$x['status'])) ?></span><?php if(!empty($x['destructive'])): ?> <span class="fail"><?= e(L('inst-destructive')) ?></span><?php endif; ?></li><?php endforeach; ?></ul>
<?php if($plan['mismatches']): ?><h2><?= e(L('inst-mismatches')) ?></h2><ul><?php foreach($plan['mismatches'] as $x): ?><li class="warn"><?= e($x['table'].'.'.$x['name']) ?>: <?= e((string)$x['actual']) ?> → <?= e((string)$x['expected']) ?> (<?= e(L('inst-manual-review')) ?>)</li><?php endforeach; ?></ul><?php endif; ?>
<?php if($plan['manual']): ?><h2><?= e(L('inst-manual')) ?></h2><ul><?php foreach($plan['manual'] as $x): ?><li class="fail"><?= e($x['name'].': '.$x['reason']) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php foreach($errors as $x): ?><p class="error"><?= e($x) ?></p><?php endforeach; ?><?php if($done): ?><p class="ok"><?= e(L('inst-update-done')) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="action" value="apply"><button type="submit" <?= (!$dbAvailable||!$ok||$changes===0)?'disabled':'' ?>><?= e(L('inst-confirm-apply')) ?></button></form>
<p class="note"><?= e(L('inst-safe-note')) ?></p><p><a href="<?= e(rtrim(cfg('app.base_url'),'/').'/') ?>"><?= e(L('inst-open')) ?></a></p>
</section></main></body></html>
