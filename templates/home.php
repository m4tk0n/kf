<?php
lang_load('events');

$section = (string)($_GET['section'] ?? 'events');
if (!in_array($section, ['events', 'venues', 'bands'], true)) {
    $section = 'events';
}

$cityCol  = lang_code() === 'cs' ? 'cs' : 'en';
$items    = [];
$lineups  = [];
$today    = date('Y-m-d');
$cur      = $today;
$explicit = false;
$page     = 1;
$hasNext  = false;
$prevPeriod = $prevDay = $nextDay = $nextPeriod = null;

if ($section === 'events') {
    $pdo     = db();
    $events  = tbl('events');
    $perPage = (int)cfg('paging.events');
    $page    = max(1, (int)($_GET['strana'] ?? 1));

    // Jedno místo pro dotazy typu "najdi datum": vrací Y-m-d, nebo null
    $dateAt = static function (string $sql, string $arg) use ($pdo): ?string {
        $q = $pdo->prepare($sql);
        $q->execute([$arg]);
        $v = $q->fetchColumn();
        return $v ? (string)$v : null;
    };

    // Počáteční den: zadané ?od=, jinak nejbližší den s akcí od dneška
    $od     = (string)($_GET['od'] ?? '');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $od);
    if ($parsed !== false && $parsed->format('Y-m-d') === $od) {
        $cur      = $od;
        $explicit = true;
    } else {
        $cur = $dateAt("SELECT DATE(MIN(starts_at)) FROM {$events} WHERE starts_at >= ?", $today . ' 00:00:00') ?? $today;
    }

    $day            = new DateTimeImmutable($cur);
    $curStart       = $cur . ' 00:00:00';
    $nextDayStart   = $day->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $monthStart     = $day->modify('first day of this month')->format('Y-m-d') . ' 00:00:00';
    $nextMonthStart = $day->modify('first day of next month')->format('Y-m-d') . ' 00:00:00';

    // Přepínač skáče po dnech, kdy jsou akce (může to být klidně týden i měsíc)
    $prevDay    = $dateAt("SELECT DATE(MAX(starts_at)) FROM {$events} WHERE starts_at < ?", $curStart);
    $nextDay    = $dateAt("SELECT DATE(MIN(starts_at)) FROM {$events} WHERE starts_at >= ?", $nextDayStart);
    $nextPeriod = $dateAt("SELECT DATE(MIN(starts_at)) FROM {$events} WHERE starts_at >= ?", $nextMonthStart);
    $lastBefore = $dateAt("SELECT MAX(starts_at) FROM {$events} WHERE starts_at < ?", $monthStart);
    if ($lastBefore !== null) {
        $prevMonth  = (new DateTimeImmutable($lastBefore))->modify('first day of this month')->format('Y-m-d') . ' 00:00:00';
        $prevPeriod = $dateAt("SELECT DATE(MIN(starts_at)) FROM {$events} WHERE starts_at >= ?", $prevMonth);
    }

    // Výpis od zvoleného dne dál; o jeden řádek víc, abychom poznali další stránku bez COUNT
    $sql = 'SELECT e.id, e.name, e.type, e.starts_at,
                   v.name AS venue_name, c.' . $cityCol . ' AS city_name,
                   co.iso2 AS country_code
            FROM ' . $events . ' e
            JOIN ' . tbl('venues') . ' v ON v.id = e.venue_id
            JOIN ' . tbl('cities') . ' c ON c.id = v.city_id
            JOIN ' . tbl('countries') . ' co ON co.id = c.country_id
            WHERE e.starts_at >= ?
            ORDER BY e.starts_at ASC, e.id ASC
            LIMIT ' . ($perPage + 1) . ' OFFSET ' . (($page - 1) * $perPage);
    $q = $pdo->prepare($sql);
    $q->execute([$curStart]);
    $items   = $q->fetchAll();
    $hasNext = count($items) > $perPage;
    $items   = array_slice($items, 0, $perPage);

    // Lineup jedním dotazem jen pro akce na této stránce
    if ($items) {
        $ids = array_map(static fn(array $r): int => (int)$r['id'], $items);
        $q = $pdo->prepare(
            'SELECT eb.event_id, b.name
             FROM ' . tbl('event_bands') . ' eb
             JOIN ' . tbl('bands') . ' b ON b.id = eb.band_id
             WHERE eb.event_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             ORDER BY eb.event_id, eb.pos, b.name'
        );
        $q->execute($ids);
        foreach ($q->fetchAll() as $r) {
            $lineups[(int)$r['event_id']][] = $r['name'];
        }
    }
} elseif ($section === 'venues') {
    $search = trim((string)($_GET['q'] ?? ''));
    $sql = 'SELECT v.id, v.name, ' . (lang_code() === 'cs' ? 'c.cs' : 'c.en') . ' AS city_name,
                   co.iso2 AS country_code
            FROM ' . tbl('venues') . ' v
            JOIN ' . tbl('cities') . ' c ON c.id = v.city_id
            JOIN ' . tbl('countries') . ' co ON co.id = c.country_id';
    if (mb_strlen($search, 'UTF-8') >= 3) {
        $sql .= ' WHERE v.name LIKE ? OR c.cs LIKE ? OR c.en LIKE ? OR co.iso2 LIKE ?';
        $like = '%' . $search . '%';
        $stmt = db()->prepare($sql . ' ORDER BY v.name ASC LIMIT ' . (int)cfg('paging.venues'));
        $stmt->execute([$like, $like, $like, $like]);
        $items = $stmt->fetchAll();
    } else {
        $items = db()->query($sql . ' ORDER BY v.name ASC LIMIT ' . (int)cfg('paging.venues'))->fetchAll();
    }
} else {
    $sql = 'SELECT b.name, co.iso2 AS country_code
            FROM ' . tbl('bands') . ' b
            JOIN ' . tbl('countries') . ' co ON co.id = b.country_id
            ORDER BY b.name ASC
            LIMIT ' . (int)cfg('paging.bands');
    $items = db()->query($sql)->fetchAll();
}

$pageLink = fn(int $p): string => url() . '?' . http_build_query(['od' => $cur, 'strana' => $p]);
$arrow = static function (?string $target, string $label, string $glyph): string {
    if ($target === null) {
        return '<button class="date-arrow" type="button" disabled aria-label="' . e($label) . '" style="opacity:.3;cursor:default">' . $glyph . '</button>';
    }
    return '<button class="date-arrow" type="submit" name="od" value="' . e($target) . '" aria-label="' . e($label) . '">' . $glyph . '</button>';
};
?>
<?php if ($section === 'events'): ?>
<form class="date-panel" method="get" action="<?= e(url()) ?>" aria-label="[date-picker-label]">
  <?= $arrow($prevPeriod, L('date-previous-period'), '‹‹') ?>
  <?= $arrow($prevDay, L('date-previous-day'), '‹') ?>
  <button class="date-current" type="submit" formaction="<?= e(url()) ?>"><?= e(date(L('fmt-date'), strtotime($cur))) ?> <span><?= e($cur === $today ? L('date-today') : L($explicit ? 'date-go-today' : 'date-nearest')) ?></span></button>
  <?= $arrow($nextDay, L('date-next-day'), '›') ?>
  <?= $arrow($nextPeriod, L('date-next-period'), '››') ?>
</form>
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
        <?php if ($section === 'venues'): ?>
          <a class="listing-row listing-row-link" href="<?= e(url('venue/' . (int)$item['id'] . '/' . slugify((string)$item['name']))) ?>">
        <?php else: ?>
          <article class="listing-row">
        <?php endif; ?>
          <div class="listing-main">
            <strong><?= e($item['name']) ?></strong>
            <?php if ($section === 'events'): ?>
              <?php $typeKey = cfg('enums.event_type')[(int)$item['type']] ?? 'other'; ?>
              <span><?php if ($typeKey !== 'concert'): ?><?= e(L('event-type-' . $typeKey)) ?> · <?php endif; ?><?= e($item['venue_name']) ?> · <?= e($item['city_name']) ?> (<?= e($item['country_code']) ?>)</span>
              <?php if (!empty($lineups[(int)$item['id']])): ?>
                <span><?= e(implode(', ', $lineups[(int)$item['id']])) ?></span>
              <?php endif; ?>
            <?php elseif ($section === 'venues'): ?>
              <span><?= e($item['city_name']) ?> (<?= e($item['country_code']) ?>)</span>
            <?php else: ?>
              <span><?= e($item['country_code']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($section === 'events'): ?>
            <time datetime="<?= e(date('c', strtotime($item['starts_at']))) ?>"><?= e(date(L('fmt-datetime'), strtotime($item['starts_at']))) ?></time>
          <?php endif; ?>
        <?php if ($section === 'venues'): ?></a><?php else: ?></article><?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($section === 'events' && ($page > 1 || $hasNext)): ?>
    <nav class="listing-pager" style="display:flex;justify-content:space-between;gap:8px;margin-top:10px">
      <?php if ($page > 1): ?><a class="filter-button" href="<?= e($pageLink($page - 1)) ?>"><?= e(L('pager-prev')) ?></a><?php else: ?><span></span><?php endif; ?>
      <?php if ($hasNext): ?><a class="filter-button" href="<?= e($pageLink($page + 1)) ?>"><?= e(L('pager-next')) ?></a><?php endif; ?>
    </nav>
  <?php endif; ?>
</section>

<?php if ($section === 'venues'): ?>
<script>
(() => {
  const form = document.querySelector('.section-toolbar');
  const search = document.getElementById('global-search');
  const section = form?.querySelector('[name="section"]');
  if (!form || !search || section?.value !== 'venues') return;
  let timer;
  let requestId = 0;
  let wasSearching = new URLSearchParams(window.location.search).get('q')?.trim().length >= 3;

  search.addEventListener('input', () => {
    clearTimeout(timer);
    const length = search.value.trim().length;
    if (length >= 3 || (wasSearching && length < 3)) {
      timer = setTimeout(async () => {
        const currentRequest = ++requestId;
        const url = new URL(window.location.href);
        url.searchParams.set('section', 'venues');
        if (length >= 3) url.searchParams.set('q', search.value.trim());
        else url.searchParams.delete('q');

        try {
          const response = await fetch(url.href, {
            headers: { 'X-Partial': '1' },
            credentials: 'same-origin'
          });
          if (!response.ok) throw new Error('Venue search failed');
          const html = await response.text();
          if (currentRequest !== requestId) return;

          const parsed = new DOMParser().parseFromString(html, 'text/html');
          const updatedList = parsed.querySelector('.event-section');
          const currentList = document.querySelector('.event-section');
          if (!updatedList || !currentList) return;

          currentList.replaceWith(updatedList);
          history.replaceState({}, '', url.href);
          wasSearching = length >= 3;
          search.focus({ preventScroll: true });
        } catch (error) {
          console.error(error);
        }
      }, 250);
    }
  });
})();
</script>
<?php endif; ?>

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
