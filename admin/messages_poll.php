<?php
session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

// Same check every admin page runs via admin_header.php. This endpoint is
// polled directly by fetch(), so it needs its own auth check since it never
// includes admin_header.php.
requireAdminLogin();

// Philippine time for PHP and the MySQL session.
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

$driver_id = intval($_GET['driver'] ?? 0);
$after_id  = intval($_GET['after_id'] ?? 0);

if (!$driver_id) {
    echo json_encode(['messages' => [], 'seen' => []]);
    exit;
}

// 1) New messages since the last one the page has
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

// 2) Mark the driver's messages as read now (admin is viewing this thread)
$upd = $conn->prepare("UPDATE driver_message SET is_read = 1, read_at = NOW()
    WHERE driver_id = ? AND sender = 'driver' AND is_read = 0");
$upd->bind_param("i", $driver_id);
$upd->execute();

// 3) Which admin messages has the driver already seen? (lets the ✓ turn into ✓✓ Seen)
$seenStmt = $conn->prepare("SELECT id, read_at FROM driver_message
    WHERE driver_id = ? AND sender = 'admin' AND is_read = 1
    ORDER BY id DESC LIMIT 100");
$seenStmt->bind_param("i", $driver_id);
$seenStmt->execute();
$sr = $seenStmt->get_result();

$seen = [];
while ($r = $sr->fetch_assoc()) {
    $seen[] = [
        'id'   => (int)$r['id'],
        'time' => $r['read_at'] ? (new DateTime($r['read_at']))->format('g:i A') : '',
    ];
}

echo json_encode(['messages' => $out, 'seen' => $seen]);
