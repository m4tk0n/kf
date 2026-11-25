<?php
// templates/filters.php
declare(strict_types=1);
/** @var array $filters @var array<string,array<string,array>> $options */
?>
<section class="card">
  <h1><?= e(t('menu.filters')) ?></h1>
  <p><?= e(t('filters.intro')) ?></p>
  <form method="post" action="<?= e(url('filters')) ?>" class="stack" data-filter-form>
    <?= csrf_field() ?>
    <label class="check filter-select-all">
      <input type="checkbox" data-filter-all<?= $filters['show_all'] ? ' checked' : '' ?>>
      <strong><?= e(t('filters.select_all')) ?></strong>
    </label>

    <?php foreach (['events', 'bands', 'venues'] as $section): ?>
      <?php $sectionSets = $filters['sets'][$section] ?? []; ?>
      <details class="fgroup"<?= array_sum(array_map('count', $sectionSets)) ? ' open' : '' ?>>
        <summary><?= e(t('filters.section.' . $section)) ?></summary>

        <?php if (isset($options[$section]['countries'])): ?>
          <?php $rows = $options[$section]['countries']; $selected = $sectionSets['countries'] ?? []; ?>
          <details class="fgroup fgroup-nested">
            <summary><?= e(t('filters.countries')) ?> <span class="muted">(<?= count($selected) ?>)</span></summary>
            <?php if (!$rows): ?>
              <p class="muted"><?= e(t('filters.empty')) ?></p>
            <?php else: ?>
              <div class="checks">
                <?php foreach ($rows as $row): ?>
                  <label class="check"><input type="checkbox" name="filters[<?= e($section) ?>][countries][]" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($filters['show_all'] || !$selected || in_array((int)$row['id'], $selected, true)) ? ' checked' : '' ?>> <?= e((string)$row['name']) ?></label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </details>
        <?php endif; ?>

        <?php if (isset($options[$section]['cities'])): ?>
          <?php
          $rows = $options[$section]['cities'];
          $selected = $sectionSets['cities'] ?? [];
          $groups = [];
          foreach ($rows as $row) $groups[(int)$row['country_id']] ??= ['name' => (string)$row['country_name'], 'rows' => []];
          foreach ($rows as $row) $groups[(int)$row['country_id']]['rows'][] = $row;
          ?>
          <details class="fgroup fgroup-nested">
            <summary><?= e(t('filters.cities')) ?> <span class="muted">(<?= count($selected) ?>)</span></summary>
            <?php if (!$groups): ?>
              <p class="muted"><?= e(t('filters.empty')) ?></p>
            <?php else: ?>
              <?php foreach ($groups as $group): ?>
                <details class="fgroup fgroup-country">
                  <summary><?= e($group['name']) ?></summary>
                  <div class="checks">
                    <?php foreach ($group['rows'] as $row): ?>
                      <label class="check"><input type="checkbox" name="filters[<?= e($section) ?>][cities][]" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($filters['show_all'] || !$selected || in_array((int)$row['id'], $selected, true)) ? ' checked' : '' ?>> <?= e((string)$row['name']) ?></label>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </details>
        <?php endif; ?>

        <?php if (isset($options[$section]['genres'])): ?>
          <?php $rows = $options[$section]['genres']; $selected = $sectionSets['genres'] ?? []; ?>
          <details class="fgroup fgroup-nested">
            <summary><?= e(t('filters.genres')) ?> <span class="muted">(<?= count($selected) ?>)</span></summary>
            <?php if (!$rows): ?>
              <p class="muted"><?= e(t('filters.empty')) ?></p>
            <?php else: ?>
              <div class="checks">
                <?php foreach ($rows as $row): ?>
                  <label class="check"><input type="checkbox" name="filters[<?= e($section) ?>][genres][]" data-filter-option value="<?= (int)$row['id'] ?>"<?= ($filters['show_all'] || !$selected || in_array((int)$row['id'], $selected, true)) ? ' checked' : '' ?>> <?= e((string)$row['name']) ?></label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </details>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>

    <button type="submit" class="btn"><?= e(t('common.save')) ?></button>
  </form>
</section>
