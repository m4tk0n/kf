<?php
// src/filters/session.php
declare(strict_types=1);

// Dočasný stav filtrů pro aktuální požadavek a sessionStorage.

/**
 * Vrátí filtry platné pro aktuální požadavek.
 * Dočasný JSON z kf_filters je pouze transportní kopie sessionStorage pro AJAX;
 * nikdy se z něj nic nezapisuje do profilu. ID se znovu ověřují proti aktuálním možnostem.
 *
 * @return array{show_all:bool,none:bool,sets:array<string,array<string,int[]>>}
 */
function filters_effective(string $kind = ''): array
{
    $user = auth_resolve();
    $base = $user !== null
        ? filters_get((int)$user['id'])
        : ['show_all' => true, 'none' => false, 'sets' => []];

    // Dočasný stav se smí použít jen při načítání obsahu výpisu nebo kalendáře.
    // Běžné načtení stránky vždy vychází z JSON profilu; zejména stránka user_menu_filter
    // nesmí být ovlivněna ručně přidaným parametrem ani transportní kopií sessionStorage.
    $isPartialList = isset($_GET['partial']) && (string)$_GET['partial'] === '1';
    $isCalendar = isset($_GET['cal']) && is_string($_GET['cal']);
    if (!$isPartialList && !$isCalendar) {
        return $base;
    }

    $raw = $_GET['kf_filters'] ?? null;
    if (!is_string($raw) || $raw === '' || strlen($raw) > 12000) {
        return $base;
    }
    $input = json_decode($raw, true);
    if (!is_array($input) || (int)($input['v'] ?? 0) !== 1 || !is_array($input['sets'] ?? null)) {
        return $base;
    }

    $showAll = !empty($input['show_all']);
    if ($showAll) {
        return ['show_all' => true, 'none' => false, 'sets' => []];
    }
    $sets = [];
    $hasSelection = false;
    foreach ((array)cfg('filters.sections') as $section => $fields) {
        // Ověřují se pouze hodnoty entity, jejíž výpis se právě načítá.
        if ($kind !== '' && $section !== $kind) continue;
        foreach ($fields as $field) {
            $rawIds = $input['sets'][$section][$field] ?? [];
            if (!is_array($rawIds)) $rawIds = [];
            $ids = array_values(array_unique(array_filter(
                array_map(static fn($v): int => is_scalar($v) ? (int)$v : 0, $rawIds),
                static fn(int $id): bool => $id > 0
            )));
            $ids = array_slice($ids, 0, max(1, (int)cfg('filters.max_per_type')));
            sort($ids);
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
