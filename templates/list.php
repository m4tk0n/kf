<?php
// templates/list.php
/** @var array $view */
$s = $view['req']['s'];
$rows = $view['rows'];
$kind = ['events' => 'event', 'bands' => 'band', 'venues' => 'venue'][$s];
?>
<?= kf_pager($view['nav']) ?>
<?php if (!$rows): ?>
  <p class="empty"><?= e(kf_t('empty')) ?></p>
<?php else: ?>
<ul class="list">
<?php foreach ($rows as $r):
    $id = (int)$r['id'];
    $place = e($r['city']) . ' <abbr title="' . e($r['country']) . '">(' . e($r['iso2']) . ')</abbr>';
?>
  <li><a class="item" href="<?= e(kf_detail_url($kind, $id)) ?>">
  <?php if ($s === 'events'): ?>
    <span class="date"><?= e(kf_date_range($r['date_from'], $r['date_to'])) ?></span>
    <span class="body">
      <strong class="name"><?= e($r['name']) ?></strong>
      <span class="meta"><?= $r['venue'] !== null ? e($r['venue']) . ' · ' : '' ?><?= $place ?></span>
      <span class="tags"><span class="tag t<?= (int)$r['type'] ?>"><?= e(kf_t('type.' . (int)$r['type'])) ?></span><?= $r['time_start'] !== null ? ' <span class="time">' . e(substr($r['time_start'], 0, 5)) . '</span>' : '' ?></span>
    </span>
    <?php if ((int)$r['flyer'] === 1): ?><img class="thumb" src="<?= e(kf_img('events', $id, 's')) ?>" alt="" loading="lazy"><?php endif; ?>
  <?php elseif ($s === 'bands'): ?>
    <span class="body">
      <strong class="name"><?= e($r['name']) ?></strong>
      <span class="meta"><?= $place ?><?= $r['genre'] !== null ? ' · ' . e($r['genre']) : '' ?></span>
    </span>
    <?php if ((int)$r['logo'] === 1): ?><img class="thumb" src="<?= e(kf_img('bands', $id, 's')) ?>" alt="" loading="lazy"><?php endif; ?>
  <?php else: ?>
    <span class="body">
      <strong class="name"><?= e($r['name']) ?></strong>
      <span class="meta"><?= $place ?></span>
    </span>
    <?php if ((int)$r['img'] === 1): ?><img class="thumb" src="<?= e(kf_img('venues', $id, 's')) ?>" alt="" loading="lazy"><?php endif; ?>
  <?php endif; ?>
  </a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?= kf_pager($view['nav']) ?>
