<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

$_SERVER['HTTP_USER_AGENT'] = 'KF automated integration test';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_ORIGIN'] = 'http://127.0.0.1';

$action = (string)($argv[1] ?? '');
$token = (string)($argv[2] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    fwrite(STDERR, "Invalid test session token\n");
    exit(2);
}
$_COOKIE[(string)cfg('session.name')] = $token;

switch ($action) {
    case 'event_submit':
        $_POST = [];
        break;
    case 'review_approve':
    case 'review_reject':
        $_POST = ['id' => (string)($argv[3] ?? ''), 'action' => $action === 'review_approve' ? 'approve' : 'reject'];
        break;
    case 'my_delete':
        $_POST = ['id' => (string)($argv[3] ?? '')];
        break;
    default:
        fwrite(STDERR, "Unknown integration action\\n");
        exit(2);
}
$_POST[(string)cfg('security.csrf_field')] = csrf_token();
csrf_verify();

switch ($action) {
    case 'event_submit':
        route_event_submit();
    case 'review_approve':
        $_POST = ['id' => (string)($argv[3] ?? ''), 'action' => 'approve'];
        route_review_post();
    case 'review_reject':
        $_POST = ['id' => (string)($argv[3] ?? ''), 'action' => 'reject'];
        route_review_post();
    case 'my_delete':
        $_POST = ['id' => (string)($argv[3] ?? '')];
        route_my_delete();
    default:
        fwrite(STDERR, "Unknown integration action\n");
        exit(2);
}
