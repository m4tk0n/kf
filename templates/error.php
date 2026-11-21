<?php
// templates/error.php
declare(strict_types=1);
/** @var int $code @var string $message @var ?array $user */
?>
<section class="card narrow">
  <h1><?= e((string)$code) ?></h1>
  <p class="lead"><?= e($message) ?></p>
  <?php if ($code === 403 && $user === null): ?>
    <p><?= e(t('error.login_hint')) ?></p>
    <p><a class="btn" href="<?= e(oauth_available() ? url('login?next=' . rawurlencode(req_path())) : url('login')) ?>"><?= e(t('nav.login')) ?></a></p>
  <?php endif; ?>
  <p><a href="<?= e(url('')) ?>"><?= e(t('error.back')) ?></a></p>
</section>
