<?php
// index.php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

send_security_headers(false);
header('Content-Type: text/html; charset=utf-8');

$req = kf_request();

$view = kf_load($req);
require abs_path((string)cfg('paths.templates')) . '/layout.php';

if (is_file(__DIR__ . '/cron/cron.php')) {
    require_once __DIR__ . '/cron/cron.php';
}
