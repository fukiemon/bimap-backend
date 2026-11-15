<?php
// POST /api/schedule_check.php  (auth: logged-in driver)
//   body (JSON or form): {"assignment_id": 12, "place": "Market", "checked": true}
//   -> checks / unchecks ONE place of ONE assignment for today.

require_once __DIR__ . '/../includes/api_helpers.php';

date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

$auth = require_auth();

if (($auth['user_type'] ?? '') !== 'driver') {
    json_error('Only drivers can update stops.', 403);
}

$driverId = (int)$auth['user_id'];

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = $_POST;

$assignmentId = (int)($in['assignment_id'] ?? 0);
$place        = trim((string)($in['place'] ?? ''));
$checked      = filter_var($in['checked'] ?? true, FILTER_VALIDATE_BOOLEAN);

if (!$assignmentId || $place === '') {
    json_error('assignment_id and place are required.', 422);
}

// The assignment must belong to this driver.
$s = $conn->prepare("SELECT places FROM schedule_assignment WHERE id = ? AND driver_id = ? LIMIT 1");
$s->bind_param('ii', $assignmentId, $driverId);
$s->execute();
$a = $s->get_result()->fetch_assoc();
if (!$a) {
    json_error('Assignment not found.', 404);
}

// The place must actually be one of its stops.
$places = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$a['places'])), 'strlen'));
if (!in_array($place, $places, true)) {
    json_error('That place is not part of this assignment.', 422);
}

$today = date('Y-m-d');

if ($checked) {
    $i = $conn->prepare(
        "INSERT INTO schedule_place_check (assignment_id, driver_id, place_name, check_date)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE checked_at = checked_at"
    );
    $i->bind_param('iiss', $assignmentId, $driverId, $place, $today);
    $i->execute();
} else {
    $d = $conn->prepare(
        "DELETE FROM schedule_place_check
         WHERE assignment_id = ? AND driver_id = ? AND place_name = ? AND check_date = ?"
    );
    $d->bind_param('iiss', $assignmentId, $driverId, $place, $today);
    $d->execute();
}

// Fresh progress for this assignment.
$c = $conn->prepare(
    "SELECT COUNT(*) AS n FROM schedule_place_check
     WHERE assignment_id = ? AND driver_id = ? AND check_date = ?"
);
$c->bind_param('iis', $assignmentId, $driverId, $today);
$c->execute();
$done = (int)$c->get_result()->fetch_assoc()['n'];

json_ok([
    'assignment_id' => $assignmentId,
    'place'         => $place,
    'checked'       => $checked,
    'done_count'    => $done,
    'total_count'   => count($places),
]);
