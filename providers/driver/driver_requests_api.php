<?php
require __DIR__ . '/../../pgdb/db.php';
header('Content-Type: application/json');

try {
  $stmt = $pdo->query("
    SELECT
      booking_id,
      COALESCE(date::text, '') AS date,
      COALESCE(service_time::text, COALESCE(booking_time::text,'')) AS service_time,
      COALESCE(address, '') AS address,
      COALESCE(destination, '') AS destination,
      COALESCE(patient_id::text, '') AS patient_id,
      COALESCE(payment_status, '') AS payment_status
    FROM booking
    WHERE provider_id IS NULL
    ORDER BY booking_id DESC
    LIMIT 50
  ");

  echo json_encode(['ok' => true, 'items' => $stmt->fetchAll()]);
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}