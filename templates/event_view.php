<?php
// templates/event_view.php
declare(strict_types=1);
/** @var array $ev @var string $imgUrl  Společné zobrazení akce v podobě detailu (náhled před odesláním, schvalování). */
$v = $ev['v'];
$place = e((string)$ev['city']) . ' <abbr title="' . e((string)$ev['country']) . '">(' . e((string)$ev['iso2']) . ')</abbr>';
$a = (string)$v['date_from'];
$b = (string)$v['date_to'];
$row = static fn(string $label, string $html): string => '<dt>' . e($label) . '</dt><dd>' . $html . '</dd>';
?>
<article class="card detail detail-event">
  <div class="detail-head"><h1><?= e((string)$v['name']) ?></h1></div>
  <?php if ($imgUrl !== ''): ?>
    <div class="detail-img"><img src="<?= e($imgUrl) ?>" alt="<?= e((string)$v['name']) ?>"></div>
  <?php endif; ?>
  <dl class="kv">
    <?php
    echo $row(t('detail.type'), '<span class="tag t' . (int)$v['type'] . '">' . e(t('type.' . (int)$v['type'])) . '</span>');
    echo $row(t('detail.date'), e($a . ($b !== $a ? ' – ' . $b : '')));
    if ($v['time_start'] !== '') {
        echo $row(t('detail.time'), e((string)$v['time_start']));
    }
    echo $row(t('detail.place'), $place);
    if ($ev['venue'] !== '') {
        echo $row(t('detail.venue'), e((string)$ev['venue']));
    }
    if (kf_safe_url((string)$v['www']) !== null) {
        echo $row(t('detail.www'), e((string)preg_replace('~^https?://~i', '', (string)$v['www'])));
    }
    ?>
  </dl>
  <?php if ($v['description'] !== ''): ?>
    <p class="detail-text"><?= nl2br(e((string)$v['description'])) ?></p>
  <?php endif; ?>
  <h2><?= e(t('detail.bands')) ?></h2>
  <?php if (!$ev['bands']): ?>
    <p class="muted"><?= e(t('detail.none')) ?></p>
  <?php else: ?>
    <ul class="evlist">
      <?php foreach ($ev['bands'] as $bd): ?>
        <li><span class="play-row"><span class="play"><?= $bd['time'] !== '' ? e((string)$bd['time']) : '' ?></span><span><?= e((string)$bd['name']) ?></span></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</article>
