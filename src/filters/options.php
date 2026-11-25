<?php
// src/filters/options.php
declare(strict_types=1);

// Nabídka hodnot dostupných v publikovaných záznamech konkrétní entity.

/**
 * Dostupné hodnoty se odvozují výhradně z publikovaných záznamů dané sekce.
 * Země a města jsou přes vazbu city_id; žánry pouze z kapel, které je používají.
 *
 * @return array<int,array<string,int|string>>
 */
function filters_options(string $section, string $field): array
{
    $allowed = (array)cfg('filters.sections.' . $section, []);
    if (!in_array($field, $allowed, true)) {
        return [];
    }

    $table = tbl($section);
    $countryName = 'co.`name_' . current_lang() . '`';
    if ($field === 'countries') {
        return q(
            'SELECT DISTINCT co.id, ' . $countryName . ' AS name'
            . ' FROM ' . $table . ' a'
            . ' JOIN ' . tbl('cities') . ' c ON c.id = a.city_id'
            . ' JOIN ' . tbl('countries') . ' co ON co.id = c.country_id'
            . ' ORDER BY name'
        )->fetchAll();
    }

    if ($field === 'cities') {
        return q(
            'SELECT DISTINCT c.id, c.name, co.id AS country_id, ' . $countryName . ' AS country_name'
            . ' FROM ' . $table . ' a'
            . ' JOIN ' . tbl('cities') . ' c ON c.id = a.city_id'
            . ' JOIN ' . tbl('countries') . ' co ON co.id = c.country_id'
            . ' ORDER BY country_name, c.name'
        )->fetchAll();
    }

    if ($section === 'bands' && $field === 'genres') {
        return q(
            'SELECT DISTINCT g.id, g.name'
            . ' FROM ' . tbl('bands') . ' a'
            . ' JOIN ' . tbl('genres') . ' g ON g.id = a.genre_id'
            . ' ORDER BY g.name'
        )->fetchAll();
    }

    return [];
}
