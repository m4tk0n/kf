<?php
// templates/list.php
/** @var array $view */
$s = $view['req']['s'];
$rows = $view['rows'];
$kind = ['events' => 'event', 'bands' => 'band', 'venues' => 'venue'][$s];
?>
<?= kf_pager($view['nav']) ?>
<?php if (!$rows): ?>
  <?php
  $emptyKey = 'empty';
  if (!empty($view['req']['fav']) && $user !== null) {
      $emptyKey = fav_count((int)$user['id'], $kind) === 0 ? 'fav.empty' : 'fav.no_match';
  }
  ?>
  <p class="empty"><?= e(t($emptyKey)) ?></p>
<?php else: ?>
<ul class="list list-<?= e($s) ?>">
<?php foreach ($rows as $r):
    $id = (int)$r['id'];
    $place = e($r['city']) . ' <abbr title="' . e($r['country']) . '">(' . e($r['iso2']) . ')</abbr>';
?>
  <li><a data-list-item="<?= $id ?>" class="item<?= $s === 'events' ? ' item-event item-type-' . (int)$r['type'] : ' item-' . e($kind) ?>" href="<?= e(kf_detail_url($kind, $id)) ?>">
  <?php if ($s === 'events'): ?>
    <span class="date"><?= e(kf_date_range($r['date_from'], $r['date_to'])) ?></span>
    <span class="body">
      <strong class="name"><?= e($r['name']) ?></strong>
      <span class="meta"><?= $r['venue'] !== null ? '<span class="meta-venue">' . e($r['venue']) . '</span> <span class="meta-sep">·</span> ' : '' ?><?= $place ?></span>
      <span class="tags"><span class="tag t<?= (int)$r['type'] ?>"><?= e(t('type.' . (int)$r['type'])) ?></span><?= $r['time_start'] !== null ? ' <span class="time">' . e(substr($r['time_start'], 0, 5)) . '</span>' : '' ?></span>
    </span>
    <?php if ((int)$r['flyer'] === 1): ?><img class="thumb" src="<?= e(kf_img('events', $id, 's')) ?>" alt="" loading="lazy"><?php endif; ?>
  <?php elseif ($s === 'bands'): ?>
    <span class="body">
      <strong class="name"><?= e($r['name']) ?></strong>
      <span class="meta"><?= $place ?><?= $r['genre'] !== null ? ' <span class="meta-sep">·</span> ' . e($r['genre']) : '' ?></span>
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
