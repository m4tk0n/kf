<?php
// Detail klubu. Stejnou šablonu používá skutečný detail i náhled před odesláním,
// takže náhled vypadá přesně jako výsledek. Očekává proměnnou $venue (viz venue_load()).
$v = $venue;
$mapUrl = '';
if (($v['lat'] ?? null) !== null && ($v['lng'] ?? null) !== null) {
    $lat = rawurlencode((string)$v['lat']);
    $lng = rawurlencode((string)$v['lng']);
    $mapUrl = 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lng . '#map=17/' . $lat . '/' . $lng;
}
$phoneHref = preg_replace('/[^0-9+]/', '', (string)($v['phone'] ?? ''));
?>
<article class="venue-detail">
  <h2 class="venue-title"><?= e($v['name']) ?></h2>
  <p class="venue-place"><?= e($v['city']) ?><?= $v['country_code'] !== '' ? ' (' . e($v['country_code']) . ')' : '' ?></p>
  <?php if (!empty($v['images'])): ?>
    <div class="venue-gallery">
      <?php foreach ($v['images'] as $imageUrl): ?>
        <img src="<?= e($imageUrl) ?>" alt="<?= e($v['name']) ?>" loading="lazy">
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if (trim((string)$v['description']) !== ''): ?>
    <p class="venue-description"><?= nl2br(e($v['description'])) ?></p>
  <?php endif; ?>
  <dl class="venue-info">
    <?php if ($v['address'] !== ''): ?>
      <div><dt><?= e(L('add-address')) ?></dt><dd><?= e($v['address']) ?></dd></div>
    <?php endif; ?>
    <?php if ($v['phone'] !== ''): ?>
      <div><dt><?= e(L('add-phone')) ?></dt><dd><?php if ($phoneHref !== ''): ?><a href="tel:<?= e($phoneHref) ?>"><?= e($v['phone']) ?></a><?php else: ?><?= e($v['phone']) ?><?php endif; ?></dd></div>
    <?php endif; ?>
    <?php if ($mapUrl !== ''): ?>
      <div><dt><?= e(L('venue-map')) ?></dt><dd><a href="<?= e($mapUrl) ?>" target="_blank" rel="noopener"><?= e(L('venue-open-map')) ?></a></dd></div>
    <?php endif; ?>
  </dl>
</article>
