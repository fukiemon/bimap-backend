<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Same check every admin page runs via admin_header.php. This endpoint is
// hit directly by fetch(), so it needs its own auth check since it never
// includes admin_header.php.
requireAdminLogin();

$driver_id   = intval($_POST['driver_id'] ?? 0);
$driver_name = trim($_POST['driver_name'] ?? 'Driver');
$msg         = trim($_POST['reply_msg'] ?? '');

if (!$driver_id || $msg === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

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
