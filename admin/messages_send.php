<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Same check every admin page runs via admin_header.php. This endpoint is
// hit directly by fetch(), so it needs its own auth check since it never
// includes admin_header.php.
requireAdminLogin();

// Philippine time for PHP and the MySQL session, so created_at is real local time.
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

$driver_id = intval($_POST['driver_id'] ?? 0);
$msg       = trim($_POST['reply_msg'] ?? '');

if (!$driver_id || $msg === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}
if (mb_strlen($msg) > 500) {
    echo json_encode(['success' => false, 'error' => 'Message must be 500 characters or less.']);
    exit;
}

// The driver must exist and be admin-approved. The name saved with the
// message comes from the driver's account, not from the browser.
$chk = $conn->prepare("SELECT first_name, last_name FROM driver WHERE id = ? AND is_verified = 1");
$chk->bind_param("i", $driver_id);
$chk->execute();
$driver = $chk->get_result()->fetch_assoc();

if (!$driver) {
    echo json_encode(['success' => false, 'error' => 'Driver not found or not verified.']);
    exit;
}

$driver_name = trim(($driver['first_name'] ?? '') . ' ' . ($driver['last_name'] ?? ''));
if ($driver_name === '') $driver_name = 'Driver';

$ins = $conn->prepare("INSERT INTO driver_message (driver_id, driver_name, message, sender) VALUES (?, ?, ?, 'admin')");
$ins->bind_param("iss", $driver_id, $driver_name, $msg);

if (!$ins->execute()) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$new_id = $conn->insert_id;

$stmt = $conn->prepare("SELECT id, message, created_at FROM driver_message WHERE id = ?");
$stmt->bind_param("i", $new_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

$dt = new DateTime($row['created_at']);

echo json_encode([
    'success' => true,
    'id'      => (int)$row['id'],
    'message' => $row['message'],
    'time'    => $dt->format('g:i A'), // real send time, straight from the DB
]);
