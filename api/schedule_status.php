<?php
// GET /api/schedule_status.php  (auth: logged-in driver)
//   -> {"success":true,"updated_at":"..."}
// Cheap check used by the app's bottom-nav badge. The stamp changes whenever
// the admin adds, edits or deletes one of this driver's assignments.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

$auth = require_auth();

if (($auth['user_type'] ?? '') !== 'driver') {
    json_ok(['updated_at' => null]);
}

$driverId = (int)$auth['user_id'];

$u = $conn->prepare("SELECT schedule_updated_at FROM driver WHERE id = ? LIMIT 1");
$u->bind_param('i', $driverId);
$u->execute();
$row = $u->get_result()->fetch_assoc();

json_ok(['updated_at' => $row['schedule_updated_at'] ?? null]);
