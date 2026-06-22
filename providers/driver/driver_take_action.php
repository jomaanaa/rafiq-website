<?php
require __DIR__ . '/../../pgdb/db.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$booking_id = (int)($_POST['booking_id'] ?? 0);
$driver_id  = (int)($_POST['driver_id'] ?? 0);

if ($booking_id <= 0 || $driver_id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'Invalid booking_id or driver_id']);
    exit();
}

try {
    if ($action === 'accept') {
        $stmt = $pdo->prepare("
            UPDATE booking
            SET provider_id = :driver_id,
                payment_status = 'accepted'
            WHERE booking_id = :id
              AND provider_id IS NULL
              AND payment_status = 'pending'
        ");
        $stmt->execute([':driver_id'=>$driver_id, ':id'=>$booking_id]);
        echo json_encode(['ok'=>true]);
        exit();
    }

    if ($action === 'reject') {
        $stmt = $pdo->prepare("
            UPDATE booking
            SET payment_status = 'rejected'
            WHERE booking_id = :id
              AND provider_id IS NULL
              AND payment_status = 'pending'
        ");
        $stmt->execute([':id'=>$booking_id]);
        echo json_encode(['ok'=>true]);
        exit();
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown action']);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}