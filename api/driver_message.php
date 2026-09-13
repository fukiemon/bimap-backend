<?php
// api/driver_message.php
//
// Driver-facing chat endpoint for the SHARED admin inbox — any authorized
// admin can see and reply to a driver's thread, so no admin_id is involved.
//   POST -> driver sends a message
//   GET  -> driver fetches their full conversation
//
// AUTH ASSUMPTION: no existing copy of this file was available, so this
// assumes the `driver` table has a `token` column set at login (simple
// bearer-token lookup). If your real login endpoint stores/issues tokens
// differently, change resolve_driver_id() below to match it exactly —
// don't let two different auth schemes exist side by side.

require_once '../includes/db.php';
header('Content-Type: application/json');
// Native HTTP clients (the Flutter app) aren't subject to CORS the way a
// browser is, but this costs nothing and covers you if a web client ever
// calls this endpoint directly.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

function resolve_driver_id($conn, $token) {
    if (!$token) return null;
    $stmt = $conn->prepare("SELECT id FROM driver WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? intval($row['id']) : null;
}

function bearer_token() {
    $headers = getallheaders();
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization' && stripos($v, 'Bearer ') === 0) {
            return trim(substr($v, 7));
        }
    }
    return null;
}

$driver_id = resolve_driver_id($conn, bearer_token());
if (!$driver_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $message = trim($body['message'] ?? '');

    if ($message === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'message is required']);
        exit;
    }

    // Pull the driver's name so driver_message.driver_name stays populated
    // the same way messages.php already relies on for the admin thread list.
    $name_stmt = $conn->prepare("SELECT name FROM driver WHERE id = ?");
    $name_stmt->bind_param("i", $driver_id);
    $name_stmt->execute();
    $driver_name = $name_stmt->get_result()->fetch_assoc()['name'] ?? 'Driver';

    $ins = $conn->prepare(
        "INSERT INTO driver_message (driver_id, driver_name, message, sender) VALUES (?, ?, ?, 'driver')"
    );
    $ins->bind_param("iss", $driver_id, $driver_name, $message);

    if ($ins->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Insert failed']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare(
        "SELECT id, sender, message, created_at FROM driver_message
         WHERE driver_id = ? ORDER BY created_at ASC LIMIT 200"
    );
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
