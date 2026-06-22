<?php
// ── Tracking API ─────────────────────────────────────────────
// Actions (all JSON responses):
//   GET  ?action=get&booking_id=X          → current tracking row
//   POST ?action=update_location           → driver updates lat/lng
//   POST ?action=update_status             → driver changes trip_status
// ─────────────────────────────────────────────────────────────
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require __DIR__ . '/../pgdb/db.php';

function fail(string $msg): void { echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
function ok(array $data = []): void { echo json_encode(['ok'=>true] + $data); exit; }

// Auto-create table if missing
$pdo->exec("
    CREATE TABLE IF NOT EXISTS ride_tracking (
        booking_id   INT PRIMARY KEY,
        driver_lat   DOUBLE PRECISION,
        driver_lng   DOUBLE PRECISION,
        trip_status  VARCHAR(20) NOT NULL DEFAULT 'waiting',
        updated_at   TIMESTAMP NOT NULL DEFAULT NOW()
    )
");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET STATUS ──────────────────────────────────────────────
if ($action === 'get') {
    $bid = (int)($_GET['booking_id'] ?? 0);
    if ($bid <= 0) fail('Missing booking_id');

    $stmt = $pdo->prepare("SELECT * FROM ride_tracking WHERE booking_id = :bid");
    $stmt->execute([':bid' => $bid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        ok(['tracking' => null]);
    }

    ok(['tracking' => $row]);
}

// ── UPDATE LOCATION (driver sends GPS) ─────────────────────
if ($action === 'update_location') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $bid = (int)($data['booking_id'] ?? 0);
    $lat = (float)($data['lat'] ?? 0);
    $lng = (float)($data['lng'] ?? 0);

    if ($bid <= 0) fail('Missing booking_id');
    if ($lat === 0.0 && $lng === 0.0) fail('Invalid coordinates');

    $pdo->prepare("
        INSERT INTO ride_tracking (booking_id, driver_lat, driver_lng, trip_status, updated_at)
        VALUES (:bid, :lat, :lng, 'arriving', NOW())
        ON CONFLICT (booking_id) DO UPDATE
        SET driver_lat  = EXCLUDED.driver_lat,
            driver_lng  = EXCLUDED.driver_lng,
            updated_at  = NOW()
    ")->execute([':bid' => $bid, ':lat' => $lat, ':lng' => $lng]);

    ok();
}

// ── UPDATE STATUS (driver changes trip phase) ───────────────
if ($action === 'update_status') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $bid    = (int)($data['booking_id'] ?? 0);
    $status = $data['status'] ?? '';

    $allowed = ['waiting', 'arriving', 'arrived', 'in_progress', 'completed'];
    if ($bid <= 0)                    fail('Missing booking_id');
    if (!in_array($status, $allowed)) fail('Invalid status');

    $pdo->prepare("
        INSERT INTO ride_tracking (booking_id, trip_status, updated_at)
        VALUES (:bid, :status, NOW())
        ON CONFLICT (booking_id) DO UPDATE
        SET trip_status = EXCLUDED.trip_status,
            updated_at  = NOW()
    ")->execute([':bid' => $bid, ':status' => $status]);

    // If completed, also update main booking status
    if ($status === 'completed') {
        $pdo->prepare("
            UPDATE booking SET status = 'completed' WHERE booking_id = :bid
        ")->execute([':bid' => $bid]);
    }

    ok(['status' => $status]);
}

fail('Unknown action');
