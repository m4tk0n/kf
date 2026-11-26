<?php
// debug/test_profiles.php
declare(strict_types=1);

/**
 * Vytvoří nebo obnoví pět testovacích profilů.
 * Volá se pouze samostatnou akcí instalátoru; vyžaduje existující hlavní účet ID 2.
 *
 * @return array{ok:bool,count:int,message:string}
 */
function debug_test_profiles_create(): array
{
    $pdo = db();
    $owner = q('SELECT id FROM ' . tbl('users') . ' WHERE id = 2')->fetchColumn();
    if (!$owner) {
        throw new RuntimeException('Nejdříve vytvoř a přihlas hlavní účet (user ID 2). Testovací profily zatím nelze vytvořit.');
    }

    $path = KF_ROOT . '/debug/test_profiles.sql';
    $sql = @file_get_contents($path);
    if (!is_string($sql) || trim($sql) === '') {
        throw new RuntimeException('Chybí SQL soubor debug/test_profiles.sql.');
    }

    $prefix = (string)cfg('db.prefix');
    if ($prefix === '' || !preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        throw new RuntimeException('Neplatný prefix databázových tabulek.');
    }
    $sql = str_replace('{prefix}', $prefix, $sql);
    if (str_contains($sql, '{prefix}')) {
        throw new RuntimeException('Nepodařilo se doplnit prefix databázové tabulky.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $count = (int)q(
            'SELECT COUNT(*) FROM ' . tbl('users') . " WHERE provider = 3 AND provider_uid IN
             ('kf_test_user', 'kf_test_blocked', 'kf_test_trusted', 'kf_test_moderator', 'kf_test_admin')"
        )->fetchColumn();
        if ($count !== 5) {
            throw new RuntimeException('V databázi není všech pět testovacích profilů.');
        }
        $pdo->commit();
        security_log('debug_test_profiles_created', ['count' => $count]);
        return ['ok' => true, 'count' => $count, 'message' => 'Testovací profily jsou připravené.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
