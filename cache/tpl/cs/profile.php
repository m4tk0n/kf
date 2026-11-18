<?php
$user = $_SESSION['user'] ?? [];
$record = db()->prepare('SELECT email, display_name, deletion_scheduled_at FROM ' . tbl('users') . ' WHERE id = ? LIMIT 1');
$record->execute([(int)($user['id'] ?? 0)]);
$profile = $record->fetch() ?: [];
$csrf = (string)($_SESSION['csrf_token'] ?? '');
?>
<section class="account-page">
  <div class="account-page-heading"><h1><?= e(L('profile-title')) ?></h1><a class="back-button" href="<?= e(url()) ?>"><?= e(L('back-button')) ?></a></div>
  <?php if (!empty($_SESSION['profile_error'])): ?><p class="form-message error"><?= e($_SESSION['profile_error']) ?></p><?php unset($_SESSION['profile_error']); endif; ?>
  <?php if (!empty($_SESSION['profile_notice'])): ?><p class="form-message success"><?= e($_SESSION['profile_notice']) ?></p><?php unset($_SESSION['profile_notice']); endif; ?>
  <form method="post" action="<?= e(url('profile/save')) ?>" class="account-form">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <label><?= e(L('profile-display-name')) ?><input type="text" name="display_name" maxlength="80" required value="<?= e($profile['display_name'] ?: ($user['name'] ?? '')) ?>"></label>
    <label><?= e(L('profile-email')) ?><input type="email" value="<?= e($profile['email'] ?? '') ?>" readonly></label>
    <button type="submit" class="primary-button"><?= e(L('profile-save')) ?></button>
  </form>
  <section class="delete-account">
    <h2><?= e(L('delete-account-title')) ?></h2>
    <?php if (!empty($profile['deletion_scheduled_at'])): ?>
      <p class="delete-warning"><?= e(L('delete-account-pending', ['date' => date(lang_code() === 'cs' ? 'j. n. Y H:i' : 'M j, Y H:i', strtotime($profile['deletion_scheduled_at']))])) ?></p>
      <form method="post" action="<?= e(url('account/delete-cancel')) ?>">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="primary-button"><?= e(L('delete-account-cancel')) ?></button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= e(url('account/delete-request')) ?>" onsubmit="return confirm('<?= e(L('delete-account-confirm')) ?>')">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <button type="submit" class="danger-button"><?= e(L('delete-account-request')) ?></button>
      </form>
    <?php endif; ?>
  </section>
</section>