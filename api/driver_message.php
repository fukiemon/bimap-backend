<?php
// POST /api/driver_message.php  -> driver sends a message to the shared admin inbox
//   Body: { "message": "..." }
// GET  /api/driver_message.php  -> driver fetches their full conversation with admins
//
// Requires: Authorization: Bearer <token> (driver)
//
// Shared inbox model: any authorized admin can see and reply to a driver's
// thread (see messages.php), so there's no admin_id involved here.

require_once __DIR__ . '/../includes/api_helpers.php';

$auth = require_auth();
if ($auth['user_type'] !== 'driver') {
    json_error('Only drivers can use this endpoint.', 403);
}
$driverId = (int) $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body = get_json_body();
    $message = trim($body['message'] ?? '');
    if ($message === '') {
        json_error('Field "message" is required.');
    }

    // Pull the driver's name so driver_message.driver_name stays populated
    // the same way messages.php already relies on for the admin thread list.
    $stmt = $conn->prepare("SELECT first_name, last_name FROM driver WHERE id = ?");
    $stmt->bind_param('i', $driverId);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();
    if (!$driver) json_error('Driver account not found.', 404);
    $driverName = trim($driver['first_name'] . ' ' . $driver['last_name']) ?: 'Driver';

    $ins = $conn->prepare(
        "INSERT INTO driver_message (driver_id, driver_name, message, sender) VALUES (?, ?, ?, 'driver')"
    );
    $ins->bind_param('iss', $driverId, $driverName, $message);
    $ins->execute();

    json_ok(['id' => $conn->insert_id], 201);

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Lightweight check for a home-screen/nav badge: how many admin replies
    // has this driver NOT seen yet. Deliberately does not mark anything as
    // read, since just glancing at a badge count isn't "opening the chat".
    if (isset($_GET['count_only']) && $_GET['count_only'] === '1') {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM driver_message
             WHERE driver_id = ? AND sender = 'admin' AND is_read = 0"
        );
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $unread = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        json_ok(['unread' => $unread]);
    }

    $stmt = $conn->prepare(
        "SELECT id, sender, message, created_at FROM driver_message
         WHERE driver_id = ? ORDER BY created_at ASC LIMIT 200"
    );
    $stmt->bind_param('i', $driverId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Opening the full conversation means the driver has now seen every
    // admin reply in it, so clear the unread flag those rows were counted
    // under above.
    $read = $conn->prepare(
        "UPDATE driver_message SET is_read = 1 WHERE driver_id = ? AND sender = 'admin' AND is_read = 0"
    );
    $read->bind_param('i', $driverId);
    $read->execute();

    json_ok(['messages' => $rows]);

} else {
    json_error('Method not allowed. Use GET or POST.', 405);
}
