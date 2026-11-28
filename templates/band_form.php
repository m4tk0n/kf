<?php
// templates/band_form.php
declare(strict_types=1);
?>
<section class="card narrow">
  <h1><?= e(t('band.new.title')) ?></h1>
  <?php if ($errors): ?><ul class="warn"><?php foreach (array_unique($errors) as $code): ?><li><?= e(t('band.err.' . $code)) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <form method="post" action="<?= e(url('band/new')) ?>" enctype="multipart/form-data" class="stack" data-band-form>
    <?= csrf_field() ?>
    <label><?= e(t('band.f.name')) ?><input type="text" name="name" value="<?= e((string)$old['name']) ?>" maxlength="120" required></label>
    <div class="venue-location-grid">
      <label class="event-name-field"><?= e(t('band.f.city')) ?>
        <input type="hidden" name="city_id" value="<?= (int)$old['city_id'] ?>" data-venue-city-id>
        <input type="text" name="city_name" value="<?= e((string)$old['city_name']) ?>" maxlength="120" minlength="2" required autocomplete="off" data-venue-city-input data-suggest-min="<?= max(1,(int)cfg('paging.autocomplete_min_chars',2)) ?>" data-suggest-url="<?= e(url('venue/city-suggest')) ?>">
        <div class="autocomplete-list" data-venue-city-suggestions role="listbox" hidden></div>
      </label>
      <label class="event-name-field"><?= e(t('band.f.country')) ?>
        <input type="hidden" name="country_id" value="<?= (int)$old['country_id'] ?>" data-venue-country-id>
        <input type="text" name="country_name" value="<?= e((string)$old['country_name']) ?>" maxlength="120" required autocomplete="off" data-venue-country-input data-suggest-min="<?= max(1,(int)cfg('paging.autocomplete_min_chars',2)) ?>" data-suggest-url="<?= e(url('venue/country-suggest')) ?>">
        <div class="autocomplete-list" data-venue-country-suggestions role="listbox" hidden></div>
      </label>
      <label aria-label="ISO2"><span class="venue-iso2" data-venue-country-iso2><?= e((string)$old['country_iso2']) ?></span></label>
    </div>
    <label><?= e(t('band.f.genre')) ?><select name="genre_id" required><option value="0">–</option><?php foreach ($genres as $g): ?><option value="<?= (int)$g['id'] ?>"<?= (int)$old['genre_id']===(int)$g['id']?' selected':'' ?>><?= e((string)$g['name']) ?></option><?php endforeach; ?></select></label>
    <?php foreach (['www','facebook','bandzone'] as $key): ?><label><?= e(t('band.f.'.$key)) ?><input type="url" name="<?= e($key) ?>" value="<?= e((string)$old[$key]) ?>" maxlength="300" placeholder="https://" data-validate-url></label><?php endforeach; ?>
    <label><?= e(t('band.f.description')) ?><textarea name="description" rows="5" maxlength="5000"><?= e((string)$old['description']) ?></textarea></label>
    <fieldset class="image-field">
      <legend><?= e(t('band.f.logo')) ?></legend>
      <label><?= e(t('band.f.image_file')) ?><input type="file" name="logo_image" accept="image/jpeg,image/png,image/webp,image/gif" data-band-image="logo"></label>
      <label><?= e(t('band.f.image_url')) ?><input type="url" name="logo_image_url" maxlength="300" placeholder="https://" data-validate-url data-band-image-url="logo"></label>
      <?php if ($logoUrl !== ''): ?><label class="check"><input type="checkbox" name="remove_logo" value="1"><?= e(t('band.f.remove_image')) ?></label><?php endif; ?>
      <img class="event-preview" data-band-image-preview="logo" alt="" src="<?= e($logoUrl) ?>"<?= $logoUrl===''?' hidden':'' ?>>
    </fieldset>
    <fieldset class="image-field">
      <legend><?= e(t('band.f.detail_image')) ?></legend>
      <label><?= e(t('band.f.image_file')) ?><input type="file" name="detail_image" accept="image/jpeg,image/png,image/webp,image/gif" data-band-image="detail"></label>
      <label><?= e(t('band.f.image_url')) ?><input type="url" name="detail_image_url" maxlength="300" placeholder="https://" data-validate-url data-band-image-url="detail"></label>
      <?php if ($detailUrl !== ''): ?><label class="check"><input type="checkbox" name="remove_detail_image" value="1"><?= e(t('band.f.remove_image')) ?></label><?php endif; ?>
      <img class="event-preview" data-band-image-preview="detail" alt="" src="<?= e($detailUrl) ?>"<?= $detailUrl===''?' hidden':'' ?>>
    </fieldset>
    <button type="submit" class="btn"><?= e(t('event.btn.preview')) ?></button>
  </form>
</section>
<script src="<?= e(asset('js/band-form.js')) ?>" defer></script>
