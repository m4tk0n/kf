<?php
$backUrl = url() . '?section=venues';
$hasGps = $venue['lat'] !== null && $venue['lng'] !== null;
?>
<section class="account-page venue-detail-page">
  <div class="account-page-heading">
    <h1><?= e($venue['name']) ?></h1>
    <a class="back-button" href="<?= e($backUrl) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <p class="venue-location-line"><?= e($venue['city']) ?> (<?= e($venue['country_code']) ?>)</p>

  <?php if (!empty($venue['images'])): ?>
    <div class="venue-gallery">
      <?php foreach ($venue['images'] as $image): ?>
        <img src="<?= e($image) ?>" alt="<?= e($venue['name']) ?>" loading="lazy">
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <dl class="venue-facts">
    <?php if ($venue['address'] !== ''): ?>
      <div><dt><?= e(lang_code() === 'cs' ? 'Adresa' : 'Address') ?></dt><dd><?= e($venue['address']) ?></dd></div>
    <?php endif; ?>
    <?php if ($venue['phone'] !== ''): ?>
      <div><dt><?= e(lang_code() === 'cs' ? 'Telefon' : 'Phone') ?></dt><dd><a href="tel:<?= e(preg_replace('/[^+0-9]/', '', $venue['phone'])) ?>"><?= e($venue['phone']) ?></a></dd></div>
    <?php endif; ?>
    <?php if ($hasGps): ?>
      <div><dt>GPS</dt><dd><a href="https://www.openstreetmap.org/?mlat=<?= rawurlencode((string)$venue['lat']) ?>&amp;mlon=<?= rawurlencode((string)$venue['lng']) ?>#map=16/<?= rawurlencode((string)$venue['lat']) ?>/<?= rawurlencode((string)$venue['lng']) ?>" target="_blank" rel="noopener noreferrer"><?= e($venue['lat'] . ', ' . $venue['lng']) ?></a></dd></div>
    <?php endif; ?>
  </dl>

  <?php if ($venue['description'] !== ''): ?>
    <section class="venue-description">
      <h2><?= e(lang_code() === 'cs' ? 'O klubu' : 'About the venue') ?></h2>
      <p><?= nl2br(e($venue['description'])) ?></p>
    </section>
  <?php endif; ?>
</section>
