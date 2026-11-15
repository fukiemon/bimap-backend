<?php
// GET /api/schedule.php  (auth: logged-in driver)
//   -> the driver's own assignments, each with per-place (stop) check status
//      for today, plus the stamp of the last schedule change.
// Residents have no schedule screen, so they just get an empty list.

require_once __DIR__ . '/../includes/api_helpers.php';

date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

$auth = require_auth();

if (($auth['user_type'] ?? '') !== 'driver') {
    json_ok(['schedule' => [], 'updated_at' => null]);
}

$driverId = (int)$auth['user_id'];
$today    = date('Y-m-d');

// Today's checks for this driver: [assignment_id][place_name] => checked_at
$checks = [];
$c = $conn->prepare(
    "SELECT assignment_id, place_name, checked_at
     FROM schedule_place_check
     WHERE driver_id = ? AND check_date = ?"
);
$c->bind_param('is', $driverId, $today);
$c->execute();
foreach ($c->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $checks[(int)$row['assignment_id']][$row['place_name']] = $row['checked_at'];
}

$stmt = $conn->prepare(
    "SELECT a.id, a.collection_type, a.weeks, a.days, a.time_range, a.places, a.notes,
            a.assigned_by, c.name AS cluster_name
     FROM schedule_assignment a
     LEFT JOIN cluster c ON c.id = a.cluster_id
     WHERE a.driver_id = ?
     ORDER BY a.id"
);
$stmt->bind_param('i', $driverId);
$stmt->execute();

$items = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $id     = (int)$r['id'];
    $places = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$r['places'])), 'strlen'));

    $stops = [];
    $done  = 0;
    foreach ($places as $i => $name) {
        $at     = $checks[$id][$name] ?? null;
        $isDone = $at !== null;
        if ($isDone) $done++;
        $stops[] = [
            'order'      => $i + 1,
            'name'       => $name,
            'done'       => $isDone,
            'checked_at' => $at,
        ];
    }

    $items[] = [
        'id'              => $id,
        'collection_type' => $r['collection_type'],
        'cluster_name'    => $r['cluster_name'],
        'weeks'           => array_map('intval', array_filter(explode(',', (string)$r['weeks']), 'strlen')),
        'days'            => array_values(array_filter(array_map('trim', explode(',', (string)$r['days'])), 'strlen')),
        'time_range'      => $r['time_range'],
        'places'          => $places,          // kept for backward compatibility
        'stops'           => $stops,           // use this for the per-place checkboxes
        'done_count'      => $done,
        'total_count'     => count($places),
        'notes'           => $r['notes'],
        'assigned_by'     => $r['assigned_by'],
    ];
}

$u = $conn->prepare("SELECT schedule_updated_at FROM driver WHERE id = ? LIMIT 1");
$u->bind_param('i', $driverId);
$u->execute();
$row = $u->get_result()->fetch_assoc();

json_ok([
    'date'       => $today,
    'schedule'   => $items,
    'updated_at' => $row['schedule_updated_at'] ?? null,
]);
