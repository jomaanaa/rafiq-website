<?php
require __DIR__ . '/../pgdb/db.php';
header('Content-Type: application/json; charset=utf-8');

$pickup_lat = (float)($_GET['pickup_lat'] ?? 0);
$pickup_lng = (float)($_GET['pickup_lng'] ?? 0);
$dest_lat   = (float)($_GET['dest_lat'] ?? 0);
$dest_lng   = (float)($_GET['dest_lng'] ?? 0);

if (!$pickup_lat || !$pickup_lng || !$dest_lat || !$dest_lng) {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"missing coords"]);
  exit;
}

try {
  // OSRM expects: lon,lat;lon,lat
  $url = "https://router.project-osrm.org/route/v1/driving/"
      . rawurlencode($pickup_lng . "," . $pickup_lat . ";" . $dest_lng . "," . $dest_lat)
      . "?overview=false&alternatives=false&steps=false";

  $ctx = stream_context_create([
    "http" => [
      "method" => "GET",
      "header" => "User-Agent: RafiQ/1.0\r\nAccept: application/json\r\n",
      "timeout" => 8
    ]
  ]);

  $json = file_get_contents($url, false, $ctx);
  if ($json === false) throw new Exception("OSRM request failed");

  $data = json_decode($json, true);
  if (!is_array($data) || ($data["code"] ?? "") !== "Ok") {
    throw new Exception("OSRM bad response");
  }

  $route = $data["routes"][0] ?? null;
  if (!$route) throw new Exception("No route returned");

  $meters = (float)($route["distance"] ?? 0);
  $seconds = (float)($route["duration"] ?? 0);

  $km = round($meters / 1000, 2);
  $min = (int)round($seconds / 60);

  echo json_encode([
    "ok" => true,
    "distance_km" => $km,
    "duration_min" => $min
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}