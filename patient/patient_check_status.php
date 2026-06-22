<?php
session_start();
require __DIR__ . '/../pgdb/db.php';
header('Content-Type: application/json; charset=utf-8');

function has_col(PDO $pdo, string $table, string $col): bool {
  $q = $pdo->prepare("
    SELECT 1 FROM information_schema.columns
    WHERE table_schema='public' AND table_name=:t AND column_name=:c LIMIT 1
  ");
  $q->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$q->fetchColumn();
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
$patient_id = (int)($_SESSION['ID'] ?? 0);

if ($booking_id <= 0) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"missing booking_id"]);
  exit;
}

try {
  $cols = ["booking_id"];

  if (has_col($pdo,'booking','payment_status')) $cols[]="payment_status";
  if (has_col($pdo,'booking','payment_method')) $cols[]="payment_method";
  if (has_col($pdo,'booking','payment_state'))  $cols[]="payment_state";
  if (has_col($pdo,'booking','payment_total'))  $cols[]="payment_total";
  if (has_col($pdo,'booking','distance_km'))    $cols[]="distance_km";
  if (has_col($pdo,'booking','driver_id'))      $cols[]="driver_id";
  if (has_col($pdo,'booking','provider_id'))    $cols[]="provider_id";

  $sql = "SELECT ".implode(",",$cols)." FROM booking WHERE booking_id=:bid";
  $params = [":bid"=>$booking_id];

  if ($patient_id > 0 && has_col($pdo,'booking','patient_id')) {
    $sql .= " AND patient_id=:pid";
    $params[":pid"] = $patient_id;
  }

  $sql .= " LIMIT 1";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    echo json_encode(["ok"=>false,"error"=>"booking not found"]);
    exit;
  }

  echo json_encode(["ok"=>true,"data"=>$row]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}