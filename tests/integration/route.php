<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

// V integračním běhu zobrazit fatální chyby z izolovaných route procesů.
ini_set('display_errors', '1');
ini_set('log_errors', '0');
error_reporting(E_ALL);

$_SERVER['HTTP_USER_AGENT'] = 'KF automated integration test';

$action = (string)($argv[1] ?? '');
$token = (string)($argv[2] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    fwrite(STDERR, "Invalid test session token\n");
    exit(2);
}
$_COOKIE[(string)cfg('session.name')] = $token;

$postActions = ['event_submit', 'band_submit', 'review_approve', 'review_reject', 'my_delete'];
$isPost = in_array($action, $postActions, true);
$_SERVER['REQUEST_METHOD'] = $isPost ? 'POST' : 'GET';

if ($action === 'event_name_suggest' || $action === 'venue_name_suggest') {
    $_GET['q'] = (string)($argv[3] ?? '');
}

if ($isPost) {
    $_SERVER['HTTP_ORIGIN'] = 'http://127.0.0.1';
    if ($action === 'review_approve' || $action === 'review_reject') {
        $_POST = [
            'id' => (string)($argv[3] ?? ''),
            'action' => $action === 'review_approve' ? 'approve' : 'reject',
        ];
    } elseif ($action === 'my_delete') {
        $_POST = ['id' => (string)($argv[3] ?? '')];
    } else {
        $_POST = [];
    }
    $_POST[(string)cfg('security.csrf_field')] = csrf_token();
    csrf_verify();
}

switch ($action) {
    case 'event_form':
        route_event_new();
    case 'band_form':
        route_band_new();
    case 'band_preview':
        route_band_preview();
    case 'band_submit':
        route_band_submit();
    case 'event_name_suggest':
        route_event_name_suggest();
    case 'venue_name_suggest':
        route_venue_name_suggest();
    case 'event_preview':
        route_event_preview();
    case 'event_submit':
        route_event_submit();
    case 'review_approve':
        route_review_post();
    case 'review_reject':
        route_review_post();
    case 'my_delete':
        route_my_delete();
    default:
        fwrite(STDERR, "Unknown integration action\n");
        exit(2);
}
