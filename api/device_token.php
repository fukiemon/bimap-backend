<?php
// POST   /api/device_token.php            -> save this phone's push token
//   Body: { "token": "..." }
// DELETE /api/device_token.php?token=...  -> remove it (call on logout)
//
// Requires: Authorization: Bearer <token> (resident or driver)

require_once __DIR__ . '/../includes/api_helpers.php';

$auth = require_auth();
$userType = $auth['user_type'];
$userId   = (int) $auth['user_id'];

if (!in_array($userType, ['resident', 'driver'], true)) {
    json_error('Only residents and drivers can use this endpoint.', 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body  = get_json_body();
    $token = trim($body['token'] ?? '');
    if ($token === '') json_error('Field "token" is required.');

    // If this phone was used by another account before, the token moves to this account.
    $stmt = $conn->prepare(
        "INSERT INTO device_token (user_id, user_type, token) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), user_type = VALUES(user_type)"
    );
    $stmt->bind_param('iss', $userId, $userType, $token);
    $stmt->execute();

    json_ok(['message' => 'Token saved.']);

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $token = trim($_GET['token'] ?? '');
    if ($token === '') json_error('Missing "token" query parameter.');

    $stmt = $conn->prepare(
        "DELETE FROM device_token WHERE token = ? AND user_id = ? AND user_type = ?"
    );
    $stmt->bind_param('sis', $token, $userId, $userType);
    $stmt->execute();

    json_ok(['message' => 'Token removed.']);

} else {
    json_error('Method not allowed. Use POST or DELETE.', 405);
}
