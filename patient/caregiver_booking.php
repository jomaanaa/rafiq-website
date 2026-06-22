<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
date_default_timezone_set('Africa/Cairo');

require __DIR__ . '/../pgdb/db.php';

/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/
$WORK_START = "10:00";
$WORK_END   = "18:00";
$SLOT_DURATION = 30;
$HALF_HOUR_RATE = 150;
$MAX_DURATION_MINUTES = 480;
$CAREGIVER_PRICE = $HALF_HOUR_RATE;
$DEFAULT_PAYMENT_METHOD = "cash";

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function postv(string $k) { return $_POST[$k] ?? null; }
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

function isValidDateYmd(?string $s): bool {
    if (!$s) return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

function isValidTimeHi(?string $s): bool {
    if (!$s) return false;
    return (bool)preg_match('/^\d{2}:\d{2}$/', $s);
}

function timeToMinutes(string $hhmm): int {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return ($h * 60) + $m;
}

function minutesToTime(int $minutes): string {
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return sprintf('%02d:%02d', $h, $m);
}

function overlaps(int $aStart, int $aEnd, int $bStart, int $bEnd): bool {
    return $aStart < $bEnd && $aEnd > $bStart;
}

function tableColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("\n        SELECT column_name\n        FROM information_schema.columns\n        WHERE table_schema = 'public'\n          AND table_name = :table\n    ");
    $stmt->execute([':table' => $table]);
    return array_map(fn($r) => $r['column_name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function buildNotCancelledWhere(PDO $pdo): string {
    try {
        $cols = tableColumns($pdo, 'booking');
        if (in_array('status', $cols, true)) return " (status IS NULL OR status <> 'cancelled') ";
        if (in_array('is_cancelled', $cols, true)) return " (is_cancelled IS NULL OR is_cancelled = FALSE) ";
        if (in_array('cancelled_at', $cols, true)) return " cancelled_at IS NULL ";
        return " TRUE ";
    } catch (Exception $e) { return " TRUE "; }
}

function generateSlots(string $workStart, string $workEnd, int $duration): array {
    $slots = [];
    $start = timeToMinutes($workStart);
    $end   = timeToMinutes($workEnd);
    for ($t = $start; $t + $duration <= $end; $t += $duration) {
        $slots[] = [
            'from' => minutesToTime($t),
            'to'   => minutesToTime($t + $duration),
            'from_min' => $t,
            'to_min'   => $t + $duration,
        ];
    }
    return $slots;
}

function caregiverIconByShift(string $shift): string {
    $shift = strtolower(trim($shift));
    if (str_contains($shift, 'morning')) return 'fa-sun';
    if (str_contains($shift, 'afternoon')) return 'fa-cloud-sun';
    if (str_contains($shift, 'evening')) return 'fa-moon';
    if (str_contains($shift, 'night')) return 'fa-circle-half-stroke';
    return 'fa-handshake';
}

function caregiverRatingById(int $id): float {
    $ratings = [4.6, 4.7, 4.8, 4.9, 4.5];
    return $ratings[$id % count($ratings)];
}

function generateCaregiverIllustration(string $gender = 'male'): string {
    $gender = strtolower(trim($gender));
    $isFemale = ($gender === 'female');
    $skin = '#f3c9a9';
    $hair = $isFemale ? '#4a3550' : '#2e3248';
    $hair2 = $isFemale ? '#5c4363' : '#41465f';
    $accent = '#404066';

    $hairShape = $isFemale
        ? '<path d="M48 49c0-18 14-31 32-31s32 13 32 31v9H48v-9z" fill="'.$hair.'"/><path d="M52 59c0 23 11 35 28 35s28-12 28-35v11c0 20-12 34-28 34S52 90 52 70V59z" fill="'.$hair2.'" opacity=".18"/>'
        : '<path d="M48 50c0-17 14-29 32-29s32 12 32 29v8H48v-8z" fill="'.$hair.'"/><path d="M56 33c6-7 14-11 24-11 11 0 19 4 24 11" stroke="'.$hair2.'" stroke-width="5" stroke-linecap="round"/>';

    $svg = '
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 180">
      <defs><linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#f8f9fd"/><stop offset="100%" stop-color="#eef1f8"/></linearGradient></defs>
      <rect width="180" height="180" rx="34" fill="url(#bg)"/>
      <circle cx="80" cy="58" r="30" fill="'.$skin.'"/>'.$hairShape.'
      <path d="M52 98c6-10 16-16 28-16s22 6 28 16v42H52V98z" fill="#ffffff" stroke="#dfe4f2" stroke-width="1.5"/>
      <path d="M64 90l16 18 16-18" fill="'.$accent.'" opacity=".15"/>
      <path d="M80 92v22" stroke="'.$accent.'" stroke-width="4" stroke-linecap="round"/>
      <path d="M50 100c-10 4-16 13-16 24v16h18v-20c0-6 1-12 4-17z" fill="#ffffff" stroke="#dfe4f2" stroke-width="1.5"/>
      <path d="M110 100c10 4 16 13 16 24v16h-18v-20c0-6-1-12-4-17z" fill="#ffffff" stroke="#dfe4f2" stroke-width="1.5"/>
      <circle cx="69" cy="60" r="2.6" fill="#2e3248"/><circle cx="91" cy="60" r="2.6" fill="#2e3248"/>
      <path d="M72 73c2 3 5 5 8 5s6-2 8-5" stroke="#a86464" stroke-width="2.6" fill="none" stroke-linecap="round"/>
      <circle cx="148" cy="58" r="18" fill="'.$accent.'"/>
      <path d="M140 58l6 6 12-13" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M70 122h20" stroke="'.$accent.'" stroke-width="5" stroke-linecap="round"/>
      <path d="M80 112v20" stroke="'.$accent.'" stroke-width="5" stroke-linecap="round"/>
    </svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

$NOT_CANCELLED_WHERE = buildNotCancelledWhere($pdo);
$todayYmd = date('Y-m-d');
$submit_error = "";
$submit_success = "";
$TOTAL_SLOTS_PER_DAY = count(generateSlots($WORK_START, $WORK_END, $SLOT_DURATION));

/*
|--------------------------------------------------------------------------
| Current Patient
|--------------------------------------------------------------------------
*/
$currentPatient = null;
$currentPatientName = "Guest";

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare('
            SELECT p.user_id AS patient_id, p.user_id, p.phone AS patient_phone, p.address AS patient_address,
                   p.gender, p.dob, u.first_name, u.last_name, u.email
            FROM "user" u
            LEFT JOIN patient p ON p.user_id = u.user_id
            WHERE u.user_id = :user_id
            LIMIT 1
        ');
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $currentPatient = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($currentPatient) {
            $fullName = trim(($currentPatient['first_name'] ?? '') . ' ' . ($currentPatient['last_name'] ?? ''));
            $currentPatientName = $fullName !== '' ? $fullName : ($_SESSION['Name'] ?? 'Patient');
            if (!empty($currentPatient['patient_id'])) $_SESSION['patient_id'] = $currentPatient['patient_id'];
            if (!empty($currentPatient['email'])) $_SESSION['email'] = $currentPatient['email'];
            if (!empty($currentPatient['patient_phone'])) $_SESSION['phone'] = $currentPatient['patient_phone'];
            if (!empty($currentPatient['patient_address'])) $_SESSION['address'] = $currentPatient['patient_address'];
            $_SESSION['Name'] = $currentPatientName;
        } else {
            $currentPatientName = $_SESSION['Name'] ?? 'Guest';
        }
    } catch (Exception $e) {
        $currentPatient = null;
        $currentPatientName = $_SESSION['Name'] ?? 'Guest';
    }
} else {
    $currentPatientName = $_SESSION['Name'] ?? 'Guest';
}

$prefillFullname = trim((string)(($currentPatientName !== 'Guest' ? $currentPatientName : '') ?: ($_SESSION['Name'] ?? '')));
$prefillPhone = trim((string)(($currentPatient['patient_phone'] ?? '') ?: ($_SESSION['phone'] ?? '')));
$prefillEmail = trim((string)(($currentPatient['email'] ?? '') ?: ($_SESSION['email'] ?? '')));
$prefillAddress = trim((string)(($currentPatient['patient_address'] ?? '') ?: ($_SESSION['address'] ?? '')));

$currentPatientId = 0;
if (!empty($currentPatient['patient_id'])) {
    $currentPatientId = (int)$currentPatient['patient_id'];
} elseif (!empty($_SESSION['patient_id'])) {
    $currentPatientId = (int)$_SESSION['patient_id'];
} elseif (!empty($_SESSION['user_id'])) {
    $currentPatientId = (int)$_SESSION['user_id'];
}

/*
|--------------------------------------------------------------------------
| Caregivers
|--------------------------------------------------------------------------
*/
$caregivers = [];
$caregiverNameById = [];
$caregiverShiftById = [];
$caregiverIconById = [];

try {
    $stmtCaregivers = $pdo->prepare("\n        SELECT 
            u.user_id,
            u.first_name,
            u.last_name,
            u.email,
            u.photo,
            p.gender,\n            p.phone,\n            p.address,\n            c.shift_preference,\n            COALESCE(ROUND(AVG(b.rating)::numeric, 1), 0) AS caregiver_rating\n        FROM \"user\" u\n        INNER JOIN provider p ON p.user_id = u.user_id\n        INNER JOIN caregiver c ON c.user_id = p.user_id\n        LEFT JOIN booking b \n            ON b.provider_id = u.user_id\n           AND b.rating IS NOT NULL\n           AND LOWER(COALESCE(b.status, '')) = 'completed'\n        WHERE u.role = :role
          AND COALESCE(p.status, 'pending') = 'accepted'\n        GROUP BY \n            u.user_id,\n            u.first_name,\n            u.last_name,\n            u.email,\n            u.photo,\n            p.gender,\n            p.phone,\n            p.address,\n            u.photo,\n            c.shift_preference\n        ORDER BY u.first_name, u.last_name\n    ");
    $stmtCaregivers->execute([':role' => 'provider']);
    $rows = $stmtCaregivers->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $id = (int)$row['user_id'];
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $gender = strtolower(trim((string)($row['gender'] ?? 'male')));
        $shift = trim((string)($row['shift_preference'] ?? 'Flexible'));
        $photoPath = trim((string)($row['photo'] ?? ''));

        if ($photoPath !== '') {
            $photoPath = str_replace('\\', '/', $photoPath);
            $img = '../pictures/providers/' . basename($photoPath);
        } else {
            $img = generateCaregiverIllustration($gender);
        }
        $ratingValue = (float)($row['caregiver_rating'] ?? 0);
        $rating = $ratingValue > 0 ? round($ratingValue, 1) : "New";
        $icon = caregiverIconByShift($shift);

        $caregivers[] = [
            "id"      => $id,
            "name"    => $name !== '' ? $name : "Caregiver #" . $id,
            "gender"  => $gender,
            "shift"   => $shift,
            "price"   => $CAREGIVER_PRICE,
            "rating"  => $rating,
            "img"     => $img,
            "phone"   => $row['phone'] ?? '',
            "address" => $row['address'] ?? '',
            "email"   => $row['email'] ?? '',
            "icon"    => $icon,
        ];

        $caregiverNameById[$id] = $name !== '' ? $name : "Caregiver #" . $id;
        $caregiverShiftById[$id] = $shift;
        $caregiverIconById[$id] = $icon;
    }
} catch (Exception $e) {
    die("Failed to load caregivers: " . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| Shift preferences from DB
|--------------------------------------------------------------------------
*/
$shiftPreferences = [];

try {
    $stmtShifts = $pdo->prepare("
        SELECT DISTINCT TRIM(c.shift_preference) AS shift_preference
        FROM caregiver c
        INNER JOIN provider p ON p.user_id = c.user_id
        INNER JOIN \"user\" u ON u.user_id = p.user_id
        WHERE u.role = 'provider'
          AND COALESCE(p.status, 'pending') = 'accepted'
          AND c.shift_preference IS NOT NULL
          AND TRIM(c.shift_preference) <> ''
        ORDER BY TRIM(c.shift_preference)
    ");
    $stmtShifts->execute();
    $shiftPreferences = array_values(array_filter(array_map(
        fn($r) => trim((string)($r['shift_preference'] ?? '')),
        $stmtShifts->fetchAll(PDO::FETCH_ASSOC)
    )));
} catch (Exception $e) {
    $shiftPreferences = [];
}

if (!$shiftPreferences) {
    $shiftPreferences = array_values(array_unique(array_filter(array_map(
        fn($c) => trim((string)($c['shift'] ?? '')),
        $caregivers
    ))));
}

/*
|--------------------------------------------------------------------------
| AJAX: booked days
|--------------------------------------------------------------------------
*/
if (isset($_GET["action"]) && $_GET["action"] === "booked_days") {
    header("Content-Type: application/json; charset=utf-8");

    $year  = isset($_GET["year"]) ? (int)$_GET["year"] : (int)date("Y");
    $month = isset($_GET["month"]) ? (int)$_GET["month"] : (int)date("n");
    $provider_id = isset($_GET["provider_id"]) ? (int)$_GET["provider_id"] : 0;
    if ($month < 1 || $month > 12) $month = (int)date("n");

    $start = sprintf("%04d-%02d-01", $year, $month);
    $endDate = new DateTime($start);
    $endDate->modify("+1 month");
    $end = $endDate->format("Y-m-d");
    $queryStart = max($start, $todayYmd);

    $baseWhere = "\n        \"date\" >= :start\n        AND \"date\" < :end\n        AND provider_id IS NOT NULL\n        AND booking_time IS NOT NULL\n        AND service_time IS NOT NULL\n        AND $NOT_CANCELLED_WHERE\n    ";

    if ($provider_id > 0) {
        $sql = "\n            SELECT \"date\"::text AS d, COUNT(*) AS cnt\n            FROM booking\n            WHERE $baseWhere AND provider_id = :provider_id\n            GROUP BY \"date\"\n            ORDER BY \"date\"\n        ";
        $params = [":start" => $queryStart, ":end" => $end, ":provider_id" => $provider_id];
    } else {
        $sql = "\n            SELECT \"date\"::text AS d, COUNT(*) AS cnt\n            FROM booking\n            WHERE $baseWhere\n            GROUP BY \"date\"\n            ORDER BY \"date\"\n        ";
        $params = [":start" => $queryStart, ":end" => $end];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode([
            "year" => $year,
            "month" => $month,
            "provider_id" => $provider_id,
            "today" => $todayYmd,
            "total_slots_per_day" => $TOTAL_SLOTS_PER_DAY,
            "booked_days" => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(["error" => true, "message" => $e->getMessage(), "booked_days" => []], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

/*
|--------------------------------------------------------------------------
| AJAX: caregiver day slots
|--------------------------------------------------------------------------
*/
if (isset($_GET["action"]) && $_GET["action"] === "caregiver_day_slots") {
    header("Content-Type: application/json; charset=utf-8");

    $date = $_GET["date"] ?? "";
    $provider_id = isset($_GET["provider_id"]) ? (int)$_GET["provider_id"] : 0;

    if (!isValidDateYmd($date)) { echo json_encode(["error" => true, "message" => "Invalid date"], JSON_UNESCAPED_UNICODE); exit(); }
    if ($date < $todayYmd) { echo json_encode(["error" => true, "message" => "Past dates cannot be booked"], JSON_UNESCAPED_UNICODE); exit(); }
    if ($provider_id <= 0) { echo json_encode(["error" => true, "message" => "Choose caregiver first"], JSON_UNESCAPED_UNICODE); exit(); }

    try {
        $stmt = $pdo->prepare("\n            SELECT booking_id, booking_time::text AS from_time, service_time::text AS to_time\n            FROM booking\n            WHERE \"date\" = :d\n              AND provider_id = :provider_id\n              AND booking_time IS NOT NULL\n              AND service_time IS NOT NULL\n              AND $NOT_CANCELLED_WHERE\n            ORDER BY booking_time ASC\n        ");
        $stmt->execute([':d' => $date, ':provider_id' => $provider_id]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $generated = generateSlots($WORK_START, $WORK_END, $SLOT_DURATION);
        $nowMinutes = timeToMinutes(date('H:i'));
        $bookedRanges = [];
        foreach ($bookings as $b) {
            $from = substr((string)$b['from_time'], 0, 5);
            $to   = substr((string)$b['to_time'], 0, 5);
            $bookedRanges[] = ['from' => $from, 'to' => $to, 'from_min' => timeToMinutes($from), 'to_min' => timeToMinutes($to)];
        }

        $available = [];
        $booked = [];
        foreach ($generated as $slot) {
            if ($date === $todayYmd && $slot['from_min'] <= $nowMinutes) {
                continue;
            }

            $isBooked = false;
            foreach ($bookedRanges as $range) {
                if (overlaps($slot['from_min'], $slot['to_min'], $range['from_min'], $range['to_min'])) { $isBooked = true; break; }
            }
            $cleanSlot = ['from' => $slot['from'], 'to' => $slot['to']];
            $isBooked ? $booked[] = $cleanSlot : $available[] = $cleanSlot;
        }

        echo json_encode([
            "date" => $date,
            "provider_id" => $provider_id,
            "provider_name" => $caregiverNameById[$provider_id] ?? ("Caregiver #" . $provider_id),
            "provider_shift" => $caregiverShiftById[$provider_id] ?? "Flexible",
            "provider_icon" => $caregiverIconById[$provider_id] ?? "fa-handshake",
            "work_start" => $WORK_START,
            "work_end" => $WORK_END,
            "duration" => $SLOT_DURATION,
            "available_slots" => $available,
            "booked_slots" => $booked
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(["error" => true, "message" => $e->getMessage(), "available_slots" => [], "booked_slots" => []], JSON_UNESCAPED_UNICODE);
    }
    exit();
}

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_booking"])) {
    $date          = postv("date");
    $booking_time  = postv("booking_time");
    $service_time  = postv("service_time");
    $duration_minutes = (int)(postv("duration_minutes") ?? 30);
    $duration_units = max(1, (int)ceil($duration_minutes / 30));
    $payment_total = $duration_units * $HALF_HOUR_RATE;
    $provider_id   = (int)(postv("provider_id") ?? 0);
    $fullname      = trim((string)postv("fullname"));
    $phone         = trim((string)postv("phone"));
    $email         = trim((string)postv("email"));
    $address       = trim((string)postv("address"));

    if (!isValidDateYmd($date)) $submit_error = "Invalid date.";
    elseif ($date < $todayYmd) $submit_error = "You cannot book a date before today.";
    elseif (isValidTimeHi((string)$booking_time) && $date === $todayYmd && timeToMinutes($booking_time) <= timeToMinutes(date('H:i'))) $submit_error = "You cannot book a time that has already passed.";
    elseif ($duration_minutes < 30 || $duration_minutes > $MAX_DURATION_MINUTES || $duration_minutes % 30 !== 0) $submit_error = "Duration must be from 30 minutes to 8 hours.";
    elseif (!isValidTimeHi($booking_time) || !isValidTimeHi($service_time)) $submit_error = "Please choose a valid slot.";
    elseif ($date === $todayYmd && timeToMinutes($booking_time) <= timeToMinutes(date('H:i'))) $submit_error = "You cannot book a time that has already passed.";
    elseif ($provider_id <= 0) $submit_error = "Please choose a caregiver.";
    elseif ($fullname === '' || $phone === '' || $email === '' || $address === '') $submit_error = "Please fill all required fields.";
    else {
        $start_at = $date . " " . $booking_time . ":00";
        $end_at   = $date . " " . $service_time . ":00";
        try {
            $dtS = new DateTime($start_at);
            $dtE = new DateTime($end_at);
            $actualDuration = (int)(($dtE->getTimestamp() - $dtS->getTimestamp()) / 60);
            if ($dtE <= $dtS) $submit_error = "End time must be after start time.";
            elseif ($actualDuration !== $duration_minutes) $submit_error = "Invalid session duration.";
            elseif (timeToMinutes($booking_time) < timeToMinutes($WORK_START) || timeToMinutes($service_time) > timeToMinutes($WORK_END)) $submit_error = "Selected session must be between $WORK_START and $WORK_END.";
        } catch (Exception $e) { $submit_error = "Invalid start/end datetime."; }
    }

    if ($submit_error === "") {
        try {
            $checkCaregiver = $pdo->prepare("\n                SELECT u.user_id\n                FROM \"user\" u\n                INNER JOIN provider p ON p.user_id = u.user_id\n                INNER JOIN caregiver c ON c.user_id = p.user_id\n                WHERE u.user_id = :provider_id AND u.role = 'provider'
                  AND COALESCE(p.status, 'pending') = 'accepted'\n                LIMIT 1\n            ");
            $checkCaregiver->execute([':provider_id' => $provider_id]);
            if (!$checkCaregiver->fetch(PDO::FETCH_ASSOC)) $submit_error = "Selected caregiver is invalid.";
        } catch (Exception $e) { $submit_error = "Caregiver validation failed: " . $e->getMessage(); }
    }

    if ($submit_error === "") {
        try {
            $checkStmt = $pdo->prepare("\n                SELECT COUNT(*)\n                FROM booking\n                WHERE \"date\" = :date\n                  AND provider_id = :provider_id\n                  AND booking_time < :service_time\n                  AND service_time > :booking_time\n                  AND $NOT_CANCELLED_WHERE\n            ");
            $checkStmt->execute([':date' => $date, ':provider_id' => $provider_id, ':booking_time' => $booking_time, ':service_time' => $service_time]);
            if ((int)$checkStmt->fetchColumn() > 0) $submit_error = "This appointment is already booked. Choose another slot.";
        } catch (Exception $e) { $submit_error = "Booking validation failed: " . $e->getMessage(); }
    }

    if ($submit_error === "") {
        $start_at = $date . " " . $booking_time . ":00";
        $end_at   = $date . " " . $service_time . ":00";
        try {
            if ($currentPatientId <= 0) {
                throw new Exception("Patient session is missing. Please log in again.");
            }

            $patientCheck = $pdo->prepare("SELECT user_id FROM patient WHERE user_id = :patient_id LIMIT 1");
            $patientCheck->execute([':patient_id' => $currentPatientId]);
            if (!$patientCheck->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Patient record was not found for this account.");
            }

            $bookingColumns = tableColumns($pdo, 'booking');
            $hasColumn = function(string $column) use ($bookingColumns): bool {
                return in_array($column, $bookingColumns, true);
            };

            $cols = []; $vals = []; $params = [];

            $add = function(string $column, string $placeholder, $value) use (&$cols, &$vals, &$params, $hasColumn): void {
                if (!$hasColumn($column)) return;
                $cols[] = ($column === 'date') ? '"date"' : $column;
                $vals[] = $placeholder;
                $params[$placeholder] = $value;
            };

            $add('date',           ':date',           $date);
            $add('booking_time',   ':booking_time',   $booking_time);
            $add('service_time',   ':service_time',   $service_time);
            $add('start_at',       ':start_at',       $start_at);
            $add('end_at',         ':end_at',         $end_at);
            $add('address',        ':address',        $address);
            $add('destination',    ':destination',    $address);
            $add('payment_total',  ':payment_total',  $payment_total);
            $add('payment_method', ':payment_method', $DEFAULT_PAYMENT_METHOD);
            $add('payment_status', ':payment_status', 'pending');
            $add('payment_state',  ':payment_state',  'unpaid');
            $add('patient_id',     ':patient_id',     $currentPatientId);
            $add('provider_id',    ':provider_id',    $provider_id);
            $add('status',         ':status',         'pending');
            $add('service_type',   ':service_type',   'Caregiver');
            $add('fullname',       ':fullname',        $fullname);
            $add('phone',          ':phone',           $phone);
            $add('email',          ':email',           $email);

            foreach (['date', 'booking_time', 'service_time', 'patient_id', 'provider_id'] as $requiredColumn) {
                if (!$hasColumn($requiredColumn)) {
                    throw new Exception("DB mismatch: booking.$requiredColumn column is missing.");
                }
            }

            $sql = "INSERT INTO booking (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ") RETURNING booking_id";

            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $bookingId = (int)$stmt->fetchColumn();
            if ($bookingId <= 0) {
                throw new Exception("Booking insert did not return an ID.");
            }

            $verifyStmt = $pdo->prepare("SELECT booking_id FROM booking WHERE booking_id = :booking_id AND patient_id = :patient_id LIMIT 1");
            $verifyStmt->execute([':booking_id' => $bookingId, ':patient_id' => $currentPatientId]);
            if (!$verifyStmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Booking insert verification failed.");
            }

            $pdo->commit();

            $_SESSION["booking_id"] = $bookingId;
            $_SESSION["patient_id"] = $currentPatientId;

            header("Location: payment.php?booking_id=" . urlencode((string)$bookingId));
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $submit_error = "Insert failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rafiq Caregiver Booking</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{
    --primary:#2B2C41;
    --primary-dark:#212233;
    --primary-soft:#efeff6;
    --green:#16a34a;
    --green-soft:#eefbf3;
    --green-line:#b8ebca;
    --red:#dc2626;
    --red-soft:#fff1f1;
    --red-line:#ffcaca;
    --bg:#f7f8fc;
    --card:#ffffff;
    --text:#23253a;
    --muted:#727692;
    --line:#e6e9f2;
    --shadow:0 14px 35px rgba(64,64,102,.10);
    --shadow-soft:0 8px 20px rgba(64,64,102,.08);
    --shadow-strong:0 18px 36px rgba(64,64,102,.16);
    --step-accent:#404066;
    --step-accent-light:#6b6fa8;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    font-family:'Poppins',sans-serif;
    background:
        radial-gradient(circle at top left, rgba(109,94,252,.08), transparent 26%),
        radial-gradient(circle at top right, rgba(20,184,166,.07), transparent 22%),
        linear-gradient(180deg,#fbfcff 0%, #f4f6fc 100%);
    color:var(--text);
}
.page-wrap{width:min(1180px, calc(100% - 24px));margin:24px auto 0;}
.top-box{position:relative;background:linear-gradient(135deg, rgba(255,255,255,.98) 0%, rgba(246,248,255,.98) 100%);border:1px solid var(--line);border-radius:32px;box-shadow:var(--shadow-strong);padding:30px;margin-bottom:24px;overflow:hidden;}
.top-box::before{content:"";position:absolute;inset:-120px auto auto -120px;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle, rgba(64,64,102,.12), transparent 68%);pointer-events:none;}
.top-box::after{content:"";position:absolute;inset:auto -100px -120px auto;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle, rgba(64,64,102,.08), transparent 68%);pointer-events:none;}
.top-content{position:relative;z-index:1;}
.top-badge{display:inline-flex;align-items:center;gap:8px;padding:9px 15px;border-radius:999px;background:linear-gradient(135deg,#eef0ff,#f4f7ff);color:var(--primary);font-size:12px;font-weight:800;margin-bottom:14px;border:1px solid #dde2fb;box-shadow:0 6px 14px rgba(64,64,102,.06);}
.top-box h1{margin:0 0 10px;font-size:34px;line-height:1.15;color:var(--primary);max-width:760px;}
.top-box p{margin:0;color:var(--muted);line-height:1.9;font-size:15px;max-width:860px;}
.steps-flow{position:relative;z-index:1;margin-top:24px;display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
.flow-step{position:relative;background:linear-gradient(180deg,#ffffff 0%, #fbfcff 100%);border:1px solid var(--line);border-radius:24px;padding:18px;box-shadow:var(--shadow-soft);transition:.28s ease;}
.flow-step:hover{transform:translateY(-4px);box-shadow:0 18px 30px rgba(64,64,102,.14);}
.flow-step.active{border-color:#cfd5ef;box-shadow:0 0 0 4px rgba(64,64,102,.08), 0 16px 28px rgba(64,64,102,.14);}
.flow-step.done{border-color:#bbc4e6;box-shadow:0 0 0 4px rgba(64,64,102,.10), 0 16px 28px rgba(64,64,102,.18);}
.flow-step::before{content:"";position:absolute;top:0;left:0;right:0;height:5px;border-radius:24px 24px 0 0;background:linear-gradient(90deg,var(--step-accent),var(--step-accent-light));}
.flow-top{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;}
.flow-number{width:42px;height:42px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;color:#fff;box-shadow:0 10px 18px rgba(64,64,102,.14);background:linear-gradient(135deg,var(--step-accent),var(--step-accent-light));}
.flow-state{display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border-radius:999px;background:#f6f7fc;border:1px solid #e7eaf6;color:#67708e;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;}
.flow-icon{width:58px;height:58px;border-radius:18px;display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:14px;border:1px solid #e5e9f6;background:linear-gradient(180deg,#f3f0ff 0%, #eef4ff 100%);box-shadow:inset 0 1px 0 rgba(255,255,255,.65);}
.flow-title{font-size:17px;font-weight:800;color:var(--primary);margin-bottom:8px;line-height:1.35;}
.flow-text{font-size:13px;line-height:1.8;color:#606987;margin-bottom:12px;}
.flow-note{padding:10px 12px;border-radius:14px;background:#f8f9fd;border:1px solid #e6eaf5;font-size:12px;line-height:1.7;color:#5e6785;}
.error-box,.success-box{margin:0 0 18px;padding:14px 16px;border-radius:14px;font-size:14px;box-shadow:var(--shadow-soft);}
.error-box{background:#fff1f1;color:#a40000;border:1px solid #ffd0d0;}
.success-box{background:#effcf4;color:#0f6b3b;border:1px solid #c9f0d9;}
.section-title{margin:0 0 12px;font-size:24px;color:var(--primary);}
.simple-card{background:#fff;border:1px solid var(--line);border-radius:24px;box-shadow:var(--shadow);padding:22px;margin-bottom:22px;}
.small-help{color:var(--muted);font-size:13px;line-height:1.8;margin-bottom:14px;}
.type-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
.type-card{background:linear-gradient(135deg,#404066,#2B2C41);padding:18px;border-radius:18px;color:#fff;font-weight:700;display:flex;align-items:center;gap:12px;cursor:pointer;border:2px solid transparent;transition:.2s;box-shadow:0 10px 22px rgba(64,64,102,.18);}
.type-card:hover{transform:translateY(-2px);}
.type-card.active{border-color:#c8cce2;box-shadow:0 0 0 4px rgba(64,64,102,.14), 0 14px 28px rgba(64,64,102,.22);}
.type-card input{accent-color:white;}
.specialty-icon{width:40px;height:40px;min-width:40px;border-radius:13px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:inset 0 1px 0 rgba(255,255,255,.18);}
.specialty-label{display:flex;align-items:center;gap:12px;}
.filters-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px;}
.filters-grid select,.filters-grid input,.duration-box select{width:100%;padding:13px 14px;border-radius:14px;border:1px solid var(--line);background:#fff;font-family:'Poppins',sans-serif;outline:none;}
.doctors-scroll,.caregivers-scroll{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;}
.doctor-card{background:#fff;color:#2d3048;border-radius:20px;padding:16px;box-shadow:var(--shadow-soft);border:2px solid transparent;cursor:pointer;transition:.18s;position:relative;}
.doctor-card:hover{transform:translateY(-3px);}
.doctor-card.selected{border-color:#b8bfdc;box-shadow:0 0 0 4px rgba(64,64,102,.12), 0 16px 30px rgba(64,64,102,.12);}
.doctor-card input{position:absolute;opacity:0;pointer-events:none;}
.doctor-top{display:flex;align-items:center;gap:14px;margin-bottom:12px;}
.doctor-card img{width:82px;height:82px;border-radius:22px;object-fit:cover;background:linear-gradient(180deg,#f8f9fd 0%, #eef1f8 100%);border:1px solid #e4e8f3;box-shadow:0 8px 18px rgba(64,64,102,.10);padding:4px;}
.doctor-name{font-size:17px;font-weight:800;}
.doctor-sub{color:#6f7b95;font-size:13px;margin-top:4px;display:flex;align-items:center;gap:6px;}
.doctor-stats{display:flex;flex-wrap:wrap;gap:8px;}
.stat-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 11px;border-radius:999px;background:var(--primary-soft);border:1px solid #dddff0;font-size:12px;font-weight:700;color:#3d425f;}
.booking-layout{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;}
.calendar{background:#fff;padding:22px;border-radius:22px;border:1px solid var(--line);box-shadow:var(--shadow-soft);}
.calendar-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;font-weight:800;color:var(--primary);}
.calendar-header button{background:var(--primary-soft);border:none;width:40px;height:40px;border-radius:12px;cursor:pointer;font-weight:800;color:var(--primary);}
.calendar-header button:hover{background:#e3e5f1;}
.weekdays{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:10px;}
.weekday{text-align:center;font-size:12px;font-weight:700;color:#7a809a;padding:6px 0;}
.calendar-days{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;}
.day{min-height:50px;padding:10px;text-align:center;border-radius:14px;cursor:pointer;position:relative;border:1px solid #e3e6f1;background:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;transition:.18s;}
.day:hover{background:#f8f9fd;transform:translateY(-1px);}
.day.active{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 10px 18px rgba(64,64,102,.18);}
.day.fully-booked{background:#fff3f3;border-color:#ffd7d7;}
.day.fully-booked::after{content:"";width:8px;height:8px;border-radius:50%;background:var(--red);position:absolute;bottom:6px;left:50%;transform:translateX(-50%);}
.day.past{background:#f3f4f8;color:#b0b4c4;border-color:#eceef5;cursor:not-allowed;pointer-events:none;}
.day.empty{visibility:hidden;pointer-events:none;}
.calendar-note{margin-top:14px;font-size:13px;color:#6f7b95;}
.duration-box{background:#f8f9fd;border:1px solid var(--line);border-radius:20px;padding:16px;margin-bottom:16px;}
.duration-label{font-weight:800;color:#404066;display:block;margin-bottom:8px;}
.price-preview{margin-top:10px;font-size:15px;font-weight:800;color:#404066;}
.side-stack{display:flex;flex-direction:column;gap:16px;}
.info-card,.selected-doctor-box,.slots-panel{background:#fff;border:1px solid var(--line);color:#333;padding:20px;border-radius:22px;box-shadow:var(--shadow-soft);}
.info-title,.selected-doctor-title{font-size:12px;color:#6f7b95;margin-bottom:10px;font-weight:700;text-transform:uppercase;letter-spacing:.35px;}
.info-value{font-size:24px;font-weight:800;color:var(--primary);}
.small-note{font-size:12px;color:#6f7b95;margin-top:8px;line-height:1.7;}
.selected-doctor-empty{color:#7f819a;font-size:14px;}
.selected-doctor-content{display:flex;gap:14px;align-items:center;}
.selected-doctor-content img{width:82px;height:82px;border-radius:22px;object-fit:cover;background:linear-gradient(180deg,#f8f9fd 0%, #eef1f8 100%);border:1px solid #e4e8f3;box-shadow:0 8px 18px rgba(64,64,102,.10);padding:4px;}
.selected-doctor-name{font-size:17px;font-weight:800;color:var(--primary);}
.selected-doctor-meta{margin-top:4px;color:#6f7b95;font-size:14px;line-height:1.8;}
.selected-price{display:inline-block;margin-top:8px;padding:8px 12px;border-radius:999px;background:var(--primary-soft);color:var(--primary);font-weight:800;font-size:13px;}
.slots-panel{margin-top:16px;}
.slots-title{font-size:18px;font-weight:800;color:var(--primary);}
.slots-subtitle{color:#6f7b95;font-size:13px;margin-top:4px;}
.slots-section{margin-top:16px;padding-top:16px;border-top:1px solid #edf1f7;}
.slots-section:first-child{margin-top:0;padding-top:0;border-top:none;}
.slot-section-title{font-size:14px;font-weight:800;margin-bottom:10px;color:var(--primary);}
.slot-grid{display:flex;flex-wrap:wrap;gap:10px;}
.slot-chip{border:none;cursor:pointer;padding:10px 14px;border-radius:999px;font-weight:800;font-family:'Poppins',sans-serif;transition:.18s;}
.slot-chip.available{background:var(--green-soft);color:var(--green);border:1px solid var(--green-line);}
.slot-chip.available:hover{transform:translateY(-1px);background:#e4f8eb;}
.slot-chip.booked{background:var(--red-soft);color:var(--red);border:1px solid var(--red-line);cursor:not-allowed;}
.slot-chip.selected{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 10px 18px rgba(64,64,102,.18);}
.no-slots{color:#6f7b95;font-size:14px;}
.selection-summary{margin-top:18px;padding:14px 16px;background:#f6f7fb;border:1px solid var(--line);border-radius:16px;color:#3d425f;font-size:14px;line-height:1.8;}
.details-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.details-grid input{padding:14px 14px;border-radius:14px;border:1px solid var(--line);background:#fff;font-size:14px;font-family:'Poppins',sans-serif;outline:none;}
.submit-area{text-align:center;margin:28px 0 10px;}
.submit-area button{background:linear-gradient(135deg,#404066,#2B2C41);color:#fff;padding:16px 52px;border:none;border-radius:16px;cursor:pointer;font-size:16px;font-weight:800;box-shadow:0 14px 25px rgba(64,64,102,.22);}
.submit-area button:hover{transform:translateY(-1px);}

.booked-card{
    width:100%;
    padding:16px 18px;
    border-radius:18px;
    background:#f8f9fd;
    border:1px solid var(--line);
    color:#2B2C41;
    line-height:1.9;
    font-size:14px;
    font-weight:600;
    box-shadow:var(--shadow-soft);
}
.booked-card strong{
    font-weight:900;
    color:var(--primary);
}
.booked-card .booked-name{
    font-size:16px;
    font-weight:900;
    color:var(--primary);
    margin-bottom:4px;
}
.booked-card .money-line{
    margin-top:6px;
    padding-top:6px;
    border-top:1px solid #e6e9f2;
}

@media (max-width:1100px){.steps-flow{grid-template-columns:repeat(2,1fr)}}
@media (max-width:980px){.booking-layout{grid-template-columns:1fr}.details-grid{grid-template-columns:1fr}.type-grid{grid-template-columns:1fr}.filters-grid{grid-template-columns:1fr}.page-wrap{width:min(1180px, calc(100% - 16px))}.top-box h1{font-size:28px}}
@media (max-width:640px){.steps-flow{grid-template-columns:1fr}}
.side-stack .slots-panel{margin-top:0}
.booked-hidden{display:none!important;}

.shift-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:14px;
}
.shift-card{
    position:relative;
    display:flex;
    align-items:center;
    gap:14px;
    min-height:96px;
    padding:18px;
    border-radius:20px;
    background:linear-gradient(135deg,#404066,#2B2C41);
    color:#fff;
    border:2px solid transparent;
    cursor:pointer;
    box-shadow:0 10px 22px rgba(64,64,102,.18);
    transition:.2s ease;
}
.shift-card:hover{
    transform:translateY(-2px);
}
.shift-card.selected{
    border-color:#c8cce2;
    box-shadow:0 0 0 4px rgba(64,64,102,.14), 0 14px 28px rgba(64,64,102,.22);
}
.shift-card input{
    position:absolute;
    opacity:0;
    pointer-events:none;
}
.shift-icon{
    width:46px;
    height:46px;
    border-radius:15px;
    background:rgba(255,255,255,.16);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    flex:0 0 auto;
}
.shift-title{
    font-size:17px;
    font-weight:900;
    line-height:1.25;
}
.shift-sub{
    margin-top:4px;
    font-size:12px;
    color:rgba(255,255,255,.78);
    font-weight:600;
}

</style>

<style>
.caregivers-scroll{
    display:grid !important;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr)) !important;
    gap:16px !important;
    overflow:visible !important;
}
.caregiver-card{
    min-height:180px !important;
    height:auto !important;
    display:flex !important;
    flex-direction:column !important;
    justify-content:space-between !important;
    padding:20px !important;
    border-radius:22px !important;
    background:#fff !important;
    border:1px solid #e6e9f2 !important;
    box-shadow:0 8px 20px rgba(64,64,102,.08) !important;
}
.caregiver-card.selected{
    border-color:#404066 !important;
    box-shadow:0 14px 35px rgba(64,64,102,.16) !important;
}
.caregiver-top{
    display:flex !important;
    align-items:center !important;
    gap:14px !important;
}
.caregiver-top img{
    width:82px !important;
    height:82px !important;
    border-radius:22px !important;
    object-fit:cover !important;
    background:#f4f6fc !important;
    flex:0 0 82px !important;
}
.caregiver-card input[type="radio"]{
    position:absolute !important;
    opacity:0 !important;
    pointer-events:none !important;
}
.caregiver-stats{
    display:flex !important;
    flex-wrap:wrap !important;
    gap:8px !important;
    margin-top:16px !important;
}
</style>

</head>
<body>
<?php include '../general/nav_patient.php'; ?>

<div class="page-wrap">
    <section class="top-box">
        <div class="top-content">
            <div class="top-badge">Caregiver booking guide</div>
            <h1>Book your caregiver</h1>
            <p>Choose a shift preference, then choose your caregiver, select a suitable duration, and pick one available appointment slot.</p>
            <div class="steps-flow">
                <div class="flow-step active" id="stepIndicator1">
                    <div class="flow-top"><div class="flow-number">1</div><div class="flow-state">Start</div></div>
                    <div class="flow-icon"><i class="fa-solid fa-clock"></i></div>
                    <div class="flow-title">Choose shift preference</div>
                    <div class="flow-text">Select the caregiver shift you prefer first.</div>
                    <div class="flow-note">Shifts are loaded directly from the database.</div>
                </div>
                <div class="flow-step" id="stepIndicator2">
                    <div class="flow-top"><div class="flow-number">2</div><div class="flow-state">Next</div></div>
                    <div class="flow-icon"><i class="fa-solid fa-handshake"></i></div>
                    <div class="flow-title">Choose caregiver</div>
                    <div class="flow-text">Pick a caregiver from the selected shift.</div>
                    <div class="flow-note">You can still filter by rating, gender, and name.</div>
                </div>
                <div class="flow-step" id="stepIndicator3">
                    <div class="flow-top"><div class="flow-number">3</div><div class="flow-state">Time</div></div>
                    <div class="flow-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <div class="flow-title">Choose date & time</div>
                    <div class="flow-text">Select the day you want, then choose one available time slot.</div>
                    <div class="flow-note">Green means available appointment.</div>
                </div>
                <div class="flow-step" id="stepIndicator4">
                    <div class="flow-top"><div class="flow-number">4</div><div class="flow-state">Finish</div></div>
                    <div class="flow-icon"><i class="fa-solid fa-file-lines"></i></div>
                    <div class="flow-title">Fill your details</div>
                    <div class="flow-text">Check your phone, email, and address before payment.</div>
                    <div class="flow-note">The request status starts as pending.</div>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($submit_error)): ?><div class="error-box"><strong>Submit Error:</strong> <?= h($submit_error) ?></div><?php endif; ?>
    <?php if (!empty($submit_success)): ?><div class="success-box"><strong>Success:</strong> <?= h($submit_success) ?></div><?php endif; ?>

    <form method="POST" id="bookingForm">
        <input type="hidden" name="date" id="selectedDate">
        <input type="hidden" name="booking_time" id="bookingTimeHidden">
        <input type="hidden" name="service_time" id="serviceTimeHidden">
        <input type="hidden" name="duration_minutes" id="durationMinutesHidden" value="30">
        <input type="hidden" name="provider_id" id="providerIdHidden" value="">

        <section id="step1Section" class="simple-card">
            <h2 class="section-title">Step 1 — Choose Shift Preference</h2>
            <div class="small-help">Choose the shift preference first. The list below comes from the caregiver records in the database.</div>
            <input type="hidden" name="shift_preference" id="shiftPreferenceHidden" value="">
            <div class="shift-grid" id="shiftPreferenceContainer"></div>
        </section>

        <section id="step2Section" class="simple-card">
            <h2 class="section-title">Step 2 — Choose Caregiver</h2>
            <div class="small-help">Caregivers will appear after you choose a shift preference.</div>
            <div class="filters-grid">
                <select id="genderFilter"><option value="">All Genders</option><option value="male">Male Caregivers</option><option value="female">Female Caregivers</option></select>
                <select id="sortCaregivers"><option value="default">Sort: Default</option><option value="rating_desc">Highest Rating</option><option value="name_asc">Name A-Z</option></select>
                <input type="text" id="caregiverSearch" placeholder="Search by caregiver name">
            </div>
            <div class="caregivers-scroll" id="caregiverContainer"></div>
        </section>

        <section id="step3Section" class="simple-card">
            <h2 class="section-title">Step 3 — Choose Date & Appointment</h2>
            <div class="small-help">Choose duration first, then choose a day and available start time.</div>

            <div class="duration-box">
                <label class="duration-label">Session Duration</label>
                <select id="durationSelect">
                    <option value="30">30 minutes</option>
                    <option value="60">1 hour</option>
                    <option value="90">1 hour 30 minutes</option>
                    <option value="120">2 hours</option>
                    <option value="150">2 hours 30 minutes</option>
                    <option value="180">3 hours</option>
                    <option value="210">3 hours 30 minutes</option>
                    <option value="240">4 hours</option>
                    <option value="270">4 hours 30 minutes</option>
                    <option value="300">5 hours</option>
                    <option value="330">5 hours 30 minutes</option>
                    <option value="360">6 hours</option>
                    <option value="390">6 hours 30 minutes</option>
                    <option value="420">7 hours</option>
                    <option value="450">7 hours 30 minutes</option>
                    <option value="480">8 hours</option>
                </select>
                <div class="price-preview" id="pricePreview" style="display:none;"></div>
            </div>

            <div class="booking-layout">
                <div>
                    <div class="calendar">
                        <div class="calendar-header"><button type="button" onclick="prevMonth()">◀</button><div id="monthYear"></div><button type="button" onclick="nextMonth()">▶</button></div>
                        <div class="weekdays"><div class="weekday">Sun</div><div class="weekday">Mon</div><div class="weekday">Tue</div><div class="weekday">Wed</div><div class="weekday">Thu</div><div class="weekday">Fri</div><div class="weekday">Sat</div></div>
                        <div class="calendar-days" id="calendarDays"></div>
                        <div class="calendar-note">Days turn red only when all appointments for that day are taken.</div>
                    </div>
                </div>
                <div class="side-stack">
                    <div class="slots-panel" id="slotsPanel" style="display:none;">
                        <div class="slots-header"><div class="slots-title" id="slotsTitle">Caregiver Schedule</div><div class="slots-subtitle" id="slotsSubtitle">Choose caregiver and date first</div></div>
                        <div class="slots-section"><div class="slot-section-title">Available Appointments</div><div class="slot-grid" id="availableSlots"></div></div>
                        <div class="slots-section"><div class="slot-section-title">Booked Appointments</div><div class="slot-grid" id="bookedSlots"></div></div>
                        <div class="selection-summary" id="selectionSummary">No appointment selected yet.</div>
                    </div>
                    <div style="display:none;">
                        <span id="selectedTimeText">--:--</span>
                        <span id="selectedEndText">--:--</span>
                        <div id="selectedCaregiverPreview" class="selected-caregiver-empty">No caregiver selected yet.</div>
                    </div>
                </div>
            </div>
        </section>

        <section id="step4Section" class="simple-card">
            <h2 class="section-title">Step 4 — Your Details</h2>
            <div class="small-help">Your saved account information is filled automatically when available.</div>
            <div class="details-grid">
                <input type="text" name="fullname" required placeholder="Full Name" value="<?= h(postv('fullname') ?? $prefillFullname) ?>">
                <input type="text" name="phone" required placeholder="Phone Number" value="<?= h(postv('phone') ?? $prefillPhone) ?>">
                <input type="email" name="email" required placeholder="Email Address" value="<?= h(postv('email') ?? $prefillEmail) ?>">
                <input type="text" name="address" required placeholder="Home Address" value="<?= h(postv('address') ?? $prefillAddress) ?>">
            </div>
            <div class="submit-area"><button type="submit" name="submit_booking" value="1">Continue to Payment</button></div>
        </section>
    </form>
</div>

<script>

const caregiversData = <?= json_encode($caregivers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const shiftPreferencesData = <?= json_encode($shiftPreferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const SLOT_DURATION = <?= (int)$SLOT_DURATION ?>;
const HALF_HOUR_RATE = <?= (int)$HALF_HOUR_RATE ?>;
const WORK_END = "<?= h($WORK_END) ?>";
const SERVER_NOW_DATE = "<?= date('Y-m-d') ?>";
const SERVER_NOW_TIME = "<?= date('H:i') ?>";
const TODAY = "<?= h($todayYmd) ?>";
const TOTAL_SLOTS_PER_DAY = <?= (int)$TOTAL_SLOTS_PER_DAY ?>;

const shiftPreferenceContainer = document.getElementById("shiftPreferenceContainer");
const shiftPreferenceHidden = document.getElementById("shiftPreferenceHidden");
const container = document.getElementById("caregiverContainer");
const selectedCaregiverPreview = document.getElementById("selectedCaregiverPreview");
const selectedDateInput = document.getElementById("selectedDate");
const providerIdHidden = document.getElementById("providerIdHidden");
const bookingTimeHidden = document.getElementById("bookingTimeHidden");
const serviceTimeHidden = document.getElementById("serviceTimeHidden");
const durationMinutesHidden = document.getElementById("durationMinutesHidden");
const durationSelect = document.getElementById("durationSelect");
const pricePreview = document.getElementById("pricePreview");
const selectedTimeText = document.getElementById("selectedTimeText");
const selectedEndText = document.getElementById("selectedEndText");
const genderFilter = document.getElementById("genderFilter");
const sortCaregiversEl = document.getElementById("sortCaregivers");
const caregiverSearchEl = document.getElementById("caregiverSearch");
const slotsPanel = document.getElementById("slotsPanel");
const slotsTitle = document.getElementById("slotsTitle");
const slotsSubtitle = document.getElementById("slotsSubtitle");
const availableSlotsEl = document.getElementById("availableSlots");
const bookedSlotsEl = document.getElementById("bookedSlots");
const selectionSummary = document.getElementById("selectionSummary");
const step1Indicator = document.getElementById("stepIndicator1");
const step2Indicator = document.getElementById("stepIndicator2");
const step3Indicator = document.getElementById("stepIndicator3");
const step4Indicator = document.getElementById("stepIndicator4");

let currentDate = new Date();
let fullyBookedMap = {};
let selectedPrice = 0;
let selectedShiftPreference = "";
let selectedProviderId = 0;
let selectedProviderName = "";
let selectedProviderShift = "";
let selectedProviderRating = "";
let selectedProviderGender = "";
let selectedProviderImg = "";
let selectedProviderIcon = "fa-handshake";
let selectedSlot = null;
let lastSlotsData = null;

function pad2(n){ return String(n).padStart(2,'0'); }
function timeToMinutesJs(time){ const [h,m] = String(time).split(":").map(Number); return h * 60 + m; }
function minutesToTimeJs(minutes){ return `${String(Math.floor(minutes / 60)).padStart(2,'0')}:${String(minutes % 60).padStart(2,'0')}`; }
function addMinutesToTime(time, minutesToAdd){ return minutesToTimeJs(timeToMinutesJs(time) + minutesToAdd); }
function rangesOverlap(aStart, aEnd, bStart, bEnd){ return aStart < bEnd && aEnd > bStart; }
function getSelectedDuration(){ return parseInt(durationSelect.value, 10) || 30; }
function getSelectedTotal(){ return Math.ceil(getSelectedDuration() / 30) * HALF_HOUR_RATE; }

function calculateTotalForDuration(minutes){
    return Math.ceil(minutes / 30) * HALF_HOUR_RATE;
}
function formatMoney(value){
    const n = Number(value) || 0;
    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
}
function getRevenueBreakdown(total){
    return {
        commission: total * 0.15,
        provider: total * 0.85
    };
}
function cleanProviderName(name, fallback){
    const text = String(name || '').trim();
    if(!text || /#\d+/.test(text)) return fallback;
    return text.replace(/\s*#\d+\s*/g, '').trim() || fallback;
}

function formatDuration(minutes){ if(minutes < 60) return `${minutes} minutes`; const h = Math.floor(minutes / 60); const m = minutes % 60; if(m === 0) return `${h} hour${h > 1 ? 's' : ''}`; return `${h} hour${h > 1 ? 's' : ''} ${m} minutes`; }
function updatePricePreview(){
    const duration = getSelectedDuration();
    const total = getSelectedTotal();
    durationMinutesHidden.value = duration;
    if(pricePreview) pricePreview.innerText = `Total: ${total} EGP`;
    if(selectedSlot){
        selectedSlot.to = addMinutesToTime(selectedSlot.from, duration);
        bookingTimeHidden.value = selectedSlot.from;
        serviceTimeHidden.value = selectedSlot.to;
        selectedTimeText.innerText = selectedSlot.from;
        selectedEndText.innerText = selectedSlot.to;
        updateSelectionSummary();
    }
}
function scrollToSection(id){ const el = document.getElementById(id); if(el) el.scrollIntoView({behavior:"smooth", block:"start"}); }
function setStepState(el,state){ el.classList.remove("active","done"); if(state) el.classList.add(state); }
function escapeHtml(s){ return String(s ?? "").replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
function apiUrl(params){ const u = new URL(window.location.origin + window.location.pathname); for(const [k,v] of Object.entries(params)){ if(v !== undefined && v !== null && v !== "") u.searchParams.set(k,v); } return u.toString(); }

function updateTopSteps(){
    const step1Done = !!selectedShiftPreference;
    const step2Done = !!selectedProviderId;
    const step3Done = !!selectedDateInput.value && !!selectedSlot;
    const detailInputs = document.querySelectorAll('#step4Section input[required]');
    const detailsFilled = [...detailInputs].every(inp => String(inp.value || '').trim() !== "");
    const step4Done = step3Done && detailsFilled;

    if(!step1Done){
        setStepState(step1Indicator,"active");
        setStepState(step2Indicator,"");
        setStepState(step3Indicator,"");
        setStepState(step4Indicator,"");
        return;
    }

    setStepState(step1Indicator,"done");

    if(!step2Done){
        setStepState(step2Indicator,"active");
        setStepState(step3Indicator,"");
        setStepState(step4Indicator,"");
        return;
    }

    setStepState(step2Indicator,"done");

    if(!step3Done){
        setStepState(step3Indicator,"active");
        setStepState(step4Indicator,"");
        return;
    }

    setStepState(step3Indicator,"done");
    setStepState(step4Indicator, step4Done ? "done" : "active");
}

function clearBookingError(){
    const box = document.getElementById("bookingErrorBox");
    if(box) box.remove();
}

function applyCaregiverFilters(){
    let list = JSON.parse(JSON.stringify(caregiversData));
    const gender = genderFilter.value.trim().toLowerCase();
    const shift = selectedShiftPreference.trim().toLowerCase();
    const search = caregiverSearchEl.value.trim().toLowerCase();
    const sortBy = sortCaregiversEl.value;

    if(shift) list = list.filter(c => String(c.shift || '').trim().toLowerCase() === shift);
    else list = [];

    if(gender) list = list.filter(c => String(c.gender).toLowerCase() === gender);
    if(search) list = list.filter(c => String(c.name).toLowerCase().includes(search));

    if(sortBy === "rating_desc") list.sort((a,b) => {
        const ar = isNaN(parseFloat(a.rating)) ? 0 : parseFloat(a.rating);
        const br = isNaN(parseFloat(b.rating)) ? 0 : parseFloat(b.rating);
        return br - ar;
    });
    else if(sortBy === "name_asc") list.sort((a,b) => String(a.name).localeCompare(String(b.name)));

    return list;
}


function shiftIconByName(shift){
    const s = String(shift || '').toLowerCase();
    if(s.includes('morning')) return 'fa-sun';
    if(s.includes('afternoon')) return 'fa-cloud-sun';
    if(s.includes('evening')) return 'fa-moon';
    if(s.includes('night')) return 'fa-circle-half-stroke';
    return 'fa-clock';
}

function renderShiftPreferences(){
    if(!shiftPreferenceContainer) return;

    shiftPreferenceContainer.innerHTML = "";

    if(!shiftPreferencesData.length){
        shiftPreferenceContainer.innerHTML = `
            <div style="grid-column:1/-1;background:#fff;color:#444;padding:18px;border-radius:18px;text-align:center;font-weight:600;border:1px solid #e6e9f2;">
                No shift preferences found in the database.
            </div>
        `;
        return;
    }

    shiftPreferencesData.forEach(shift => {
        const selectedClass = String(shift).trim().toLowerCase() === selectedShiftPreference.trim().toLowerCase() ? "selected" : "";
        const checked = selectedClass ? "checked" : "";
        const count = caregiversData.filter(c => String(c.shift || '').trim().toLowerCase() === String(shift).trim().toLowerCase()).length;

        shiftPreferenceContainer.innerHTML += `
            <label class="shift-card ${selectedClass}">
                <input type="radio" name="shift_preference_select" value="${escapeHtml(shift)}" ${checked}>
                <div class="shift-icon"><i class="fa-solid ${shiftIconByName(shift)}"></i></div>
                <div>
                    <div class="shift-title">${escapeHtml(shift)}</div>
                    <div class="shift-sub">${count} caregiver${count === 1 ? '' : 's'} available</div>
                </div>
            </label>
        `;
    });
}

function resetCaregiverSelection(){
    selectedProviderId = 0;
    selectedProviderName = "";
    selectedProviderShift = "";
    selectedProviderRating = "";
    selectedProviderGender = "";
    selectedProviderImg = "";
    selectedProviderIcon = "fa-handshake";
    providerIdHidden.value = "";
    selectedDateInput.value = "";
    lastSlotsData = null;
    if(slotsPanel) slotsPanel.style.display = "none";
    resetSelectedSlot();
    renderSelectedCaregiverPreview();
}

function chooseShiftPreference(radio){
    selectedShiftPreference = String(radio.value || '').trim();
    if(shiftPreferenceHidden) shiftPreferenceHidden.value = selectedShiftPreference;

    document.querySelectorAll(".shift-card").forEach(card => card.classList.remove("selected"));
    radio.closest(".shift-card")?.classList.add("selected");
    radio.checked = true;

    clearBookingError();
    resetCaregiverSelection();
    renderCaregivers();
    updateTopSteps();
    scrollToSection("step2Section");
}

function renderCaregivers(){
    container.innerHTML = "";

    const list = applyCaregiverFilters();

    if(!list.length){
        container.innerHTML = `
            <div style="grid-column:1/-1;background:#fff;color:#444;padding:18px;border-radius:18px;text-align:center;font-weight:600;border:1px solid #e6e9f2;">
                No caregivers found for this shift with the selected filters.
            </div>
        `;
        updateTopSteps();
        return;
    }

    list.forEach(c => {
        const checked = Number(c.id) === Number(selectedProviderId) ? "checked" : "";
        const selectedClass = Number(c.id) === Number(selectedProviderId) ? "selected" : "";

        container.innerHTML += `
            <label class="caregiver-card ${selectedClass}">
                <input
                    type="radio"
                    name="caregiver_select"
                    value="${c.id}"
                    data-id="${c.id}"
                    data-name="${escapeHtml(c.name)}"
                    data-shift="${escapeHtml(c.shift || 'Flexible')}"
                    data-rating="${c.rating}"
                    data-gender="${escapeHtml(c.gender)}"
                    data-img="${c.img}"
                    data-icon="${escapeHtml(c.icon || 'fa-handshake')}"
                    ${checked}
                >

                <div class="caregiver-top">
                    <img src="${c.img}" alt="${escapeHtml(c.name)}">
                    <div>
                        <div class="caregiver-name">${escapeHtml(c.name)}</div>
                        <div class="caregiver-sub">
                            <span><i class="fa-solid ${escapeHtml(c.icon || 'fa-handshake')}"></i></span>
                            <span>${escapeHtml(c.shift || 'Flexible')}</span>
                        </div>
                    </div>
                </div>

                <div class="caregiver-stats">
                    <span class="stat-pill"><i class="fa-solid fa-star"></i> ${c.rating}</span>
                    <span class="stat-pill">${c.gender === 'female' ? 'Female' : 'Male'}</span>
                </div>
            </label>
        `;
    });

    updateTopSteps();
}

function resetSelectedSlot(){
    selectedSlot = null;
    bookingTimeHidden.value = "";
    serviceTimeHidden.value = "";
    durationMinutesHidden.value = getSelectedDuration();
    selectedTimeText.innerText = "--:--";
    selectedEndText.innerText = "--:--";
    document.querySelectorAll(".slot-chip.available").forEach(x => x.classList.remove("selected"));
    updateSelectionSummary();
}

function updateSelectionSummary(){
    if(!selectedProviderId || !selectedDateInput.value || !selectedSlot){ selectionSummary.innerHTML = `No appointment selected yet.`; return; }
    selectionSummary.innerHTML = `<strong>${escapeHtml(selectedProviderName)}</strong><br>Shift: <strong>${escapeHtml(selectedProviderShift)}</strong><br>Date: <strong>${escapeHtml(selectedDateInput.value)}</strong><br>Duration: <strong>${escapeHtml(formatDuration(getSelectedDuration()))}</strong><br>Time: <strong>${escapeHtml(selectedSlot.from)} → ${escapeHtml(selectedSlot.to)}</strong><br>Total Payment: <strong>EGP ${formatMoney(getSelectedTotal())}</strong><br>Status: <strong>Pending</strong>`;
}

function renderSelectedCaregiverPreview(){
    if(!selectedProviderId){ selectedCaregiverPreview.className = "selected-caregiver-empty"; selectedCaregiverPreview.innerHTML = `No caregiver selected yet.`; return; }
    selectedCaregiverPreview.className = "";
    selectedCaregiverPreview.innerHTML = `<div class="selected-caregiver-content"><img src="${selectedProviderImg}" alt="${escapeHtml(selectedProviderName)}"><div><div class="selected-caregiver-name">${escapeHtml(selectedProviderName)}</div><div class="selected-caregiver-meta"><div><i class="fa-solid ${escapeHtml(selectedProviderIcon)}"></i> ${escapeHtml(selectedProviderShift)}</div><div><i class="fa-solid fa-star"></i> ${escapeHtml(selectedProviderRating)}</div><div>${selectedProviderGender === 'female' ? 'Female caregiver' : 'Male caregiver'}</div></div></div></div>`;
    updateTopSteps();
}

async function chooseCaregiver(radio){
    selectedPrice = 150;
    selectedProviderId = parseInt(radio.dataset.id,10) || 0;
    selectedProviderName = radio.dataset.name || "";
    selectedProviderShift = radio.dataset.shift || "Flexible";
    selectedProviderRating = radio.dataset.rating || "New";
    selectedProviderGender = radio.dataset.gender || "";
    selectedProviderImg = radio.dataset.img || "";
    selectedProviderIcon = radio.dataset.icon || "fa-handshake";

    providerIdHidden.value = selectedProviderId;
    clearBookingError();

    document.querySelectorAll(".caregiver-card").forEach(c => c.classList.remove("selected"));
    radio.closest(".caregiver-card")?.classList.add("selected");
    radio.checked = true;

    resetSelectedSlot();
    renderSelectedCaregiverPreview();
    await refreshCalendar();

    if(selectedDateInput.value){
        await refreshCaregiverDaySlots();
    }

    updateTopSteps();
    scrollToSection('step3Section');
}

async function fetchBookedDays(year, monthIndex){
    const url = apiUrl({action:"booked_days", year, month:monthIndex+1, provider_id:selectedProviderId ? selectedProviderId : ""});
    const res = await fetch(url, {cache:"no-store"});
    const data = await res.json();
    fullyBookedMap = {};
    if(data && !data.error && Array.isArray(data.booked_days)){
        const totalSlots = parseInt(data.total_slots_per_day || TOTAL_SLOTS_PER_DAY, 10);
        data.booked_days.forEach(row => { if((parseInt(row.cnt,10)||0) >= totalSlots) fullyBookedMap[row.d] = true; });
    }
}

function renderCalendar(){
    const y = currentDate.getFullYear();
    const m = currentDate.getMonth();

    document.getElementById("monthYear").innerText =
        currentDate.toLocaleString('en-US', {month:'long', year:'numeric'});

    const firstDay = new Date(y,m,1).getDay();
    const daysInMonth = new Date(y,m+1,0).getDate();
    const cal = document.getElementById("calendarDays");
    cal.innerHTML = "";

    for(let i=0;i<firstDay;i++){
        const empty = document.createElement("div");
        empty.className = "day empty";
        cal.appendChild(empty);
    }

    for(let i=1;i<=daysInMonth;i++){
        const d = document.createElement("div");
        d.className = "day";
        d.innerText = i;

        const dateStr = `${y}-${pad2(m+1)}-${pad2(i)}`;

        if(dateStr < TODAY){
            d.classList.add("past");
            d.title = "Past date cannot be booked";
        } else {
            if(fullyBookedMap[dateStr]){
                d.classList.add("fully-booked");
                d.title = "No appointments available on this day";
            }

            if(selectedDateInput.value === dateStr){
                d.classList.add("active");
            }

            d.onclick = async function(){
                if(!selectedProviderId){
                    showBookingError("Please choose a caregiver first.");
                    scrollToSection("step2Section");
                    return;
                }

                document.querySelectorAll(".day").forEach(x => x.classList.remove("active"));
                this.classList.add("active");

                selectedDateInput.value = dateStr;
                clearBookingError();
                resetSelectedSlot();

                await refreshCaregiverDaySlots();
                updateTopSteps();
            };
        }

        cal.appendChild(d);
    }
}

async function refreshCalendar(){
    renderCalendar();

    try{
        await fetchBookedDays(currentDate.getFullYear(), currentDate.getMonth());
    } catch(e){
        fullyBookedMap = {};
    }

    renderCalendar();
}
function nextMonth(){ currentDate.setMonth(currentDate.getMonth()+1); refreshCalendar(); }
function prevMonth(){ currentDate.setMonth(currentDate.getMonth()-1); refreshCalendar(); }

function canUseSlotForDuration(slot, bookedSlots){
    const duration = getSelectedDuration();
    const start = timeToMinutesJs(slot.from);
    const end = start + duration;
    const workEnd = timeToMinutesJs(WORK_END);
    if(end > workEnd) return false;
    for(const booked of bookedSlots){
        const bStart = timeToMinutesJs(booked.from);
        const bEnd = timeToMinutesJs(booked.to);
        if(rangesOverlap(start, end, bStart, bEnd)) return false;
    }
    return true;
}

function selectStartTimeFromSlot(startTime){
    if(!startTime){
        resetSelectedSlot();
        return;
    }

    const btn = document.querySelector(`.slot-chip.available[data-from="${startTime}"]`);

    if(!btn){
        resetSelectedSlot();
        return;
    }

    document.querySelectorAll(".slot-chip.available").forEach(x => x.classList.remove("selected"));
    btn.classList.add("selected");

    selectedSlot = {
        from: btn.dataset.from,
        to: btn.dataset.to
    };

    clearBookingError();

    durationMinutesHidden.value = getSelectedDuration();
    bookingTimeHidden.value = selectedSlot.from;
    serviceTimeHidden.value = selectedSlot.to;

    selectedTimeText.innerText = selectedSlot.from;
    selectedEndText.innerText = selectedSlot.to;

    updatePricePreview();
    updateSelectionSummary();
    renderSelectedCaregiverPreview();
    updateTopSteps();
}

function renderSlotsFromLastData(){
    if(!lastSlotsData) return;
    const data = lastSlotsData;
    const available = Array.isArray(data.available_slots) ? data.available_slots : [];
    const booked = Array.isArray(data.booked_slots) ? data.booked_slots : [];
    const previousStart = selectedSlot ? selectedSlot.from : "";
    const nowMinutes = timeToMinutesJs(SERVER_NOW_TIME);

        const filteredAvailable = available.filter(slot => {
            if (!canUseSlotForDuration(slot, booked)) {
                return false;
            }

            const slotStart = timeToMinutesJs(slot.from);

            if (selectedDateInput.value === SERVER_NOW_DATE && slotStart <= nowMinutes) {
                return false;
            }

            return true;
        });

    if(!filteredAvailable.length){
        availableSlotsEl.innerHTML = `<div class="no-slots">No available start times for ${escapeHtml(formatDuration(getSelectedDuration()))} on this date.</div>`;
    } else {
        availableSlotsEl.innerHTML = filteredAvailable.map(slot => {
            const end = addMinutesToTime(slot.from, getSelectedDuration());
            return `<button type="button" class="slot-chip available" data-from="${slot.from}" data-to="${end}">${slot.from} → ${end}</button>`;
        }).join('');
    }

    if(!booked.length){
        bookedSlotsEl.innerHTML = `<div class="no-slots">No booked appointments for this day.</div>`;
    } else {
        bookedSlotsEl.innerHTML = booked.map(slot => {
            const minutes = timeToMinutesJs(slot.to) - timeToMinutesJs(slot.from);
            const total = calculateTotalForDuration(minutes);
            const money = getRevenueBreakdown(total);
            return `<div class="booked-card">
                <div class="booked-name">${escapeHtml(selectedProviderName || 'Caregiver')}</div>
                <div>Duration: <strong>${escapeHtml(formatDuration(minutes))}</strong></div>
                <div>Time: <strong>${escapeHtml(slot.from)} → ${escapeHtml(slot.to)}</strong></div>
                <div class="money-line">Total Payment: <strong>EGP ${formatMoney(total)}</strong></div>
                <div>Status: <strong>Pending</strong></div>
            </div>`;
        }).join('');
    }

    if(previousStart){
        const stillExists = document.querySelector(`.slot-chip.available[data-from="${previousStart}"]`);
        if(stillExists) selectStartTimeFromSlot(previousStart);
        else resetSelectedSlot();
    } else {
        updateSelectionSummary();
    }
}

async function refreshCaregiverDaySlots(){
    const dateStr = selectedDateInput.value;
    if(!selectedProviderId){ slotsPanel.style.display = "block"; slotsTitle.innerText = "Caregiver Schedule"; slotsSubtitle.innerText = "Please choose a caregiver first."; availableSlotsEl.innerHTML = `<div class="no-slots">Select caregiver first to see available appointments.</div>`; bookedSlotsEl.innerHTML = `<div class="no-slots">No data yet.</div>`; updateSelectionSummary(); return; }
    if(!dateStr){ slotsPanel.style.display = "block"; slotsTitle.innerText = "Caregiver Schedule"; slotsSubtitle.innerText = "Please choose a date from the calendar."; availableSlotsEl.innerHTML = `<div class="no-slots">Choose a date first.</div>`; bookedSlotsEl.innerHTML = `<div class="no-slots">Choose a date first.</div>`; updateSelectionSummary(); return; }
    try{
        const res = await fetch(apiUrl({action:"caregiver_day_slots", date:dateStr, provider_id:selectedProviderId}), {cache:"no-store"});
        const data = await res.json();
        slotsPanel.style.display = "block";
        if(data.error){ slotsTitle.innerText = "Caregiver Schedule"; slotsSubtitle.innerText = data.message || "Failed to load slots."; availableSlotsEl.innerHTML = `<div class="no-slots">${escapeHtml(data.message || 'No data')}</div>`; bookedSlotsEl.innerHTML = `<div class="no-slots">No data.</div>`; updateSelectionSummary(); return; }
        lastSlotsData = data;
        slotsTitle.innerHTML = `<i class="fa-solid ${escapeHtml(data.provider_icon || 'fa-handshake')}"></i> ${escapeHtml(cleanProviderName(data.provider_name, selectedProviderName || 'Caregiver'))} Schedule`;
        slotsSubtitle.innerText = `${data.date} · Working hours ${data.work_start} - ${data.work_end}`;
        renderSlotsFromLastData();
    }catch(e){ slotsPanel.style.display = "block"; slotsTitle.innerText = "Caregiver Schedule"; slotsSubtitle.innerText = "Failed to load appointments."; availableSlotsEl.innerHTML = `<div class="no-slots">Network error.</div>`; bookedSlotsEl.innerHTML = `<div class="no-slots">No data.</div>`; }
}

availableSlotsEl.addEventListener("click", function(e){
    const btn = e.target.closest(".slot-chip.available");
    if(!btn) return;
    selectStartTimeFromSlot(btn.dataset.from);
});

durationSelect.addEventListener("change", function(){
    durationMinutesHidden.value = this.value;
    updatePricePreview();
    resetSelectedSlot();

    if(lastSlotsData && selectedProviderId && selectedDateInput.value){
        renderSlotsFromLastData();
    } else {
        updateSelectionSummary();
    }

    renderSelectedCaregiverPreview();
    updateTopSteps();
});

shiftPreferenceContainer.addEventListener("change", function(e){
    if(e.target && e.target.name === "shift_preference_select") chooseShiftPreference(e.target);
});
container.addEventListener("change", async function(e){ if(e.target && e.target.name === "caregiver_select") await chooseCaregiver(e.target); });
genderFilter.addEventListener("change", renderCaregivers);
sortCaregiversEl.addEventListener("change", renderCaregivers);
caregiverSearchEl.addEventListener("input", renderCaregivers);
document.querySelectorAll('#step4Section input[required]').forEach(el => { el.addEventListener('input', updateTopSteps); el.addEventListener('change', updateTopSteps); });

function showBookingError(message){
    let box = document.getElementById("bookingErrorBox");

    if(!box){
        box = document.createElement("div");
        box.id = "bookingErrorBox";
        box.className = "error-box";
        box.style.marginBottom = "18px";

        const form = document.getElementById("bookingForm");
        form.parentNode.insertBefore(box, form);
    }

    box.innerHTML = "<strong>Booking Error:</strong> " + escapeHtml(message);
    box.scrollIntoView({ behavior: "smooth", block: "center" });
}

document.getElementById("bookingForm").addEventListener("submit", function(e){
    clearBookingError();

    if(!providerIdHidden.value){
        e.preventDefault();
        showBookingError("Please choose a caregiver first.");
        scrollToSection("step1Section");
        return;
    }

    if(!selectedDateInput.value || !bookingTimeHidden.value || !serviceTimeHidden.value){
        e.preventDefault();
        showBookingError("Please choose an available start time first.");
        scrollToSection("step3Section");
        return;
    }
});

selectedPrice = 150;
updatePricePreview();
renderShiftPreferences();
renderCaregivers();
renderSelectedCaregiverPreview();
refreshCalendar();
updateSelectionSummary();
updateTopSteps();

window.addEventListener("load", function(){
    if(localStorage.getItem("rafiq_scroll_to_caregivers") === "1"){
        localStorage.removeItem("rafiq_scroll_to_caregivers");
        setTimeout(() => scrollToSection("step1Section"), 250);
    }
});
</script>

<?php include '../general/footer.php'; ?>
</body>
</html>

