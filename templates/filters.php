<?php
// templates/filters.php
declare(strict_types=1);
/** @var array $filters @var array<string,array<string,array>> $options */
$showAll = !empty($filters['show_all']);
$sets = (array)($filters['sets'] ?? []);
$quick_filters = false;
$active_filter_section = 'events';
?>
<section class="card">
  <div class="page-title-row">
    <div class="page-title-main">
      <h1><?= e(t('menu.filters')) ?></h1>
      <button type="button" class="info-button" aria-label="<?= e(t('filters.help.open')) ?>" aria-haspopup="dialog" aria-controls="filters-help-modal" data-filters-help-open>i</button>
    </div>
    <a class="btn page-back" href="<?= e(url('')) ?>"><?= e(t('common.back')) ?></a>
  </div>
  <div class="modal-backdrop" id="filters-help-modal" role="presentation" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="filters-help-title" aria-describedby="filters-help-message">
      <button type="button" class="modal-close" aria-label="<?= e(t('common.close')) ?>" data-filters-help-close>&times;</button>
      <h2 id="filters-help-title"><?= e(t('filters.help.title')) ?></h2>
      <p id="filters-help-message"><?= e(t('filters.intro')) ?></p>
      <p><?= e(t('filters.help.text')) ?></p>
      <button type="button" class="btn" data-filters-help-close><?= e(t('common.close')) ?></button>
    </section>
  </div>
  <form method="post" action="<?= e(url('filters')) ?>" class="stack filter-form" data-filter-form>
    <?= csrf_field() ?>
    <label class="check filter-select-all">
      <input type="hidden" name="show_filtr_all" value="0">
      <input type="checkbox" name="show_filtr_all" value="1" data-filter-all<?= $showAll ? ' checked' : '' ?>>
      <strong><?= e(t('filters.select_all')) ?></strong>
    </label>

    <?php require __DIR__ . '/filter_sections.php'; ?>

    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
