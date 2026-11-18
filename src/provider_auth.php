<?php
declare(strict_types=1);

/**
 * Resolves a verified external identity to a K&F account and starts a fresh session.
 * Email matching is used only for legacy Google-only accounts created by the old
 * Google login. Facebook identities are never silently merged by email.
 */
function complete_provider_login(
    string $provider,
    string $providerId,
    string $email,
    string $name,
    string $picture = ''
): never {
    if (!in_array($provider, ['google', 'facebook'], true) ||
        $providerId === '' || strlen($providerId) > 191) {
        throw new RuntimeException('Invalid provider identity.');
    }

    $email = mb_strtolower(trim($email), 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        throw new RuntimeException('The provider did not return a usable email address.');
    }

    $pdo = db();
    $users = tbl('users');
    $identities = tbl('user_identities');
    $pdo->beginTransaction();

    try {
        $findIdentity = $pdo->prepare(
            "SELECT u.id, u.email, u.display_name, u.role, u.state, u.confirmed
             FROM {$identities} i INNER JOIN {$users} u ON u.id = i.user_id
             WHERE i.provider = ? AND i.provider_id = ? LIMIT 1"
        );
        $findIdentity->execute([$provider, $providerId]);
        $user = $findIdentity->fetch();

        if (!$user) {
            $findEmail = $pdo->prepare(
                "SELECT id, email, display_name, role, state, confirmed FROM {$users} WHERE email = ? LIMIT 1"
            );
            $findEmail->execute([$email]);
            $existing = $findEmail->fetch();

            if ($existing) {
                if ($provider !== 'google') {
                    throw new RuntimeException('This email already belongs to an account. Sign in with its linked provider first.');
                }

                // Existing accounts were created through Google before identities were stored.
                // Bind only if the account has no recorded provider identity at all.
                $hasIdentity = $pdo->prepare("SELECT 1 FROM {$identities} WHERE user_id = ? LIMIT 1");
                $hasIdentity->execute([(int)$existing['id']]);
                if ($hasIdentity->fetchColumn()) {
                    throw new RuntimeException('This email is already linked to another sign-in identity.');
                }
                $user = $existing;
            } else {
                $safeName = mb_substr(trim($name), 0, 80, 'UTF-8');
                $insertUser = $pdo->prepare(
                    "INSERT INTO {$users} (email, display_name, role, state, confirmed) VALUES (?, ?, 1, 0, 1)"
                );
                $insertUser->execute([$email, $safeName !== '' ? $safeName : null]);
                $user = [
                    'id' => (int)$pdo->lastInsertId(),
                    'email' => $email,
                    'display_name' => $safeName,
                    'role' => 1,
                    'state' => 0,
                    'confirmed' => 1,
                ];
            }

            $insertIdentity = $pdo->prepare(
                "INSERT INTO {$identities} (user_id, provider, provider_id) VALUES (?, ?, ?)"
            );
            $insertIdentity->execute([(int)$user['id'], $provider, $providerId]);
        }

        if ((int)$user['state'] === 2 || (int)$user['confirmed'] !== 1) {
            throw new RuntimeException('This account is not permitted to sign in.');
        }

        $displayName = trim((string)($user['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim($name) !== '' ? mb_substr(trim($name), 0, 80, 'UTF-8') : $email;
            $pdo->prepare("UPDATE {$users} SET display_name = ? WHERE id = ?")
                ->execute([$displayName, (int)$user['id']]);
        }

        // A fresh session ID prevents session fixation. Only the user ID is needed.
        session_regenerate_id(true);
        $_SESSION = [
            'user' => [
                'id' => (int)$user['id'],
                'name' => $displayName,
                'picture' => preg_match('~^https://~i', $picture) ? $picture : '',
            ],
            'auth_created_at' => time(),
            'auth_last_seen_at' => time(),
            'csrf_token' => bin2hex(random_bytes(32)),
        ];
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    header('Cache-Control: no-store');
    header('Location: ' . url(''), true, 303);
    exit;
}

/**
 * Explicitly link an additional provider while the user is already authenticated.
 * The current K&F session proves ownership of the account; OAuth proves ownership
 * of the external identity. Email equality is not required for this operation.
 */
function complete_provider_link(string $provider, string $providerId, int $userId): never
{
    $current = refresh_authenticated_user();
    if (!$current || (int)$current['id'] !== $userId ||
        !in_array($provider, ['google', 'facebook'], true) ||
        $providerId === '' || strlen($providerId) > 191) {
        http_response_code(404);
        exit('Not Found');
    }

    $pdo = db();
    $identities = tbl('user_identities');
    $pdo->beginTransaction();
    try {
        $byIdentity = $pdo->prepare(
            "SELECT user_id FROM {$identities} WHERE provider = ? AND provider_id = ? LIMIT 1"
        );
        $byIdentity->execute([$provider, $providerId]);
        $owner = $byIdentity->fetchColumn();
        if ($owner !== false && (int)$owner !== $userId) {
            throw new RuntimeException('This external account is already linked to another K&F account.');
        }

        $byUser = $pdo->prepare(
            "SELECT provider_id FROM {$identities} WHERE user_id = ? AND provider = ? LIMIT 1"
        );
        $byUser->execute([$userId, $provider]);
        $linkedId = $byUser->fetchColumn();
        if ($linkedId !== false && !hash_equals((string)$linkedId, $providerId)) {
            throw new RuntimeException('A different account from this provider is already linked.');
        }

        if ($owner === false) {
            $insert = $pdo->prepare(
                "INSERT INTO {$identities} (user_id, provider, provider_id) VALUES (?, ?, ?)"
            );
            $insert->execute([$userId, $provider, $providerId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Cache-Control: no-store');
    header('Location: ' . url('settings'), true, 303);
    exit;
}
