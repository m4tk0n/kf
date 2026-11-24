<?php
// templates/home.php
declare(strict_types=1);
/** @var ?array $user */
?>
<section class="card hero">
  <h1><?= e((string)cfg('app.name')) ?></h1>
  <p class="lead"><?= e(t('app.tagline')) ?></p>
  <?php if ($user === null): ?>
    <p><a class="btn" href="<?= e(url('login')) ?>"><?= e(t('nav.login')) ?></a></p>
  <?php else: ?>
    <p><?= e(t('home.welcome', ['name' => (string)$user['display_name']])) ?></p>
  <?php endif; ?>
  <p class="muted"><?= e(t('home.soon')) ?></p>
</section>
