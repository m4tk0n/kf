<?php
$type = (string)($_GET['type'] ?? '');
$types = [
    'event' => ['title' => L('add-event'), 'heading' => L('add-event-heading'), 'description' => L('add-event-description')],
    'venue' => ['title' => L('add-venue'), 'heading' => L('add-venue-heading'), 'description' => L('add-venue-description')],
    'band' => ['title' => L('add-band'), 'heading' => L('add-band-heading'), 'description' => L('add-band-description')],
];
$selected = $types[$type] ?? null;
$countries = $genres = $venues = [];
if ($selected) {
    $label = lang_code() === 'en' ? 'en' : 'cs';
    if ($type === 'band') {
        $countries = db()->query("SELECT iso2, {$label} AS name FROM " . tbl('countries') . " ORDER BY name")->fetchAll();
        $genres = db()->query("SELECT id, name FROM " . tbl('genres') . " ORDER BY name")->fetchAll();
    } elseif ($type === 'venue') {
        $countries = db()->query("SELECT iso2, {$label} AS name FROM " . tbl('countries') . " ORDER BY name")->fetchAll();
    } else {
        $venues = db()->query("SELECT v.id, v.name, c.{$label} AS city FROM " . tbl('venues') . " v JOIN " . tbl('cities') . " c ON c.id=v.city_id ORDER BY v.name")->fetchAll();
    }
}
$error = (string)($_SESSION['add_error'] ?? '');
$success = (string)($_SESSION['add_success'] ?? '');
unset($_SESSION['add_error'], $_SESSION['add_success']);
?>
<section class="account-page">
  <div class="account-page-heading">
    <h1><?= e($selected ? $selected['heading'] : L('add-title')) ?></h1>
    <a class="back-button" href="<?= e($selected ? url('add') : url()) ?>"><?= e(L('back-button')) ?></a>
  </div>
  <?php if ($error !== ''): ?><p class="form-message" role="alert"><?= e($error) ?></p><?php endif; ?>
  <?php if ($success !== ''): ?><p class="form-message" role="status"><?= e($success) ?></p><?php endif; ?>
  <?php if (!$selected): ?>
    <p class="add-intro"><?= e(L('add-intro')) ?></p>
    <div class="add-options">
      <?php foreach ($types as $key => $item): ?>
        <a class="add-option" href="<?= e(url('add')) ?>?type=<?= e($key) ?>">
          <strong><?= e($item['title']) ?></strong>
          <span><?= e($item['description']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="add-intro"><?= e($selected['description']) ?></p>
    <form class="account-form add-form" method="post" action="<?= e(url('add')) ?>?type=<?= e($type) ?>" autocomplete="off"<?= $type === 'venue' ? ' enctype="multipart/form-data"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <label><?= e(L('add-name')) ?>
        <input name="name" maxlength="120" required value="<?= e($_POST['name'] ?? '') ?>">
      </label>
      <?php if ($type === 'band'): ?>
        <label><?= e(L('country-label')) ?>
          <select name="country" required><option value="">—</option>
            <?php foreach ($countries as $country): ?><option value="<?= e($country['iso2']) ?>"><?= e($country['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label><?= e(L('add-genre')) ?>
          <select name="genre" required><option value="">—</option>
            <?php foreach ($genres as $genre): ?><option value="<?= (int)$genre['id'] ?>"><?= e($genre['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php elseif ($type === 'venue'): ?>
        <label><?= e(L('country-label')) ?>
          <select name="country" required><option value="">—</option>
            <?php foreach ($countries as $country): ?><option value="<?= e($country['iso2']) ?>"<?= (($_POST['country'] ?? '') === $country['iso2']) ? ' selected' : '' ?>><?= e($country['name']) ?> (<?= e($country['iso2']) ?>)</option><?php endforeach; ?>
          </select>
        </label>
        <label><?= e(L('add-city')) ?>
          <input name="city" maxlength="100" required value="<?= e($_POST['city'] ?? '') ?>">
        </label>
        <label><?= e(L('add-address')) ?>
          <input name="address" maxlength="190" value="<?= e($_POST['address'] ?? '') ?>">
        </label>
        <label><?= e(L('add-phone')) ?>
          <input name="phone" type="tel" maxlength="40" value="<?= e($_POST['phone'] ?? '') ?>">
        </label>
        <label><?= e(L('add-description')) ?>
          <textarea name="description" rows="4" maxlength="3000"><?= e($_POST['description'] ?? '') ?></textarea>
        </label>
        <div class="form-grid-two">
          <label><?= e(L('add-latitude')) ?>
            <input name="latitude" type="number" min="-90" max="90" step="any" inputmode="decimal" value="<?= e($_POST['latitude'] ?? '') ?>">
          </label>
          <label><?= e(L('add-longitude')) ?>
            <input name="longitude" type="number" min="-180" max="180" step="any" inputmode="decimal" value="<?= e($_POST['longitude'] ?? '') ?>">
          </label>
        </div>
        <label><?= e(L('add-image-urls')) ?>
          <textarea name="image_urls" rows="3" placeholder="https://…"><?= e($_POST['image_urls'] ?? '') ?></textarea>
          <small><?= e(L('add-image-urls-help')) ?></small>
        </label>
        <label><?= e(L('add-image-files')) ?>
          <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
          <small><?= e(L('add-image-files-help')) ?></small>
        </label>
      <?php else: ?>
        <label><?= e(L('add-venue')) ?>
          <select name="venue_id" required><option value="">—</option>
            <?php foreach ($venues as $venue): ?><option value="<?= (int)$venue['id'] ?>"><?= e($venue['name'] . ' – ' . $venue['city']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label><?= e(L('event-type-label')) ?>
          <select name="event_type" required>
            <option value="1"><?= e(L('event-type-concert')) ?></option>
            <option value="2"><?= e(L('event-type-festival')) ?></option>
            <option value="3"><?= e(L('event-type-other')) ?></option>
          </select>
        </label>
        <label><?= e(L('add-starts')) ?><input type="datetime-local" name="starts_at" required></label>
        <label><?= e(L('add-ends')) ?><input type="datetime-local" name="ends_at"></label>
      <?php endif; ?>
      <button type="submit" class="primary-button"><?= e(L('add-submit')) ?></button>
    </form>
  <?php endif; ?>
</section>
