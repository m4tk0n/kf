<?php
// templates/login.php
declare(strict_types=1);
/** @var array<string,array> $providers @var string $next */
?>
<section class="card narrow">
  <h1><?= e(t('login.title')) ?></h1>
  <p><?= e(t('login.intro')) ?></p>
  <?php if (!$providers): ?>
    <p class="warn"><?= e(t('login.none')) ?></p>
  <?php else: ?>
    <?php foreach ($providers as $name => $c): ?>
      <p><a class="btn wide" href="<?= e(oauth_login_url((string)$name, $next)) ?>"><?= e(t('login.with', ['provider' => (string)$c['label']])) ?></a></p>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
