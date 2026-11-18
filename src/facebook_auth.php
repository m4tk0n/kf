<?php
declare(strict_types=1);

function facebook_oauth_enabled(): bool
{
    return trim((string)env_optional('FACEBOOK_APP_ID')) !== '' &&
        trim((string)env_optional('FACEBOOK_APP_SECRET')) !== '';
}

function facebook_oauth_request(string $url): array
{
    $context = stream_context_create([
        'http' => ['timeout' => 15, 'ignore_errors' => true],
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
        throw new RuntimeException('Facebook authentication request failed.');
    }
    $data = json_decode($body, true);
    if (!is_array($data) || isset($data['error'])) {
        throw new RuntimeException('Facebook returned an invalid response.');
    }
    return $data;
}

function require_facebook_auth(string $action): never
{
    try {
        $appId = trim((string)env_optional('FACEBOOK_APP_ID'));
        $appSecret = trim((string)env_optional('FACEBOOK_APP_SECRET'));
        if ($appId === '' || $appSecret === '') {
            http_response_code(404);
            exit('Not Found');
        }
        $redirectUri = rtrim(cfg('app.base_url'), '/') . '/auth/facebook/callback';

        if ($action === 'start') {
            if (refresh_authenticated_user()) {
                header('Location: ' . url(''), true, 303);
                exit;
            }
            $state = bin2hex(random_bytes(32));
            $_SESSION['facebook_oauth_state'] = $state;
            $query = http_build_query([
                'client_id' => $appId,
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => 'email,public_profile',
                'state' => $state,
            ]);
            header('Cache-Control: no-store');
            header('Location: https://www.facebook.com/dialog/oauth?' . $query, true, 302);
            exit;
        }

        if ($action !== 'callback') {
            throw new RuntimeException('Unknown Facebook authentication action.');
        }
        if (isset($_GET['error'])) {
            throw new RuntimeException('Facebook authorization was cancelled or denied.');
        }

        $state = (string)($_GET['state'] ?? '');
        $expectedState = (string)($_SESSION['facebook_oauth_state'] ?? '');
        unset($_SESSION['facebook_oauth_state']);
        if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new RuntimeException('Facebook sign-in state validation failed.');
        }

        $code = (string)($_GET['code'] ?? '');
        if ($code === '') {
            throw new RuntimeException('Facebook did not return an authorization code.');
        }

        $tokenUrl = 'https://graph.facebook.com/oauth/access_token?' . http_build_query([
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);
        $token = facebook_oauth_request($tokenUrl);
        $accessToken = (string)($token['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Facebook did not issue an access token.');
        }

        // Validate that the access token belongs to this exact Meta application.
        $appToken = $appId . '|' . $appSecret;
        $debug = facebook_oauth_request('https://graph.facebook.com/debug_token?' . http_build_query([
            'input_token' => $accessToken,
            'access_token' => $appToken,
        ]));
        $tokenData = $debug['data'] ?? [];
        if (!is_array($tokenData) ||
            empty($tokenData['is_valid']) ||
            !hash_equals($appId, (string)($tokenData['app_id'] ?? '')) ||
            empty($tokenData['user_id']) ||
            (int)($tokenData['expires_at'] ?? 0) <= time()) {
            throw new RuntimeException('Facebook access token validation failed.');
        }

        $profile = facebook_oauth_request('https://graph.facebook.com/me?' . http_build_query([
            'fields' => 'id,name,email,picture.type(large)',
            'access_token' => $accessToken,
        ]));
        if (empty($profile['id']) ||
            !hash_equals((string)$tokenData['user_id'], (string)$profile['id']) ||
            empty($profile['email'])) {
            throw new RuntimeException('Facebook did not provide a verified account identity and email.');
        }

        $picture = (string)($profile['picture']['data']['url'] ?? '');
        complete_provider_login(
            'facebook',
            (string)$profile['id'],
            (string)$profile['email'],
            (string)($profile['name'] ?? ''),
            $picture
        );
    } catch (Throwable $e) {
        error_log('Facebook OAuth: ' . $e->getMessage());
        http_response_code(400);
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Přihlášení přes Facebook se nepodařilo dokončit. Zkuste to prosím znovu.';
        exit;
    }
}
