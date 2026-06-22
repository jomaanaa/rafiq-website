<?php
session_start();
header('Content-Type: application/json');

function get_patient_id(): int {
    if (!empty($_SESSION['user_id']))    return (int)$_SESSION['user_id'];
    if (!empty($_SESSION['patient_id'])) return (int)$_SESSION['patient_id'];
    if (!empty($_SESSION['ID']))         return (int)$_SESSION['ID'];
    return 0;
}

$patient_id = get_patient_id();
if ($patient_id <= 0) {
    echo json_encode(['bookings' => []]);
    exit;
}

try {
    require __DIR__ . '/../pgdb/db.php';
    $stmt = $pdo->prepare("
        SELECT booking_id, status, service_type
        FROM booking
        WHERE patient_id = :pid
          AND status NOT IN ('completed','declined','cancelled')
        ORDER BY booking_id DESC
        LIMIT 20
    ");
    $stmt->execute([':pid' => $patient_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['bookings' => $rows]);
} catch (Exception $e) {
    echo json_encode(['bookings' => [], 'error' => $e->getMessage()]);
}
