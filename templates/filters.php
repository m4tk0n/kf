<?php
// templates/filters.php
declare(strict_types=1);
/** @var array $filters @var array<string,array> $options @var int $limit */
?>
<section class="card">
  <h1><?= e(t('menu.filters')) ?></h1>
  <p><?= e(t('filters.intro')) ?></p>
  <form method="post" action="<?= e(url('filters')) ?>" class="stack">
    <?= csrf_field() ?>
    <label class="check"><input type="checkbox" name="enabled" value="1"<?= $filters['enabled'] ? ' checked' : '' ?>> <?= e(t('filters.enabled')) ?></label>
    <?php foreach ($options as $type => $rows): ?>
      <details class="fgroup"<?= $filters['sets'][$type] ? ' open' : '' ?>>
        <summary><?= e(t('filters.' . $type)) ?> <span class="muted">(<?= count($filters['sets'][$type]) ?>)</span></summary>
        <?php if (!$rows): ?>
          <p class="muted"><?= e(t('filters.empty')) ?></p>
        <?php else: ?>
          <div class="checks">
            <?php foreach ($rows as $r): ?>
              <label class="check"><input type="checkbox" name="<?= e((string)$type) ?>[]" value="<?= (int)$r['id'] ?>"<?= in_array((int)$r['id'], $filters['sets'][$type], true) ? ' checked' : '' ?>> <?= e((string)$r['name']) ?></label>
            <?php endforeach; ?>
          </div>
          <?php if (count($rows) >= $limit): ?>
            <p class="muted"><?= e(t('filters.more', ['n' => $limit])) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
