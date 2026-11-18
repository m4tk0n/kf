<?php
$section = (string)($_GET['section'] ?? 'events');
if (!in_array($section, ['events', 'venues', 'bands'], true)) {
    $section = 'events';
}

$items = [];
if ($section === 'events') {
    $sql = 'SELECT e.id, e.name, e.type, e.starts_at, e.ends_at,
                   v.name AS venue_name, c.' . (lang_code() === 'cs' ? 'cs' : 'en') . ' AS city_name,
                   co.iso2 AS country_code
            FROM ' . tbl('events') . ' e
            JOIN ' . tbl('venues') . ' v ON v.id = e.venue_id
            JOIN ' . tbl('cities') . ' c ON c.id = v.city_id
            JOIN ' . tbl('countries') . ' co ON co.id = c.country_id
            ORDER BY e.starts_at ASC
            LIMIT ' . (int)cfg('paging.events');
    $items = db()->query($sql)->fetchAll();
} elseif ($section === 'venues') {
    $sql = 'SELECT v.name, ' . (lang_code() === 'cs' ? 'c.cs' : 'c.en') . ' AS city_name,
                   co.iso2 AS country_code
            FROM ' . tbl('venues') . ' v
            JOIN ' . tbl('cities') . ' c ON c.id = v.city_id
            JOIN ' . tbl('countries') . ' co ON co.id = c.country_id
            ORDER BY v.name ASC
            LIMIT ' . (int)cfg('paging.venues');
    $items = db()->query($sql)->fetchAll();
} else {
    $sql = 'SELECT b.name, co.iso2 AS country_code
            FROM ' . tbl('bands') . ' b
            JOIN ' . tbl('countries') . ' co ON co.id = b.country_id
            ORDER BY b.name ASC
            LIMIT ' . (int)cfg('paging.bands');
    $items = db()->query($sql)->fetchAll();
}
?>
<?php if ($section === 'events'): ?>
<section class="date-panel" aria-label="[date-picker-label]">
  <button class="date-arrow" type="button" aria-label="[date-previous-period]">‹‹</button>
  <button class="date-arrow" type="button" aria-label="[date-previous-day]">‹</button>
  <button class="date-current" type="button">[date-today] <span><?= e(date(lang_code() === 'cs' ? 'j. n. Y' : 'M j, Y')) ?></span></button>
  <button class="date-arrow" type="button" aria-label="[date-next-day]">›</button>
  <button class="date-arrow" type="button" aria-label="[date-next-period]">››</button>
</section>
<?php endif; ?>

<section class="event-section listing-section">
  <?php if (!$items): ?>
    <div class="empty-state">
      <div class="empty-icon" aria-hidden="true">[img]</div>
      <strong><?= e(L($section . '-empty-title')) ?></strong>
      <p><?= e(L($section . '-empty-description')) ?></p>
    </div>
  <?php else: ?>
    <div class="listing">
      <?php foreach ($items as $item): ?>
        <article class="listing-row">
          <div class="listing-main">
            <strong><?= e($item['name']) ?></strong>
            <?php if ($section === 'events'): ?>
              <span><?= e($item['venue_name']) ?> · <?= e($item['city_name']) ?> (<?= e($item['country_code']) ?>)</span>
            <?php elseif ($section === 'venues'): ?>
              <span><?= e($item['city_name']) ?> (<?= e($item['country_code']) ?>)</span>
            <?php else: ?>
              <span><?= e($item['country_code']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($section === 'events'): ?>
            <time><?= e(date(lang_code() === 'cs' ? 'j. n. Y · H:i' : 'M j, Y · H:i', strtotime($item['starts_at']))) ?></time>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($section === 'events'): ?>
<section class="filters" id="filtry">
  <h2>[search-heading]</h2>
  <div class="filter-grid">
    <label>[search-label]<input type="search" placeholder="[search-placeholder]"></label>
    <label>[country-label]<select><option>[country-all]</option><option>CZ</option></select></label>
    <label>[event-type-label]<select><option>[event-type-all]</option><option>[event-type-concert]</option><option>[event-type-festival]</option><option>[event-type-other]</option></select></label>
  </div>
</section>
<?php endif; ?>
