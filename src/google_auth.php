<?php
declare(strict_types=1);

/** HTTPS-only Google OAuth/OIDC requests with bounded timeout. */
function google_oauth_request(string $url, array $options = []): array
{
    $context = stream_context_create([
        'http' => $options + ['timeout' => 15, 'ignore_errors' => true],
    ]);
    $body = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $m)) $status = (int)$m[1];
    }
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Google authentication request failed.');
    }
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Google returned an invalid response.');
    return $data;
}

function require_google_auth(string $action): never
{
    try {
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $redirectUri = rtrim(cfg('app.base_url'), '/') . '/auth/google/callback';

        if ($action === 'start') {
            // Do not start account linking/login while an authenticated session is active.
            if (refresh_authenticated_user()) {
                header('Location: ' . url(''), true, 303);
                exit;
            }
            $state = bin2hex(random_bytes(32));
            $nonce = bin2hex(random_bytes(32));
            $_SESSION['google_oauth_state'] = $state;
            $_SESSION['google_oauth_nonce'] = $nonce;
            $_SESSION['google_oauth_lang'] = lang_code();

            $query = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'nonce' => $nonce,
                'prompt' => 'select_account',
            ]);
            header('Cache-Control: no-store');
            header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query, true, 302);
            exit;
        }

        if ($action !== 'callback') throw new RuntimeException('Unknown Google authentication action.');

        $receivedState = (string)($_GET['state'] ?? '');
        $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
        $expectedNonce = (string)($_SESSION['google_oauth_nonce'] ?? '');
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_nonce']);

        if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)) {
            unset($_SESSION['google_oauth_lang']);
            throw new RuntimeException('Google sign-in state validation failed.');
        }

        $returnLang = (string)($_SESSION['google_oauth_lang'] ?? cfg('app.default_lang'));
        unset($_SESSION['google_oauth_lang']);
        if (!in_array($returnLang, cfg('app.languages'), true)) $returnLang = cfg('app.default_lang');

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') throw new RuntimeException('Google did not return an authorization code.');

        $token = google_oauth_request('https://oauth2.googleapis.com/token', [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => http_build_query([
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]),
        ]);

        $idToken = (string)($token['id_token'] ?? '');
        if ($idToken === '') throw new RuntimeException('Google did not issue an OpenID identity token.');

        // Google validates the JWT signature before returning its claims from tokeninfo.
        $identity = google_oauth_request(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken),
            ['header' => "Accept: application/json\r\n"]
        );
        $issuer = (string)($identity['iss'] ?? '');
        $expires = (int)($identity['exp'] ?? 0);
        $verifiedEmail = ($identity['email_verified'] ?? '') === true || ($identity['email_verified'] ?? '') === 'true';

        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true) ||
            !hash_equals($clientId, (string)($identity['aud'] ?? '')) ||
            $expires <= time() ||
            $expectedNonce === '' ||
            !hash_equals($expectedNonce, (string)($identity['nonce'] ?? '')) ||
            empty($identity['sub']) || empty($identity['email']) || !$verifiedEmail) {
            throw new RuntimeException('Google identity token validation failed.');
        }

        $email = mb_strtolower(trim((string)$identity['email']), 'UTF-8');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid verified email.');
        $name = trim((string)($identity['name'] ?? ''));
        $pdo = db();
        $users = tbl('users');

        $findUser = $pdo->prepare("SELECT id, email, role, state, confirmed FROM {$users} WHERE email = ? LIMIT 1");
        $findUser->execute([$email]);
        $user = $findUser->fetch();

        if (!$user) {
            $randomPassword = bin2hex(random_bytes(32));
            try {
                $insertUser = $pdo->prepare(
                    "INSERT INTO {$users} (email, pass, role, state, confirmed) VALUES (?, ?, 1, 0, 1)"
                );
                $insertUser->execute([$email, password_hash($randomPassword, PASSWORD_DEFAULT)]);
                $user = [
                    'id' => (int)$pdo->lastInsertId(), 'email' => $email,
                    'role' => 1, 'state' => 0, 'confirmed' => 1,
                ];
            } catch (PDOException $insertError) {
                $findUser->execute([$email]);
                $user = $findUser->fetch();
                if (!$user) throw $insertError;
            }
        } elseif ((int)$user['confirmed'] === 0) {
            $pdo->prepare("UPDATE {$users} SET confirmed = 1 WHERE id = ?")->execute([(int)$user['id']]);
            $user['confirmed'] = 1;
        }

        if ((int)$user['state'] === 2 || (int)$user['confirmed'] !== 1) {
            throw new RuntimeException('Account is not permitted to sign in.');
        }

        $displayName = $name !== '' ? $name : (string)$user['email'];
        try {
            $profileQuery = $pdo->prepare("SELECT display_name, deletion_scheduled_at FROM {$users} WHERE id = ? LIMIT 1");
            $profileQuery->execute([(int)$user['id']]);
            $profileData = $profileQuery->fetch(PDO::FETCH_ASSOC);
            if (is_array($profileData)) {
                if (trim((string)($profileData['display_name'] ?? '')) !== '') {
                    $displayName = trim((string)$profileData['display_name']);
                }
                if (!empty($profileData['deletion_scheduled_at'])) {
                    $pdo->prepare("UPDATE {$users} SET deletion_scheduled_at = NULL WHERE id = ?")->execute([(int)$user['id']]);
                }
            }
        } catch (PDOException $profileError) {
            // The optional profile migration may not yet be installed.
            error_log('K&F optional profile fields unavailable: ' . $profileError->getMessage());
        }

        // Prevent session fixation; rotate ID and discard any pre-authentication data.
        session_regenerate_id(true);
        $_SESSION = [
            'user' => [
                'id' => (int)$user['id'],
                'email' => (string)$user['email'],
                'name' => $displayName,
                'role' => (int)$user['role'],
                'state' => (int)$user['state'],
                'provider' => 'google',
                'provider_id' => (string)$identity['sub'],
                'picture' => (string)($identity['picture'] ?? ''),
            ],
            'auth_created_at' => time(),
            'auth_last_seen_at' => time(),
            'csrf_token' => bin2hex(random_bytes(32)),
        ];

        header('Cache-Control: no-store');
        header('Location: ' . url('', $returnLang), true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('Google OAuth: ' . $e->getMessage());
        http_response_code(400);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Přihlášení přes Google se nepodařilo dokončit. Zkuste to prosím znovu.';
        exit;
    }
}
