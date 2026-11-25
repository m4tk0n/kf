<?php
// templates/filters.php
declare(strict_types=1);
/** @var array $filters @var array<string,array<string,array>> $options */
?>
<section class="card">
  <div class="page-title-row">
    <div class="page-title-main">
      <h1><?= e(t('menu.filters')) ?></h1>
      <button type="button" class="info-button" aria-label="<?= e(t('filters.help.open')) ?>" aria-haspopup="dialog" aria-controls="filters-help-modal" data-filters-help-open>i</button>
    </div>
    <a class="btn" href="<?= e(url('')) ?>"><?= e(t('common.back')) ?></a>
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
      <input type="checkbox" name="show_filtr_all" value="1" data-filter-all<?= $filters['show_all'] ? ' checked' : '' ?>>
      <strong><?= e(t('filters.select_all')) ?></strong>
    </label>

    <div class="filter-tabs" role="tablist" aria-label="<?= e(t('menu.filters')) ?>">
      <?php foreach (['events', 'bands', 'venues'] as $i => $section): ?>
        <button type="button" class="filter-tab<?= $i === 0 ? ' active' : '' ?>" role="tab" id="filter-tab-<?= e($section) ?>" aria-controls="filter-panel-<?= e($section) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>" data-filter-tab="<?= e($section) ?>"><?= e(t('filters.section.' . $section)) ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach (['events', 'bands', 'venues'] as $i => $section): ?>
      <?php
      $sectionSets = $filters['sets'][$section] ?? [];
      $countries = $options[$section]['countries'] ?? [];
      $cities = $options[$section]['cities'] ?? [];
      $genres = $options[$section]['genres'] ?? [];
      $cityGroups = [];
      foreach ($cities as $row) $cityGroups[(int)$row['country_id']] ??= ['name' => (string)$row['country_name'], 'rows' => []];
      foreach ($cities as $row) $cityGroups[(int)$row['country_id']]['rows'][] = $row;
      ?>
      <section class="filter-panel" id="filter-panel-<?= e($section) ?>" role="tabpanel" aria-labelledby="filter-tab-<?= e($section) ?>" data-filter-panel="<?= e($section) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
        <?php if ($countries): ?>
          <div class="filter-group">
            <h2><?= e(t('filters.countries')) ?></h2>
            <div class="checks filter-checks">
              <?php foreach ($countries as $row): ?>
                <label class="check filter-option"><input type="checkbox" name="filters[<?= e($section) ?>][countries][]" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($filters['show_all'] || in_array((int)$row['id'], $sectionSets['countries'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($cities): ?>
          <div class="filter-group">
            <h2><?= e(t('filters.cities')) ?></h2>
            <?php foreach ($cityGroups as $group): ?>
              <div class="filter-country-group">
                <h3><?= e($group['name']) ?></h3>
                <div class="checks filter-checks">
                  <?php foreach ($group['rows'] as $row): ?>
                    <label class="check filter-option"><input type="checkbox" name="filters[<?= e($section) ?>][cities][]" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($filters['show_all'] || in_array((int)$row['id'], $sectionSets['cities'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($genres): ?>
          <div class="filter-group">
            <h2><?= e(t('filters.genres')) ?></h2>
            <div class="checks filter-checks">
              <?php foreach ($genres as $row): ?>
                <label class="check filter-option"><input type="checkbox" name="filters[<?= e($section) ?>][genres][]" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($filters['show_all'] || in_array((int)$row['id'], $sectionSets['genres'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$countries && !$cities && !$genres): ?><p class="muted"><?= e(t('filters.empty')) ?></p><?php endif; ?>
      </section>
    <?php endforeach; ?>

    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
