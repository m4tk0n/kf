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
    return ['s' => $s, 'q' => mb_substr(trim(kf_get('q')), 0, 100), 'p' => max(1, (int)kf_get('p')), 'd' => $d, 'k' => preg_match('/^\d{1,5}$/', kf_get('k')) ? (int)kf_get('k') : 0, 'fav' => kf_get('fav') === '1' ? 1 : 0];
}

function kf_url(array $req, array $over = []): string
{
    $p = array_merge(['s' => $req['s'], 'q' => $req['q'], 'p' => $req['p'], 'd' => $req['d'], 'k' => $req['k'], 'fav' => $req['fav']], $over);
    if ($p['s'] === 'events') unset($p['s']);
    if ((int)$p['p'] <= 1) unset($p['p']);
    if ((int)$p['k'] <= 0) unset($p['k']);
    if ((int)$p['fav'] <= 0) unset($p['fav']);
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
    $params = [$kind => $id];

    // Při prvním otevření detailu uložit aktuální stav výpisu.
    // Při přechodu na související záznam zachovat původní návratový stav.
    $return = kf_get('return');
    if ($return === '') {
        $return = http_build_query(kf_request());
    }
    $params['return'] = $return;

    if (kf_get('fav') === '1' && auth_resolve() !== null) {
        $params['fav'] = '1';
    }
    return url('') . '?' . http_build_query($params);
}

/** Bezpečně obnoví pouze povolené parametry výpisu z návratového stavu detailu. */
function kf_detail_return_url(string $fallbackDate = ''): string
{
    $req = ['s' => 'events', 'q' => '', 'p' => 1, 'd' => $fallbackDate, 'k' => 0, 'fav' => 0];
    $raw = kf_get('return');

    if ($raw !== '') {
        parse_str($raw, $saved);
        if (isset($saved['s']) && is_string($saved['s']) && in_array($saved['s'], ['events', 'bands', 'venues'], true)) {
            $req['s'] = $saved['s'];
        }
        if (isset($saved['q']) && is_string($saved['q'])) {
            $req['q'] = mb_substr(trim($saved['q']), 0, 100);
        }
        if (isset($saved['p']) && is_scalar($saved['p']) && preg_match('/^\\d{1,6}$/', (string)$saved['p'])) {
            $req['p'] = max(1, (int)$saved['p']);
        }
        if (isset($saved['d']) && is_string($saved['d'])) {
            // Prázdné datum znamená původní automatický výběr dne; nesmí se nahradit datem akce.
            if ($saved['d'] === '') {
                $req['d'] = '';
            } elseif (preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $saved['d'], $m)
                && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                $req['d'] = $saved['d'];
            }
        }
        if (isset($saved['k']) && is_scalar($saved['k']) && preg_match('/^\\d{1,5}$/', (string)$saved['k'])) {
            $req['k'] = (int)$saved['k'];
        }
        $req['fav'] = isset($saved['fav']) && (string)$saved['fav'] === '1' ? 1 : 0;
    }

    return kf_url($req);
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
    $cal = $nav['cal'] ?? null; // u akcí je datum tlačítko otevírající kalendář
    $cur = $cal
        ? '<button type="button" class="pager-cur" data-cal data-date="' . e($cal['date']) . '" data-cal-url="' . e($cal['cal_url']) . '" data-day-url="' . e($cal['day_url']) . '">' . e($nav['label']) . '</button>'
        : '<span class="pager-cur">' . e($nav['label']) . '</span>';
    return '<nav class="pager" aria-label="' . e(kf_t('pager.label')) . '">'
        . $btn($l['first'], '&lt;&lt;', $t['first']) . $btn($l['prev'], '&lt;', $t['prev'])
        . $cur
        . $btn($l['next'], '&gt;', $t['next']) . $btn($l['last'], '&gt;&gt;', $t['last'])
        . '</nav>';
}

function kf_add_months(DateTimeImmutable $d, int $n): DateTimeImmutable
{
    $first = $d->modify('first day of this month')->modify(($n >= 0 ? '+' : '') . $n . ' month');
    return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min((int)$d->format('j'), (int)$first->format('t')));
}

// Společný filtr výpisů, pageru i kalendáře: hledání, oblíbené a uživatelské preference.
function kf_list_filter(string $qa, string $kind): array
{
    if (!in_array($kind, ['events', 'bands', 'venues'], true)) throw new InvalidArgumentException('kind');
    $sql = '';
    $p = [];
    if ($qa !== '') {
        $sql .= ' AND LOWER(a.name_ascii) LIKE ?';
        $p[] = '%' . addcslashes($qa, '%_\\\\') . '%';
    }

    $user = auth_resolve();
    if ($user !== null) {
        $uFilters = filters_get((int)$user['id']);
        if (!$uFilters['show_all']) {
            if (!empty($uFilters['none'])) {
                $sql .= ' AND 1=0';
            }
            $sets = $uFilters['sets'][$kind] ?? [];

            // Stát se vztahuje k městu záznamu; subquery nevyžaduje další JOIN v pageru ani kalendáři.
            $countries = array_values(array_filter(array_map('intval', $sets['countries'] ?? []), static fn(int $id): bool => $id > 0));
            if ($countries) {
                $in = implode(',', array_fill(0, count($countries), '?'));
                $sql .= ' AND a.city_id IN (SELECT fc.id FROM ' . tbl('cities') . ' fc WHERE fc.country_id IN (' . $in . '))';
                array_push($p, ...$countries);
            }

            // Pokud je vybraný alespoň jeden stát, zahrnuje automaticky všechna jeho města.
            // Výběr konkrétních měst se proto uplatní pouze tehdy, když není vybraný žádný stát.
            if (!$countries) {
                $cities = array_values(array_filter(array_map('intval', $sets['cities'] ?? []), static fn(int $id): bool => $id > 0));
                if ($cities) {
                    $in = implode(',', array_fill(0, count($cities), '?'));
                    $sql .= ' AND a.city_id IN (' . $in . ')';
                    array_push($p, ...$cities);
                }
            }

            if ($kind === 'bands') {
                $genres = array_values(array_filter(array_map('intval', $sets['genres'] ?? []), static fn(int $id): bool => $id > 0));
                if ($genres) {
                    $in = implode(',', array_fill(0, count($genres), '?'));
                    $sql .= ' AND a.genre_id IN (' . $in . ')';
                    array_push($p, ...$genres);
                }
            }
        }
    }

    // Host parametr fav ignoruje; oblíbené jsou dostupné jen přihlášenému uživateli.
    if (kf_get('fav') === '1' && $user !== null) {
        $sql .= ' AND EXISTS (SELECT 1 FROM ' . tbl('fav_' . $kind) . ' f WHERE f.id = a.id AND f.user_id = ?)';
        $p[] = (int)$user['id'];
    }
    return [$sql, $p];
}

function kf_events_filter(string $qa): array
{
    return kf_list_filter($qa, 'events');
}

// nejbližší den, ve kterém akce začíná (podle hledání): '>=' '>' = první, '<=' '<' = poslední
function kf_day_find(string $op, string $t, string $qa): ?string
{
    if (!in_array($op, ['<', '<=', '>', '>='], true)) throw new InvalidArgumentException('op');
    [$f, $p] = kf_events_filter($qa);
    $v = q('SELECT ' . ($op[0] === '<' ? 'MAX' : 'MIN') . '(a.date_from) FROM ' . tbl('events') . ' a WHERE a.date_from ' . $op . ' ?' . $f, array_merge([$t], $p))->fetchColumn();
    return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
}

// počet akcí (podle hledání), které začínají v den ($op '=') nebo od dne dále ($op '>=')
function kf_day_count(string $op, string $t, string $qa): int
{
    if (!in_array($op, ['=', '>='], true)) throw new InvalidArgumentException('op');
    [$f, $p] = kf_events_filter($qa);
    return (int)q('SELECT COUNT(*) FROM ' . tbl('events') . ' a WHERE a.date_from ' . $op . ' ?' . $f, array_merge([$t], $p))->fetchColumn();
}


// Počet akcí, které v daný den ještě probíhají nebo začínají později.
function kf_event_count_from(string $date, string $qa): int
{
    [$f, $p] = kf_events_filter($qa);
    return (int)q(
        'SELECT COUNT(*) FROM ' . tbl('events') . ' a WHERE a.date_to >= ?' . $f,
        array_merge([$date], $p)
    )->fetchColumn();
}

// výchozí den: první den akce, která dnes běží nebo teprve začne
function kf_default_day(string $qa): string
{
    $today = date('Y-m-d');
    [$f, $p] = kf_events_filter($qa);
    $v = q('SELECT MIN(a.date_from) FROM ' . tbl('events') . ' a WHERE a.date_to >= ?' . $f, array_merge([$today], $p))->fetchColumn();
    return is_string($v) && $v !== '' ? $v : (kf_day_find('<=', $today, $qa) ?? $today);
}

/**
 * << < dd.mm.yyyy > >> : < > = předchozí/další den s akcí, << >> = o měsíc zpět/vpřed.
 * Stránka = $n akcí od dne $d dále (po $k akcích téhož dne). Má-li den víc akcí než $n, šipky nejdřív
 * projdou jeho části (nic se nepřeskočí). Šipka je zablokovaná, pokud by cílová stránka měla méně než $n akcí.
 */
function kf_event_nav(array $req, string $d, int $k, int $n, string $qa): array
{
    $cur = new DateTimeImmutable($d);
    $url = static fn(string $dd): string => kf_url($req, ['d' => $dd, 'k' => 0, 'p' => 1]);

    // Šipky vždy přecházejí na předchozí/následující den, kdy začíná alespoň jedna akce.
    // Vícedenní akce zůstává ve výpisu i v dalších dnech díky filtru date_to >= zvolený den.
    $prevDay = kf_day_find('<', $d, $qa);
    $nextDay = kf_day_find('>', $d, $qa);

    $monthBack = kf_day_find('<=', kf_add_months($cur, -1)->format('Y-m-d'), $qa);
    $monthForward = kf_day_find('>=', kf_add_months($cur, 1)->format('Y-m-d'), $qa);

    return [
        'label' => $cur->format('d.m.Y'),
        'links' => [
            'first' => $monthBack !== null ? $url($monthBack) : null,
            'prev' => $prevDay !== null ? $url($prevDay) : null,
            'next' => $nextDay !== null ? $url($nextDay) : null,
            'last' => $monthForward !== null ? $url($monthForward) : null,
        ],
        'titles' => ['first' => kf_t('pager.prev_month'), 'last' => kf_t('pager.next_month')],
        'cal' => [
            'date' => $d,
            'cal_url' => url('') . '?' . http_build_query(array_filter(['cal' => '__M__', 'q' => $req['q'], 'fav' => $req['fav'] ? '1' : ''], static fn($v): bool => $v !== '')),
            'day_url' => kf_url($req, ['d' => '__D__', 'k' => 0, 'p' => 1]),
        ],
    ];
}

// Typy akcí pro všech 42 buněk kalendáře (pondělí až neděle); klíčem je YYYY-MM-DD.
// Vícedenní akce se zapíše do každého dne, který zasahuje do zobrazené mřížky.
function kf_calendar(string $month, string $qa): array
{
    $firstOfMonth = new DateTimeImmutable($month . '-01');
    $offset = ((int)$firstOfMonth->format('N')) - 1;
    $first = $firstOfMonth->modify('-' . $offset . ' days')->format('Y-m-d');
    $last = (new DateTimeImmutable($first))->modify('+41 days')->format('Y-m-d');
    [$f, $p] = kf_events_filter($qa);
    $rows = q(
        'SELECT a.date_from, a.date_to, a.type FROM ' . tbl('events') . ' a'
        . ' WHERE a.date_from <= ? AND a.date_to >= ?' . $f,
        array_merge([$last, $first], $p)
    )->fetchAll(PDO::FETCH_ASSOC);
    $days = [];
    foreach ($rows as $r) {
        $to = min((string)$r['date_to'], $last);
        for ($t = new DateTimeImmutable(max((string)$r['date_from'], $first)); $t->format('Y-m-d') <= $to; $t = $t->modify('+1 day')) {
            $days[$t->format('Y-m-d')][(int)$r['type']] = true;
        }
    }
    $out = [];
    foreach ($days as $date => $types) {
        ksort($types);
        $out[$date] = array_keys($types);
    }
    return $out;
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
    $per = static fn(string $kind): int => max(1, min((int)cfg('paging.per_page_' . $kind), (int)cfg('paging.max_per_page')));
    $country = 'co.`name_' . kf_lang() . '`'; // jazyk je ověřený proti app.supported_langs
    $geo = ' JOIN ' . tbl('cities') . ' c ON c.id = a.city_id JOIN ' . tbl('countries') . ' co ON co.id = c.country_id';
    $s = $req['s'];
    $qa = kf_ascii($req['q']);
    [$f, $params] = kf_list_filter($qa, $s);
    $where = ' WHERE 1=1' . $f;

    if ($s === 'events') {
        $from = ' FROM ' . tbl('events') . ' a' . $geo . ' LEFT JOIN ' . tbl('venues') . ' v ON v.id = a.venue_id';
        $cols = 'a.id, a.name, a.type, a.date_from, a.date_to, a.time_start, a.flyer, c.name AS city, co.iso2, '
            . $country . ' AS country, v.name AS venue';
        $n = $per('events');
        $d = $req['d'] !== '' ? $req['d'] : kf_default_day($qa);
        $k = 0; // pager akcí přechází po dnech, ne po blocích záznamů
        $rows = q(
            'SELECT ' . $cols . $from . $where . ' AND a.date_to >= ?'
            . ' ORDER BY a.date_from, a.time_start IS NULL, a.time_start, a.id LIMIT ' . $n,
            array_merge([$d], $params)
        )->fetchAll(PDO::FETCH_ASSOC);
        $total = (int)q('SELECT COUNT(*) FROM ' . tbl('events') . ' a' . $where, $params)->fetchColumn();
        return ['req' => $req, 'rows' => $rows, 'count' => $total, 'nav' => kf_event_nav($req, $d, $k, $n, $qa)];
    } elseif ($s === 'bands') {
        $from = ' FROM ' . tbl('bands') . ' a' . $geo . ' LEFT JOIN ' . tbl('genres') . ' g ON g.id = a.genre_id';
        $cols = 'a.id, a.name, a.logo, c.name AS city, co.iso2, ' . $country . ' AS country, g.name AS genre';
        $r = kf_paged($cols, $from, $where, $params, 'a.name, a.id', [], $req, $per('bands'));
    } else {
        $from = ' FROM ' . tbl('venues') . ' a' . $geo;
        $cols = 'a.id, a.name, a.img, c.name AS city, co.iso2, ' . $country . ' AS country';
        $r = kf_paged($cols, $from, $where, $params, 'a.name, a.id', [], $req, $per('venues'));
    }
    return ['req' => $req] + $r;
}
