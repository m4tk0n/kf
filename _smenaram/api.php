<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

function apiResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = getDbConnection();
    $tableName = DB_PREFIX . 'smenaram';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($input) || empty($input['datum'])) {
            apiResponse(['status' => 'error', 'message' => 'Chybí datum nebo data nejsou platný JSON.'], 400);
        }

        $datum = (string)$input['datum'];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
        if (!$date || $date->format('Y-m-d') !== $datum) {
            apiResponse(['status' => 'error', 'message' => 'Neplatné datum.'], 400);
        }

        $normalizeShift = static function ($value): ?string {
            if ($value === null) return null;
            $value = trim((string)$value);
            return $value === '' ? null : substr($value, 0, 10);
        };

        $osoba1 = $normalizeShift($input['osoba1'] ?? null);
        $osoba2 = $normalizeShift($input['osoba2'] ?? null);
        $osoba3 = $normalizeShift($input['osoba3'] ?? null);

        if ($osoba1 === null && $osoba2 === null && $osoba3 === null) {
            $stmt = $pdo->prepare("DELETE FROM `{$tableName}` WHERE datum = :datum");
            $stmt->execute([':datum' => $datum]);
        } else {
            // Každý placeholder je unikátní: funguje i s vypnutou emulací PDO prepared statements.
            $sql = "INSERT INTO `{$tableName}` (datum, osoba1, osoba2, osoba3)
                    VALUES (:datum, :osoba1, :osoba2, :osoba3)
                    ON DUPLICATE KEY UPDATE
                        osoba1 = :update_osoba1,
                        osoba2 = :update_osoba2,
                        osoba3 = :update_osoba3";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':datum' => $datum,
                ':osoba1' => $osoba1,
                ':osoba2' => $osoba2,
                ':osoba3' => $osoba3,
                ':update_osoba1' => $osoba1,
                ':update_osoba2' => $osoba2,
                ':update_osoba3' => $osoba3,
            ]);
        }

        apiResponse(['status' => 'success']);
    }

    if ($method === 'GET') {
        $start = $_GET['start'] ?? null;
        $end = $_GET['end'] ?? null;

        if (!$start || !$end) {
            apiResponse(['status' => 'error', 'message' => 'Chybí počáteční nebo koncové datum.'], 400);
        }

        $stmt = $pdo->prepare(
            "SELECT datum, osoba1, osoba2, osoba3
             FROM `{$tableName}`
             WHERE datum BETWEEN :start AND :end"
        );
        $stmt->execute([':start' => $start, ':end' => $end]);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['datum']] = [
                'Osoba1' => $row['osoba1'],
                'Osoba2' => $row['osoba2'],
                'Osoba3' => $row['osoba3'],
            ];
        }

        apiResponse($result);
    }

    apiResponse(['status' => 'error', 'message' => 'Nepodporovaná metoda.'], 405);
} catch (Throwable $e) {
    error_log('[SmenarAM API] ' . $e->getMessage());
    apiResponse([
        'status' => 'error',
        'message' => 'Operaci se nepodařilo dokončit. Zkontroluj databázovou tabulku a její sloupce.',
    ], 500);
}
