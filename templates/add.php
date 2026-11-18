<?php
$type = (string)($_GET['type'] ?? '');
$types = [
    'event' => ['title' => L('add-event'), 'heading' => L('add-event-heading'), 'description' => L('add-event-description')],
    'venue' => ['title' => L('add-venue'), 'heading' => L('add-venue-heading'), 'description' => L('add-venue-description')],
    'band' => ['title' => L('add-band'), 'heading' => L('add-band-heading'), 'description' => L('add-band-description')],
];
$selected = $types[$type] ?? null;
$countries = $genres = $cities = $venues = [];
if ($selected) {
    $label = lang_code() === 'en' ? 'en' : 'cs';
    if ($type === 'band') {
        $countries = db()->query("SELECT iso2, {$label} AS name FROM " . tbl('countries') . " ORDER BY name")->fetchAll();
        $genres = db()->query("SELECT id, name FROM " . tbl('genres') . " ORDER BY name")->fetchAll();
    } elseif ($type === 'venue') {
        $cities = db()->query("SELECT c.id, c.{$label} AS city, co.{$label} AS country FROM " . tbl('cities') . " c JOIN " . tbl('countries') . " co ON co.id=c.country_id ORDER BY co.{$label}, c.{$label}")->fetchAll();
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
    <form class="account-form add-form" method="post" action="<?= e(url('add')) ?>?type=<?= e($type) ?>" autocomplete="off">
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
        <label><?= e(L('add-city')) ?>
          <select name="city_id" required><option value="">—</option>
            <?php foreach ($cities as $city): ?><option value="<?= (int)$city['id'] ?>"><?= e($city['city'] . ', ' . $city['country']) ?></option><?php endforeach; ?>
          </select>
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
