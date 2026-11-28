<?php
declare(strict_types=1);
$v=$venue['v'];$place=e((string)$venue['city']).' <abbr title="'.e((string)$venue['country']).'">('.e((string)$venue['iso2']).')</abbr>';
$row=static fn(string $l,string $h):string=>'<dt>'.e($l).'</dt><dd>'.$h.'</dd>';
?>
<article class="card detail detail-venue"><div class="detail-head"><h1><?=e((string)$v['name'])?></h1></div>
<?php if($imgUrl!==''):?><div class="detail-img"><img src="<?=e($imgUrl)?>" alt="<?=e((string)$v['name'])?>"></div><?php endif;?>
<dl class="kv"><?=$row(t('detail.place'),$place)?>
<?php if($v['address']!==''):?><?=$row(t('detail.address'),e((string)$v['address']))?><?php endif;?>
<?php if($v['www']!==''):?><?=$row(t('detail.www'),'<a href="'.e((string)$v['www']).'" target="_blank" rel="noopener">'.e((string)preg_replace('~^https?://~i','',(string)$v['www'])).'</a>')?><?php endif;?>
<?php if($v['email']!==''):?><?=$row(t('detail.email'),'<a href="mailto:'.e((string)$v['email']).'">'.e((string)$v['email']).'</a>')?><?php endif;?>
<?php if($v['phone']!==''):?><?=$row(t('detail.phone'),'<a href="tel:'.e((string)preg_replace('/[^0-9+]/','',(string)$v['phone'])).'">'.e((string)$v['phone']).'</a>')?><?php endif;?></dl>
<?php if($v['google_maps']!==''||$v['mapy_maps']!==''):?><div class="maplinks"><?php if($v['mapy_maps']!==''):?><a class="btn small ghost" target="_blank" rel="noopener" href="<?=e((string)$v['mapy_maps'])?>">Mapy.com</a><?php endif;?><?php if($v['google_maps']!==''):?><a class="btn small ghost" target="_blank" rel="noopener" href="<?=e((string)$v['google_maps'])?>">Google Maps</a><?php endif;?></div><?php endif;?>
<?php if($v['description']!==''):?><p class="detail-text"><?=nl2br(e((string)$v['description']))?></p><?php endif;?></article>