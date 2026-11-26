<?php
// templates/review.php
declare(strict_types=1);
?>
<?php if ($mode === 'list'): ?>
<section class="card">
  <h1><?= e(t('review.title')) ?></h1>
  <?php if (!$rows): ?><p class="muted"><?= e(t('review.empty')) ?></p>
  <?php else: ?>
  <div class="tablewrap"><table class="grid">
    <thead><tr><th><?= e(t('review.col.title')) ?></th><th><?= e(t('review.col.type')) ?></th><th><?= e(t('review.col.date')) ?></th><th><?= e(t('review.col.author')) ?></th><th><?= e(t('review.col.created')) ?></th><th><?= e(t('review.col.lock')) ?></th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?><tr>
      <td><?= e((string)$r['title']) ?></td><td><?= e(t('pending.type.' . (int)$r['type'])) ?></td>
      <td><?= $r['date_from'] !== null ? e((string)$r['date_from']) : '–' ?></td>
      <td><?= e((string)$r['display_name']) ?></td><td><?= e(date('Y-m-d H:i',(int)$r['created_at'])) ?></td>
      <td><?= is_array($r['lock'] ?? null) ? e((string)$r['lock']['name']) : '–' ?></td>
      <td><a class="btn small" href="<?= e(url('review?id=' . (int)$r['id'])) ?>"><?= e(t('review.open')) ?></a></td>
    </tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php else: ?>
<p class="back"><a href="<?= e(url('review')) ?>">← <?= e(t('review.title')) ?></a></p>
<?php if ($ev !== null): ?><?php require __DIR__ . '/event_view.php'; ?>
<?php else: ?><section class="card"><h1><?= e((string)$pend['title']) ?></h1><p class="muted"><?= e(t('flash.review_unsupported')) ?></p></section><?php endif; ?>
<section class="card narrow">
  <?php if (!$lockMine): ?>
    <p><?= e(str_replace('{name}', (string)($lock['name'] ?? ''), t('review.locked_by'))) ?></p>
    <p class="muted"><?= e(t('review.view_only')) ?></p>
    <?php if ($canTakeover): ?><form method="post" action="<?= e(url('review')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pend['id'] ?>"><button class="btn" name="action" value="takeover" data-confirm="<?= e(t('review.takeover_confirm')) ?>"><?= e(t('review.btn.takeover')) ?></button></form><?php endif; ?>
  <?php else: ?>
    <p class="muted"><?= e(str_replace('{min}', (string)ceil(max(60,(int)cfg('review.lock_ttl_sec',900))/60), t('review.lock_mine'))) ?></p>
    <div class="inline">
      <?php if ($ev !== null): ?><a class="btn" href="<?= e(url('review/edit?id=' . (int)$pend['id'])) ?>"><?= e(t('review.btn.edit')) ?></a>
      <form method="post" action="<?= e(url('review')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pend['id'] ?>"><button class="btn" name="action" value="approve"><?= e(t('review.approve')) ?></button></form><?php endif; ?>
      <form method="post" action="<?= e(url('review')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pend['id'] ?>"><button class="btn danger" name="action" value="reject" data-confirm="<?= e(t('review.reject_confirm')) ?>"><?= e(t('review.reject')) ?></button></form>
      <form method="post" action="<?= e(url('review')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pend['id'] ?>"><button class="btn ghost" name="action" value="unlock"><?= e(t('review.btn.release')) ?></button></form>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>