<?php
// src/pager.php – samostatná logika navigace výpisu a kalendáře.
declare(strict_types=1);

function kf_pager(array $nav): string
{
    $btn = static function (?string $href, string $sym, string $label): string {
        return $href === null
            ? '<span class="pg off" aria-hidden="true">' . $sym . '</span>'
            : '<a class="pg" href="' . e($href) . '" aria-label="' . e($label) . '">' . $sym . '</a>';
    };
    $l = $nav['links'];
    $t = $nav['titles'] ?? [];
    foreach (['first', 'prev', 'next', 'last'] as $k) $t[$k] ??= t('pager.' . $k);
    $cal = $nav['cal'] ?? null; // u akcí je datum tlačítko otevírající kalendář
    $cur = $cal
        ? '<button type="button" class="pager-cur" data-cal data-date="' . e($cal['date']) . '" data-cal-url="' . e($cal['cal_url']) . '" data-day-url="' . e($cal['day_url']) . '">' . e($nav['label']) . '</button>'
        : '<span class="pager-cur">' . e($nav['label']) . '</span>';
    return '<nav class="pager" aria-label="' . e(t('pager.label')) . '">'
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

/**
 * Najde nejbližší den, ve kterém začíná alespoň jedna filtrovaná akce.
 *
 * > / <  – striktně následující / předchozí datum začátku akce.
 * >= / <= – nejbližší datum začátku od/do zadaného data včetně.
 *
 * Délka vícedenní akce neovlivňuje navigaci pageru. Její průběh zůstává
 * dostupný ve výpisu zvoleného dne a v samostatném přehledu probíhajících akcí.
 */
function kf_active_day_find(string $op, string $t, string $qa): ?string
{
    if (!in_array($op, ['<', '<=', '>', '>='], true)) throw new InvalidArgumentException('op');
    [$f, $p] = kf_events_filter($qa);

    if ($op === '>' || $op === '>=') {
        $cmp = $op === '>' ? '>' : '>=';
        $sql = 'SELECT MIN(a.date_from) FROM ' . tbl('events') . ' a'
            . ' WHERE a.date_from ' . $cmp . ' ?' . $f;
    } else {
        $cmp = $op === '<' ? '<' : '<=';
        $sql = 'SELECT MAX(a.date_from) FROM ' . tbl('events') . ' a'
            . ' WHERE a.date_from ' . $cmp . ' ?' . $f;
    }

    $v = q($sql, array_merge([$t], $p))->fetchColumn();
    return is_string($v) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $v) ? $v : null;
}

function kf_day_count(string $op, string $t, string $qa): int
{
    if (!in_array($op, ['=', '>='], true)) throw new InvalidArgumentException('op');
    [$f, $p] = kf_events_filter($qa);
    return (int)q('SELECT COUNT(*) FROM ' . tbl('events') . ' a WHERE a.date_from ' . $op . ' ?' . $f, array_merge([$t], $p))->fetchColumn();
}

function kf_event_count_from(string $date, string $qa): int
{
    [$f, $p] = kf_events_filter($qa);
    return (int)q(
        'SELECT COUNT(*) FROM ' . tbl('events') . ' a WHERE a.date_to >= ?' . $f,
        array_merge([$date], $p)
    )->fetchColumn();
}

function kf_default_day(string $qa): string
{
    $today = date('Y-m-d');
    [$f, $p] = kf_events_filter($qa);
    $v = q('SELECT MIN(a.date_from) FROM ' . tbl('events') . ' a WHERE a.date_to >= ?' . $f, array_merge([$today], $p))->fetchColumn();
    return is_string($v) && $v !== '' ? $v : (kf_active_day_find('<=', $today, $qa) ?? $today);
}

function kf_event_nav(array $req, string $d, int $k, int $n, string $qa): array
{
    $cur = new DateTimeImmutable($d);
    $url = static fn(string $dd): string => kf_url($req, ['d' => $dd, 'k' => 0, 'p' => 1]);

    // Šipky přecházejí pouze mezi daty začátků akcí. Dny uvnitř festivalu se neproklikávají.
    $prevDay = kf_active_day_find('<', $d, $qa);
    $nextDay = kf_active_day_find('>', $d, $qa);

    $monthBack = kf_active_day_find('<=', kf_add_months($cur, -1)->format('Y-m-d'), $qa);
    $monthForward = kf_active_day_find('>=', kf_add_months($cur, 1)->format('Y-m-d'), $qa);

    return [
        'label' => $cur->format('d.m.Y'),
        'links' => [
            'first' => $monthBack !== null ? $url($monthBack) : null,
            'prev' => $prevDay !== null ? $url($prevDay) : null,
            'next' => $nextDay !== null ? $url($nextDay) : null,
            'last' => $monthForward !== null ? $url($monthForward) : null,
        ],
        'titles' => ['first' => t('pager.prev_month'), 'last' => t('pager.next_month')],
        'cal' => [
            'date' => $d,
            'cal_url' => url('') . '?' . http_build_query(array_filter(['cal' => '__M__', 'q' => $req['q'], 'fav' => $req['fav'] ? '1' : ''], static fn($v): bool => $v !== '')),
            'day_url' => kf_url($req, ['d' => '__D__', 'k' => 0, 'p' => 1]),
        ],
    ];
}

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
