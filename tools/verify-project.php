<?php
declare(strict_types=1);

/**
 * K&F repository consistency checks.
 * Run from repository root: php tools/verify-project.php
 *
 * This is a static source check. It does NOT claim to inspect the live server,
 * live database, FTP deployment, or browser behavior.
 */

$root = dirname(__DIR__);
$schemaFile = $root . '/install/sql/001_schema.sql';
$indexFile = $root . '/index.php';
$errors = [];

foreach ([$schemaFile, $indexFile] as $file) {
    if (!is_file($file) || !is_readable($file)) {
        $errors[] = 'Missing or unreadable file: ' . $file;
    }
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

$schema = file_get_contents($schemaFile);
$index = file_get_contents($indexFile);

function check(bool $condition, string $message, array &$errors): void
{
    if (!$condition) {
        $errors[] = $message;
    }
}

// pending table must contain only the approved searchable/core fields plus payload.
if (!preg_match('/CREATE TABLE IF NOT EXISTS \x60\{p\}pending\x60\s*\((.*?)\) ENGINE=/is', $schema, $match)) {
    $errors[] = 'Could not parse the pending table definition.';
} else {
    $pendingDefinition = $match[1];
    foreach (['name', 'country_id', 'city', 'payload'] as $column) {
        check((bool)preg_match('/\x60' . preg_quote($column, '/') . '\x60\s+/i', $pendingDefinition),
            'pending is missing expected column: ' . $column, $errors);
    }
    foreach (['address', 'phone', 'latitude', 'longitude', 'description'] as $column) {
        check(!(bool)preg_match('/\x60' . preg_quote($column, '/') . '\x60\s+/i', $pendingDefinition),
            'pending must not contain detail column: ' . $column, $errors);
    }
}

// Venue submission stores details in JSON and inserts only core columns.
check(str_contains($index, "'address' => \$address !== '' ? \$address : null"),
    'Venue address is not written into JSON payload.', $errors);
check(str_contains($index, "'phone' => \$phone !== '' ? \$phone : null"),
    'Venue phone is not written into JSON payload.', $errors);
check(str_contains($index, "'description' => \$description"),
    'Venue description is not written into JSON payload.', $errors);
check(str_contains("'gps' => \$latitude"),
    'Internal verifier sanity check failed.', $errors);
check(str_contains($index, "'gps' => \$latitude"),
    'Venue GPS is not written into JSON payload.', $errors);
check(str_contains($index, "'images' => \$imageUrls"),
    'Venue image metadata is not written into JSON payload.', $errors);
check(str_contains($index, '(type, dupkey, user_id, name, country_id, city, payload) VALUES'),
    'Venue INSERT does not match the approved core DB fields.', $errors);
check(!str_contains($index, 'city, address, phone, payload'),
    'Venue INSERT still references address/phone database columns.', $errors);
check(!str_contains($index, "\$venueFields['address']") && !str_contains($index, "\$venueFields['phone']"),
    'Venue INSERT still binds address/phone database values.', $errors);

if ($errors) {
    fwrite(STDERR, "K&F verification failed:" . PHP_EOL . '- ' . implode(PHP_EOL . '- ', $errors) . PHP_EOL);
    exit(1);
}

echo "K&F static verification passed." . PHP_EOL;
echo "Checked: pending schema, venue JSON payload, and venue INSERT fields." . PHP_EOL;
echo "Not checked: live database, FTP deployment, and browser/runtime behavior." . PHP_EOL;
