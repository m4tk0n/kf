<?php
declare(strict_types=1);

/**
 * Google OAuth 2.0 authorization-code flow.
 *
 * Google always returns to one language-independent callback URL. The
 * language selected when sign-in started is kept in the session and restored
 * after Google redirects back.
 */
function google_oauth_request(string $url, array $options = []): array
{
    $context = stream_context_create([
        'http' => $options + [
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);

    $body = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;

    foreach ($headers as $header) {
        if (preg_match('~^HTTP/\\S+\\s+(\\d{3})~', $header, $m)) {
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

        if ($action === 'start') {
            $state = bin2hex(random_bytes(32));
            $_SESSION['google_oauth_state'] = $state;
            $_SESSION['google_oauth_lang'] = lang_code();

            $query = http_build_query([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'openid email profile',
                'state' => $state,
                'prompt' => 'select_account',
            ]);

            header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query, true, 302);
            exit;
        }

        if ($action !== 'callback') {
            throw new RuntimeException('Unknown Google authentication action.');
        }

        if (isset($_GET['error'])) {
            unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_lang']);
            throw new RuntimeException('Google sign-in was cancelled or denied.');
        }

        $receivedState = (string)($_GET['state'] ?? '');
        $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
        unset($_SESSION['google_oauth_state']);

        if ($expectedState === '' || $receivedState === '' || !hash_equals($expectedState, $receivedState)) {
            unset($_SESSION['google_oauth_lang']);
            throw new RuntimeException('Google sign-in state validation failed.');
        }

        $returnLang = (string)($_SESSION['google_oauth_lang'] ?? cfg('app.default_lang'));
        unset($_SESSION['google_oauth_lang']);
        if (!in_array($returnLang, cfg('app.languages'), true)) {
            $returnLang = cfg('app.default_lang');
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            throw new RuntimeException('Google did not return an authorization code.');
        }

        $token = google_oauth_request('https://oauth2.googleapis.com/token', [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\\r\\nAccept: application/json\\r\\n",
            'content' => http_build_query([
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ]),
        ]);

        $accessToken = (string)($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Google did not issue an access token.');
        }

        $identity = google_oauth_request('https://openidconnect.googleapis.com/v1/userinfo', [
            'header' => "Authorization: Bearer {$accessToken}\\r\\nAccept: application/json\\r\\n",
        ]);

        if (empty($identity['sub']) || empty($identity['email']) || empty($identity['email_verified'])) {
            throw new RuntimeException('Google did not provide a verified email identity.');
        }

        session_regenerate_id(true);
        $_SESSION['google_pending_identity'] = [
            'provider' => 'google',
            'provider_id' => (string)$identity['sub'],
            'email' => (string)$identity['email'],
            'name' => (string)($identity['name'] ?? ''),
            'picture' => (string)($identity['picture'] ?? ''),
            'email_verified' => true,
        ];

        // Local account lookup/creation will be connected after confirming
        // the current users table schema; this is not a full login yet.
        header('Location: ' . url('login?google=verified', $returnLang), true, 302);
        exit;
    } catch (Throwable $e) {
        error_log('Google OAuth: ' . $e->getMessage());
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Přihlášení přes Google se nepodařilo dokončit. Zkuste to prosím znovu.';
        exit;
    }
}
