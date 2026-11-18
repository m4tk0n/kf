<?php
declare(strict_types=1);

// Run daily from the hosting cron. Deletes only accounts whose 30-day
// grace period has expired. Database foreign-key rules should cascade related data.
require dirname(__DIR__) . '/src/bootstrap.php';

$users = tbl('users');
$stmt = db()->prepare("DELETE FROM {$users} WHERE deletion_scheduled_at IS NOT NULL AND deletion_scheduled_at <= NOW()");
$stmt->execute();
echo 'Deleted accounts: ' . $stmt->rowCount() . PHP_EOL;
