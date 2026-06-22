<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json; charset=utf-8");
require __DIR__ . '/../pgdb/db.php';

$action = $_GET['action'] ?? '';

try {


    if ($action === 'nearby_places') {
        $lat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? floatval($_GET['lat']) : null;
        $lng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? floatval($_GET['lng']) : null;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? intval($_GET['limit']) : 12;
        $limit = max(4, min(30, $limit));

        $hasLocation = $lat !== null && $lng !== null;

        if ($hasLocation) {
            $sql = "
                SELECT
                    place_id,
                    osm_id,
                    name,
                    type,
                    address,
                    latitude,
                    longitude,
                    wheelchair,
                    elevator,
                    ramp,
                    toilet,
                    parking,
                    comment,
                    status,
                    rating,
                    ROUND(
                        (
                            6371 * ACOS(
                                LEAST(1, GREATEST(-1,
                                    COS(RADIANS(:lat)) *
                                    COS(RADIANS(latitude)) *
                                    COS(RADIANS(longitude) - RADIANS(:lng)) +
                                    SIN(RADIANS(:lat)) *
                                    SIN(RADIANS(latitude))
                                ))
                            )
                        )::numeric,
                        2
                    ) AS distance_km
                FROM place
                WHERE status = 'active'
                  AND latitude IS NOT NULL
                  AND longitude IS NOT NULL
                  AND (rating IS NULL OR rating = 0)
                ORDER BY distance_km ASC, rating DESC, place_id DESC
                LIMIT :limit
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':lat', $lat);
            $stmt->bindValue(':lng', $lng);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $sql = "
                SELECT
                    place_id,
                    osm_id,
                    name,
                    type,
                    address,
                    latitude,
                    longitude,
                    wheelchair,
                    elevator,
                    ramp,
                    toilet,
                    parking,
                    comment,
                    status,
                    rating,
                    NULL::numeric AS distance_km
                FROM place
                WHERE status = 'active'
                  AND latitude IS NOT NULL
                  AND longitude IS NOT NULL
                  AND (rating IS NULL OR rating = 0)
                ORDER BY place_id DESC
                LIMIT :limit
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
        }

        $toBool = function ($value) {
            $value = strtolower(trim((string)$value));
            return in_array($value, ['1', 'true', 't', 'yes', 'y'], true);
        };

        $places = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $features = [];
            if ($toBool($row['wheelchair'] ?? '')) $features[] = 'Wheelchair';
            if ($toBool($row['elevator'] ?? ''))   $features[] = 'Elevator';
            if ($toBool($row['ramp'] ?? ''))       $features[] = 'Ramp';
            if ($toBool($row['toilet'] ?? ''))     $features[] = 'Accessible Toilet';
            if ($toBool($row['parking'] ?? ''))    $features[] = 'Parking';

            $places[] = [
                'id'          => $row['osm_id'] ?: ('place_' . $row['place_id']),
                'place_id'    => intval($row['place_id']),
                'name'        => $row['name'] ?: 'Accessible place',
                'type'        => $row['type'] ?: 'Place',
                'address'     => $row['address'] ?: '',
                'latitude'    => floatval($row['latitude']),
                'longitude'   => floatval($row['longitude']),
                'distance_km' => $row['distance_km'] !== null ? floatval($row['distance_km']) : null,
                'rating'      => $row['rating'] !== null ? floatval($row['rating']) : 0,
                'comment'     => $row['comment'] ?: '',
                'features'    => $features,
                'wheelchair'  => $toBool($row['wheelchair'] ?? ''),
                'elevator'    => $toBool($row['elevator'] ?? ''),
                'ramp'        => $toBool($row['ramp'] ?? ''),
                'toilet'      => $toBool($row['toilet'] ?? ''),
                'parking'     => $toBool($row['parking'] ?? '')
            ];
        }

        echo json_encode([
            'success' => true,
            'places' => $places
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'submit_review') {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            echo json_encode(["success" => false, "message" => "No data received"]);
            exit;
        }

        $osm_id    = trim($data['osm_id'] ?? '');
        $name      = trim($data['name'] ?? '');
        $type      = trim($data['type'] ?? 'Place');
        $address   = trim($data['address'] ?? '');
        $lat       = isset($data['latitude']) ? floatval($data['latitude']) : null;
        $lng       = isset($data['longitude']) ? floatval($data['longitude']) : null;
        $comment   = trim($data['comment'] ?? '');
        $rating    = isset($data['rating']) ? intval($data['rating']) : null;

        if ($rating !== null) {
            $rating = max(1, min(5, $rating));
        }

        $wheelchair = !empty($data['wheelchair']) ? 'true' : 'false';
        $elevator   = !empty($data['elevator']) ? 'true' : 'false';
        $ramp       = !empty($data['ramp']) ? 'true' : 'false';
        $toilet     = !empty($data['toilet']) ? 'true' : 'false';
        $parking    = !empty($data['parking']) ? 'true' : 'false';

        if ($osm_id === '' || $name === '' || $lat === null || $lng === null) {
            echo json_encode([
                "success" => false,
                "message" => "Missing required fields"
            ]);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO place
                (osm_id, name, type, address, latitude, longitude,
                 wheelchair, elevator, ramp, toilet, parking,
                 comment, status, rating)
            VALUES
                (:osm_id, :name, :type, :address, :latitude, :longitude,
                 :wheelchair, :elevator, :ramp, :toilet, :parking,
                 :comment, 'active', :rating)
        ");

        $stmt->execute([
            ':osm_id'     => $osm_id,
            ':name'       => $name,
            ':type'       => $type,
            ':address'    => $address,
            ':latitude'   => $lat,
            ':longitude'  => $lng,
            ':wheelchair' => $wheelchair,
            ':elevator'   => $elevator,
            ':ramp'       => $ramp,
            ':toilet'     => $toilet,
            ':parking'    => $parking,
            ':comment'    => $comment,
            ':rating'     => $rating
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Review submitted successfully"
        ]);
        exit;
    }

    if ($action === 'reviewed_places') {
        $stmt = $pdo->query("
            SELECT
                osm_id,
                MAX(place_id) AS place_id,
                MAX(name) AS name,
                MAX(type) AS type,
                MAX(address) AS address,
                MAX(latitude) AS latitude,
                MAX(longitude) AS longitude,
                MAX(status) AS status,
                COUNT(*) AS review_count,

                ROUND(AVG(CASE WHEN rating IS NOT NULL AND rating > 0 THEN rating::numeric END), 2) AS avg_rating,

                SUM(CASE WHEN wheelchair = 'true' OR wheelchair = 't' THEN 1 ELSE 0 END) AS wheelchair_yes,
                SUM(CASE WHEN elevator = 'true' OR elevator = 't' THEN 1 ELSE 0 END) AS elevator_yes,
                SUM(CASE WHEN ramp = 'true' OR ramp = 't' THEN 1 ELSE 0 END) AS ramp_yes,
                SUM(CASE WHEN toilet = 'true' OR toilet = 't' THEN 1 ELSE 0 END) AS toilet_yes,
                SUM(CASE WHEN parking = 'true' OR parking = 't' THEN 1 ELSE 0 END) AS parking_yes,

                json_agg(comment ORDER BY place_id ASC)
                    FILTER (WHERE comment IS NOT NULL AND TRIM(comment) != '') AS comments_json

            FROM place
            WHERE status = 'active'
              AND osm_id IS NOT NULL
            GROUP BY osm_id
            ORDER BY MAX(place_id) DESC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $places = [];

        foreach ($rows as $row) {
            $count = intval($row['review_count']);

            $majority = function ($yes) use ($count) {
                return $count > 0 && (intval($yes) / $count) > 0.5;
            };

$comments = [];

if (!empty($row['comments_json'])) {
    $raw = json_decode($row['comments_json'], true);

    if (is_array($raw)) {
        foreach ($raw as $comment) {
            $clean = trim((string)$comment);

            $clean = preg_replace('/\[osm:[^\]]*\]\s*/i', '', $clean);
            $clean = preg_replace('/\|\s*Not wheelchair accessible\s*/i', '', $clean);
            $clean = preg_replace('/\|\s*Wheelchair accessible\s*/i', '', $clean);
            $clean = trim($clean);

            if ($clean !== '') {
                $comments[] = $clean;
            }
        }
    }
}

            $places[] = [
                "place_id"     => intval($row['place_id']),
                "osm_id"       => $row['osm_id'],
                "name"         => $row['name'],
                "type"         => $row['type'] ?? "Place",
                "address"      => $row['address'] ?? "",
                "latitude"     => floatval($row['latitude']),
                "longitude"    => floatval($row['longitude']),
                "wheelchair"   => $majority($row['wheelchair_yes']),
                "elevator"     => $majority($row['elevator_yes']),
                "ramp"         => $majority($row['ramp_yes']),
                "toilet"       => $majority($row['toilet_yes']),
                "parking"      => $majority($row['parking_yes']),
                "rating"       => $row['avg_rating'] !== null ? floatval($row['avg_rating']) : 0,
                "review_count" => $count,
                "comments"     => $comments,
                "status"       => $row['status']
            ];
        }

        echo json_encode($places);
        exit;
    }

    if ($action === 'place_features') {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['osm_ids']) || !is_array($data['osm_ids'])) {
            echo json_encode([]);
            exit;
        }

        $ids = array_values(array_filter($data['osm_ids'], function ($id) {
            return preg_match('/^[a-zA-Z]+_[0-9]+$/', $id);
        }));

        if (empty($ids)) {
            echo json_encode([]);
            exit;
        }

        $placeholders = [];
        $params = [];

        foreach ($ids as $i => $id) {
            $key = ":id$i";
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = "
            SELECT
                osm_id,
                COUNT(*) AS review_count,
                ROUND(AVG(CASE WHEN rating IS NOT NULL AND rating > 0 THEN rating::numeric END), 2) AS avg_rating,

                SUM(CASE WHEN wheelchair = 'true' OR wheelchair = 't' THEN 1 ELSE 0 END) AS wheelchair_yes,
                SUM(CASE WHEN elevator = 'true' OR elevator = 't' THEN 1 ELSE 0 END) AS elevator_yes,
                SUM(CASE WHEN ramp = 'true' OR ramp = 't' THEN 1 ELSE 0 END) AS ramp_yes,
                SUM(CASE WHEN toilet = 'true' OR toilet = 't' THEN 1 ELSE 0 END) AS toilet_yes,
                SUM(CASE WHEN parking = 'true' OR parking = 't' THEN 1 ELSE 0 END) AS parking_yes

            FROM place
            WHERE status = 'active'
              AND osm_id IN (" . implode(",", $placeholders) . ")
            GROUP BY osm_id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];

        foreach ($rows as $row) {
            $count = intval($row['review_count']);

            $majority = function ($yes) use ($count) {
                return $count > 0 && (intval($yes) / $count) > 0.5;
            };

            $result[$row['osm_id']] = [
                "wheelchair"   => $majority($row['wheelchair_yes']),
                "elevator"     => $majority($row['elevator_yes']),
                "ramp"         => $majority($row['ramp_yes']),
                "toilet"       => $majority($row['toilet_yes']),
                "parking"      => $majority($row['parking_yes']),
                "rating"       => $row['avg_rating'] !== null ? floatval($row['avg_rating']) : 0,
                "review_count" => $count
            ];
        }

        echo json_encode($result);
        exit;
    }

    echo json_encode([
        "success" => false,
        "message" => "Invalid action"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>