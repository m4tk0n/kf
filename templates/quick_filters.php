<?php
// templates/quick_filters.php
declare(strict_types=1);
/** @var string $section @var array $options @var array $base_state @var string $back_url */
$sets = (array)($base_state['sets'][$section] ?? []);
$showAll = !empty($base_state['show_all']);
$cityGroups = [];
foreach (($options['cities'] ?? []) as $row) {
    $countryId = (int)$row['country_id'];
    $cityGroups[$countryId] ??= ['name' => (string)$row['country_name'], 'rows' => []];
    $cityGroups[$countryId]['rows'][] = $row;
}
$stateJson = json_encode($base_state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<section class="card">
  <div class="page-title-row">
    <h1><?= e(t('filters.quick_title')) ?></h1>
    <a class="btn page-back" href="<?= e($back_url) ?>"><?= e(t('common.back')) ?></a>
  </div>
  <form class="stack filter-form" data-quick-filter-form
        data-section="<?= e($section) ?>"
        data-base-state="<?= e($stateJson === false ? '{}' : $stateJson) ?>"
        data-back-url="<?= e($back_url) ?>">
    <label class="check filter-select-all">
      <input type="checkbox" data-quick-all<?= $showAll ? ' checked' : '' ?>>
      <strong><?= e(t('filters.select_all')) ?></strong>
    </label>

    <?php if (!empty($options['countries'])): ?>
      <div class="filter-group">
        <h2><?= e(t('filters.countries')) ?></h2>
        <div class="checks filter-checks">
          <?php foreach ($options['countries'] as $row): ?>
            <label class="check filter-option"><input type="checkbox" data-quick-option="countries" value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sets['countries'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($options['cities'])): ?>
      <div class="filter-group">
        <h2><?= e(t('filters.cities')) ?></h2>
        <?php foreach ($cityGroups as $group): ?>
          <div class="filter-country-group">
            <h3><?= e($group['name']) ?></h3>
            <div class="checks filter-checks">
              <?php foreach ($group['rows'] as $row): ?>
                <label class="check filter-option"><input type="checkbox" data-quick-option="cities" value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sets['cities'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($options['genres'])): ?>
      <div class="filter-group">
        <h2><?= e(t('filters.genres')) ?></h2>
        <div class="checks filter-checks">
          <?php foreach ($options['genres'] as $row): ?>
            <label class="check filter-option"><input type="checkbox" data-quick-option="genres" value="<?= (int)$row['id'] ?>"<?= ($showAll || in_array((int)$row['id'], $sets['genres'] ?? [], true)) ? ' checked' : '' ?>><span><?= e((string)$row['name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (empty($options['countries']) && empty($options['cities']) && empty($options['genres'])): ?>
      <p class="muted"><?= e(t('filters.empty')) ?></p>
    <?php endif; ?>

    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
