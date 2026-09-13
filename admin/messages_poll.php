<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Same check every admin page runs via admin_header.php. This endpoint is
// polled directly by fetch(), so it needs its own auth check since it never
// includes admin_header.php.
requireAdminLogin();

$driver_id = intval($_GET['driver'] ?? 0);
$after_id  = intval($_GET['after_id'] ?? 0);

if (!$driver_id) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, message, sender, created_at FROM driver_message
    WHERE driver_id = ? AND id > ? ORDER BY created_at ASC");
$stmt->bind_param("ii", $driver_id, $after_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
    $dt = new DateTime($row['created_at']);
    $out[] = [
        'id'      => (int)$row['id'],
        'message' => $row['message'],
        'sender'  => $row['sender'],
        'time'    => $dt->format('g:i A'),
    ];
}

// Mark any newly-fetched driver messages as read since admin is actively viewing this thread
$upd = $conn->prepare("UPDATE driver_message SET is_read = 1 WHERE driver_id = ? AND sender = 'driver'");
$upd->bind_param("i", $driver_id);
$upd->execute();

echo json_encode($out);
