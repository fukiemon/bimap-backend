<?php
// POST /api/password_reset.php?action=request
//   Body: { "identifier": "email or phone", "user_type": "resident" | "driver" }
//   -> generates a reset code. NOTE: this does not send an email/SMS yet (no
//      mail service is wired up in this backend) - it returns the raw code
//      in the response so you can test the flow end-to-end. Wire up a mailer
//      before shipping and stop returning "debug_code" in production.
//
// POST /api/password_reset.php?action=confirm
//   Body: { "identifier": "...", "user_type": "...", "code": "...", "new_password": "..." }
//   -> verifies the code and updates the password.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

$action = $_GET['action'] ?? '';
$body = get_json_body();
$identifier = trim($body['identifier'] ?? '');
$userType = strtolower(trim($body['user_type'] ?? 'resident'));

if (!in_array($userType, ['resident', 'driver'], true)) {
    json_error('user_type must be "resident" or "driver".');
}
if ($identifier === '') {
    json_error('Field "identifier" (email or phone) is required.');
}

if ($action === 'request') {

    $table = $userType;
    $stmt = $conn->prepare("SELECT id FROM `$table` WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Always respond the same way whether or not the account exists, so this
    // endpoint can't be used to check which emails/phones are registered.
    if ($user) {
        $code = strval(random_int(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes

        $stmt = $conn->prepare(
            "INSERT INTO password_reset (identifier, user_type, token, expires_at) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $identifier, $userType, $code, $expiresAt);
        $stmt->execute();

        // TODO: send $code via email/SMS instead of returning it directly.
        json_ok(['message' => 'If that account exists, a reset code has been generated.', 'debug_code' => $code]);
    }

    json_ok(['message' => 'If that account exists, a reset code has been generated.']);

} elseif ($action === 'confirm') {

    $code = trim($body['code'] ?? '');
    $newPassword = $body['new_password'] ?? '';
    if ($code === '' || $newPassword === '') {
        json_error('Fields "code" and "new_password" are required.');
    }
    if (strlen($newPassword) < 6) {
        json_error('Password must be at least 6 characters.');
    }

    $stmt = $conn->prepare(
        "SELECT id FROM password_reset
         WHERE identifier = ? AND user_type = ? AND token = ? AND expires_at >= NOW()
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('sss', $identifier, $userType, $code);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    if (!$reset) json_error('Invalid or expired code.', 401);

    $table = $userType;
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE `$table` SET password = ? WHERE email = ? OR phone = ?");
    $stmt->bind_param('sss', $hash, $identifier, $identifier);
    $stmt->execute();

    // consume the code so it can't be reused
    $del = $conn->prepare("DELETE FROM password_reset WHERE id = ?");
    $del->bind_param('i', $reset['id']);
    $del->execute();

    json_ok(['message' => 'Password updated. You can now log in with your new password.']);

} else {
    json_error('Query parameter "action" must be "request" or "confirm".');
}
