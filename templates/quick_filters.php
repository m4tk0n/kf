<?php
// templates/quick_filters.php
declare(strict_types=1);
/** @var string $section @var array $options @var array $base_state @var string $back_url */
$showAll = !empty($base_state['show_all']);
$sets = (array)($base_state['sets'] ?? []);
$quick_filters = true;
$active_filter_section = $section;
$quickUser = auth_resolve();
$stateJson = json_encode($base_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<section class="card">
  <div class="page-title-row">
    <div class="page-title-main">
      <h1><?= e(t('filters.quick_title')) ?></h1>
      <button type="button" class="info-button" aria-label="<?= e(t('filters.quick_help.open')) ?>" aria-haspopup="dialog" aria-controls="filters-help-modal" data-filters-help-open>i</button>
    </div>
    <a class="btn page-back" href="<?= e($back_url) ?>"><?= e(t('common.back')) ?></a>
  </div>
  <div class="modal-backdrop" id="filters-help-modal" role="presentation" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="quick-filters-help-title" aria-describedby="quick-filters-help-message">
      <button type="button" class="modal-close" aria-label="<?= e(t('common.close')) ?>" data-filters-help-close>&times;</button>
      <h2 id="quick-filters-help-title"><?= e(t('filters.quick_help.title')) ?></h2>
      <p id="quick-filters-help-message"><?= e(t($quickUser !== null ? 'filters.quick_help.user' : 'filters.quick_help.guest')) ?></p>
      <button type="button" class="btn" data-filters-help-close><?= e(t('common.close')) ?></button>
    </section>
  </div>
  <form class="stack filter-form" data-quick-filter-form data-filter-form
        data-base-state="<?= e($stateJson === false ? '{}' : $stateJson) ?>"
        data-back-url="<?= e($back_url) ?>">
    <label class="check filter-select-all">
      <input type="checkbox" data-filter-all<?= $showAll ? ' checked' : '' ?>>
      <strong><?= e(t('filters.select_all')) ?></strong>
    </label>

    <?php require __DIR__ . '/filter_sections.php'; ?>

    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
