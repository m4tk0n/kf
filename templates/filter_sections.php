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
    <?php foreach (['countries' => 'countries', 'cities' => 'cities', 'genres' => 'genres'] as $field => $label): ?>
      <?php if (!empty($sectionOptions[$field])): ?>
        <div class="filter-group">
          <h2><?= e(t('filters.' . $label)) ?></h2>
          <?php if ($field === 'cities'): ?>
            <?php foreach ($cityGroups as $group): ?>
              <div class="filter-country-group">
                <h3><?= e($group['name']) ?></h3>
                <div class="checks filter-checks">
                  <?php foreach ($group['rows'] as $row): ?>
                    <label class="check filter-option"><input type="checkbox" <?php if ($quick_filters): ?>data-quick-option="<?= e($kind) ?>:cities" data-country-id="<?= (int)$group['id'] ?>"<?php else: ?>name="filters[<?= e($kind) ?>][cities][]"<?php endif; ?> data-filter-option value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sectionSets['cities'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="checks filter-checks">
              <?php foreach ($sectionOptions[$field] as $row): ?>
                <label class="check filter-option"><input type="checkbox" <?php if ($quick_filters): ?>data-quick-option="<?= e($kind) ?>:<?= e($field) ?>"<?php if ($field === 'countries'): ?>data-quick-country-option<?php endif; ?><?php else: ?>name="filters[<?= e($kind) ?>][<?= e($field) ?>][]"<?php endif; ?> data-filter-option value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sectionSets[$field] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (empty($sectionOptions['countries']) && empty($sectionOptions['cities']) && empty($sectionOptions['genres'])): ?>
      <p class="muted"><?= e(t('filters.empty')) ?></p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
