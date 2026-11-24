<?php
// api.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $pdo = getDbConnection();
    $tableName = DB_PREFIX . 'smenaram';
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Chyba připojení k databázi']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// 1. Uložení / Úprava / Smazání směn pro dané datum
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['datum'])) {
        $datum  = $input['datum'];
        $osoba1 = !empty($input['osoba1']) ? trim($input['osoba1']) : null;
        $osoba2 = !empty($input['osoba2']) ? trim($input['osoba2']) : null;
        $osoba3 = !empty($input['osoba3']) ? trim($input['osoba3']) : null;

        // Pokud jsou všechny směny prázdné, řádek z databáze smažeme
        if ($osoba1 === null && $osoba2 === null && $osoba3 === null) {
            $stmt = $pdo->prepare("DELETE FROM `{$tableName}` WHERE datum = :datum");
            $stmt->execute([':datum' => $datum]);
        } else {
            // Jinak uložíme nebo aktualizujeme
            $sql = "INSERT INTO `{$tableName}` (datum, osoba1, osoba2, osoba3) 
                    VALUES (:datum, :osoba1, :osoba2, :osoba3)
                    ON DUPLICATE KEY UPDATE osoba1 = :osoba1, osoba2 = :osoba2, osoba3 = :osoba3";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':datum'  => $datum,
                ':osoba1' => $osoba1,
                ':osoba2' => $osoba2,
                ':osoba3' => $osoba3
            ]);
        }

        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Chybná data']);
    }
    exit;
}

// 2. Načtení směn pro rozsah dnů
if ($method === 'GET') {
    $start = $_GET['start'] ?? null;
    $end   = $_GET['end'] ?? null;

    if ($start && $end) {
        $stmt = $pdo->prepare("SELECT datum, osoba1, osoba2, osoba3 FROM `{$tableName}` WHERE datum BETWEEN :start AND :end");
        $stmt->execute([':start' => $start, ':end' => $end]);
        $rows = $stmt->fetchAll();

        // Převedení do formátu pro JavaScript
        $result = [];
        foreach ($rows as $row) {
            $result[$row['datum']] = [
                'Osoba1' => $row['osoba1'],
                'Osoba2' => $row['osoba2'],
                'Osoba3' => $row['osoba3']
            ];
        }

        echo json_encode($result);
        exit;
    }
}
?>
