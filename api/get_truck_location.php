<?php
// GET /api/get_truck_location.php
//
// LOGGED-IN RESIDENT (Authorization: Bearer <token>):
//   -> the garbage truck(s) whose driver location text contains THIS
//      resident's barangay.
//
// GUEST (no Authorization header at all):
//   -> every truck. Guests can only view live tracking.
//
// A truck is returned for TRUCK_VISIBLE_SECONDS after its last update.
// The app decides the label:
//   is_tracking = 1 and age <= 20s  -> Live
//   is_tracking = 1 and age  > 20s  -> Offline (weak signal / outdated)
//   is_tracking = 0                 -> Driver paused tracking
//
// Response: {"success":true,"trucks":[{truck_id, driver_name, barangay, latitude,
//            longitude, is_tracking, age_seconds, updated_at}]}
//
// age_seconds is calculated by the database (NOW() - updated_at) so server
// timezone differences can't make a fresh location look old.

require_once __DIR__ . '/../includes/api_helpers.php';

// How long the last known position stays visible to residents.
const TRUCK_VISIBLE_SECONDS = 600; // 10 minutes

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

// Guest = no Authorization header at all. A header that is present but
// invalid/expired still goes through require_auth() and gets a 401.
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($authHeader === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $k => $v) {
        if (strtolower($k) === 'authorization') {
            $authHeader = $v;
            break;
        }
    }
}
$isGuest = trim($authHeader) === '';

$barangay = '';
if (!$isGuest) {
    $auth = require_auth();
    if ($auth['user_type'] !== 'resident') {
        json_error('Only residents can use this endpoint.', 403);
    }
    $residentId = (int) $auth['user_id'];

    // Find the resident's barangay.
    $stmt = $conn->prepare("SELECT barangay FROM resident WHERE id = ?");
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $resident = $stmt->get_result()->fetch_assoc();
    $barangay = trim($resident['barangay'] ?? '');

    if ($barangay === '') {
        json_ok(['trucks' => []]);
    }
}

// Only the driver's first name is exposed.
$select =
    "SELECT dl.driver_id,
            d.first_name,
            d.location,
            dl.latitude,
            dl.longitude,
            dl.is_tracking,
            dl.updated_at,
            TIMESTAMPDIFF(SECOND, dl.updated_at, NOW()) AS age_seconds
     FROM driver_location dl
     JOIN driver d ON d.id = dl.driver_id";

if ($isGuest) {
    $rows = $conn->query($select . "
         WHERE TIMESTAMPDIFF(SECOND, dl.updated_at, NOW()) <= " . TRUCK_VISIBLE_SECONDS . "
         ORDER BY dl.updated_at DESC")
         ->fetch_all(MYSQLI_ASSOC);
} else {
    $stmt = $conn->prepare(
        $select . "
         WHERE TIMESTAMPDIFF(SECOND, dl.updated_at, NOW()) <= " . TRUCK_VISIBLE_SECONDS . "
           AND LOCATE(LOWER(?), LOWER(d.location)) > 0
         ORDER BY dl.updated_at DESC"
    );
    $stmt->bind_param('s', $barangay);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$trucks = array_map(function ($r) {
    return [
        'truck_id'    => (string) $r['driver_id'],
        'driver_name' => $r['first_name'],
        'barangay'    => $r['location'],
        'latitude'    => (float) $r['latitude'],
        'longitude'   => (float) $r['longitude'],
        'is_tracking' => (bool) $r['is_tracking'],
        'age_seconds' => (int) $r['age_seconds'],
        'updated_at'  => $r['updated_at'],
    ];
}, $rows);

json_ok(['trucks' => $trucks]);
