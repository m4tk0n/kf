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
        $excludedCities = array_values(array_filter(array_map('intval', $uFilters['city_exclusions'][$kind] ?? []), static fn(int $id): bool => $id > 0));
        $genres = $kind === 'bands' ? array_values(array_filter(array_map('intval', $sets['genres'] ?? []), static fn(int $id): bool => $id > 0)) : [];
        // Stát zahrnuje všechna svá města kromě výslovně odebraných; samostatná města se přidávají přes OR.
        if (!$countries && !$cities && !$genres) {
            $sql .= ' AND 1=0';
        } else {
            if ($countries) {
                $countryIn = implode(',', array_fill(0, count($countries), '?'));
                $cityExclusionSql = '';
                if ($excludedCities) {
                    $excludedIn = implode(',', array_fill(0, count($excludedCities), '?'));
                    $cityExclusionSql = ' AND a.city_id NOT IN (' . $excludedIn . ')';
                }
                $sql .= ' AND ((a.city_id IN (SELECT fc.id FROM ' . tbl('cities') . ' fc WHERE fc.country_id IN (' . $countryIn . '))' . $cityExclusionSql . ')';
                array_push($params, ...$countries);
                if ($excludedCities) array_push($params, ...$excludedCities);
                if ($cities) {
                    $cityIn = implode(',', array_fill(0, count($cities), '?'));
                    $sql .= ' OR a.city_id IN (' . $cityIn . ')';
                    array_push($params, ...$cities);
                }
                $sql .= ')';
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
