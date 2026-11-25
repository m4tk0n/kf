<?php
// templates/filter_page.php
declare(strict_types=1);
/** @var string $filter_mode @var array $filters @var array $base_state @var array $options @var string $section @var string $back_url */
$isQuick = $filter_mode === 'quick';
$state = $isQuick ? (array)$base_state : (array)$filters;
$showAll = !empty($state['show_all']);
$sets = (array)($state['sets'] ?? []);
$quick_filters = $isQuick;
$active_filter_section = in_array($section ?? '', ['events', 'bands', 'venues'], true) ? $section : 'events';
$titleKey = $isQuick ? 'filters.quick_title' : 'menu.filters';
$stateJson = $isQuick ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
?>
<section class="card">
  <div class="page-title-row">
    <div class="page-title-main">
      <h1><?= e(t($titleKey)) ?></h1>
      <button type="button" class="info-button" aria-label="<?= e(t($isQuick ? 'filters.quick_help.open' : 'filters.help.open')) ?>" aria-haspopup="dialog" aria-controls="filters-help-modal" data-filters-help-open>i</button>
    </div>
    <a class="btn page-back" href="<?= e($isQuick ? $back_url : url('')) ?>"><?= e(t('common.back')) ?></a>
  </div>
  <div class="modal-backdrop" id="filters-help-modal" role="presentation" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="filters-help-title" aria-describedby="filters-help-message">
      <button type="button" class="modal-close" aria-label="<?= e(t('common.close')) ?>" data-filters-help-close>&times;</button>
      <h2 id="filters-help-title"><?= e(t($isQuick ? 'filters.quick_help.title' : 'filters.help.title')) ?></h2>
      <p id="filters-help-message"><?= e(t($isQuick ? ($user !== null ? 'filters.quick_help.user' : 'filters.quick_help.guest') : 'filters.intro')) ?></p>
      <?php if (!$isQuick): ?><p><?= e(t('filters.help.text')) ?></p><?php endif; ?>
      <button type="button" class="btn" data-filters-help-close><?= e(t('common.close')) ?></button>
    </section>
  </div>

  <form
    class="stack filter-form"
    data-filter-form
    <?php if ($isQuick): ?>
      data-quick-filter-form
      data-base-state="<?= e($stateJson === false ? '{}' : $stateJson) ?>"
      data-back-url="<?= e($back_url) ?>"
    <?php else: ?>
      method="post"
      action="<?= e(url('filters')) ?>"
    <?php endif; ?>
  >
    <?php if (!$isQuick): ?>
      <?= csrf_field() ?>
    <?php endif; ?>
    <label class="check filter-select-all">
      <?php if (!$isQuick): ?><input type="hidden" name="show_filtr_all" value="0"><?php endif; ?>
      <input type="checkbox" <?php if ($isQuick): ?>data-filter-all<?php else: ?>name="show_filtr_all" value="1" data-filter-all<?php endif; ?><?= $showAll ? ' checked' : '' ?>>
      <strong><?= e(t('filters.select_all')) ?></strong>
    </label>

    <?php require __DIR__ . '/filter_sections.php'; ?>

    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
