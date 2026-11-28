<?php
// templates/band_list_preview.php
declare(strict_types=1);
$v = $bandData['v'];
?>
<ul class="list list-bands">
  <li><div class="item item-band">
    <span class="body">
      <strong class="name"><?= e((string)$v['name']) ?></strong>
      <span class="meta"><?= e($bandData['city']) ?> <abbr title="<?= e($bandData['country']) ?>">(<?= e($bandData['iso2']) ?>)</abbr><?= $bandData['genre'] !== '' ? ' <span class="meta-sep">·</span> ' . e($bandData['genre']) : '' ?></span>
    </span>
    <?php if ($logoUrl !== ''): ?><img class="thumb" src="<?= e($logoUrl) ?>" alt="" loading="lazy"><?php endif; ?>
  </div></li>
</ul>
