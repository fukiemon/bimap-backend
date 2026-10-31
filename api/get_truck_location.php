<?php
// GET /api/get_truck_location.php
//
// LOGGED-IN RESIDENT (Authorization: Bearer <token>):
//   -> the garbage truck(s) whose driver covers THIS resident's barangay.
//
// GUEST (no Authorization header at all):
//   -> every truck that has a location row. Guests can only view live tracking.
//
// A driver can cover several barangays: driver.assigned_barangay may hold a
// comma-separated list, for example "Poblacion, Felis, Lais".
// A resident sees the truck if their barangay is anywhere in that list
// (not case-sensitive).
//
// Response: {"success":true,"trucks":[{truck_id, driver_name, barangay, latitude,
//            longitude, is_tracking, age_seconds, updated_at}]}
//
// truck_id is the driver's id. The app uses it to tell trucks apart so each
// marker can glide smoothly (first names are not unique).
//
// age_seconds is calculated by the database (NOW() - updated_at) so server
// timezone differences can't make a fresh location look old.

require_once __DIR__ . '/../includes/api_helpers.php';

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
            d.assigned_barangay,
            dl.latitude,
            dl.longitude,
            dl.is_tracking,
            dl.updated_at,
            TIMESTAMPDIFF(SECOND, dl.updated_at, NOW()) AS age_seconds
     FROM driver_location dl
     JOIN driver d ON d.id = dl.driver_id";

if ($isGuest) {
    // Guests: every truck that has a location row.
    $rows = $conn->query($select . " ORDER BY dl.updated_at DESC")
                 ->fetch_all(MYSQLI_ASSOC);
} else {
    // Residents: trucks whose driver covers their barangay.
    // The REPLACEs remove spaces around the commas so "A, B , C" still matches.
    $stmt = $conn->prepare(
        $select . "
         WHERE FIND_IN_SET(
                   LOWER(?),
                   LOWER(REPLACE(REPLACE(d.assigned_barangay, ', ', ','), ' ,', ','))
               ) > 0
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
        'barangay'    => $r['assigned_barangay'],
        'latitude'    => (float) $r['latitude'],
        'longitude'   => (float) $r['longitude'],
        'is_tracking' => (bool) $r['is_tracking'],
        'age_seconds' => (int) $r['age_seconds'],
        'updated_at'  => $r['updated_at'],
    ];
}, $rows);

json_ok(['trucks' => $trucks]);
