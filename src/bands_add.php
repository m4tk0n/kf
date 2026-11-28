<?php
// src/bands_add.php
declare(strict_types=1);

function kf_band_clean(array $r): array {
    $s=static fn($k,$n)=>is_string($r[$k]??null)?mb_substr(trim($r[$k]),0,$n):'';
    return ['name'=>$s('name',120),'city_id'=>is_scalar($r['city_id']??null)?(int)$r['city_id']:0,'city_name'=>$s('city_name',120),
        'country_id'=>is_scalar($r['country_id']??null)?(int)$r['country_id']:0,'genre_id'=>is_scalar($r['genre_id']??null)?(int)$r['genre_id']:0,
        'www'=>$s('www',300),'facebook'=>$s('facebook',300),'bandzone'=>$s('bandzone',300),'description'=>$s('description',5000)];
}
function kf_band_validate(array $v): array {
    $e=[]; if($v['name']==='')$e[]='name';
    if($v['city_id']>0){$c=q('SELECT country_id FROM '.tbl('cities').' WHERE id=?',[$v['city_id']])->fetchColumn();if($c===false||(int)$c!==$v['country_id'])$e[]='city';}
    elseif(!kf_form_valid_city($v['city_name']))$e[]='city';
    if($v['country_id']<1||!q('SELECT 1 FROM '.tbl('countries').' WHERE id=?',[$v['country_id']])->fetchColumn())$e[]='country';
    if($v['genre_id']<1||!q('SELECT 1 FROM '.tbl('genres').' WHERE id=?',[$v['genre_id']])->fetchColumn())$e[]='genre';
    foreach(['www','facebook','bandzone'] as $k)if($v[$k]!==''&&!kf_form_valid_url($v[$k]))$e[]=$k;
    return array_values(array_unique($e));
}
function kf_band_view_data(array $v): array {
    $c=$v['city_id']>0?q('SELECT c.name AS city,co.iso2,co.`'.country_col().'` AS country FROM '.tbl('cities').' c JOIN '.tbl('countries').' co ON co.id=c.country_id WHERE c.id=?',[$v['city_id']])->fetch():false;
    if(!$c){$c=q('SELECT iso2,`'.country_col().'` AS country FROM '.tbl('countries').' WHERE id=?',[$v['country_id']])->fetch()?:['iso2'=>'','country'=>''];$c['city']=$v['city_name'];}
    return ['v'=>$v,'city'=>(string)$c['city'],'iso2'=>(string)$c['iso2'],'country'=>(string)$c['country'],'genre'=>(string)(q('SELECT name FROM '.tbl('genres').' WHERE id=?',[$v['genre_id']])->fetchColumn()?:'')];
}
function kf_band_duplicate(array $v,bool $pending): bool {
    $city=kf_city_existing_id($v['city_id'],$v['city_name'],$v['country_id']);
    if($city>0&&q('SELECT 1 FROM '.tbl('bands').' WHERE name COLLATE utf8mb4_unicode_ci=? AND city_id=? LIMIT 1',[$v['name'],$city])->fetchColumn())return true;
    return $pending&&(bool)q('SELECT 1 FROM '.tbl('pending').' WHERE type=? AND name_ascii=? LIMIT 1',[(int)cfg('enums.pending_type.band'),kf_ascii($v['name'])])->fetchColumn();
}
function kf_band_limit_error(int $uid): ?string {
    $n=(int)q('SELECT COUNT(*) FROM '.tbl('pending').' WHERE created_by=? AND type=?',[$uid,(int)cfg('enums.pending_type.band')])->fetchColumn();
    if($n>=(int)cfg('limits.pending_per_type.band'))return 'limit_pending';
    $today=(int)q('SELECT COUNT(*) FROM '.tbl('pending').' WHERE created_by=? AND created_at>=?',[$uid,strtotime('today')])->fetchColumn();
    return $today>=(int)cfg('limits.submissions_per_day')?'limit_day':null;
}
function kf_band_draft_get(int $uid): ?array {$d=json_read_file(draft_rel($uid));return ($d['kind']??'')==='band'&&is_array($d['values']??null)?$d:null;}
function kf_band_image_path(int $uid,string $name,string $size): ?string {
    if(!in_array($size,['s','l'],true)||!preg_match('/^u'.$uid.'_[0-9a-f]{16}$/',$name))return null;
    $p=abs_path((string)cfg('storage.tmp_dir')).'/'.$name.'_'.$size.'.webp';return is_file($p)?$p:null;
}
function kf_band_image_remove(int $uid,string $name): void {foreach(['s','l'] as $s){$p=kf_band_image_path($uid,$name,$s);if($p!==null)@unlink($p);}}
function kf_band_draft_clear(int $uid): void {
    $d=kf_band_draft_get($uid);if(!$d)return;
    kf_band_image_remove($uid,(string)($d['logo_image']??''));kf_band_image_remove($uid,(string)($d['detail_image']??''));
    @unlink(abs_path(draft_rel($uid)));
}
function kf_band_image_request(string $field,string $url): array {
    $bin='';$f=$_FILES[$field]??null;
    if(is_array($f)&&(int)($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        $e=(int)$f['error'];if($e===UPLOAD_ERR_INI_SIZE||$e===UPLOAD_ERR_FORM_SIZE)return [null,'img_size'];
        if($e!==UPLOAD_ERR_OK||!is_uploaded_file((string)$f['tmp_name']))return [null,'img_type'];$bin=(string)file_get_contents((string)$f['tmp_name']);
    }elseif($url!==''){try{$r=http_request($url,['max_bytes'=>(int)cfg('img.max_bytes')]);if($r['status']!==200)return [null,'img_url'];$bin=$r['body'];}catch(Throwable){return [null,'img_url'];}}
    if($bin==='')return [null,null];$im=kf_img_decode($bin);return $im instanceof GdImage?[$im,null]:[null,$im];
}
function kf_band_publish(array $v,?string $logo,?string $large,int $uid): int {
    $pdo=db();$id=0;$base='';$pdo->beginTransaction();
    try{
        $city=kf_city_resolve_or_create($v['city_id'],$v['city_name'],$v['country_id']);
        q('INSERT INTO '.tbl('bands').' (name,city_id,genre_id,logo,created_by,created_at) VALUES (?,?,?,?,?,?)',[$v['name'],$city,$v['genre_id'],$logo!==null?1:0,$uid,time()]);
        $id=(int)$pdo->lastInsertId();$base=abs_path((string)cfg('img.subdirs.bands')).'/'.intdiv($id,(int)cfg('img.folder_divisor')).'/'.$id;ensure_dir(dirname($base));
        if($logo!==null&&!copy($logo,$base.'_s.webp'))throw new RuntimeException('logo copy failed');
        if($large!==null&&!copy($large,$base.'_l.webp'))throw new RuntimeException('detail image copy failed');
        json_write_file(json_bucket_rel('bands',$id),['www'=>kf_safe_url($v['www'])??'','facebook'=>kf_safe_url($v['facebook'])??'','bandzone'=>kf_safe_url($v['bandzone'])??'','description'=>$v['description'],'image_l'=>$large!==null,'logo_s'=>$logo!==null],true);
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($id>0)json_remove('bands',$id);if($base!=='')kf_img_remove($base);throw $e;}
}
function kf_band_pending_create(array $v,?string $logo,?string $large,int $uid): int {
    $pdo=db();$id=0;$lp=$dp='';$pdo->beginTransaction();
    try{
        q('INSERT INTO '.tbl('pending').' (type,created_by,title,name_ascii,date_from,source_url,created_at) VALUES (?,?,?, ?,NULL,NULL,?)',[(int)cfg('enums.pending_type.band'),$uid,$v['name'],kf_ascii($v['name']),time()]);
        $id=(int)$pdo->lastInsertId();$rel=json_bucket_rel('pending',$id);json_write_file($rel,['kind'=>'band','values'=>$v,'logo'=>$logo!==null,'image'=>$large!==null],true);$dir=abs_path(dirname($rel));
        if($logo!==null){$lp=$dir.'/'.$id.'_s.webp';if(!copy($logo,$lp))throw new RuntimeException('pending logo copy failed');}
        if($large!==null){$dp=$dir.'/'.$id.'_l.webp';if(!copy($large,$dp))throw new RuntimeException('pending image copy failed');}
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($id>0)json_remove('pending',$id);if($lp!=='')@unlink($lp);if($dp!=='')@unlink($dp);throw $e;}
}
function kf_band_form_page(array $v,array $errors=[],string $logo='',string $large='',int $status=200): never {
    $c=$v['city_id']>0?q('SELECT c.name AS city,c.country_id,co.iso2,co.`'.country_col().'` AS country FROM '.tbl('cities').' c JOIN '.tbl('countries').' co ON co.id=c.country_id WHERE c.id=?',[$v['city_id']])->fetch():false;
    if($c){$v['city_name']=(string)$c['city'];$v['country_id']=(int)$c['country_id'];}
    $co=$v['country_id']>0?q('SELECT `'.country_col().'` AS country,iso2 FROM '.tbl('countries').' WHERE id=?',[$v['country_id']])->fetch():false;
    $v['country_name']=(string)($co['country']??'');$v['country_iso2']=(string)($co['iso2']??'');
    $genres=q('SELECT id,name FROM '.tbl('genres').' ORDER BY name COLLATE utf8mb4_unicode_ci')->fetchAll();
    page(t('band.new.title'),'band_form',['old'=>$v,'errors'=>$errors,'genres'=>$genres,
        'logoUrl'=>$logo!==''?url('band/draft-image?which=logo').'&v='.time():'',
        'detailUrl'=>$large!==''?url('band/draft-image?which=detail').'&v='.time():''],$status);
}
function route_band_new(): never {
    $u=guard('submit');$d=kf_band_draft_get((int)$u['id']);$v=$d?kf_band_clean($d['values']):kf_band_clean([]);
    $logo=(string)($d['logo_image']??'');$large=(string)($d['detail_image']??'');
    if(kf_band_image_path((int)$u['id'],$logo,'s')===null)$logo='';if(kf_band_image_path((int)$u['id'],$large,'l')===null)$large='';
    kf_band_form_page($v,[],$logo,$large);
}
function route_band_new_post(): never {
    $u=guard('submit');$uid=(int)$u['id'];$v=kf_band_clean($_POST);$errors=kf_band_validate($v);$d=kf_band_draft_get($uid)??[];
    $logo=(string)($d['logo_image']??'');$large=(string)($d['detail_image']??'');
    if(kf_band_image_path($uid,$logo,'s')===null)$logo='';if(kf_band_image_path($uid,$large,'l')===null)$large='';
    $newLogo=$newLarge=null;
    if(!$errors){[$newLogo,$e]=kf_band_image_request('logo_image',post_str('logo_image_url',300));if($e!==null)$errors[]='logo_'.$e;
        [$newLarge,$e]=kf_band_image_request('detail_image',post_str('detail_image_url',300));if($e!==null)$errors[]='detail_'.$e;}
    if($errors){if($newLogo instanceof GdImage)imagedestroy($newLogo);if($newLarge instanceof GdImage)imagedestroy($newLarge);kf_band_form_page($v,$errors,$logo,$large,422);}
    try{
        $tmp=abs_path((string)cfg('storage.tmp_dir'));
        if($newLogo instanceof GdImage){kf_band_image_remove($uid,$logo);$logo='u'.$uid.'_'.random_hex(8);kf_img_write($newLogo,$tmp.'/'.$logo);imagedestroy($newLogo);}
        elseif(post_str('remove_logo',1)==='1'){kf_band_image_remove($uid,$logo);$logo='';}
        if($newLarge instanceof GdImage){kf_band_image_remove($uid,$large);$large='u'.$uid.'_'.random_hex(8);kf_img_write($newLarge,$tmp.'/'.$large);imagedestroy($newLarge);}
        elseif(post_str('remove_detail_image',1)==='1'){kf_band_image_remove($uid,$large);$large='';}
        json_write_file(draft_rel($uid),['kind'=>'band','values'=>$v,'logo_image'=>$logo,'detail_image'=>$large,'ts'=>time()]);
    }catch(Throwable $e){error_log('band draft: '.$e->getMessage());kf_band_form_page($v,['save'],$logo,$large,500);}
    redirect('band/preview');
}
function route_band_preview(): never {
    $u=guard('submit');$uid=(int)$u['id'];$draft=kf_band_draft_get($uid);if(!$draft)redirect('band/new');
    $v=kf_band_clean($draft['values']);$data=kf_band_view_data($v);
    $logo=kf_band_image_path($uid,(string)($draft['logo_image']??''),'s');$large=kf_band_image_path($uid,(string)($draft['detail_image']??''),'l');
    $d=['kind'=>'band','id'=>0,'row'=>['name'=>$v['name'],'city'=>$data['city'],'country'=>$data['country'],'iso2'=>$data['iso2'],'genre'=>$data['genre']],
        'extra'=>['desc'=>$v['description'],'www'=>kf_safe_url($v['www']),'email'=>'','phone'=>'','facebook'=>kf_safe_url($v['facebook']),'bandzone'=>kf_safe_url($v['bandzone']),'large'=>$large!==null],
        'fav'=>false,'back'=>url('band/new'),'upcoming'=>[],'past'=>[],'preview'=>true,'preview_img'=>$large!==null?url('band/draft-image?which=detail').'&v='.(int)($draft['ts']??0):''];
    page(t('band.preview.title'),'band_preview',['d'=>$d,'user'=>$u,'bandData'=>$data,
        'logoUrl'=>$logo!==null?url('band/draft-image?which=logo').'&v='.(int)($draft['ts']??0):'','direct'=>can('publish_direct')]);
}
function route_band_draft_image(): never {
    $u=guard('submit');$d=kf_band_draft_get((int)$u['id']);$which=(string)($_GET['which']??'');
    $size=$which==='logo'?'s':($which==='detail'?'l':'');$key=$which==='logo'?'logo_image':'detail_image';
    $p=$d&&$size!==''?kf_band_image_path((int)$u['id'],(string)($d[$key]??''),$size):null;if($p===null)abort(404,t('error.404'));kf_serve_webp($p);
}
function route_band_submit(): never {
    $u=guard('submit');$uid=(int)$u['id'];$direct=can('publish_direct');$d=kf_band_draft_get($uid);if(!$d)redirect('band/new');$v=kf_band_clean($d['values']);
    if(kf_band_validate($v))redirect('band/new?m=invalid');
    if(!$direct&&($lim=kf_band_limit_error($uid))!==null)redirect('band/preview?m=band_'.$lim);
    $city=kf_city_existing_id($v['city_id'],$v['city_name'],$v['country_id']);if($city>0)$v['city_id']=$city;
    if(kf_band_duplicate($v,!$direct))redirect('band/preview?m=band_duplicate');
    $logo=kf_band_image_path($uid,(string)($d['logo_image']??''),'s');$large=kf_band_image_path($uid,(string)($d['detail_image']??''),'l');
    try{if($direct)$id=kf_band_publish($v,$logo,$large,$uid);else kf_band_pending_create($v,$logo,$large,$uid);}
    catch(Throwable $e){error_log('band submit: '.$e->getMessage());redirect('band/preview?m=save_failed');}
    kf_band_draft_clear($uid);redirect($direct?'?band='.$id.'&m=band_saved':'?m=band_submitted');
}
function route_band_cancel(): never { $u=guard('submit');kf_band_draft_clear((int)$u['id']);redirect('band/new?m=band_cancelled');}
