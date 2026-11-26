<?php
// templates/my.php
declare(strict_types=1);
/** @var array $rows */
?>
<section class="card">
  <h1><?= e(t('my.title')) ?></h1>
  <?php if (!$rows): ?>
    <p class="muted"><?= e(t('my.empty')) ?></p>
  <?php else: ?>
    <div class="tablewrap">
      <table class="grid">
        <thead><tr>
          <th><?= e(t('review.col.title')) ?></th><th><?= e(t('review.col.type')) ?></th><th><?= e(t('review.col.date')) ?></th>
          <th><?= e(t('review.col.created')) ?></th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e((string)$r['title']) ?></td>
            <td><?= e(t('pending.type.' . (int)$r['type'])) ?></td>
            <td><?= $r['date_from'] !== null ? e((string)$r['date_from']) : '–' ?></td>
            <td><?= e(date('Y-m-d H:i', (int)$r['created_at'])) ?></td>
            <td>
              <form method="post" action="<?= e(url('my/delete')) ?>" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn small danger" data-confirm="<?= e(t('my.delete_confirm')) ?>"><?= e(t('my.delete')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
