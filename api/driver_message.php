<?php
// POST /api/driver_message.php  (auth: driver only)
//   Body: { "message": "..." }
//   -> sends a message from the logged-in driver to the admin. Shows up in
//      admin/messages.php automatically (same driver_message table).
//
// GET /api/driver_message.php  (auth: driver only)
//   -> returns the full conversation (both sides) for the logged-in driver,
//      oldest first.
//
// NOTE: `is_read` on this table tracks whether the ADMIN has read a
// driver-sent message (see admin/messages.php's unread badge), not the
// other way around, so this endpoint doesn't touch it.

require_once __DIR__ . '/../includes/api_helpers.php';

$payload = require_auth();
if ($payload['user_type'] !== 'driver') {
    json_error('Only drivers can use this endpoint.', 403);
}
$driverId = (int) $payload['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_json_body();
    $message = trim($body['message'] ?? '');
    if ($message === '') {
        json_error('Field "message" is required.');
    }

    $stmt = $conn->prepare("SELECT first_name, last_name FROM driver WHERE id = ?");
    $stmt->bind_param('i', $driverId);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    if (!$driver) {
        json_error('Driver not found.', 404);
    }
    $driverName = trim($driver['first_name'] . ' ' . $driver['last_name']);

    $stmt = $conn->prepare(
        "INSERT INTO driver_message (driver_id, driver_name, message, sender) VALUES (?, ?, ?, 'driver')"
    );
    $stmt->bind_param('iss', $driverId, $driverName, $message);
    $stmt->execute();

    json_ok(['id' => $conn->insert_id], 201);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare(
        "SELECT id, driver_id, driver_name, message, sender, is_read, created_at
         FROM driver_message WHERE driver_id = ? ORDER BY created_at ASC LIMIT 200"
    );
    $stmt->bind_param('i', $driverId);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    json_ok(['messages' => $messages]);

} else {
    json_error('Method not allowed. Use GET or POST.', 405);
}
