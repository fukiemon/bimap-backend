<?php
// GET /api/schedule.php  (auth: any logged-in resident or driver)
//   Optional query: ?barangay=Poblacion to only return that barangay's schedule.
//   -> returns the collection schedule, ordered Monday through Sunday.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

require_auth();

$dayOrder = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
$barangay = trim($_GET['barangay'] ?? '');

if ($barangay !== '') {
    $stmt = $conn->prepare("SELECT * FROM schedule WHERE barangay = ? ORDER BY $dayOrder");
    $stmt->bind_param('s', $barangay);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $rows = $conn->query("SELECT * FROM schedule ORDER BY barangay, $dayOrder")->fetch_all(MYSQLI_ASSOC);
}

json_ok(['schedule' => $rows]);
