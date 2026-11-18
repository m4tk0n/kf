<?php
$rows = is_array($rows ?? null) ? $rows : [];
$csrf = (string)($_SESSION['csrf_token'] ?? '');
$typeLabels = [
    'event' => L('mod-type-event'),
    'venue' => L('mod-type-venue'),
    'band' => L('mod-type-band'),
];
?>
<section class="account-page moderation-page">
  <div class="account-page-heading">
    <h1><?= e($title ?? L('moderation-title')) ?></h1>
    <a class="back-button" href="<?= e(url()) ?>"><?= e(L('back-button')) ?></a>
  </div>

  <?php if (($notice ?? '') !== ''): ?>
    <p class="form-message success"><?= e($notice) ?></p>
  <?php endif; ?>
  <?php if (($error ?? '') !== ''): ?>
    <p class="form-message error"><?= e($error) ?></p>
  <?php endif; ?>

  <p class="moderation-count"><?= e(L('mod-pending-count', ['n' => count($rows)])) ?></p>

  <?php if (!$rows): ?>
    <div class="empty-state">
      <strong><?= e(L('mod-empty-title')) ?></strong>
      <p><?= e(L('mod-empty-description')) ?></p>
    </div>
  <?php else: ?>
    <div class="moderation-list">
      <?php foreach ($rows as $row): ?>
        <?php
          $kind = (string)($row['kind'] ?? 'unknown');
          $data = is_array($row['data'] ?? null) ? $row['data'] : [];
          $name = trim((string)($data['name'] ?? $row['name'] ?? ''));
          if ($name === '') $name = '—';
          $submittedBy = trim((string)($row['display_name'] ?? ''));
          if ($submittedBy === '') $submittedBy = (string)($row['email'] ?? '—');
        ?>
        <article class="moderation-item">
          <div class="moderation-item-head">
            <h2><?= e($name) ?></h2>
            <span class="moderation-type"><?= e($typeLabels[$kind] ?? $kind) ?></span>
          </div>
          <div class="moderation-meta">
            <span><?= e(L('mod-submitted-by')) ?>: <?= e($submittedBy) ?></span>
            <?php if ($kind === 'venue'): ?>
              <?php if (!empty($row['city'])): ?><span><?= e(L('mod-location')) ?>: <?= e($row['city']) ?></span><?php endif; ?>
              <?php if (!empty($data['address'])): ?><span><?= e(L('add-address')) ?>: <?= e($data['address']) ?></span><?php endif; ?>
              <?php if (!empty($data['phone'])): ?><span><?= e(L('add-phone')) ?>: <?= e($data['phone']) ?></span><?php endif; ?>
              <?php if (!empty($data['description'])): ?><span><?= e(L('add-description')) ?>: <?= e($data['description']) ?></span><?php endif; ?>
              <?php
                $uploadedImages = array_values(array_filter((array)($data['images'] ?? []), static fn($image): bool => is_array($image) && ($image['type'] ?? '') === 'upload'));
              ?>
              <?php if ($uploadedImages): ?>
                <span><?= e(L('add-image-files')) ?>: <?= count($uploadedImages) ?></span>
              <?php endif; ?>
            <?php elseif ($kind === 'event'): ?>
              <?php if (!empty($data['starts_at'])): ?><span><?= e(L('mod-starts')) ?>: <?= e((string)$data['starts_at']) ?></span><?php endif; ?>
              <?php if (!empty($data['venue_id'])): ?><span><?= e(L('add-venue')) ?> ID: <?= (int)$data['venue_id'] ?></span><?php endif; ?>
            <?php elseif ($kind === 'band'): ?>
              <?php if (!empty($data['country_id'])): ?><span><?= e(L('country-label')) ?> ID: <?= (int)$data['country_id'] ?></span><?php endif; ?>
              <?php if (!empty($data['genre_id'])): ?><span><?= e(L('add-genre')) ?> ID: <?= (int)$data['genre_id'] ?></span><?php endif; ?>
            <?php endif; ?>
          </div>
          <div class="moderation-actions">
            <form method="post" action="<?= e(url('moderation')) ?>" onsubmit="return confirm(<?= e(json_encode(L('mod-confirm-approve'))) ?>)">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="action" value="approve">
              <button type="submit" class="approve-button"><?= e(L('mod-approve')) ?></button>
            </form>
            <form method="post" action="<?= e(url('moderation')) ?>" onsubmit="return confirm(<?= e(json_encode(L('mod-confirm-reject'))) ?>)">
              <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <button type="submit" class="reject-button"><?= e(L('mod-reject')) ?></button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
