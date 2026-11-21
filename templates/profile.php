<?php
// templates/profile.php
declare(strict_types=1);
/** @var array $user @var string $email @var string $provider @var bool $fresh @var bool $can_delete */
?>
<section class="card narrow">
  <h1><?= e(t('menu.profile')) ?></h1>
  <dl class="kv">
    <dt><?= e(t('profile.provider')) ?></dt><dd><?= e(ucfirst($provider)) ?></dd>
    <dt><?= e(t('profile.role')) ?></dt><dd><?= e(t('role.' . (int)$user['role'])) ?></dd>
    <dt><?= e(t('profile.email')) ?></dt><dd><?= $email !== '' ? e($email) : '<span class="muted">' . e(t('profile.email_none')) . '</span>' ?> <span class="muted">(<?= e(t('profile.email_note')) ?>)</span></dd>
  </dl>

  <form method="post" action="<?= e(url('profile')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="name">
    <label><?= e(t('profile.name')) ?>
      <input type="text" name="display_name" value="<?= e((string)$user['display_name']) ?>" maxlength="<?= (int)cfg('users.display_name_max') ?>" required>
    </label>
    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>

  <form method="post" action="<?= e(url('profile')) ?>" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="lang">
    <label><?= e(t('profile.lang')) ?>
      <select name="lang">
        <?php foreach ((array)cfg('app.supported_langs') as $l): ?>
          <option value="<?= e((string)$l) ?>"<?= $l === current_lang() ? ' selected' : '' ?>><?= e(strtoupper((string)$l)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="muted"><?= e(t('profile.lang_note')) ?></p>
    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>

<section class="card narrow danger-zone">
  <h2><?= e(t('profile.delete.title')) ?></h2>
  <?php if (!$can_delete): ?>
    <p class="warn"><?= e(t('profile.delete.blocked')) ?></p>
  <?php else: ?>
    <p><?= e(t('profile.delete.warn')) ?></p>
    <?php if (!$fresh): ?>
      <p class="muted"><?= e(t('profile.delete.fresh')) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('profile/delete')) ?>" class="stack">
      <?= csrf_field() ?>
      <label class="check"><input type="checkbox" name="confirm" value="1" required> <?= e(t('profile.delete.confirm')) ?></label>
      <button type="submit" class="btn danger" data-confirm="<?= e(t('profile.delete.confirm_js')) ?>"><?= e(t('profile.delete.button')) ?></button>
    </form>
  <?php endif; ?>
</section>
