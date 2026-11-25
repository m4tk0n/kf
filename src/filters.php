<?php
// src/filters.php
declare(strict_types=1);

// Veškerá logika filtrů: JSON profil, validace, možnosti a SQL podmínky.

/** @return array{show_all:bool,sets:array<string,array<string,int[]>>} */
function filters_get(int $uid): array
{
    $p = user_profile_get($uid);
    // Migrace starších profilů: bez vlastního výběru se zobrazují všechny záznamy.
    if (!array_key_exists('show_filtr_all', $p)) {
        // Převod případných starších uložených voleb bez ztráty uživatelského nastavení.
        $legacyHasSelection = false;
        if (!array_key_exists('filters_enabled', $p) || !empty($p['filters_enabled'])) {
            foreach ((array)($p['filters'] ?? []) as $fields) {
                foreach ((array)$fields as $ids) {
                    if (is_array($ids) && $ids) {
                        $legacyHasSelection = true;
                        break 2;
                    }
                }
            }
        }
        $p['show_filtr_all'] = !$legacyHasSelection;
        unset($p['filters_enabled']);
        if ($p['show_filtr_all']) {
            unset($p['filters']);
        }
        user_profile_put($uid, $p);
    }

    $sets = [];
    foreach ((array)cfg('filters.sections') as $section => $fields) {
        foreach ($fields as $field) {
            $sets[$section][$field] = array_values(array_unique(array_filter(
                array_map('intval', (array)($p['filters'][$section][$field] ?? [])),
                static fn(int $id): bool => $id > 0
            )));
        }
    }
    return ['show_all' => (bool)$p['show_filtr_all'], 'none' => !empty($p['filters_none']), 'sets' => $sets];
}

/**
 * Vrátí filtry platné pro aktuální požadavek.
 * Dočasný JSON z kf_filters je pouze transportní kopie sessionStorage pro AJAX;
 * nikdy se z něj nic nezapisuje do profilu. ID se znovu ověřují proti aktuálním možnostem.
 *
 * @return array{show_all:bool,none:bool,sets:array<string,array<string,int[]>>}
 */
function filters_effective(): array
{
    $user = auth_resolve();
    $base = $user !== null
        ? filters_get((int)$user['id'])
        : ['show_all' => true, 'none' => false, 'sets' => []];

    $raw = $_GET['kf_filters'] ?? null;
    if (!is_string($raw) || $raw === '' || strlen($raw) > 12000) {
        return $base;
    }
    $input = json_decode($raw, true);
    if (!is_array($input) || (int)($input['v'] ?? 0) !== 1 || !is_array($input['sets'] ?? null)) {
        return $base;
    }

    $showAll = !empty($input['show_all']);
    $sets = [];
    $hasSelection = false;
    foreach ((array)cfg('filters.sections') as $section => $fields) {
        foreach ($fields as $field) {
            $rawIds = $input['sets'][$section][$field] ?? [];
            if (!is_array($rawIds)) $rawIds = [];
            $ids = array_values(array_unique(array_filter(
                array_map(static fn($v): int => is_scalar($v) ? (int)$v : 0, $rawIds),
                static fn(int $id): bool => $id > 0
            )));
            $ids = array_slice($ids, 0, max(1, (int)cfg('filters.max_per_type')));
            if ($ids) {
                $available = filters_options((string)$section, (string)$field);
                $allowed = array_fill_keys(array_map(static fn(array $row): int => (int)$row['id'], $available), true);
                $ids = array_values(array_filter($ids, static fn(int $id): bool => isset($allowed[$id])));
                sort($ids);
            }
            $sets[$section][$field] = $ids;
            if ($ids) $hasSelection = true;
        }
    }

    return [
        'show_all' => $showAll,
        'none' => !$showAll && !$hasSelection,
        'sets' => $sets,
    ];
}

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
    $countryName = 'co.`name_' . kf_lang() . '`';
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

/** Uloží vlastní výběr. Pokud jsou vybrány všechny dostupné možnosti, uloží se jen show_filtr_all=true. */
function filters_save(int $uid, array $input): void
{
    $p = user_profile_get($uid);
    $showAll = !empty($input['show_filtr_all']);
    $saved = [];
    $hasSelection = false;

    foreach ((array)cfg('filters.sections') as $section => $fields) {
        foreach ($fields as $field) {
            $available = filters_options((string)$section, (string)$field);
            $allowedIds = array_fill_keys(array_map(
                static fn(array $row): int => (int)$row['id'],
                $available
            ), true);

            $raw = $input['filters'][$section][$field] ?? [];
            if (!is_array($raw)) $raw = [];
            $ids = array_values(array_unique(array_filter(
                array_map(static fn($value): int => is_scalar($value) ? (int)$value : 0, $raw),
                static fn(int $id): bool => $id > 0
            )));
            $ids = array_values(array_filter($ids, static fn(int $id): bool => isset($allowedIds[$id])));
            sort($ids);

            $saved[$section][$field] = $ids;
            if ($ids) {
                $hasSelection = true;
            }
        }
    }

    // Stav checkboxu se ukládá přesně podle odeslané hodnoty.
    // Prázdný vlastní výběr je samostatný stav (nezobrazovat žádné záznamy).
    $p['show_filtr_all'] = $showAll;
    if ($showAll) {
        unset($p['filters'], $p['filters_none']);
    } else {
        $p['filters'] = $saved;
        $p['filters_none'] = !$hasSelection;
    }
    unset($p['filters_enabled']);
    user_profile_put($uid, $p);
}


/** Vrátí SQL podmínky pro katalog; hledání a oblíbené zůstávají v catalog.php. */
function filters_catalog_conditions(string $kind): array
{
    if (!in_array($kind, ['events', 'bands', 'venues'], true)) throw new InvalidArgumentException('kind');
    $sql = ''; $params = [];
    $uFilters = filters_effective();
    if (!$uFilters['show_all']) {
        if (!empty($uFilters['none'])) $sql .= ' AND 1=0';
        $sets = $uFilters['sets'][$kind] ?? [];
        $countries = array_values(array_filter(array_map('intval', $sets['countries'] ?? []), static fn(int $id): bool => $id > 0));
        if ($countries) {
            $in = implode(',', array_fill(0, count($countries), '?'));
            $sql .= ' AND a.city_id IN (SELECT fc.id FROM ' . tbl('cities') . ' fc WHERE fc.country_id IN (' . $in . '))';
            array_push($params, ...$countries);
        }
        if (!$countries) {
            $cities = array_values(array_filter(array_map('intval', $sets['cities'] ?? []), static fn(int $id): bool => $id > 0));
            if ($cities) {
                $in = implode(',', array_fill(0, count($cities), '?'));
                $sql .= ' AND a.city_id IN (' . $in . ')';
                array_push($params, ...$cities);
            }
        }
        if ($kind === 'bands') {
            $genres = array_values(array_filter(array_map('intval', $sets['genres'] ?? []), static fn(int $id): bool => $id > 0));
            if ($genres) {
                $in = implode(',', array_fill(0, count($genres), '?'));
                $sql .= ' AND a.genre_id IN (' . $in . ')';
                array_push($params, ...$genres);
            }
        }
    }
    return [$sql, $params];
}
