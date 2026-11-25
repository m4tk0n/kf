<?php
// src/filters/profile.php
declare(strict_types=1);

// Trvalé nastavení filtrů v JSON profilu uživatele.

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
    $profileChanged = false;
    foreach ((array)cfg('filters.sections') as $section => $fields) {
        foreach ($fields as $field) {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array)($p['filters'][$section][$field] ?? [])),
                static fn(int $id): bool => $id > 0
            )));

            // Migrace starého samostatného výběru států na konkrétní města.
            // Dříve uložený stát znamenal všechna města státu; nově je pouze souhrnným checkboxem.
            if ($field === 'cities') {
                $legacyCountries = array_values(array_unique(array_filter(
                    array_map('intval', (array)($p['filters'][$section]['countries'] ?? [])),
                    static fn(int $id): bool => $id > 0
                )));
                if ($legacyCountries) {
                    foreach (filters_options((string)$section, 'cities') as $city) {
                        if (in_array((int)$city['country_id'], $legacyCountries, true)) {
                            $ids[] = (int)$city['id'];
                        }
                    }
                    $ids = array_values(array_unique($ids));
                    sort($ids);
                    unset($p['filters'][$section]['countries']);
                    $profileChanged = true;
                }
            }
            $sets[$section][$field] = $ids;
        }
    }
    if ($profileChanged) {
        user_profile_put($uid, $p);
    }
    return ['show_all' => (bool)$p['show_filtr_all'], 'none' => !empty($p['filters_none']), 'sets' => $sets];
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
