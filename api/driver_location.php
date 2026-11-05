<?php
// GET /api/driver_location.php  (auth: any logged-in resident or driver)
//   Optional query: ?barangay=Poblacion to only show trucks whose location
//   text contains that barangay.
//   -> returns the location of every truck that is currently collecting.
//
// Truck locations are WRITTEN only by update_location.php.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

require_auth();

$barangay = trim($_GET['barangay'] ?? '');
if ($barangay !== '') {
    $stmt = $conn->prepare(
        "SELECT dl.driver_id AS truck_id, dl.latitude, dl.longitude, dl.updated_at,
                d.first_name, d.last_name, d.location
         FROM driver_location dl
         JOIN driver d ON d.id = dl.driver_id
         WHERE dl.is_tracking = 1
           AND TIMESTAMPDIFF(SECOND, dl.updated_at, NOW()) <= 30
           AND LOCATE(LOWER(?), LOWER(d.location)) > 0"
    );
    $stmt->bind_param('s', $barangay);
    $stmt->execute();
    $locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $locations = $conn->query(
        "SELECT dl.driver_id AS truck_id, dl.latitude, dl.longitude, dl.updated_at,
                d.first_name, d.last_name, d.location
         FROM driver_location dl
         JOIN driver d ON d.id = dl.driver_id
         WHERE dl.is_tracking = 1
           AND TIMESTAMPDIFF(SECOND, dl.updated_at, NOW()) <= 30"
    )->fetch_all(MYSQLI_ASSOC);
}

json_ok(['locations' => $locations]);
