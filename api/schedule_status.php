<?php
// GET /api/schedule_status.php  (auth: logged-in driver)
//   -> { success: true, updated_at: "2026-11-05 10:30:00" | null }
// Tiny response, safe to poll every ~15 seconds for the badge.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed. Use GET.', 405);
}

$auth = require_auth();

if (($auth['user_type'] ?? '') !== 'driver') {
    json_ok(['updated_at' => null]);
}

$driverId = (int)$auth['user_id'];
$stmt = $conn->prepare("SELECT schedule_updated_at FROM driver WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $driverId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

json_ok(['updated_at' => $row['schedule_updated_at'] ?? null]);
