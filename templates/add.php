<?php
$type = (string)($_GET['type'] ?? '');
$types = [
    'event' => ['title' => L('add-event'), 'description' => L('add-event-description')],
    'venue' => ['title' => L('add-venue'), 'description' => L('add-venue-description')],
    'band' => ['title' => L('add-band'), 'description' => L('add-band-description')],
];
?>
<section class="account-page">
  <div class="account-page-heading">
    <h1><?= e(L('add-title')) ?></h1>
    <a class="back-button" href="<?= e(url()) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <p class="add-intro"><?= e(L('add-intro')) ?></p>
  <?php if (isset($types[$type])): ?>
    <p class="form-message"><?= e(L('add-form-not-ready')) ?></p>
  <?php endif; ?>
  <div class="add-options">
    <?php foreach ($types as $key => $item): ?>
      <a class="add-option" href="<?= e(url('add')) ?>?type=<?= e($key) ?>">
        <strong><?= e($item['title']) ?></strong>
        <span><?= e($item['description']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
