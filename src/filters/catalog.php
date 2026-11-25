<?php
// src/filters/catalog.php
declare(strict_types=1);

// SQL podmínky filtrů pro samostatné katalogy Akcí, Kapel a Klubů.

/** Vrátí SQL podmínky pro katalog; hledání a oblíbené zůstávají v catalog.php. */
function filters_catalog_conditions(string $kind): array
{
    if (!in_array($kind, ['events', 'bands', 'venues'], true)) throw new InvalidArgumentException('kind');
    $sql = ''; $params = [];
    $uFilters = filters_effective($kind);
    if (!$uFilters['show_all']) {
        $sets = $uFilters['sets'][$kind] ?? [];
        // Stát je pouze souhrnný checkbox; do filtru vstupují konkrétní města.
        $cities = array_values(array_filter(array_map('intval', $sets['cities'] ?? []), static fn(int $id): bool => $id > 0));
        $genres = $kind === 'bands' ? array_values(array_filter(array_map('intval', $sets['genres'] ?? []), static fn(int $id): bool => $id > 0)) : [];
        // Stát zahrnuje všechna svá města kromě výslovně odebraných; samostatná města se přidávají přes OR.
        if (!$countries && !$cities && !$genres) {
            $sql .= ' AND 1=0';
        } else {
            if ($cities) {
                $in = implode(',', array_fill(0, count($cities), '?'));
                $sql .= ' AND a.city_id IN (' . $in . ')';
                array_push($params, ...$cities);
            }
            if ($genres) {
                $in = implode(',', array_fill(0, count($genres), '?'));
                $sql .= ' AND a.genre_id IN (' . $in . ')';
                array_push($params, ...$genres);
            }
        }
    }
    return [$sql, $params];
}
