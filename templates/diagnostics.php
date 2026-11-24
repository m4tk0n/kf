<?php
// templates/diagnostics.php
declare(strict_types=1);
/** @var array $user @var bool $active */
?>
<section class="card narrow">
  <h1><?= e(t('diagnostics.title')) ?></h1>
  <p><?= e(t('diagnostics.active')) ?>: <?= e(t($active ? 'diagnostics.yes' : 'diagnostics.no')) ?></p>
  <form method="post" action="<?= e(url('diagnostics')) ?>">
    <?= csrf_field() ?>
    <button type="submit" class="btn"><?= e(t($active ? 'diagnostics.off' : 'diagnostics.show')) ?></button>
  </form>
</section>
