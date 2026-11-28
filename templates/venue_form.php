<?php
declare(strict_types=1);
?>
<section class="card narrow"><h1><?= e(t('venue.new.title')) ?></h1>
<?php if($errors): ?><ul class="warn"><?php foreach(array_unique($errors) as $code): ?><li><?= e(t('venue.err.'.$code)) ?></li><?php endforeach; ?></ul><?php endif; ?>
<form method="post" action="<?= e(url('venue/new')) ?>" enctype="multipart/form-data" class="stack"><?= csrf_field() ?>
<label><?= e(t('venue.f.name')) ?><input name="name" maxlength="120" required value="<?= e((string)$old['name']) ?>"></label>
<label><?= e(t('venue.f.city')) ?><select name="city_id" required><option value="0"><?= e(t('event.f.city_pick')) ?></option><?php $last='';foreach($cities as $c):if($last!==(string)$c['country']):if($last!==''):?></optgroup><?php endif;?><optgroup label="<?=e((string)$c['country'])?>"><?php $last=(string)$c['country'];endif;?><option value="<?=(int)$c['id']?>"<?= (int)$old['city_id']===(int)$c['id']?' selected':'' ?>><?=e((string)$c['name'])?> (<?=e((string)$c['iso2'])?>)</option><?php endforeach;if($last!==''):?></optgroup><?php endif;?></select></label>
<?php foreach(['address','email','phone','www','google_maps','mapy_maps'] as $k):?><label><?=e(t('venue.f.'.$k))?><input name="<?=e($k)?>" value="<?=e((string)$old[$k])?>" maxlength="<?= $k==='address'?255:($k==='email'?120:($k==='phone'?30:($k==='www'?300:500))) ?>"<?=in_array($k,['www','google_maps','mapy_maps'],true)?' placeholder="https://"':''?>></label><?php endforeach;?>
<label><?=e(t('venue.f.description'))?><textarea name="description" rows="5" maxlength="5000"><?=e((string)$old['description'])?></textarea></label>
<label><?=e(t('venue.f.image'))?><input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"></label>
<label><?=e(t('event.f.image_url'))?><input name="image_url" maxlength="300" placeholder="https://"></label>
<?php if($imgUrl!==''):?><label class="check"><input type="checkbox" name="remove_image" value="1"> <?=e(t('event.f.image_remove'))?></label><img class="event-preview" alt="" src="<?=e($imgUrl)?>"><?php endif;?>
<button class="btn" type="submit"><?=e(t('event.btn.preview'))?></button></form></section>