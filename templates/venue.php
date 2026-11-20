<section class="account-page venue-page">
  <div class="account-page-heading">
    <h1><?= e(L('section-venues')) ?></h1>
    <a class="back-button" href="<?= e(url('') . '?section=venues') ?>"><?= e(L('back-button')) ?></a>
  </div>
  <?= view('venue-detail', ['venue' => $venue]) ?>
</section>
