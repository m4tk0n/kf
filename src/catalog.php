<?php
// src/catalog.php
declare(strict_types=1);

function kf_get(string $key): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? $v : '';
}

function kf_request(): array
{
    $s = kf_get('s');
    if (!in_array($s, ['events', 'bands', 'venues'], true)) $s = 'events';
    $d = kf_get('d');
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) $d = '';
    return ['s' => $s, 'q' => mb_substr(trim(kf_get('q')), 0, 100), 'p' => max(1, (int)kf_get('p')), 'd' => $d];
}

function kf_url(array $req, array $over = []): string
{
    $p = array_merge(['s' => $req['s'], 'q' => $req['q'], 'p' => $req['p'], 'd' => $req['d']], $over);
    if ($p['s'] === 'events') unset($p['s']);
    if ((int)$p['p'] <= 1) unset($p['p']);
    $p = array_filter($p, static fn($v): bool => $v !== '' && $v !== null);
    return $p ? url('') . '?' . http_build_query($p) : url('');
}

function kf_asset(string $rel): string
{
    $path = (string)cfg('paths.assets') . '/' . $rel;
    return url($path . '?v=' . (int)@filemtime(abs_path($path)));
}

function kf_ascii(string $s): string
{
    return strtr(mb_strtolower($s), [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ĺ' => 'l', 'ľ' => 'l',
        'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ť' => 't',
        'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ý' => 'y', 'ž' => 'z',
    ]);
}

// 23.10. | 13.–25.10. | 30.10.–2.11.
function kf_date_range(string $from, string $to): string
{
    $a = new DateTimeImmutable($from);
    $b = new DateTimeImmutable($to);
    if ($from === $to) return $a->format('j.n.');
    if ($a->format('Y-m') === $b->format('Y-m')) return $a->format('j.') . '–' . $b->format('j.n.');
    return $a->format('j.n.') . '–' . $b->format('j.n.');
}

// cesta k obrázku se skládá z id a cfg (příznak v DB jen říká, že soubor existuje)
function kf_img(string $kind, int $id, string $size): string
{
    $bucket = intdiv($id, (int)cfg('img.folder_divisor'));
    return url((string)cfg('img.subdirs.' . $kind) . '/' . $bucket . '/' . $id . '_' . $size . '.' . (string)cfg('img.format'));
}

function kf_detail_url(string $kind, int $id): string
{
    return url('') . '?' . http_build_query([$kind => $id]);
}

function kf_pager(array $nav): string
{
    $btn = static function (?string $href, string $sym, string $label): string {
        return $href === null
            ? '<span class="pg off" aria-hidden="true">' . $sym . '</span>'
            : '<a class="pg" href="' . e($href) . '" aria-label="' . e($label) . '">' . $sym . '</a>';
    };
    $l = $nav['links'];
    $t = $nav['titles'] ?? [];
    foreach (['first', 'prev', 'next', 'last'] as $k) $t[$k] ??= kf_t('pager.' . $k);
    return '<nav class="pager" aria-label="' . e(kf_t('pager.label')) . '">'
        . $btn($l['first'], '&lt;&lt;', $t['first']) . $btn($l['prev'], '&lt;', $t['prev'])
        . '<span class="pager-cur">' . e($nav['label']) . '</span>'
        . $btn($l['next'], '&gt;', $t['next']) . $btn($l['last'], '&gt;&gt;', $t['last'])
        . '</nav>';
}

function kf_add_months(DateTimeImmutable $d, int $n): DateTimeImmutable
{
    $first = $d->modify('first day of this month')->modify(($n >= 0 ? '+' : '') . $n . ' month');
    return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min((int)$d->format('j'), (int)$first->format('t')));
}

// nejbližší den s akcí (podle hledání): from = první >= t, upto = poslední <= t, after = první > t, before = poslední < t
function kf_event_date(string $mode, string $t, string $qa): ?string
{
    $d = new DateTimeImmutable($t);
    $t1 = $d->modify('+1 day')->format('Y-m-d');
    $t0 = $d->modify('-1 day')->format('Y-m-d');
    [$sql, $p] = match ($mode) {
        'from' => ['MIN(GREATEST(a.date_from, ?)) WHERE a.date_to >= ?', [$t, $t]],
        'upto' => ['MAX(LEAST(a.date_to, ?)) WHERE a.date_from <= ?', [$t, $t]],
        'after' => ['MIN(CASE WHEN a.date_from > ? THEN a.date_from ELSE ? END) WHERE a.date_to > ?', [$t, $t1, $t]],
        default => ['MAX(CASE WHEN a.date_to < ? THEN a.date_to ELSE ? END) WHERE a.date_from < ?', [$t, $t0, $t]],
    };
    if ($qa !== '') {
        $sql .= ' AND LOWER(a.name_ascii) LIKE ?';
        $p[] = '%' . addcslashes($qa, '%_\\') . '%';
    }
    $v = q('SELECT ' . preg_replace('/ WHERE /', ' FROM ' . tbl('events') . ' a WHERE ', $sql, 1), $p)->fetchColumn();
    return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

// << < dd.mm.yyyy > >> : < > = předchozí/další den s akcí, << >> = o měsíc zpět/vpřed (nejbližší den s akcí)
function kf_day_nav(array $req, string $d, string $qa): array
{
    $cur = new DateTimeImmutable($d);
    $go = static fn(?string $to): ?string => $to === null ? null : kf_url($req, ['d' => $to, 'p' => 1]);
    return [
        'label' => $cur->format('d.m.Y'),
        'links' => [
            'first' => $go(kf_event_date('upto', kf_add_months($cur, -1)->format('Y-m-d'), $qa)),
            'prev' => $go(kf_event_date('before', $d, $qa)),
            'next' => $go(kf_event_date('after', $d, $qa)),
            'last' => $go(kf_event_date('from', kf_add_months($cur, 1)->format('Y-m-d'), $qa)),
        ],
        'titles' => [
            'first' => kf_t('pager.prev_month'), 'prev' => kf_t('pager.prev_day'),
            'next' => kf_t('pager.next_day'), 'last' => kf_t('pager.next_month'),
        ],
    ];
}

function kf_paged(string $cols, string $from, string $where, array $params, string $order, array $orderParams, array $req, int $per): array
{
    $total = (int)q('SELECT COUNT(*)' . $from . $where, $params)->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    $p = min($req['p'], $pages);
    $rows = q(
        'SELECT ' . $cols . $from . $where . ' ORDER BY ' . $order . ' LIMIT ' . $per . ' OFFSET ' . (($p - 1) * $per),
        array_merge($params, $orderParams)
    )->fetchAll(PDO::FETCH_ASSOC);
    $u = static fn(int $n): string => kf_url($req, ['p' => $n]);
    return [
        'rows' => $rows,
        'count' => $total,
        'nav' => [
            'label' => $p . ' / ' . $pages,
            'links' => [
                'first' => $p > 1 ? $u(1) : null, 'prev' => $p > 1 ? $u($p - 1) : null,
                'next' => $p < $pages ? $u($p + 1) : null, 'last' => $p < $pages ? $u($pages) : null,
            ],
        ],
    ];
}

function kf_load(array $req): array
{
    $per = max(1, min((int)cfg('paging.per_page'), (int)cfg('paging.max_per_page')));
    $country = 'co.`name_' . kf_lang() . '`'; // jazyk je ověřený proti app.supported_langs
    $geo = ' JOIN ' . tbl('cities') . ' c ON c.id = a.city_id JOIN ' . tbl('countries') . ' co ON co.id = c.country_id';
    $qa = kf_ascii($req['q']);
    $where = ' WHERE 1=1' . ($qa !== '' ? ' AND LOWER(a.name_ascii) LIKE ?' : '');
    $params = $qa !== '' ? ['%' . addcslashes($qa, '%_\\') . '%'] : [];
    $s = $req['s'];

    if ($s === 'events') {
        $from = ' FROM ' . tbl('events') . ' a' . $geo . ' LEFT JOIN ' . tbl('venues') . ' v ON v.id = a.venue_id';
        $cols = 'a.id, a.name, a.type, a.date_from, a.date_to, a.time_start, a.flyer, c.name AS city, co.iso2, '
            . $country . ' AS country, v.name AS venue';
        $today = date('Y-m-d');
        $d = $req['d'] !== '' ? $req['d']
            : (kf_event_date('from', $today, $qa) ?? kf_event_date('upto', $today, $qa) ?? $today);
        $rows = q(
            'SELECT ' . $cols . $from . $where . ' AND a.date_from <= ? AND a.date_to >= ?'
            . ' ORDER BY a.date_from, a.time_start IS NULL, a.time_start, a.id LIMIT ' . (int)cfg('paging.max_per_page'),
            array_merge($params, [$d, $d])
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['req' => $req, 'rows' => $rows, 'count' => count($rows), 'nav' => kf_day_nav($req, $d, $qa)];
    } elseif ($s === 'bands') {
        $from = ' FROM ' . tbl('bands') . ' a' . $geo . ' LEFT JOIN ' . tbl('genres') . ' g ON g.id = a.genre_id';
        $cols = 'a.id, a.name, a.logo, c.name AS city, co.iso2, ' . $country . ' AS country, g.name AS genre';
        $r = kf_paged($cols, $from, $where, $params, 'a.name, a.id', [], $req, $per);
    } else {
        $from = ' FROM ' . tbl('venues') . ' a' . $geo;
        $cols = 'a.id, a.name, a.img, c.name AS city, co.iso2, ' . $country . ' AS country';
        $r = kf_paged($cols, $from, $where, $params, 'a.name, a.id', [], $req, $per);
    }
    return ['req' => $req] + $r;
}
