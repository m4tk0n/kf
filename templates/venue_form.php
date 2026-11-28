<?php
// templates/venue_form.php
declare(strict_types=1);
?>
<section class="card narrow">
  <h1><?= e(t('venue.new.title')) ?></h1>

  <?php if ($errors): ?>
    <ul class="warn">
      <?php foreach (array_unique($errors) as $code): ?>
        <li><?= e(t('venue.err.' . $code)) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" action="<?= e(url('venue/new')) ?>" enctype="multipart/form-data" class="stack" data-venue-form>
    <?= csrf_field() ?>

    <label><?= e(t('venue.f.name')) ?>
      <input type="text" name="name" value="<?= e((string)$old['name']) ?>" maxlength="120" required>
    </label>

    <div class="venue-location-grid">
      <label class="event-name-field"><?= e(t('venue.f.city')) ?>
        <input type="hidden" name="city_id" value="<?= (int)$old['city_id'] ?>" data-venue-city-id>
        <input type="text" name="city_name" value="<?= e((string)($old['city_name'] ?? '')) ?>" maxlength="120"
          autocomplete="off" placeholder="<?= e(t('event.f.city_pick')) ?>"
          data-venue-city-input data-suggest-min="<?= max(1, (int)cfg('paging.autocomplete_min_chars', 2)) ?>"
          data-suggest-url="<?= e(url('venue/city-suggest')) ?>">
        <div class="autocomplete-list" data-venue-city-suggestions role="listbox" hidden></div>
      </label>

      <label class="event-name-field"><?= e(t('venue.f.country')) ?>
        <input type="hidden" name="country_id" value="<?= (int)$old['country_id'] ?>" data-venue-country-id>
        <input type="text" name="country_name" value="<?= e((string)($old['country_name'] ?? '')) ?>" maxlength="120" required
          autocomplete="off" placeholder="<?= e(t('venue.f.country')) ?>"
          data-venue-country-input data-suggest-min="<?= max(1, (int)cfg('paging.autocomplete_min_chars', 2)) ?>"
          data-suggest-url="<?= e(url('venue/country-suggest')) ?>">
        <div class="autocomplete-list" data-venue-country-suggestions role="listbox" hidden></div>
      </label>

      <label aria-label="ISO2">
        <span class="venue-iso2" data-venue-country-iso2><?= e((string)($old['country_iso2'] ?? '')) ?></span>
      </label>    </div>

    <?php foreach (['address', 'email', 'phone', 'www', 'google_maps', 'mapy_maps'] as $k): ?>
      <label><?= e(t('venue.f.' . $k)) ?>
        <input type="text" name="<?= e($k) ?>" value="<?= e((string)$old[$k]) ?>"
          maxlength="<?= $k === 'address' ? 255 : ($k === 'email' ? 120 : ($k === 'phone' ? 30 : ($k === 'www' ? 300 : 500))) ?>"
          <?= in_array($k, ['www', 'google_maps', 'mapy_maps'], true) ? 'placeholder="https://"' : '' ?>>
      </label>
    <?php endforeach; ?>

    <label><?= e(t('venue.f.description')) ?>
      <textarea name="description" rows="5" maxlength="5000"><?= e((string)$old['description']) ?></textarea>
    </label>

    <label><?= e(t('venue.f.image')) ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-event-image>
    </label>

    <label><?= e(t('event.f.image_url')) ?>
      <input type="text" name="image_url" maxlength="300" placeholder="https://" data-event-image-url>
    </label>

    <?php if ($imgUrl !== ''): ?>
      <label class="check">
        <input type="checkbox" name="remove_image" value="1"> <?= e(t('event.f.image_remove')) ?>
      </label>
    <?php endif; ?>
    <img class="event-preview" data-event-preview alt="" src="<?= e($imgUrl) ?>"<?= $imgUrl === '' ? ' hidden' : '' ?>>

    <button type="submit" class="btn"><?= e(t('event.btn.preview')) ?></button>
  </form>
</section>
<script src="<?= e(asset('js/venue-form.js')) ?>" defer></script>
