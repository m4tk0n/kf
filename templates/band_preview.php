<?php
// templates/band_preview.php
declare(strict_types=1);
?>
<p class="back"><a href="<?= e(url('band/new')) ?>">← <?= e(t('event.btn.edit')) ?></a></p>
<?php require __DIR__ . '/detail.php'; ?>
<section class="card narrow band-list-preview">
  <h2><?= e(t('band.preview.list_title')) ?></h2>
  <?php require __DIR__ . '/band_list_preview.php'; ?>
</section>
<section class="card narrow">
  <p class="muted"><?= e(t($direct ? 'event.note.direct' : 'event.note.pending')) ?></p>
  <div class="inline">
    <a class="btn ghost" href="<?= e(url('band/new')) ?>"><?= e(t('event.btn.edit')) ?></a>
    <form method="post" action="<?= e(url('band/submit')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="btn"><?= e(t('event.btn.send')) ?></button></form>
    <form method="post" action="<?= e(url('band/cancel')) ?>" class="inline"><?= csrf_field() ?><button type="submit" class="btn danger" data-confirm="<?= e(t('band.cancel_confirm')) ?>"><?= e(t('event.btn.cancel')) ?></button></form>
  </div>
</section>
