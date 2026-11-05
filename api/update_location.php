<?php
// POST /api/update_location.php
//   {"latitude": 6.41, "longitude": 125.61}  -> save the driver's current position (tracking ON)
//   {"is_tracking": false}                   -> end collection (residents stop seeing the truck)
//
// Requires: Authorization: Bearer <token> (driver approved by the admin)

require_once __DIR__ . '/../includes/api_helpers.php';

$auth = require_auth();
if ($auth['user_type'] !== 'driver') {
    json_error('Only drivers can use this endpoint.', 403);
}
$driverId = (int) $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

// Only drivers verified by the admin may share a location.
$v = $conn->prepare("SELECT is_verified FROM driver WHERE id = ?");
$v->bind_param('i', $driverId);
$v->execute();
$drv = $v->get_result()->fetch_assoc();
if (!$drv || (int) $drv['is_verified'] !== 1) {
    json_error('Your driver account is not yet approved by the admin.', 403);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    json_error('Invalid JSON body.');
}

// End collection / pause tracking.
if (array_key_exists('is_tracking', $body) && $body['is_tracking'] === false) {
    $stmt = $conn->prepare("UPDATE driver_location SET is_tracking = 0 WHERE driver_id = ?");
    $stmt->bind_param('i', $driverId);
    $stmt->execute();
    json_ok(['message' => 'Collection ended.']);
}

// Save position.
if (!isset($body['latitude'], $body['longitude'])
    || !is_numeric($body['latitude']) || !is_numeric($body['longitude'])) {
    json_error('latitude and longitude are required numbers.');
}

$lat = (float) $body['latitude'];
$lng = (float) $body['longitude'];

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_error('Coordinates out of range.');
}

// One row per driver. truck_id is the table's primary key, so we derive it from the driver id.
$truckId = 'driver-' . $driverId;

$stmt = $conn->prepare(
    "INSERT INTO driver_location (truck_id, driver_id, latitude, longitude, is_tracking, updated_at)
     VALUES (?, ?, ?, ?, 1, NOW())
     ON DUPLICATE KEY UPDATE
        driver_id = VALUES(driver_id),
        latitude = VALUES(latitude),
        longitude = VALUES(longitude),
        is_tracking = 1,
        updated_at = NOW()"
);
$stmt->bind_param('sidd', $truckId, $driverId, $lat, $lng);
$stmt->execute();

json_ok(['message' => 'Location saved.']);
