<?php
// POST /api/driver_location.php  (auth: driver only)
//   Body: { "latitude": 7.123456, "longitude": 125.123456 }
//   -> upserts the calling driver's truck location (truck_id = driver's own id).
//
// GET /api/driver_location.php  (auth: any logged-in resident or driver)
//   Optional query: ?barangay=Poblacion to only show trucks assigned to that barangay.
//   -> returns every driver's last known location, joined with driver name/barangay.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = require_auth();
    if ($payload['user_type'] !== 'driver') {
        json_error('Only drivers can update a truck location.', 403);
    }

    $body = get_json_body();
    $lat = $body['latitude'] ?? null;
    $lng = $body['longitude'] ?? null;
    if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
        json_error('Fields "latitude" and "longitude" (numeric) are required.');
    }

    // There's no separate "trucks" table in this schema, so we use the
    // driver's own id (as a string) as the truck_id.
    $truckId = (string) $payload['user_id'];

    $stmt = $conn->prepare(
        "INSERT INTO driver_location (truck_id, latitude, longitude)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude)"
    );
    $stmt->bind_param('sdd', $truckId, $lat, $lng);
    $stmt->execute();

    json_ok(['message' => 'Location updated.']);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Any logged-in resident or driver can view current truck locations (for the map screen).
    require_auth();

    $barangay = trim($_GET['barangay'] ?? '');
    if ($barangay !== '') {
        $stmt = $conn->prepare(
            "SELECT dl.truck_id, dl.latitude, dl.longitude, dl.updated_at,
                    d.first_name, d.last_name, d.assigned_barangay
             FROM driver_location dl
             JOIN driver d ON d.id = dl.truck_id
             WHERE d.assigned_barangay = ?"
        );
        $stmt->bind_param('s', $barangay);
        $stmt->execute();
        $locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $locations = $conn->query(
            "SELECT dl.truck_id, dl.latitude, dl.longitude, dl.updated_at,
                    d.first_name, d.last_name, d.assigned_barangay
             FROM driver_location dl
             JOIN driver d ON d.id = dl.truck_id"
        )->fetch_all(MYSQLI_ASSOC);
    }

    json_ok(['locations' => $locations]);

} else {
    json_error('Method not allowed. Use GET or POST.', 405);
}
