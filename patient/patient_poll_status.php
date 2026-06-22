<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../pgdb/db.php';

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($booking_id <= 0) {
  echo json_encode(['ok'=>false,'error'=>'missing booking_id']);
  exit;
}

$stmt = $pdo->prepare("
  SELECT booking_id, payment_status, driver_id
  FROM booking
  WHERE booking_id = :id
  LIMIT 1
");
$stmt->execute([':id'=>$booking_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  echo json_encode(['ok'=>false,'error'=>'not found']);
  exit;
}

echo json_encode(['ok'=>true,'data'=>$row]);