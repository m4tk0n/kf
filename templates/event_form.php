<?php
// templates/event_form.php
declare(strict_types=1);
/** @var array $old @var string[] $errors @var array $cities @var array $venues @var array $bands @var string $imgUrl @var int $maxBands */
$types = array_values((array)cfg('enums.event_type'));
$byCountry = [];
foreach ($cities as $c) {
    $byCountry[(string)$c['country']][] = $c;
}
?>
<section class="card narrow">
  <h1><?= e(t('event.new.title')) ?></h1>
  <?php if ($errors): ?>
    <ul class="warn">
      <?php foreach (array_unique($errors) as $code): ?><li><?= e(t('event.err.' . $code)) ?></li><?php endforeach; ?>
    </ul>
    <?php if ($imgUrl === '' && (string)$old['image_url'] === ''): ?><p class="muted"><?= e(t('event.err.reselect')) ?></p><?php endif; ?>
  <?php endif; ?>
  <form method="post" action="<?= e(url('event/new')) ?>" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <label><?= e(t('event.f.name')) ?>
      <input type="text" name="name" value="<?= e((string)$old['name']) ?>" maxlength="160" required>
    </label>
    <label><?= e(t('event.f.type')) ?>
      <select name="type">
        <?php foreach ($types as $tv): ?>
          <option value="<?= (int)$tv ?>"<?= (int)$old['type'] === (int)$tv ? ' selected' : '' ?>><?= e(t('type.' . (int)$tv)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('event.f.date_from')) ?>
      <input type="date" name="date_from" value="<?= e((string)$old['date_from']) ?>" required>
    </label>
    <label><?= e(t('event.f.date_to')) ?>
      <input type="date" name="date_to" value="<?= e((string)$old['date_to']) ?>">
    </label>
    <label><?= e(t('event.f.time')) ?>
      <input type="time" name="time_start" value="<?= e((string)$old['time_start']) ?>">
    </label>
    <label><?= e(t('event.f.venue')) ?>
      <select name="venue_id">
        <option value="0"><?= e(t('event.f.venue_none')) ?></option>
        <?php foreach ($venues as $vn): ?>
          <option value="<?= (int)$vn['id'] ?>"<?= (int)$old['venue_id'] === (int)$vn['id'] ? ' selected' : '' ?>><?= e((string)$vn['name'] . ' (' . (string)$vn['city'] . ')') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('event.f.city')) ?>
      <select name="city_id">
        <option value="0"><?= e(t('event.f.city_pick')) ?></option>
        <?php foreach ($byCountry as $country => $rows): ?>
          <optgroup label="<?= e($country) ?>">
            <?php foreach ($rows as $c): ?>
              <option value="<?= (int)$c['id'] ?>"<?= (int)$old['city_id'] === (int)$c['id'] ? ' selected' : '' ?>><?= e((string)$c['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </label>

    <fieldset class="bands-box">
      <legend><?= e(t('event.f.bands')) ?></legend>
      <?php for ($i = 0; $i < $maxBands; $i++): $b = $old['bands'][$i] ?? ['id' => 0, 'time' => '']; ?>
        <div class="band-row" data-band-row>
          <select name="band_id[]" aria-label="<?= e(t('event.f.band_pick')) ?>">
            <option value="0"><?= e(t('event.f.band_pick')) ?></option>
            <?php foreach ($bands as $bn): ?>
              <option value="<?= (int)$bn['id'] ?>"<?= (int)$b['id'] === (int)$bn['id'] ? ' selected' : '' ?>><?= e((string)$bn['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="time" name="band_time[]" value="<?= e((string)$b['time']) ?>" aria-label="<?= e(t('event.f.band_time')) ?>">
        </div>
      <?php endfor; ?>
      <button type="button" class="btn small ghost" data-band-more hidden><?= e(t('event.f.band_more')) ?></button>
    </fieldset>

    <label><?= e(t('event.f.www')) ?>
      <input type="text" name="www" value="<?= e((string)$old['www']) ?>" maxlength="300" placeholder="https://">
    </label>
    <label><?= e(t('event.f.desc')) ?>
      <textarea name="description" rows="5" maxlength="5000"><?= e((string)$old['description']) ?></textarea>
    </label>
    <label><?= e(t('event.f.image')) ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-event-image>
    </label>
    <label><?= e(t('event.f.image_url')) ?>
      <input type="text" name="image_url" value="<?= e((string)$old['image_url']) ?>" maxlength="300" placeholder="https://" data-event-image-url>
    </label>
    <?php if ($imgUrl !== ''): ?>
      <label class="check"><input type="checkbox" name="remove_image" value="1"> <?= e(t('event.f.image_remove')) ?></label>
    <?php endif; ?>
    <img class="event-preview" data-event-preview alt="" src="<?= e($imgUrl) ?>"<?= $imgUrl === '' ? ' hidden' : '' ?>>
    <button type="submit" class="btn"><?= e(t('event.btn.preview')) ?></button>
  </form>
</section>
<script src="<?= e(asset('js/event-form.js')) ?>" defer></script>
