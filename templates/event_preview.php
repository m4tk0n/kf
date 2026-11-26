<?php
// templates/event_preview.php
declare(strict_types=1);
/** @var array $ev @var string $imgUrl @var bool $direct */
?>
<p class="back"><a href="<?= e(url('event/new')) ?>">← <?= e(t('event.btn.edit')) ?></a></p>
<?php require __DIR__ . '/event_view.php'; ?>
<section class="card narrow">
  <p class="muted"><?= e(t($direct ? 'event.note.direct' : 'event.note.pending')) ?></p>
  <div class="inline">
    <a class="btn ghost" href="<?= e(url('event/new')) ?>"><?= e(t('event.btn.edit')) ?></a>
    <form method="post" action="<?= e(url('event/submit')) ?>" class="inline">
      <?= csrf_field() ?>
      <button type="submit" class="btn"><?= e(t('event.btn.send')) ?></button>
    </form>
    <form method="post" action="<?= e(url('event/cancel')) ?>" class="inline">
      <?= csrf_field() ?>
      <button type="submit" class="btn danger" data-confirm="<?= e(t('event.cancel_confirm')) ?>"><?= e(t('event.btn.cancel')) ?></button>
    </form>
  </div>
</section>
