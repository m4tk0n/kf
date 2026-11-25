<?php
// templates/quick_filters.php
declare(strict_types=1);
/** @var string $section @var array $options @var array $base_state @var string $back_url */
$showAll = !empty($base_state['show_all']);
$quickUser = auth_resolve();
$stateJson = json_encode($base_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$sections = ['events', 'bands', 'venues'];
?>
<section class="card">
  <div class="page-title-row">
    <div class="page-title-main">
      <h1><?= e(t('filters.quick_title')) ?></h1>
      <button type="button" class="info-button" aria-label="<?= e(t('filters.quick_help.open')) ?>" aria-haspopup="dialog" aria-controls="quick-filters-help-modal" data-filters-help-open>i</button>
    </div>
    <a class="btn page-back" href="<?= e($back_url) ?>"><?= e(t('common.back')) ?></a>
  </div>
  <div class="modal-backdrop" id="quick-filters-help-modal" role="presentation" hidden>
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="quick-filters-help-title" aria-describedby="quick-filters-help-message">
      <button type="button" class="modal-close" aria-label="<?= e(t('common.close')) ?>" data-filters-help-close>&times;</button>
      <h2 id="quick-filters-help-title"><?= e(t('filters.quick_help.title')) ?></h2>
      <p id="quick-filters-help-message"><?= e(t($quickUser !== null ? 'filters.quick_help.user' : 'filters.quick_help.guest')) ?></p>
      <button type="button" class="btn" data-filters-help-close><?= e(t('common.close')) ?></button>
    </section>
  </div>
  <form class="stack filter-form" data-quick-filter-form
        data-section="<?= e($section) ?>"
        data-base-state="<?= e($stateJson === false ? '{}' : $stateJson) ?>"
        data-back-url="<?= e($back_url) ?>">
    <label class="check filter-select-all">
      <input type="checkbox" data-quick-all<?= $showAll ? ' checked' : '' ?>>
      <strong><?= e(t('filters.select_all')) ?></strong>
    </label>

    <div class="filter-tabs" role="tablist" aria-label="<?= e(t('menu.filters')) ?>">
      <?php foreach ($sections as $i => $kind): ?>
        <button type="button" class="filter-tab<?= $kind === $section ? ' active' : '' ?>" role="tab"
                id="quick-filter-tab-<?= e($kind) ?>" aria-controls="quick-filter-panel-<?= e($kind) ?>"
                aria-selected="<?= $kind === $section ? 'true' : 'false' ?>"
                tabindex="<?= $kind === $section ? '0' : '-1' ?>"
                data-quick-tab="<?= e($kind) ?>"><?= e(t('filters.section.' . $kind)) ?></button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($sections as $kind): ?>
      <?php
      $sets = (array)($base_state['sets'][$kind] ?? []);
      $sectionOptions = $options[$kind] ?? [];
      $cityGroups = [];
      foreach (($sectionOptions['cities'] ?? []) as $row) {
          $countryId = (int)$row['country_id'];
          $cityGroups[$countryId] ??= ['name' => (string)$row['country_name'], 'rows' => []];
          $cityGroups[$countryId]['rows'][] = $row;
      }
      ?>
      <section class="filter-panel" id="quick-filter-panel-<?= e($kind) ?>" role="tabpanel"
               aria-labelledby="quick-filter-tab-<?= e($kind) ?>" data-quick-panel="<?= e($kind) ?>"<?= $kind === $section ? '' : ' hidden' ?>>
        <?php if (!empty($sectionOptions['countries'])): ?>
          <div class="filter-group">
            <h2><?= e(t('filters.countries')) ?></h2>
            <div class="checks filter-checks">
              <?php foreach ($sectionOptions['countries'] as $row): ?>
                <label class="check filter-option"><input type="checkbox" data-quick-option="<?= e($kind) ?>:countries" value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sets['countries'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($sectionOptions['cities'])): ?>
          <div class="filter-group">
            <h2><?= e(t('filters.cities')) ?></h2>
            <?php foreach ($cityGroups as $group): ?>
              <div class="filter-country-group">
                <h3><?= e($group['name']) ?></h3>
                <div class="checks filter-checks">
                  <?php foreach ($group['rows'] as $row): ?>
                    <label class="check filter-option"><input type="checkbox" data-quick-option="<?= e($kind) ?>:cities" value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sets['cities'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($sectionOptions['genres'])): ?>
          <div class="filter-group">
            <h2><?= e(t('filters.genres')) ?></h2>
            <div class="checks filter-checks">
              <?php foreach ($sectionOptions['genres'] as $row): ?>
                <label class="check filter-option"><input type="checkbox" data-quick-option="<?= e($kind) ?>:genres" value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sets['genres'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (empty($sectionOptions['countries']) && empty($sectionOptions['cities']) && empty($sectionOptions['genres'])): ?>
          <p class="muted"><?= e(t('filters.empty')) ?></p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
