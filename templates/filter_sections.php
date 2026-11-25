<?php
// templates/filter_sections.php
/** @var array $options @var array $sets @var bool $showAll @var bool $quick_filters @var string $active_filter_section */
$sections = ['events', 'bands', 'venues'];
?>
<div class="filter-tabs" role="tablist" aria-label="<?= e(t('menu.filters')) ?>">
  <?php foreach ($sections as $kind): ?>
    <button type="button" class="filter-tab<?= $kind === $active_filter_section ? ' active' : '' ?>" role="tab"
            id="filter-tab-<?= e($kind) ?>" aria-controls="filter-panel-<?= e($kind) ?>"
            aria-selected="<?= $kind === $active_filter_section ? 'true' : 'false' ?>"
            tabindex="<?= $kind === $active_filter_section ? '0' : '-1' ?>"
            data-filter-tab="<?= e($kind) ?>"><?= e(t('filters.section.' . $kind)) ?></button>
  <?php endforeach; ?>
</div>

<?php foreach ($sections as $kind): ?>
  <?php
  $sectionSets = (array)($sets[$kind] ?? []);
  $sectionOptions = $options[$kind] ?? [];
  $cityGroups = [];
  foreach (($sectionOptions['cities'] ?? []) as $row) {
      $countryId = (int)$row['country_id'];
      $cityGroups[$countryId] ??= ['id' => $countryId, 'name' => (string)$row['country_name'], 'rows' => []];
      $cityGroups[$countryId]['rows'][] = $row;
  }
  ?>
  <section class="filter-panel" id="filter-panel-<?= e($kind) ?>" role="tabpanel"
           aria-labelledby="filter-tab-<?= e($kind) ?>" data-filter-panel="<?= e($kind) ?>"<?= $kind === $active_filter_section ? '' : ' hidden' ?>>
    <?php if ($quick_filters): ?>
      <?php if (!empty($sectionOptions['countries'])): ?>
        <div class="filter-group">
          <h2><?= e(t('filters.countries')) ?></h2>
          <div class="checks filter-checks">
            <?php foreach ($sectionOptions['countries'] as $row): ?>
              <label class="check filter-option"><input type="checkbox" data-quick-option="<?= e($kind) ?>:countries" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sectionSets['countries'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
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
                  <label class="check filter-option"><input type="checkbox" data-quick-option="<?= e($kind) ?>:cities" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sectionSets['cities'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <?php if (!empty($sectionOptions['cities'])): ?>
        <div class="filter-group">
          <h2><?= e(t('filters.cities')) ?></h2>
          <?php foreach ($cityGroups as $group): ?>
            <div class="filter-country-group">
              <h3>
                <label class="check filter-option filter-country-option">
                  <input type="checkbox" data-filter-country-option data-country-id="<?= (int)$group['id'] ?>" value="<?= (int)$group['id'] ?>"<?= ($showAll || count(array_intersect(array_map(static fn(array $row): int => (int)$row['id'], $group['rows']), array_map('intval', $sectionSets['cities'] ?? []))) === count($group['rows'])) ? ' checked' : '' ?>>
                  <span><?= e($group['name']) ?></span>
                </label>
              </h3>
              <div class="checks filter-checks">
                <?php foreach ($group['rows'] as $row): ?>
                  <label class="check filter-option"><input type="checkbox" name="filters[<?= e($kind) ?>][cities][]" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sectionSets['cities'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($sectionOptions['genres'])): ?>
      <div class="filter-group">
        <h2><?= e(t('filters.genres')) ?></h2>
        <div class="checks filter-checks">
          <?php foreach ($sectionOptions['genres'] as $row): ?>
            <label class="check filter-option"><input type="checkbox" <?php if ($quick_filters): ?>data-quick-option="<?= e($kind) ?>:genres"<?php else: ?>name="filters[<?= e($kind) ?>][genres][]"<?php endif; ?> data-filter-option value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sectionSets['genres'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (empty($sectionOptions['cities']) && empty($sectionOptions['genres']) && (!$quick_filters || empty($sectionOptions['countries']))): ?>
      <p class="muted"><?= e(t('filters.empty')) ?></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
