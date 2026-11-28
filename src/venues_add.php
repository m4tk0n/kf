<?php
// src/venues_add.php
declare(strict_types=1);

function kf_venue_clean(array $r): array {
    $s=static fn($k,$n)=>is_string($r[$k]??null)?mb_substr(trim($r[$k]),0,$n):'';
    return ['name'=>$s('name',120),'city_id'=>is_scalar($r['city_id']??null)?(int)$r['city_id']:0,
    'address'=>$s('address',255),'email'=>$s('email',120),'phone'=>$s('phone',30),'www'=>$s('www',300),
    'google_maps'=>$s('google_maps',500),'mapy_maps'=>$s('mapy_maps',500),'description'=>$s('description',5000)];
}
function kf_venue_validate(array $v): array {
    $e=[]; if($v['name']==='')$e[]='name';
    if($v['city_id']<1||!q('SELECT 1 FROM '.tbl('cities').' WHERE id=?',[$v['city_id']])->fetchColumn())$e[]='city';
    if($v['email']!==''&&!filter_var($v['email'],FILTER_VALIDATE_EMAIL))$e[]='email';
    foreach(['www','google_maps','mapy_maps'] as $k)if($v[$k]!==''&&kf_safe_url($v[$k])===null)$e[]=$k;
    return array_values(array_unique($e));
}
function kf_venue_input(): array {$v=kf_venue_clean($_POST);$v['image_url']=post_str('image_url',300);return [$v,kf_venue_validate($v)];}
function kf_venue_view_data(array $v): array {
    $c=q('SELECT c.name AS city,co.iso2,co.`'.country_col().'` AS country FROM '.tbl('cities').' c JOIN '.tbl('countries').' co ON co.id=c.country_id WHERE c.id=?',[$v['city_id']])->fetch()?:['city'=>'','iso2'=>'','country'=>''];
    return ['v'=>$v,'city'=>$c['city'],'iso2'=>$c['iso2'],'country'=>$c['country']];
}
function kf_venue_duplicate(array $v,bool $pending): bool {
    if(q('SELECT 1 FROM '.tbl('venues').' WHERE name COLLATE utf8mb4_unicode_ci=? AND city_id=? LIMIT 1',[$v['name'],$v['city_id']])->fetchColumn())return true;
    return $pending&&(bool)q('SELECT 1 FROM '.tbl('pending').' WHERE type=? AND name_ascii=? LIMIT 1',[(int)cfg('enums.pending_type.venue'),kf_ascii($v['name'])])->fetchColumn();
}
function kf_venue_limit_error(int $uid): ?string {
    $n=(int)q('SELECT COUNT(*) FROM '.tbl('pending').' WHERE created_by=? AND type=?',[$uid,(int)cfg('enums.pending_type.venue')])->fetchColumn();
    if($n>=(int)cfg('limits.pending_per_type.venue'))return 'limit_pending';
    $today=(int)q('SELECT COUNT(*) FROM '.tbl('pending').' WHERE created_by=? AND created_at>=?',[$uid,strtotime('today')])->fetchColumn();
    return $today>=(int)cfg('limits.submissions_per_day)?'limit_day':null;
}
function kf_venue_publish(array $v,?string $srcL,int $uid): int {
    $pdo=db();$id=0;$base='';$pdo->beginTransaction();
    try {
        q('INSERT INTO '.tbl('venues').' (name,city_id,address,img,created_by,created_at) VALUES (?,?,?,?,?,?)',[$v['name'],$v['city_id'],$v['address']?:null,$srcL!==null?1:0,$uid,time()]);
        $id=(int)$pdo->lastInsertId();
        json_write_file(json_bucket_rel('venues',$id),['description'=>$v['description'],'address'=>$v['address'],'www'=>kf_safe_url($v['www'])??'','email'=>$v['email'],'phone'=>$v['phone'],'google_maps'=>kf_safe_url($v['google_maps'])??'','mapy_maps'=>kf_safe_url($v['mapy_maps'])??'','img_l'=>$srcL!==null],true);
        if($srcL!==null){$base=abs_path((string)cfg('img.subdirs.venues')).'/'.intdiv($id,(int)cfg('img.folder_divisor')).'/'.$id;kf_img_install($srcL,$base);}
        $pdo->commit();return $id;
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($id>0)json_remove('venues',$id);if($base!=='')kf_img_remove($base);throw $e;}
}
function kf_venue_pending_create(array $v,?string $srcL,int $uid): int {
    $pdo=db();$id=0;$img='';$pdo->beginTransaction();
    try {
        q('INSERT INTO '.tbl('pending').' (type,created_by,title,name_ascii,date_from,source_url,created_at) VALUES (?,?,?,?,NULL,NULL,?)',[(int)cfg('enums.pending_type.venue'),$uid,$v['name'],kf_ascii($v['name']),time()]);
        $id=(int)$pdo->lastInsertId();$rel=json_bucket_rel('pending',$id);
        json_write_file($rel,['kind'=>'venue','values'=>$v,'img'=>$srcL!==null],true);
        if($srcL!==null){$img=abs_path(dirname($rel)).'/'.$id.'_l.webp';if(!copy($srcL,$img))throw new RuntimeException('pending image copy failed');}
        $pdo->commit();return $id;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($id>0)json_remove('pending',$id);if($img!=='')@unlink($img);throw $e;}
}
function kf_venue_form_page(array $v,array $errors=[],string $img='',int $status=200): never {
    $cities=q('SELECT c.id,c.name,co.iso2,co.`'.country_col().'` AS country FROM '.tbl('cities').' c JOIN '.tbl('countries').' co ON co.id=c.country_id ORDER BY country,c.name')->fetchAll();
    page(t('venue.new.title'),'venue_form',['old'=>$v,'errors'=>$errors,'cities'=>$cities,'imgUrl'=>$img!==''?url('venue/draft-image').'?v='.time():''],$status);
}
function kf_venue_draft_get(int $uid): ?array {$d=json_read_file(draft_rel($uid));return ($d['kind']??'')==='venue'&&is_array($d['values']??null)?$d:null;}
function route_venue_new(): never {
    $u=guard('submit');$d=kf_venue_draft_get((int)$u['id']);$v=$d?kf_venue_clean($d['values']):kf_venue_clean([]);
    $img=(string)($d['image']??'');kf_venue_form_page($v,[],$img!==''&&kf_draft_image_path((int)$u['id'],$img)!==null?$img:'');
}
function route_venue_new_post(): never {
    $u=guard('submit');$uid=(int)$u['id'];[$v,$errors]=kf_venue_input();$d=kf_venue_draft_get($uid);$image=(string)($d['image']??'');
    if(kf_draft_image_path($uid,$image)===null)$image='';$new=null;
    if(!$errors){[$new,$err]=kf_event_image_request($v['image_url']);if($err!==null)$errors[]=$err;}
    if($errors){$v['image_url']='';kf_venue_form_page($v,$errors,$image,422);}
    try {
        if($new!==null){kf_draft_image_remove($uid,$image);$image='u'.$uid.'_'.random_hex(8);kf_img_write($new,abs_path((string)cfg('storage.tmp_dir')).'/'.$image,['l']);}
        elseif($image!==''&&post_str('remove_image',1)==='1'){kf_draft_image_remove($uid,$image);$image='';}
        $v['image_url']='';json_write_file(draft_rel($uid),['kind'=>'venue','values'=>$v,'image'=>$image,'ts'=>time()]);
    }catch(Throwable $e){error_log('venue draft: '.$e->getMessage());kf_venue_form_page($v,['save'],'',500);}
    redirect('venue/preview');
}
function route_venue_preview(): never {
    $u=guard('submit');$d=kf_venue_draft_get((int)$u['id']);if($d===null)redirect('venue/new');$v=kf_venue_clean($d['values']);$img=(string)($d['image']??'');
    page(t('venue.preview.title'),'venue_preview',['venue'=>kf_venue_view_data($v),'imgUrl'=>kf_draft_image_path((int)$u['id'],$img)!==null?url('venue/draft-image').'?v='.(int)($d['ts']??0):'','direct'=>can('publish_direct')]);
}
function route_venue_draft_image(): never {
    $u=guard('submit');$d=kf_venue_draft_get((int)$u['id']);$p=$d?kf_draft_image_path((int)$u['id'],(string)($d['image']??'')):null;if($p===null)abort(404,t('error.404'));kf_serve_webp($p);
}
function route_venue_submit(): never {
    $u=guard('submit');$uid=(int)$u['id'];$direct=can('publish_direct');$d=kf_venue_draft_get($uid);if($d===null)redirect('venue/new');$v=kf_venue_clean($d['values']);
    if(kf_venue_validate($v))redirect('venue/new?m=invalid');
    if(!$direct&&($lim=kf_venue_limit_error($uid))!==null)redirect('venue/preview?m=venue_'.$lim);
    if(kf_venue_duplicate($v,!$direct))redirect('venue/preview?m=venue_duplicate');
    $src=kf_draft_image_path($uid,(string)($d['image']??''));try{if($direct)$id=kf_venue_publish($v,$src,$uid);else kf_venue_pending_create($v,$src,$uid);}
    catch(Throwable $e){error_log('venue submit: '.$e->getMessage());redirect('venue/preview?m=save_failed');}
    kf_draft_clear($uid);redirect($direct?'?venue='.$id.'&m=venue_saved':'?m=venue_submitted');
}
function route_venue_cancel(): never { $u=guard('submit');kf_draft_clear((int)$u['id']);redirect('venue/new?m=venue_cancelled'); }
