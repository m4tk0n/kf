<?php
lang_load('events');
$rows = is_array($rows ?? null) ? $rows : [];
$counts = is_array($counts ?? null) ? $counts : ['event'=>0,'venue'=>0,'band'=>0,'venue_change'=>0];
$type = (string)($type ?? '');
$selected = is_array($selected ?? null) ? $selected : null;
$kind = $selected ? (string)$selected['kind'] : $type;
$data = $selected && is_array($selected['data'] ?? null) ? $selected['data'] : [];
$csrf = (string)($_SESSION['csrf_token'] ?? '');
$typeLabels = ['event'=>L('mod-type-event'),'venue'=>L('mod-type-venue'),'band'=>L('mod-type-band'),'venue_change'=>L('mod-type-venue-change')];
$label = lang_code() === 'en' ? 'en' : 'cs';
$countries = db()->query("SELECT id, iso2, {$label} AS country_name FROM " . tbl('countries') . " ORDER BY country_name")->fetchAll();
$genres = db()->query("SELECT id, name FROM " . tbl('genres') . " ORDER BY name")->fetchAll();
$venues = db()->query("SELECT v.id, v.name, c.{$label} AS city_name FROM " . tbl('venues') . " v JOIN " . tbl('cities') . " c ON c.id=v.city_id ORDER BY v.name")->fetchAll();
$countryId = $kind === 'venue' ? (int)($selected['country_id'] ?? 0) : (int)($data['country_id'] ?? 0);
$countryCode = '';
foreach ($countries as $country) if ((int)$country['id'] === $countryId) $countryCode = (string)$country['iso2'];
$startValue = !empty($data['starts_at']) ? date('Y-m-d\\TH:i', strtotime((string)$data['starts_at'])) : '';
$endValue = !empty($data['ends_at']) ? date('Y-m-d\\TH:i', strtotime((string)$data['ends_at'])) : '';
$images = [];
foreach ((array)($data['images'] ?? []) as $image) {
    if (!is_array($image) || empty($image['value'])) continue;
    if (($image['type'] ?? '') === 'upload') $images[] = rtrim(cfg('app.base_url'), '/') . '/' . ltrim((string)$image['value'], '/');
    elseif (($image['type'] ?? '') === 'url') $images[] = (string)$image['value'];
}
$backToType = $kind !== '' ? url('moderation') . '?type=' . rawurlencode($kind) : url('moderation');
?>
<section class="account-page moderation-page">
  <div class="account-page-heading">
    <h1><?= e($title ?? L('moderation-title')) ?></h1>
    <a class="back-button" href="<?= e(url()) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <?php if (!$selected && $type === ''): ?><p class="moderation-intro"><?= e(L('moderation-description')) ?></p><?php endif; ?>
  <?php if (($notice ?? '') !== ''): ?><p class="form-message success"><?= e($notice) ?></p><?php endif; ?>
  <?php if (($error ?? '') !== ''): ?><p class="form-message error" role="alert"><?= e($error) ?></p><?php endif; ?>

  <?php if (!$selected && $type === ''): ?>
    <div class="moderation-categories">
      <?php foreach (['event','venue','band','venue_change'] as $key): ?>
        <?php $count = (int)($counts[$key] ?? 0); ?>
        <?php if ($count > 0): ?>
          <a class="moderation-category" href="<?= e(url('moderation')) ?>?type=<?= e($key) ?>">
            <span><?= e($typeLabels[$key]) ?></span>
            <strong>(<?= $count ?>)</strong>
            <span class="moderation-category-arrow" aria-hidden="true">›</span>
          </a>
        <?php else: ?>
          <div class="moderation-category is-disabled" aria-disabled="true">
            <span><?= e($typeLabels[$key]) ?></span>
            <strong>(0)</strong>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php if (array_sum(array_map('intval', $counts)) === 0): ?>
      <div class="moderation-empty-notice" role="status">
        <strong><?= e(L('mod-empty-title')) ?></strong>
        <p><?= e(L('mod-empty-description')) ?></p>
      </div>
    <?php endif; ?>
  <?php elseif (!$selected): ?>
    <div class="account-page-heading moderation-subheading">
      <h2><?= e($typeLabels[$type] ?? L('moderation-title')) ?></h2>
      <a class="back-button" href="<?= e(url('moderation')) ?>"><?= e(L('mod-all-categories')) ?></a>
    </div>
    <?php if (!$rows): ?>
      <div class="empty-state"><strong><?= e(L('mod-empty-title')) ?></strong><p><?= e(L('mod-empty-description')) ?></p></div>
    <?php else: ?>
      <div class="moderation-list">
        <?php foreach ($rows as $row): ?>
          <?php
            $rowData = is_array($row['data'] ?? null) ? $row['data'] : [];
            $name = trim((string)($rowData['name'] ?? $row['name'] ?? '')) ?: '—';
            $submittedBy = trim((string)($row['display_name'] ?? '')) ?: (string)($row['email'] ?? '—');
            $meta = in_array($type, ['venue','venue_change'], true) ? trim((string)($row['city'] ?? '') . ' · ' . (string)($rowData['address'] ?? ''), ' ·') :
                ($type === 'event' ? (string)($rowData['starts_at'] ?? '') : '');
          ?>
          <a class="moderation-list-row" href="<?= e(url('moderation')) ?>?type=<?= e($type) ?>&id=<?= (int)$row['id'] ?>">
            <span class="moderation-list-main"><strong><?= e($name) ?></strong><?php if ($meta !== ''): ?><small><?= e($meta) ?></small><?php endif; ?><small><?= e(L('mod-submitted-by')) ?>: <?= e($submittedBy) ?></small></span>
            <span class="moderation-open" aria-hidden="true">›</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="account-page-heading moderation-subheading">
      <h2><?= e($typeLabels[$kind] ?? L('moderation-title')) ?></h2>
      <a class="back-button" href="<?= e($backToType) ?>"><?= e(L('mod-back-to-list')) ?></a>
    </div>

    <section class="moderation-preview">
      <div class="moderation-preview-label"><?= e(L('mod-public-preview')) ?></div>
      <?php if (in_array($kind, ['venue','venue_change'], true)): ?>
        <article class="public-preview-card">
          <?php if ($images): ?><div class="public-preview-images"><?php foreach (array_slice($images, 0, 4) as $image): ?><img src="<?= e($image) ?>" alt="" loading="lazy"><?php endforeach; ?></div><?php endif; ?>
          <div class="public-preview-content">
            <h2><?= e($selected['name']) ?></h2>
            <p><?= e((string)($selected['city'] ?? '')) ?><?= $countryCode !== '' ? ' (' . e($countryCode) . ')' : '' ?></p>
            <?php if (!empty($data['address'])): ?><p><?= e($data['address']) ?></p><?php endif; ?>
            <?php if (!empty($data['phone'])): ?><p><?= e($data['phone']) ?></p><?php endif; ?>
            <?php if (!empty($data['description'])): ?><div class="public-preview-description"><?= nl2br(e($data['description'])) ?></div><?php endif; ?>
          </div>
        </article>
      <?php elseif ($kind === 'band'): ?>
        <article class="public-preview-card public-preview-compact"><h2><?= e($selected['name']) ?></h2><p><?= e($countryCode) ?><?php foreach ($genres as $genre): if ((int)$genre['id'] === (int)($data['genre_id'] ?? 0)): ?> · <?= e($genre['name']) ?><?php endif; endforeach; ?></p></article>
      <?php else: ?>
        <?php
          $venueLabel = '';
          foreach ($venues as $venue) if ((int)$venue['id'] === (int)($data['venue_id'] ?? 0)) $venueLabel = $venue['name'] . ' · ' . $venue['city_name'];
          $eventTypeKey = cfg('enums.event_type')[(int)($data['event_type'] ?? 0)] ?? 'other';
        ?>
        <article class="public-preview-card public-preview-compact"><h2><?= e($selected['name']) ?></h2><p><?= e(L('event-type-' . $eventTypeKey)) ?> · <?= e($venueLabel) ?></p><?php if ($startValue !== ''): ?><p><?= e(date(L('fmt-datetime'), strtotime((string)$data['starts_at']))) ?></p><?php endif; ?></article>
      <?php endif; ?>
    </section>

    <form class="account-form moderation-edit-form" method="post" action="<?= e(url('moderation')) ?>">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$selected['id'] ?>">
      <input type="hidden" name="kind" value="<?= e($kind) ?>">
      <?php if ($kind === 'venue_change'): $old = (array)($selected['original'] ?? []); ?>
        <section class="venue-change-comparison">
          <h3><?= e(L('venue-change-comparison')) ?></h3>
          <div><strong><?= e(L('venue-change-original')) ?></strong><p><?= e(($old['name']??'').' · '.($old['city']??'')) ?></p><p><?= e($old['address']??'') ?> · <?= e($old['phone']??'') ?></p><p><?= nl2br(e($old['description']??'')) ?></p></div>
          <div><strong><?= e(L('venue-change-proposed')) ?></strong><p><?= e(($data['name']??'').' · '.($data['city']??'')) ?></p><p><?= e($data['address']??'') ?> · <?= e($data['phone']??'') ?></p><p><?= nl2br(e($data['description']??'')) ?></p></div>
        </section>
      <?php endif; ?>
      <label><?= e(L('add-name')) ?><input name="name" maxlength="120" required value="<?= e($selected['name']) ?>"></label>
      <?php if (in_array($kind, ['venue','venue_change'], true)): ?>
        <label><?= e(L('country-label')) ?><select name="country_id" required><?php foreach ($countries as $country): ?><option value="<?= (int)$country['id'] ?>"<?= (int)$country['id'] === $countryId ? ' selected' : '' ?>><?= e($country['country_name']) ?> (<?= e($country['iso2']) ?>)</option><?php endforeach; ?></select></label>
        <label><?= e(L('add-city')) ?><input name="city" maxlength="100" required value="<?= e($selected['city'] ?? '') ?>"></label>
        <label><?= e(L('add-address')) ?><input name="address" maxlength="190" value="<?= e($data['address'] ?? '') ?>"></label>
        <label><?= e(L('add-phone')) ?><input name="phone" maxlength="40" value="<?= e($data['phone'] ?? '') ?>"></label>
        <label><?= e(L('add-description')) ?><textarea name="description" rows="5" maxlength="3000"><?= e($data['description'] ?? '') ?></textarea></label>
        <div class="form-grid-two"><label><?= e(L('add-latitude')) ?><input type="number" name="latitude" step="any" min="-90" max="90" value="<?= e($data['gps']['latitude'] ?? '') ?>"></label><label><?= e(L('add-longitude')) ?><input type="number" name="longitude" step="any" min="-180" max="180" value="<?= e($data['gps']['longitude'] ?? '') ?>"></label></div>
        <?php if ($images): ?><div class="moderation-image-list"><strong><?= e(L('mod-submitted-images')) ?></strong><?php foreach ($images as $image): ?><a href="<?= e($image) ?>" target="_blank" rel="noopener"><?= e(basename(parse_url($image, PHP_URL_PATH) ?: $image)) ?></a><?php endforeach; ?></div><?php endif; ?>
      <?php elseif ($kind === 'band'): ?>
        <label><?= e(L('country-label')) ?><select name="country_id" required><?php foreach ($countries as $country): ?><option value="<?= (int)$country['id'] ?>"<?= (int)$country['id'] === $countryId ? ' selected' : '' ?>><?= e($country['country_name']) ?> (<?= e($country['iso2']) ?>)</option><?php endforeach; ?></select></label>
        <label><?= e(L('add-genre')) ?><select name="genre_id" required><?php foreach ($genres as $genre): ?><option value="<?= (int)$genre['id'] ?>"<?= (int)$genre['id'] === (int)($data['genre_id'] ?? 0) ? ' selected' : '' ?>><?= e($genre['name']) ?></option><?php endforeach; ?></select></label>
      <?php else: ?>
        <label><?= e(L('add-venue')) ?><select name="venue_id" required><?php foreach ($venues as $venue): ?><option value="<?= (int)$venue['id'] ?>"<?= (int)$venue['id'] === (int)($data['venue_id'] ?? 0) ? ' selected' : '' ?>><?= e($venue['name'] . ' – ' . $venue['city_name']) ?></option><?php endforeach; ?></select></label>
        <label><?= e(L('event-type-label')) ?><select name="event_type" required><?php foreach ([1=>'concert',2=>'festival',3=>'other'] as $id=>$eventKey): ?><option value="<?= $id ?>"<?= $id === (int)($data['event_type'] ?? 0) ? ' selected' : '' ?>><?= e(L('event-type-' . $eventKey)) ?></option><?php endforeach; ?></select></label>
        <label><?= e(L('add-starts')) ?><input type="datetime-local" name="starts_at" required value="<?= e($startValue) ?>"></label>
        <label><?= e(L('add-ends')) ?><input type="datetime-local" name="ends_at" value="<?= e($endValue) ?>"></label>
      <?php endif; ?>
      <div class="moderation-edit-actions">
        <button type="submit" name="action" value="save" class="primary-button"><?= e(L('mod-save-changes')) ?></button>
        <button type="submit" name="action" value="approve" class="approve-button"><?= e(L('mod-approve')) ?></button>
        <button type="submit" name="action" value="reject" class="reject-button" formnovalidate onclick="return confirm(<?= e(json_encode(L('mod-confirm-reject'))) ?>)"><?= e(L('mod-reject')) ?></button>
      </div>
    </form>
  <?php endif; ?>
</section>
