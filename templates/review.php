<?php
// templates/review.php
declare(strict_types=1);
/** @var string $mode  list|detail; list: @var array $rows; detail: @var array $pend @var ?array $ev @var string $imgUrl */
?>
<?php if ($mode === 'list'): ?>
<section class="card">
  <h1><?= e(t('review.title')) ?></h1>
  <?php if (!$rows): ?>
    <p class="muted"><?= e(t('review.empty')) ?></p>
  <?php else: ?>
    <div class="tablewrap">
      <table class="grid">
        <thead><tr>
          <th><?= e(t('review.col.title')) ?></th><th><?= e(t('review.col.type')) ?></th><th><?= e(t('review.col.date')) ?></th>
          <th><?= e(t('review.col.author')) ?></th><th><?= e(t('review.col.created')) ?></th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e((string)$r['title']) ?></td>
            <td><?= e(t('pending.type.' . (int)$r['type'])) ?></td>
            <td><?= $r['date_from'] !== null ? e((string)$r['date_from']) : '–' ?></td>
            <td><?= e((string)$r['display_name']) ?></td>
            <td><?= e(date('Y-m-d H:i', (int)$r['created_at'])) ?></td>
            <td><a class="btn small" href="<?= e(url('review?id=' . (int)$r['id'])) ?>"><?= e(t('review.open')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php else: ?>
<p class="back"><a href="<?= e(url('review')) ?>">← <?= e(t('review.title')) ?></a></p>
<?php if ($ev !== null): ?>
  <?php require __DIR__ . '/event_view.php'; ?>
<?php else: ?>
  <section class="card"><h1><?= e((string)$pend['title']) ?></h1><p class="muted"><?= e(t('flash.review_unsupported')) ?></p></section>
<?php endif; ?>
<section class="card narrow">
  <div class="inline">
    <?php if ($ev !== null): ?>
      <form method="post" action="<?= e(url('review')) ?>" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$pend['id'] ?>">
        <button type="submit" name="action" value="approve" class="btn"><?= e(t('review.approve')) ?></button>
      </form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('review')) ?>" class="inline">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$pend['id'] ?>">
      <button type="submit" name="action" value="reject" class="btn danger" data-confirm="<?= e(t('review.reject_confirm')) ?>"><?= e(t('review.reject')) ?></button>
    </form>
  </div>
</section>
<?php endif; ?>
