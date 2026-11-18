<?php
declare(strict_types=1);

function google_oauth_request(string $url, array $options = []): array
{
    $context = stream_context_create([
        'http' => $options + ['timeout' => 15, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $m)) {
            $status = (int)$m[1];
        }
    }
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Google authentication request failed.');
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Google returned an invalid response.');
    }
    return $data;
}

function require_google_auth(string $action): never
{
    try {
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $redirectUri = rtrim(cfg('app.base_url'), '/') . '/auth/google/callback';

        if ($action === 'start' || $action === 'link_start') {
            $currentUser = refresh_authenticated_user();
            if ($action === 'start' && $currentUser) {
                header('Location: ' . url(''), true, 303);
                exit;
            }
            if ($action === 'link_start' && !$currentUser) {
                http_response_code(404);
                exit('Not Found');
            }
            if ($action === 'link_start') {
                $_SESSION['google_oauth_link_user_id'] = (int)$currentUser['id'];
            } else {
                unset($_SESSION['google_oauth_link_user_id']);
            }
            $state = bin2hex(random_bytes(32));
            $nonce = bin2hex(random_bytes(32));
            $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
            $_SESSION['google_oauth_state'] = $state;
            $_SESSION['google_oauth_nonce'] = $nonce;
            $_SESSION['google_oauth_verifier'] = $verifier;

            $query = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'nonce' => $nonce,
                'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
                'code_challenge_method' => 'S256',
                'prompt' => 'select_account',
            ]);
            header('Cache-Control: no-store');
            header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query, true, 302);
            exit;
        }

        if ($action !== 'callback') {
            throw new RuntimeException('Unknown Google authentication action.');
        }
        if (isset($_GET['error'])) {
            throw new RuntimeException('Google authorization was cancelled or denied.');
        }

        $receivedState = (string)($_GET['state'] ?? '');
        $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
        $expectedNonce = (string)($_SESSION['google_oauth_nonce'] ?? '');
        $verifier = (string)($_SESSION['google_oauth_verifier'] ?? '');
        unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_nonce'], $_SESSION['google_oauth_verifier']);

        if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState) ||
            $expectedNonce === '' || $verifier === '') {
            throw new RuntimeException('Google sign-in state validation failed.');
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            throw new RuntimeException('Google did not return an authorization code.');
        }

        $token = google_oauth_request('https://oauth2.googleapis.com/token', [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'content' => http_build_query([
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'code_verifier' => $verifier,
            ]),
        ]);
        $idToken = (string)($token['id_token'] ?? '');
        if ($idToken === '') {
            throw new RuntimeException('Google did not issue an OpenID identity token.');
        }

        // Google's tokeninfo endpoint validates the signed ID token and returns its claims.
        $identity = google_oauth_request(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken),
            ['header' => "Accept: application/json\r\n"]
        );
        $issuer = (string)($identity['iss'] ?? '');
        $expires = (int)($identity['exp'] ?? 0);
        $issued = (int)($identity['iat'] ?? 0);
        $verifiedEmail = ($identity['email_verified'] ?? '') === true ||
            ($identity['email_verified'] ?? '') === 'true';

        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true) ||
            !hash_equals($clientId, (string)($identity['aud'] ?? '')) ||
            (isset($identity['azp']) && !hash_equals($clientId, (string)$identity['azp'])) ||
            $expires <= time() || $issued <= 0 || $issued > time() + 60 ||
            time() - $issued > 3600 ||
            !hash_equals($expectedNonce, (string)($identity['nonce'] ?? '')) ||
            empty($identity['sub']) || empty($identity['email']) || !$verifiedEmail) {
            throw new RuntimeException('Google identity token validation failed.');
        }

        $linkUserId = (int)($_SESSION['google_oauth_link_user_id'] ?? 0);
        unset($_SESSION['google_oauth_link_user_id']);
        if ($linkUserId > 0) {
            complete_provider_link('google', (string)$identity['sub'], $linkUserId);
        }
        complete_provider_login(
            'google',
            (string)$identity['sub'],
            (string)$identity['email'],
            (string)($identity['name'] ?? ''),
            (string)($identity['picture'] ?? '')
        );
    } catch (Throwable $e) {
        unset(
            $_SESSION['google_oauth_state'],
            $_SESSION['google_oauth_nonce'],
            $_SESSION['google_oauth_verifier'],
            $_SESSION['google_oauth_link_user_id']
        );
        error_log('Google OAuth: ' . $e->getMessage());
        http_response_code(400);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Přihlášení přes Google se nepodařilo dokončit. Zkuste to prosím znovu.';
        exit;
    }
}
