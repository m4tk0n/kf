<?php $csrf=(string)($_SESSION['csrf_token']??''); ?>
<section class="account-page venue-edit-page">
  <div class="account-page-heading"><h1><?= e($title) ?></h1><a class="back-button" href="<?= e(url('venue/'.(int)$venue['id'])) ?>"><?= e(L('back-button')) ?></a></div>
  <?php if (($error??'')!==''): ?><p class="form-message error" role="alert"><?= e($error) ?></p><?php endif; ?>
  <p><?= e(lang_code()==='cs'?'Změny se projeví až po schválení moderátorem.':'Changes will appear after moderator approval.') ?></p>
  <form class="account-form" method="post" action="<?= e(url('venue-edit/'.(int)$venue['id'])) ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <label><?= e(L('add-name')) ?><input name="name" maxlength="120" required value="<?= e($venue['name']) ?>"></label>
    <label><?= e(L('country-label')) ?><select name="country_id" required><?php foreach($countries as $country): ?><option value="<?= (int)$country['id'] ?>"<?= (int)$country['id']===$country_id?' selected':'' ?>><?= e($country['label']) ?> (<?= e($country['iso2']) ?>)</option><?php endforeach; ?></select></label>
    <label><?= e(L('add-city')) ?><input name="city" maxlength="100" required value="<?= e($venue['city']) ?>"></label>
    <label><?= e(L('add-address')) ?><input name="address" maxlength="190" value="<?= e($venue['address']) ?>"></label>
    <label><?= e(L('add-phone')) ?><input name="phone" maxlength="40" value="<?= e($venue['phone']) ?>"></label>
    <label><?= e(L('add-description')) ?><textarea name="description" rows="5" maxlength="3000"><?= e($description) ?></textarea></label>
    <div class="form-grid-two"><label><?= e(L('add-latitude')) ?><input type="number" name="latitude" step="any" min="-90" max="90" value="<?= e($venue['lat']??'') ?>"></label><label><?= e(L('add-longitude')) ?><input type="number" name="longitude" step="any" min="-180" max="180" value="<?= e($venue['lng']??'') ?>"></label></div>
    <button class="primary-button" type="submit"><?= e(L('venue-edit-submit')) ?></button>
  </form>
</section>
