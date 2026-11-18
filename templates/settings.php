<?php
$user = refresh_authenticated_user();
$linked = [];
if ($user) {
    $stmt = db()->prepare('SELECT provider FROM ' . tbl('user_identities') . ' WHERE user_id = ?');
    $stmt->execute([(int)$user['id']]);
    $linked = array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
}
?>
<section class="account-page">
  <div class="account-page-heading">
    <h1><?= e(L('settings-title')) ?></h1>
    <a class="back-button" href="<?= e(url()) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <section class="account-settings-section">
    <h2><?= e(L('settings-linked-title')) ?></h2>
    <p><?= e(L('settings-link-hint')) ?></p>
    <ul>
      <li>
        <?= e(L('settings-linked-google')) ?>:
        <?php if (isset($linked['google'])): ?>
          <strong><?= e(L('settings-connected')) ?></strong>
        <?php else: ?>
          <a href="<?= e(url('auth/google/link/start')) ?>"><?= e(L('settings-connect')) ?></a>
        <?php endif; ?>
      </li>
      <li>
        <?= e(L('settings-linked-facebook')) ?>:
        <?php if (isset($linked['facebook'])): ?>
          <strong><?= e(L('settings-connected')) ?></strong>
        <?php elseif (facebook_oauth_enabled()): ?>
          <a href="<?= e(url('auth/facebook/link/start')) ?>"><?= e(L('settings-connect')) ?></a>
        <?php else: ?>
          <span><?= e(L('settings-facebook-not-configured')) ?></span>
        <?php endif; ?>
      </li>
    </ul>
  </section>
</section>
