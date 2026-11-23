<?php
// templates/login_confirm.php
declare(strict_types=1);
?>
<section class="card narrow">
  <h1><?= e(t('login.confirm.title')) ?></h1>
  <p><?= e(t('login.confirm.text')) ?></p>
  <form method="post" action="<?= e(url('login/confirm')) ?>">
    <?= csrf_field() ?>
    <button class="btn wide" type="submit" name="action" value="confirm"><?= e(t('login.confirm.yes')) ?></button>
    <button class="btn wide" type="submit" name="action" value="cancel"><?= e(t('login.confirm.no')) ?></button>
  </form>
</section>
