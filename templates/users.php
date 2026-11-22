<?php
// templates/users.php
declare(strict_types=1);
/** @var array $user @var array $rows @var int $page_no @var int $pages @var int $total */
$roles = [(int)cfg('enums.role.user'), (int)cfg('enums.role.trusted'), (int)cfg('enums.role.moderator'), (int)cfg('enums.role.admin')];
$states = [(int)cfg('enums.user_state.active'), (int)cfg('enums.user_state.suspended')];
?>
<section class="card">
  <h1><?= e(t('menu.users')) ?> <span class="muted">(<?= (int)$total ?>)</span></h1>
  <div class="tablewrap">
  <table class="grid">
    <thead><tr>
      <th>ID</th><th><?= e(t('users.col.name')) ?></th><th><?= e(t('profile.provider')) ?></th><th><?= e(t('profile.role')) ?></th>
      <th><?= e(t('users.col.state')) ?></th><th><?= e(t('users.col.approved')) ?></th><th><?= e(t('users.col.last_login')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <?php $editable = can('change_role', $r); $isSys = (int)$r['id'] === (int)cfg('users.ai_user_id'); ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e((string)$r['display_name']) ?><?= $isSys ? ' <span class="muted">(' . e(t('users.system')) . ')</span>' : '' ?><?= (int)$r['id'] === (int)$user['id'] ? ' <span class="muted">(' . e(t('users.self')) . ')</span>' : '' ?></td>
        <td><?= e(ucfirst((string)(oauth_provider_name((int)$r['provider']) ?? 'system'))) ?></td>
        <?php if ($editable): ?>
          <td colspan="2">
            <form method="post" action="<?= e(url('admin/users')) ?>" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="page" value="<?= (int)$page_no ?>">
              <select name="role" aria-label="<?= e(t('profile.role')) ?>">
                <?php foreach ($roles as $v): ?><option value="<?= $v ?>"<?= $v === (int)$r['role'] ? ' selected' : '' ?>><?= e(t('role.' . $v)) ?></option><?php endforeach; ?>
              </select>
              <select name="state" aria-label="<?= e(t('users.col.state')) ?>">
                <?php foreach ($states as $v): ?><option value="<?= $v ?>"<?= $v === (int)$r['user_state'] ? ' selected' : '' ?>><?= e(t('state.' . $v)) ?></option><?php endforeach; ?>
              </select>
              <button type="submit" name="action" value="save" class="btn small"><?= e(t('common.save')) ?></button>
              <button type="submit" name="action" value="logout" class="btn small ghost"><?= e(t('users.logout')) ?></button>
              <button type="submit" name="action" value="delete" class="btn small danger" data-confirm="<?= e(t('users.confirm_delete')) ?>"><?= e(t('users.delete')) ?></button>
            </form>
          </td>
        <?php else: ?>
          <td><?= e(t('role.' . (int)$r['role'])) ?></td>
          <td><?= e(t('state.' . (int)$r['user_state'])) ?></td>
        <?php endif; ?>
        <td><?= (int)$r['approved_count'] ?></td>
        <td><?= $r['last_login_at'] ? e(date('Y-m-d H:i', (int)$r['last_login_at'])) : '–' ?></td>
        <td></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="<?= e(t('common.page', ['n' => $page_no, 'm' => $pages])) ?>">
      <?php if ($page_no > 1): ?><a class="btn small ghost" href="<?= e(url('admin/users?page=' . ($page_no - 1))) ?>"><?= e(t('common.prev')) ?></a><?php endif; ?>
      <span><?= e(t('common.page', ['n' => $page_no, 'm' => $pages])) ?></span>
      <?php if ($page_no < $pages): ?><a class="btn small ghost" href="<?= e(url('admin/users?page=' . ($page_no + 1))) ?>"><?= e(t('common.next')) ?></a><?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
