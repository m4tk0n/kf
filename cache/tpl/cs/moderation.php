<?php
$rows = mod_pending_rows();
$notice = (string)($_SESSION['mod_notice'] ?? '');
$error = (string)($_SESSION['mod_error'] ?? '');
unset($_SESSION['mod_notice'], $_SESSION['mod_error']);
$csrf = (string)($_SESSION['csrf_token'] ?? '');
?>
<section class="account-page moderation-page">
  <div class="account-page-heading">
    <h1><?= e(L('moderation-title')) ?></h1>
    <a class="back-button" href="<?= e(url()) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <?php if ($error !== ''): ?><p class="form-message" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($notice !== ''): ?><p class="form-message" role="status"><?= e($notice) ?></p><?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty-state">
      <strong><?= e(L('mod-empty')) ?></strong>
      <p><?= e(L('moderation-description')) ?></p>
    </div>
  <?php endif; ?>

  <?php foreach ($rows as $row): ?>
    <?php
    $data = $row['data'];
    $author = (string)($row['display_name'] ?: $row['email'] ?: '—');
    ?>
    <article class="mod-item">
      <p class="mod-meta"><span class="account-role"><?= e(L('mod-type-' . $row['kind'])) ?></span> <?= e(L('mod-author')) ?>: <?= e($author) ?></p>

      <?php if ($row['kind'] === 'venue'): ?>
        <?php
        $dup = venue_find_duplicate((int)$row['country_id'], (string)$row['city'], (string)($data['address'] ?? ''), (int)$row['id']);
        $linkUrls = [];
        foreach ((array)($data['images'] ?? []) as $image) {
            if (($image['type'] ?? '') === 'url') $linkUrls[] = (string)$image['value'];
        }
        ?>
        <?php if ($dup !== null): ?>
          <p class="form-message" role="alert"><?= e(L('mod-duplicate', ['name' => $dup['name']])) ?></p>
        <?php endif; ?>
        <?= view('venue-detail', ['venue' => mod_venue_view($row)]) ?>
        <?php if ($linkUrls): ?>
          <p class="add-intro preview-urls"><?= e(L('add-preview-urls')) ?><br>
            <?php foreach ($linkUrls as $link): ?><a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($link) ?></a><br><?php endforeach; ?></p>
        <?php endif; ?>

      <?php elseif ($row['kind'] === 'band'): ?>
        <?php
        $q = db()->prepare('SELECT c.' . (lang_code() === 'en' ? 'en' : 'cs') . ' AS country, g.name AS genre FROM ' . tbl('countries') . ' c CROSS JOIN ' . tbl('genres') . ' g WHERE c.id = ? AND g.id = ?');
        $q->execute([(int)($data['country_id'] ?? 0), (int)($data['genre_id'] ?? 0)]);
        $ref = $q->fetch() ?: ['country' => '—', 'genre' => '—'];
        ?>
        <div class="venue-detail">
          <h2 class="venue-title"><?= e($data['name'] ?? $row['name']) ?></h2>
          <p class="venue-place"><?= e($ref['country']) ?> · <?= e($ref['genre']) ?></p>
        </div>

      <?php else: ?>
        <?php
        $q = db()->prepare('SELECT v.name, c.' . (lang_code() === 'en' ? 'en' : 'cs') . ' AS city FROM ' . tbl('venues') . ' v JOIN ' . tbl('cities') . ' c ON c.id = v.city_id WHERE v.id = ?');
        $q->execute([(int)($data['venue_id'] ?? 0)]);
        $ven = $q->fetch() ?: ['name' => '—', 'city' => '—'];
        $typeKey = cfg('enums.event_type')[(int)($data['event_type'] ?? 3)] ?? 'other';
        ?>
        <div class="venue-detail">
          <h2 class="venue-title"><?= e($data['name'] ?? $row['name']) ?></h2>
          <p class="venue-place"><?= e(L('event-type-' . $typeKey)) ?> · <?= e($ven['name']) ?> · <?= e($ven['city']) ?></p>
          <p class="venue-place"><?= e(date(L('fmt-datetime'), strtotime((string)($data['starts_at'] ?? 'now')))) ?><?= !empty($data['ends_at']) ? ' – ' . e(date(L('fmt-datetime'), strtotime((string)$data['ends_at']))) : '' ?></p>
        </div>
      <?php endif; ?>

      <div class="preview-actions">
        <form method="post" action="<?= e(url('moderation/reject')) ?>" onsubmit="return confirm('<?= e(L('mod-confirm-reject')) ?>')">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <button type="submit" class="danger-button"><?= e(L('mod-reject')) ?></button>
        </form>
        <form method="post" action="<?= e(url('moderation/approve')) ?>">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <button type="submit" class="primary-button"><?= e(L('mod-approve')) ?></button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</section>
