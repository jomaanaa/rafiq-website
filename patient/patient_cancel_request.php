<?php
session_start();
require __DIR__ . '/../pgdb/db.php';
header('Content-Type: application/json; charset=utf-8');

$booking_id = (int)($_POST['booking_id'] ?? 0);
$patient_id = (int)($_SESSION['ID'] ?? 0);

if ($booking_id <= 0) { http_response_code(400); echo json_encode(["ok"=>false,"error"=>"missing booking_id"]); exit; }
if ($patient_id <= 0) { http_response_code(403); echo json_encode(["ok"=>false,"error"=>"not logged in"]); exit; }

try{
  // works with driver_id or provider_id
  $driverCol = null;
  $q = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='booking' AND column_name IN ('driver_id','provider_id')");
  $cols = $q->fetchAll(PDO::FETCH_COLUMN);
  if (in_array('driver_id',$cols,true)) $driverCol='driver_id';
  elseif (in_array('provider_id',$cols,true)) $driverCol='provider_id';
  else throw new Exception("No driver column");

  $stmt=$pdo->prepare("
    DELETE FROM booking
    WHERE booking_id=:bid
      AND patient_id=:pid
      AND payment_status='pending'
      AND {$driverCol} IS NULL
  ");
  $stmt->execute([":bid"=>$booking_id, ":pid"=>$patient_id]);

  echo json_encode(["ok"=>true,"deleted"=>$stmt->rowCount()]);
}catch(Exception $e){
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}