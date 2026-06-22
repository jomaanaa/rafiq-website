<?php
// =============================================================
// FILE: rafiq/admin/admin_api.php
// Admin API matched to the Flutter admin app features.
// Uses the website's shared PostgreSQL connection: rafiq/pgdb/db.php
// =============================================================

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in from the main login page first.']);
    exit;
}

require __DIR__ . '/../pgdb/db.php';

function db(): PDO {
    global $pdo;
    return $pdo;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

$body = [];
if (in_array($method, ['POST','PUT','PATCH'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $body = json_decode($raw, true) ?? [];
    }
}

try {
    switch ($action) {
        case 'stats':                  echo json_encode(getStats());                              break;
        case 'providers':              echo json_encode(getProviders());                          break;
        case 'provider_detail':        echo json_encode(getProviderDetail($id));                  break;
        case 'update_provider_status': echo json_encode(updateProviderStatus($id, $body));        break;
        case 'places':                 echo json_encode(getPlaces());                             break;
        case 'place_reviews':          echo json_encode(getPlaceReviews($id));                     break;
        case 'expire_bookings':        echo json_encode(expireOldBookings(true));                 break;
        case 'add_place':              echo json_encode(addPlace($body));                         break;
        case 'edit_place':             echo json_encode(editPlace($id, $body));                   break;
        case 'delete_place':           echo json_encode(deletePlace($id));                        break;
        case 'update_place_status':    echo json_encode(updatePlaceStatus($id, $body));           break;
        case 'bookings':               echo json_encode(getBookings());                           break;
        case 'patients':               echo json_encode(getPatients());                           break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

// ── Helpers ─────────────────────────────────────────────────
function boolValue($v): bool {
    return $v === true || $v === 'true' || $v === 't' || $v === 1 || $v === '1' || $v === 'yes';
}
function requireId(?int $id): int {
    if (!$id || $id <= 0) throw new Exception('Missing or invalid id');
    return $id;
}

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema='public'
          AND table_name=:table
        LIMIT 1
    ");
    $stmt->execute([':table' => $table]);
    return (bool)$stmt->fetchColumn();
}

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema='public'
          AND table_name=:table
          AND column_name=:column
        LIMIT 1
    ");
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (bool)$stmt->fetchColumn();
}

function bookingExpiredCondition(string $alias = 'b'): string {
    $a = $alias !== '' ? $alias . '.' : '';
    return "LOWER(COALESCE({$a}status,'')) = 'pending'
            AND {$a}\"date\" IS NOT NULL
            AND (
                {$a}\"date\" < CURRENT_DATE
                OR (
                    {$a}\"date\" = CURRENT_DATE
                    AND {$a}service_time IS NOT NULL
                    AND {$a}service_time::time < CURRENT_TIME
                )
            )";
}

function expireOldBookings(bool $returnDetails = false): array {
    $db = db();

    try {
        $sql = "
            UPDATE public.booking b
            SET status = 'expired'
            WHERE " . bookingExpiredCondition('b') . "
            RETURNING booking_id
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return [
            'success' => true,
            'expired_count' => count($ids),
            'expired_ids' => $returnDetails ? array_map('intval', $ids) : []
        ];
    } catch (Exception $e) {
        // If booking.status is an enum and 'expired' is not added yet, do not break the dashboard.
        return [
            'success' => false,
            'expired_count' => 0,
            'expired_ids' => [],
            'message' => $e->getMessage()
        ];
    }
}

// ── STATS ───────────────────────────────────────────────────
function getStats(): array {
    $db = db();
    expireOldBookings(false);

    $totalProviders    = (int)$db->query("SELECT COUNT(*) FROM public.provider")->fetchColumn();
    $pendingProviders  = (int)$db->query("SELECT COUNT(*) FROM public.provider WHERE status='pending'")->fetchColumn();
    $acceptedProviders = (int)$db->query("SELECT COUNT(*) FROM public.provider WHERE status='accepted'")->fetchColumn();
    $rejectedProviders = (int)$db->query("SELECT COUNT(*) FROM public.provider WHERE status='rejected'")->fetchColumn();

    $totalPlaces       = (int)$db->query("SELECT COUNT(*) FROM public.place")->fetchColumn();
    $activePlaces      = (int)$db->query("SELECT COUNT(*) FROM public.place WHERE COALESCE(status,'active')='active'")->fetchColumn();
    $pendingPlaces     = (int)$db->query("SELECT COUNT(*) FROM public.place WHERE status='pending'")->fetchColumn();
    $hiddenPlaces      = (int)$db->query("SELECT COUNT(*) FROM public.place WHERE status='hidden'")->fetchColumn();

    $totalPatients     = (int)$db->query("SELECT COUNT(*) FROM public.patient")->fetchColumn();
    $totalBookings     = (int)$db->query("SELECT COUNT(*) FROM public.booking")->fetchColumn();
    $expiredCondition = bookingExpiredCondition('');
    $pendingBookings   = (int)$db->query("SELECT COUNT(*) FROM public.booking WHERE LOWER(COALESCE(status,''))='pending' AND NOT ($expiredCondition)")->fetchColumn();
    $expiredBookings   = (int)$db->query("SELECT COUNT(*) FROM public.booking WHERE LOWER(COALESCE(status,''))='expired' OR ($expiredCondition)")->fetchColumn();
    $doneBookings      = (int)$db->query("SELECT COUNT(*) FROM public.booking WHERE status='completed'")->fetchColumn();
    $cancelledBookings = (int)$db->query("SELECT COUNT(*) FROM public.booking WHERE status='cancelled'")->fetchColumn();

    $totalRevenue = (float)$db->query("SELECT COALESCE(SUM(payment_total),0) FROM public.booking WHERE payment_total IS NOT NULL AND LOWER(COALESCE(status,'')) = 'completed'")->fetchColumn();
    $platformRevenue = round($totalRevenue * 0.15, 2);
    $providerPayouts = round($totalRevenue * 0.85, 2);
    $avgRating    = (float)$db->query("SELECT COALESCE(ROUND(AVG(rating::numeric),2),0) FROM public.booking WHERE rating IS NOT NULL AND rating > 0")->fetchColumn();

    $monthly = $db->query("
        SELECT TO_CHAR(DATE_TRUNC('month',date),'Mon') AS month, COUNT(*) AS count
        FROM public.booking
        WHERE date >= CURRENT_DATE - INTERVAL '6 months' AND date IS NOT NULL
        GROUP BY DATE_TRUNC('month',date)
        ORDER BY DATE_TRUNC('month',date)
    ")->fetchAll();

    $monthlyRevenue = $db->query("
        SELECT TO_CHAR(DATE_TRUNC('month',date),'Mon') AS month,
               COALESCE(SUM(payment_total),0) AS total
        FROM public.booking
        WHERE date >= CURRENT_DATE - INTERVAL '6 months'
          AND date IS NOT NULL
          AND payment_total IS NOT NULL
          AND LOWER(COALESCE(status,'')) = 'completed'
        GROUP BY DATE_TRUNC('month',date)
        ORDER BY DATE_TRUNC('month',date)
    ")->fetchAll();

    $services = $db->query("
        SELECT COALESCE(service_type,'Unknown') AS service_type, COUNT(*) AS count
        FROM public.booking
        GROUP BY COALESCE(service_type,'Unknown')
        ORDER BY count DESC LIMIT 8
    ")->fetchAll();

    $byCategory = $db->query("
        SELECT category, COUNT(*) AS count FROM (
            SELECT CASE
                WHEN EXISTS(SELECT 1 FROM public.driver d WHERE d.user_id=p.user_id) THEN 'Driver'
                WHEN EXISTS(SELECT 1 FROM public.doctor d WHERE d.user_id=p.user_id) THEN 'Doctor'
                WHEN EXISTS(SELECT 1 FROM public.caregiver c WHERE c.user_id=p.user_id) THEN 'Caregiver'
                WHEN EXISTS(SELECT 1 FROM public.interpreter i WHERE i.user_id=p.user_id) THEN 'Interpreter'
                ELSE 'Provider'
            END AS category
            FROM public.provider p
        ) x
        GROUP BY category
        ORDER BY count DESC
    ")->fetchAll();

    $topProviders = $db->query("
        SELECT u.user_id,
               TRIM(u.first_name||' '||u.last_name) AS name,
               CASE
                 WHEN EXISTS(SELECT 1 FROM public.driver      d WHERE d.user_id=p.user_id) THEN 'Driver'
                 WHEN EXISTS(SELECT 1 FROM public.doctor      d WHERE d.user_id=p.user_id) THEN 'Doctor'
                 WHEN EXISTS(SELECT 1 FROM public.caregiver   c WHERE c.user_id=p.user_id) THEN 'Caregiver'
                 WHEN EXISTS(SELECT 1 FROM public.interpreter i WHERE i.user_id=p.user_id) THEN 'Interpreter'
                 ELSE 'Provider'
               END AS category,
               COUNT(b.booking_id) AS total_bookings,
               COALESCE(SUM(b.payment_total),0) AS total_earned,
               COALESCE(SUM(EXTRACT(EPOCH FROM (b.end_at - b.start_at))/3600),0) AS total_hours,
               COALESCE(ROUND(AVG(b.rating::numeric),2),0) AS avg_rating,
               COUNT(b.rating) AS rating_count
        FROM public.provider p
        JOIN public.\"user\" u ON u.user_id=p.user_id
        LEFT JOIN public.booking b ON b.provider_id=p.user_id AND LOWER(COALESCE(b.status,'')) = 'completed'
        GROUP BY u.user_id, u.first_name, u.last_name, p.user_id
        ORDER BY COUNT(b.booking_id) DESC, COALESCE(SUM(b.payment_total),0) DESC
        LIMIT 8
    ")->fetchAll();

    $recent = $db->query("
        SELECT b.booking_id, b.status, b.service_type, b.date, b.payment_total, b.is_urgent,
               pu.first_name||' '||pu.last_name  AS patient_name,
               pru.first_name||' '||pru.last_name AS provider_name
        FROM public.booking b
        LEFT JOIN public.patient pat ON pat.user_id=b.patient_id
        LEFT JOIN public.\"user\" pu  ON pu.user_id=pat.user_id
        LEFT JOIN public.provider pr  ON pr.user_id=b.provider_id
        LEFT JOIN public.\"user\" pru ON pru.user_id=pr.user_id
        ORDER BY b.booking_id DESC LIMIT 8
    ")->fetchAll();

    return compact(
        'totalProviders','pendingProviders','acceptedProviders','rejectedProviders',
        'totalPlaces','activePlaces','pendingPlaces','hiddenPlaces','totalPatients',
        'totalBookings','pendingBookings','expiredBookings','doneBookings','cancelledBookings',
        'totalRevenue','platformRevenue','providerPayouts','avgRating','monthly','monthlyRevenue','services','byCategory','topProviders','recent'
    );
}

// ── PROVIDERS ───────────────────────────────────────────────
function getProviders(): array {
    $db = db();
    $search   = $_GET['search']   ?? '';
    $status   = $_GET['status']   ?? '';
    $category = $_GET['category'] ?? '';
    $where = ['1=1']; $params = [];

    if ($search) {
        $where[] = "(u.first_name ILIKE :s OR u.last_name ILIKE :s OR u.email ILIKE :s OR p.phone ILIKE :s)";
        $params[':s'] = "%$search%";
    }
    if ($status && $status !== 'all') { $where[] = "p.status=:st"; $params[':st'] = $status; }
    if ($category && $category !== 'all') {
        $map = ['driver'=>'driver','doctor'=>'doctor','caregiver'=>'caregiver','interpreter'=>'interpreter'];
        $tbl = $map[strtolower($category)] ?? null;
        if ($tbl) $where[] = "EXISTS(SELECT 1 FROM public.$tbl x WHERE x.user_id=p.user_id)";
    }
    $w = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.photo,
               p.phone, p.address, p.national_id, p.gender, p.dob, p.cv,
               p.status, p.admin_note, p.created_at,
               CASE
                 WHEN EXISTS(SELECT 1 FROM public.driver      d WHERE d.user_id=p.user_id) THEN 'Driver'
                 WHEN EXISTS(SELECT 1 FROM public.doctor      d WHERE d.user_id=p.user_id) THEN 'Doctor'
                 WHEN EXISTS(SELECT 1 FROM public.caregiver   c WHERE c.user_id=p.user_id) THEN 'Caregiver'
                 WHEN EXISTS(SELECT 1 FROM public.interpreter i WHERE i.user_id=p.user_id) THEN 'Interpreter'
                 ELSE 'Provider'
               END AS category,
               (SELECT COUNT(*) FROM public.booking b WHERE b.provider_id=p.user_id) AS total_bookings
        FROM public.provider p
        JOIN public.\"user\" u ON u.user_id=p.user_id
        WHERE $w
        ORDER BY CASE WHEN p.status='pending' THEN 0 ELSE 1 END, p.user_id DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getProviderDetail(?int $id): array {
    $id = requireId($id);
    $db = db();

    $stmt = $db->prepare("
        SELECT
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.photo,
            p.phone,
            p.address,
            p.national_id,
            p.gender,
            p.dob,
            p.cv,
            p.status,
            p.admin_note,
            p.created_at
        FROM public.provider p
        JOIN public.\"user\" u ON u.user_id = p.user_id
        WHERE p.user_id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        throw new Exception('Provider not found');
    }

    $p['category'] = 'Provider';

    $p['driving_license'] = null;
    $p['available_balance'] = null;
    $p['company_due'] = null;
    $p['total_earned'] = null;
    $p['total_trips'] = null;

    $p['medical_license'] = null;
    $p['speciality'] = null;

    $p['shift_preference'] = null;
    $p['languages'] = null;

    $p['car_model'] = null;
    $p['car_make'] = null;
    $p['car_color'] = null;
    $p['license_plate'] = null;
    $p['wheelchair_accessible'] = null;

    // Driver-specific information
    if (tableExists($db, 'driver')) {
        $stmt = $db->prepare("
            SELECT
                user_id,
                driving_license,
                available_balance,
                company_due,
                total_earned,
                total_trips
            FROM public.driver
            WHERE user_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $p['category'] = 'Driver';
            foreach ($row as $k => $v) {
                if ($k !== 'user_id') $p[$k] = $v;
            }

            if (tableExists($db, 'car')) {
                $carStmt = $db->prepare("
                    SELECT model AS car_model,
                           make AS car_make,
                           color AS car_color,
                           license_plate,
                           wheelchair_accessible
                    FROM public.car
                    WHERE driver_id = :id
                    LIMIT 1
                ");
                $carStmt->execute([':id' => $id]);
                $car = $carStmt->fetch(PDO::FETCH_ASSOC);
                if ($car) {
                    foreach ($car as $k => $v) $p[$k] = $v;
                }
            }
        }
    }

    // Doctor-specific information
    if ($p['category'] === 'Provider' && tableExists($db, 'doctor')) {
        $stmt = $db->prepare("
            SELECT user_id, medical_license, speciality
            FROM public.doctor
            WHERE user_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $p['category'] = 'Doctor';
            $p['medical_license'] = $row['medical_license'] ?? null;
            $p['speciality'] = $row['speciality'] ?? null;
        }
    }

    // Caregiver-specific information
    if ($p['category'] === 'Provider' && tableExists($db, 'caregiver')) {
        $stmt = $db->prepare("
            SELECT user_id, shift_preference
            FROM public.caregiver
            WHERE user_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $p['category'] = 'Caregiver';
            $p['shift_preference'] = $row['shift_preference'] ?? null;
        }
    }

    // Interpreter-specific information
    if ($p['category'] === 'Provider' && tableExists($db, 'interpreter')) {
        $stmt = $db->prepare("
            SELECT user_id, languages
            FROM public.interpreter
            WHERE user_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $p['category'] = 'Interpreter';
            $p['languages'] = $row['languages'] ?? null;
        }
    }

    // Booking history removed from the provider details modal.
    $p['bookings'] = [];

    return $p;
}

function updateProviderStatus(?int $id, array $body): array {
    $id = requireId($id);
    $status = $body['status'] ?? 'pending';
    $note   = $body['note']   ?? null;
    if (!in_array($status, ['pending','accepted','rejected'], true)) throw new Exception('Invalid status');
    $sql = $note === null
        ? "UPDATE public.provider SET status=:s WHERE user_id=:id"
        : "UPDATE public.provider SET status=:s, admin_note=:n WHERE user_id=:id";
    $params = [':s'=>$status, ':id'=>$id];
    if ($note !== null) $params[':n'] = $note;
    db()->prepare($sql)->execute($params);
    return ['success'=>true];
}

// ── PLACES ──────────────────────────────────────────────────
function getPlaces(): array {
    $db = db();
    $search = $_GET['search'] ?? '';
    $type   = $_GET['type']   ?? '';
    $status = $_GET['status'] ?? '';
    $where = ['1=1']; $params = [];

    if ($search) {
        $where[] = "(pl.name ILIKE :s OR pl.address ILIKE :s OR pl.type ILIKE :s OR pl.comment ILIKE :s)";
        $params[':s'] = "%$search%";
    }

    if ($type && $type !== 'all') {
        $where[] = "pl.type=:t";
        $params[':t'] = $type;
    }

    if ($status && $status !== 'all') {
        $where[] = "pl.status=:st";
        $params[':st'] = $status;
    }

    // Reviews/ratings are stored directly in public.place:
    // rating = patient's rating, comment = patient's review.
    // So only show places that actually have a rating.
    $where[] = "(pl.rating IS NOT NULL AND pl.rating > 0)";

    $w = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT
            pl.*,
            (SELECT COUNT(*) FROM public.booking b WHERE b.place_id=pl.place_id) AS booking_count,
            1 AS review_count,
            pl.rating::numeric AS avg_place_rating,
            pl.comment AS review_comment
        FROM public.place pl
        WHERE $w
        ORDER BY pl.rating DESC, pl.place_id DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function addPlace(array $d): array {
    $stmt = db()->prepare("
        INSERT INTO public.place
            (name,type,address,elevator,ramp,toilet,parking,comment,latitude,longitude,photo,status,wheelchair,rating,osm_id)
        VALUES
            (:name,:type,:address,:elevator,:ramp,:toilet,:parking,:comment,:lat,:lng,:photo,:status,:wheelchair,:rating,:osm_id)
        RETURNING *
    ");
    $stmt->execute([
        ':name'=>$d['name']??'', ':type'=>$d['type']??'', ':address'=>$d['address']??'',
        ':elevator'=>boolValue($d['elevator']??false)?'true':'false', ':ramp'=>boolValue($d['ramp']??false)?'true':'false',
        ':toilet'=>boolValue($d['toilet']??false)?'true':'false', ':parking'=>boolValue($d['parking']??false)?'true':'false',
        ':comment'=>$d['comment']??'', ':lat'=>$d['latitude']??null, ':lng'=>$d['longitude']??null,
        ':photo'=>$d['photo']??'', ':status'=>$d['status']??'active',
        ':wheelchair'=>boolValue($d['wheelchair']??false)?'true':'false',
        ':rating'=>isset($d['rating']) && $d['rating'] !== '' ? (int)$d['rating'] : null,
        ':osm_id'=>$d['osm_id']??null,
    ]);
    return $stmt->fetch();
}

function editPlace(?int $id, array $d): array {
    $id = requireId($id);
    $stmt = db()->prepare("
        UPDATE public.place SET
            name=:name,type=:type,address=:address,elevator=:elevator,ramp=:ramp,toilet=:toilet,parking=:parking,
            comment=:comment,latitude=:lat,longitude=:lng,photo=:photo,status=:status,wheelchair=:wheelchair,rating=:rating,osm_id=:osm_id
        WHERE place_id=:id
        RETURNING *
    ");
    $stmt->execute([
        ':name'=>$d['name']??'', ':type'=>$d['type']??'', ':address'=>$d['address']??'',
        ':elevator'=>boolValue($d['elevator']??false)?'true':'false', ':ramp'=>boolValue($d['ramp']??false)?'true':'false',
        ':toilet'=>boolValue($d['toilet']??false)?'true':'false', ':parking'=>boolValue($d['parking']??false)?'true':'false',
        ':comment'=>$d['comment']??'', ':lat'=>$d['latitude']??null, ':lng'=>$d['longitude']??null,
        ':photo'=>$d['photo']??'', ':status'=>$d['status']??'active',
        ':wheelchair'=>boolValue($d['wheelchair']??false)?'true':'false',
        ':rating'=>isset($d['rating']) && $d['rating'] !== '' ? (int)$d['rating'] : null,
        ':osm_id'=>$d['osm_id']??null,
        ':id'=>$id,
    ]);
    $row = $stmt->fetch();
    if (!$row) throw new Exception('Place not found');
    return $row;
}

function deletePlace(?int $id): array {
    $id = requireId($id);
    db()->prepare("DELETE FROM public.place WHERE place_id=:id")->execute([':id'=>$id]);
    return ['success'=>true, 'deleted_id'=>$id];
}

function updatePlaceStatus(?int $id, array $body): array {
    $id = requireId($id);
    $status = $body['status'] ?? 'active';
    if (!in_array($status, ['active','pending','hidden'], true)) throw new Exception('Invalid status');
    db()->prepare("UPDATE public.place SET status=:s WHERE place_id=:id")->execute([':s'=>$status,':id'=>$id]);
    return ['success'=>true];
}



// ── BOOKINGS ────────────────────────────────────────────────





function getPlaceReviews(?int $id): array {
    $id = requireId($id);
    $db = db();

    $stmt = $db->prepare("
        SELECT *
        FROM public.place
        WHERE place_id = :id
          AND rating IS NOT NULL
          AND rating > 0
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $place = $stmt->fetch();

    if (!$place) {
        throw new Exception('No rating/review found for this place.');
    }

    return [
        'place' => $place,
        'summary' => [
            'review_count' => 1,
            'avg_rating' => (float)($place['rating'] ?? 0)
        ],
        'reviews' => [[
            'review_id' => $place['place_id'],
            'place_id' => $place['place_id'],
            'patient_id' => null,
            'patient_name' => 'Patient review',
            'patient_email' => '',
            'rating' => (int)($place['rating'] ?? 0),
            'comment' => $place['comment'] ?? '',
            'created_at' => $place['created_at'] ?? null
        ]]
    ];
}

function getBookings(): array {
    $db = db();
    expireOldBookings(false);

    $search = $_GET['search']       ?? '';
    $status = $_GET['status']       ?? '';
    $stype  = $_GET['service_type'] ?? '';
    $where = ['1=1']; $params = [];

    if ($search) {
        $where[] = "(pu.first_name ILIKE :s OR pu.last_name ILIKE :s OR pru.first_name ILIKE :s OR pru.last_name ILIKE :s OR b.fullname ILIKE :s OR b.email ILIKE :s OR b.phone ILIKE :s)";
        $params[':s'] = "%$search%";
    }

    $expiredCondition = bookingExpiredCondition('b');

    if ($status && $status !== 'all') {
        if ($status === 'expired') {
            $where[] = "(LOWER(COALESCE(b.status,''))='expired' OR ($expiredCondition))";
        } elseif ($status === 'pending') {
            $where[] = "LOWER(COALESCE(b.status,''))='pending' AND NOT ($expiredCondition)";
        } else {
            $where[] = "b.status=:st";
            $params[':st'] = $status;
        }
    }

    if ($stype && $stype !== 'all') {
        $where[] = "LOWER(b.service_type)=LOWER(:stype)";
        $params[':stype'] = $stype;
    }

    $w = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT b.booking_id, b.date,
               CASE
                 WHEN LOWER(COALESCE(b.status,''))='expired' OR ($expiredCondition) THEN 'expired'
                 ELSE b.status
               END AS status,
               b.service_type, b.payment_total, b.payment_status,
               b.rating, b.is_urgent, b.is_full_day, b.address, b.destination, b.booking_time, b.service_time,
               b.payment_method, b.payment_state,
               COALESCE(NULLIF(TRIM(pu.first_name||' '||pu.last_name),''), b.fullname, '—') AS patient_name,
               COALESCE(NULLIF(TRIM(pru.first_name||' '||pru.last_name),''), '—') AS provider_name
        FROM public.booking b
        LEFT JOIN public.patient pat ON pat.user_id=b.patient_id
        LEFT JOIN public.\"user\" pu ON pu.user_id=pat.user_id
        LEFT JOIN public.provider pr ON pr.user_id=b.provider_id
        LEFT JOIN public.\"user\" pru ON pru.user_id=pr.user_id
        WHERE $w
        ORDER BY b.booking_id DESC LIMIT 300
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ── PATIENTS ────────────────────────────────────────────────
function getPatients(): array {
    $db = db();
    $search = $_GET['search'] ?? '';
    $where = ['1=1']; $params = [];
    if ($search) {
        $where[] = "(u.first_name ILIKE :s OR u.last_name ILIKE :s OR u.email ILIKE :s OR pat.phone ILIKE :s)";
        $params[':s'] = "%$search%";
    }
    $w = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, u.photo,
               pat.phone, pat.address, pat.disability, pat.gender, pat.dob,
               (SELECT COUNT(*) FROM public.booking b WHERE b.patient_id=pat.user_id) AS total_bookings
        FROM public.patient pat
        JOIN public.\"user\" u ON u.user_id=pat.user_id
        WHERE $w
        ORDER BY u.user_id DESC LIMIT 500
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}
