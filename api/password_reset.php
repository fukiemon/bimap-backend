<?php
// POST /api/password_reset.php?action=request
//   Body: { "identifier": "email or phone", "user_type": "resident" | "driver" (ignored) }
//   -> emails a 6-digit reset code to the account's email address.
//      The response is always the same, so it can't be used to find out
//      which emails/phones are registered. The code is NEVER returned.
//
// POST /api/password_reset.php?action=confirm
//   Body: { "identifier": "...", "code": "...", "new_password": "..." }
//   -> checks the code (max 5 wrong guesses), applies the password rules,
//      and updates the password.
//
// The account type (resident/driver) is looked up on the server.
//
// Needs: ALTER TABLE password_reset ADD COLUMN attempts ... (see password_reset_update.sql)

require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/verification_helpers.php';   // send_email_otp(), generate_otp_code()
require_once __DIR__ . '/password_policy.php';        // validate_password_strength()

const RESET_MAX_ATTEMPTS = 5;
const RESET_TTL_MINUTES  = 10;   // keep at 10: the email text says "expires in 10 minutes"

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

$action = $_GET['action'] ?? '';
$body = get_json_body();
$identifier = trim($body['identifier'] ?? '');

if ($identifier === '') {
    json_error('Field "identifier" (email or phone) is required.');
}

// Finds the account in the resident table first, then driver.
function find_account(mysqli $conn, string $identifier): ?array
{
    foreach (['resident', 'driver'] as $table) {
        $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM `$table` WHERE email = ? OR phone = ? LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $row['table'] = $table;
            return $row;
        }
    }
    return null;
}

if ($action === 'request') {

    $generic = 'If that account exists, a reset code has been sent to its email address.';
    $user = find_account($conn, $identifier);

    if ($user && !empty($user['email'])) {
        // Cooldown: a code created less than 60 seconds ago expires more than
        // (TTL - 1) minutes from now. In that case quietly do nothing.
        $cool = $conn->prepare(
            "SELECT id FROM password_reset
             WHERE identifier = ? AND expires_at > DATE_ADD(NOW(), INTERVAL 9 MINUTE) LIMIT 1"
        );
        $cool->bind_param('s', $identifier);
        $cool->execute();
        if (!$cool->get_result()->fetch_assoc()) {
            $code = generate_otp_code();
            $type = $user['table'];

            // One active code per account: remove older ones.
            $del = $conn->prepare("DELETE FROM password_reset WHERE identifier = ?");
            $del->bind_param('s', $identifier);
            $del->execute();

            $ttl = RESET_TTL_MINUTES;
            $ins = $conn->prepare(
                "INSERT INTO password_reset (identifier, user_type, token, expires_at)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
            );
            $ins->bind_param('sssi', $identifier, $type, $code, $ttl);
            $ins->execute();

            if (!send_email_otp($user['email'], $code)) {
                error_log('password_reset: could not email reset code');
            }
        }
    }

    json_ok(['message' => $generic]);

} elseif ($action === 'confirm') {

    $code = trim($body['code'] ?? '');
    $newPassword = (string) ($body['new_password'] ?? '');
    if ($code === '' || $newPassword === '') {
        json_error('Fields "code" and "new_password" are required.');
    }

    $stmt = $conn->prepare(
        "SELECT id, user_type, token, attempts FROM password_reset
         WHERE identifier = ? AND expires_at >= NOW()
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    if (!$reset) {
        json_error('Invalid or expired code. Please request a new one.', 401);
    }

    if ((int) $reset['attempts'] >= RESET_MAX_ATTEMPTS) {
        json_error('Too many incorrect attempts. Please request a new code.', 429);
    }

    if (!hash_equals((string) $reset['token'], $code)) {
        $bump = $conn->prepare("UPDATE password_reset SET attempts = attempts + 1 WHERE id = ?");
        $bump->bind_param('i', $reset['id']);
        $bump->execute();

        $left = RESET_MAX_ATTEMPTS - ((int) $reset['attempts'] + 1);
        json_error(
            $left > 0
                ? "Incorrect code. $left attempt" . ($left === 1 ? '' : 's') . ' left.'
                : 'Too many incorrect attempts. Please request a new code.',
            401
        );
    }

    // Code is correct. Look up the account and apply the password rules.
    $table = $reset['user_type'] === 'driver' ? 'driver' : 'resident';
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM `$table` WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    if (!$account) {
        json_error('Invalid or expired code. Please request a new one.', 401);
    }

    $pwError = validate_password_strength($newPassword, [
        $account['first_name'] ?? '',
        $account['last_name'] ?? '',
        strtok((string) ($account['email'] ?? ''), '@') ?: '',
    ]);
    if ($pwError !== null) {
        json_error($pwError);   // the code stays valid so they can try another password
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE `$table` SET password = ? WHERE id = ?");
    $upd->bind_param('si', $hash, $account['id']);
    $upd->execute();

    // Consume the code, and lift any login lockout for this account.
    $del = $conn->prepare("DELETE FROM password_reset WHERE identifier = ?");
    $del->bind_param('s', $identifier);
    $del->execute();

    $key = mb_substr(mb_strtolower($identifier), 0, 150);
    $clr = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ?");
    $clr->bind_param('s', $key);
    $clr->execute();

    json_ok(['message' => 'Password updated. You can now log in with your new password.']);

} else {
    json_error('Query parameter "action" must be "request" or "confirm".');
}
