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
        $countries = array_values(array_filter(array_map('intval', $sets['countries'] ?? []), static fn(int $id): bool => $id > 0));
        $cities = array_values(array_filter(array_map('intval', $sets['cities'] ?? []), static fn(int $id): bool => $id > 0));
        $genres = $kind === 'bands' ? array_values(array_filter(array_map('intval', $sets['genres'] ?? []), static fn(int $id): bool => $id > 0)) : [];
        // Každá sekce má vlastní výběr. Bez vybrané hodnoty se nezobrazí nic.
        if (!$countries && !$cities && !$genres) {
            $sql .= ' AND 1=0';
        } else {
            // Země má přednost před městy.
            if ($countries) {
                $in = implode(',', array_fill(0, count($countries), '?'));
                $sql .= ' AND a.city_id IN (SELECT fc.id FROM ' . tbl('cities') . ' fc WHERE fc.country_id IN (' . $in . '))';
                array_push($params, ...$countries);
            } elseif ($cities) {
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
