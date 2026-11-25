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

    [$filterSql, $filterParams] = filters_catalog_conditions($kind);
    $sql .= $filterSql;
    array_push($p, ...$filterParams);

    // Host parametr fav ignoruje; oblíbené jsou dostupné jen přihlášenému uživateli.
    $user = auth_resolve();
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
// počet akcí (podle hledání), které začínají v den ($op '=') nebo od dne dále ($op '>=')
// Počet akcí, které v daný den ještě probíhají nebo začínají později.
// výchozí den: první den akce, která dnes běží nebo teprve začne
/**
 * << < dd.mm.yyyy > >> : < > = předchozí/další den s akcí, << >> = o měsíc zpět/vpřed.
 * Stránka = $n akcí od dne $d dále (po $k akcích téhož dne). Má-li den víc akcí než $n, šipky nejdřív
 * projdou jeho části (nic se nepřeskočí). Šipka je zablokovaná, pokud by cílová stránka měla méně než $n akcí.
 */
// Typy akcí pro všech 42 buněk kalendáře (pondělí až neděle); klíčem je YYYY-MM-DD.
// Vícedenní akce se zapíše do každého dne, který zasahuje do zobrazené mřížky.
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
    $country = 'co.`name_' . current_lang() . '`'; // jazyk je ověřený proti app.supported_langs
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
        $k = 0; // pager akcí přechází po datech začátků akcí

        $rows = q(
            'SELECT ' . $cols . $from . $where . ' AND a.date_to >= ?'
            . ' ORDER BY a.date_from, a.time_start IS NULL, a.time_start, a.id LIMIT ' . $n,
            array_merge($params, [$d])
        )->fetchAll(PDO::FETCH_ASSOC);

        // Aktuální akce jsou nezávislé na datu pageru: zobrazí se i při prohlížení vzdálené budoucnosti.
        // Stejné hledání, oblíbené a uživatelské filtry jako v hlavním výpisu.
        $today = date('Y-m-d');
        $currentCount = (int)q(
            'SELECT COUNT(*) FROM ' . tbl('events') . ' a' . $where . ' AND a.date_from <= ? AND a.date_to >= ?',
            array_merge($params, [$today, $today])
        )->fetchColumn();

        $total = (int)q('SELECT COUNT(*) FROM ' . tbl('events') . ' a' . $where, $params)->fetchColumn();
        return [
            'req' => $req,
            'rows' => $rows,
            'count' => $total,
            'current_count' => $currentCount,
            'nav' => kf_event_nav($req, $d, $k, $n, $qa),
        ];
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
