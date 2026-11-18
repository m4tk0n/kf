<?php
declare(strict_types=1);

/**
 * Authentication and authorization. The database is the only source of truth
 * for account state and role; session values are never used to grant access.
 */
function clear_authenticated_session(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function refresh_authenticated_user(): ?array
{
    $sessionUser = $_SESSION['user'] ?? null;
    if (!is_array($sessionUser) || empty($sessionUser['id'])) {
        return null;
    }

    $now = time();
    $created = (int)($_SESSION['auth_created_at'] ?? 0);
    $lastSeen = (int)($_SESSION['auth_last_seen_at'] ?? 0);
    if ($created <= 0 || $lastSeen <= 0 ||
        $now - $lastSeen > KF_SESSION_IDLE_TTL ||
        $now - $created > KF_SESSION_ABSOLUTE_TTL) {
        clear_authenticated_session();
        return null;
    }

    $users = tbl('users');
    $stmt = db()->prepare(
        "SELECT id, email, display_name, role, state, confirmed FROM {$users} WHERE id = ? LIMIT 1"
    );
    $stmt->execute([(int)$sessionUser['id']]);
    $fresh = $stmt->fetch();

    if (!$fresh) {
        clear_authenticated_session();
        return null;
    }

    $role = (int)$fresh['role'];
    $state = (int)$fresh['state'];
    $knownRoles = array_map('intval', array_keys(cfg('enums.role')));
    if (!in_array($role, $knownRoles, true) ||
        !in_array($state, [0, 1, 2], true) ||
        $state === 2 ||
        (int)$fresh['confirmed'] !== 1) {
        clear_authenticated_session();
        return null;
    }

    $name = trim((string)($fresh['display_name'] ?? ''));
    if ($name === '') {
        $name = (string)$fresh['email'];
    }

    // Keep presentation data server-side. Role/state are refreshed on every request.
    $_SESSION['user'] = [
        'id' => (int)$fresh['id'],
        'email' => (string)$fresh['email'],
        'name' => $name,
        'role' => $role,
        'state' => $state,
        'picture' => (string)($sessionUser['picture'] ?? ''),
    ];
    $_SESSION['auth_last_seen_at'] = $now;
    return $_SESSION['user'];
}

/**
 * Explicit permission matrix. A role number supplied by a client is never read.
 * New permissions must be added here deliberately.
 */
function user_has_permission(array $user, string $permission): bool
{
    $role = (int)($user['role'] ?? 0);
    $matrix = [
        1 => ['account.profile', 'account.settings'],
        2 => ['account.profile', 'account.settings', 'content.submit'],
        3 => ['account.profile', 'account.settings', 'content.submit', 'content.moderate'],
        4 => ['*'],
    ];
    $granted = $matrix[$role] ?? [];
    return in_array('*', $granted, true) || in_array($permission, $granted, true);
}

function require_permission(string $permission): array
{
    $user = refresh_authenticated_user();
    if (!$user || !user_has_permission($user, $permission)) {
        http_response_code(404);
        header('Cache-Control: no-store, private');
        exit('Not Found');
    }
    return $user;
}
