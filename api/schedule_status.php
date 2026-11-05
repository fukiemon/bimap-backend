<?php
// GET /api/schedule.php  (auth: logged-in driver)
//   -> the driver's own collection assignments + the stamp of the last
//      schedule change (used by the app for the "schedule updated" badge).
// Residents have no schedule screen, so they just get an empty list.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

$auth = require_auth();

if (($auth['user_type'] ?? '') !== 'driver') {
    json_ok(['schedule' => [], 'updated_at' => null]);
}

$driverId = (int)$auth['user_id'];

$stmt = $conn->prepare(
    "SELECT a.id, a.collection_type, a.weeks, a.days, a.time_range, a.places, a.notes,
            c.name AS cluster_name
     FROM schedule_assignment a
     LEFT JOIN cluster c ON c.id = a.cluster_id
     WHERE a.driver_id = ?
     ORDER BY a.id"
);
$stmt->bind_param('i', $driverId);
$stmt->execute();

$items = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $places = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$r['places'])), 'strlen'));
    $items[] = [
        'id'              => (int)$r['id'],
        'collection_type' => $r['collection_type'],
        'cluster_name'    => $r['cluster_name'],
        'weeks'           => array_map('intval', array_filter(explode(',', $r['weeks']), 'strlen')),
        'days'            => array_values(array_filter(explode(',', $r['days']), 'strlen')),
        'time_range'      => $r['time_range'],
        'places'          => $places,
        'notes'           => $r['notes'],
    ];
}

$u = $conn->prepare("SELECT schedule_updated_at FROM driver WHERE id = ? LIMIT 1");
$u->bind_param('i', $driverId);
$u->execute();
$row = $u->get_result()->fetch_assoc();

json_ok([
    'schedule'   => $items,
    'updated_at' => $row['schedule_updated_at'] ?? null,
]);
