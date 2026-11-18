<?php
$center = (string)($center ?? '');
$isAdmin = $center === 'admin';
$description = L($isAdmin ? 'admin-description' : 'moderation-description');
?>
<section class="account-page">
  <div class="account-page-heading">
    <h1><?= e($title ?? L($isAdmin ? 'admin-title' : 'moderation-title')) ?></h1>
    <a class="back-button" href="<?= e(url()) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <div class="empty-state">
    <strong><?= e($title ?? '') ?></strong>
    <p><?= e($description) ?></p>
  </div>
</section>
