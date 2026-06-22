<?php
require __DIR__ . '/../pgdb/db.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT place_id, name, type, address, latitude, longitude
        FROM public.place
        WHERE status = 'active'
          AND (
            name    ILIKE :q
            OR type ILIKE :q
            OR address ILIKE :q
          )
        ORDER BY
            CASE WHEN name ILIKE :starts THEN 0 ELSE 1 END,
            name
        LIMIT 8
    ");
    $like   = '%' . $q . '%';
    $starts = $q . '%';
    $stmt->execute([':q' => $like, ':starts' => $starts]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([]);
}
