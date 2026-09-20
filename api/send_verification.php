<?php
/**
 * POST /api/send_verification.php
 * Body (JSON): { "contact": "09171234567" }  OR  { "contact": "user@gmail.com" }
 *
 * Detects whether the contact is a phone number or an email, generates a
 * 6-digit OTP, stores it, and sends it via SMS (Semaphore) or email (Gmail SMTP).
 *
 * CHANGES FROM YOUR ORIGINAL:
 *  - The "already registered" check queried a `users` table that doesn't
 *    exist in this schema — accounts live in separate `resident` and
 *    `driver` tables. Now checks both.
 *  - Require path fixed: verification_helpers.php lives flat in api/,
 *    right next to this file — no 'includes/' subfolder inside api/.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/verification_helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$contact = trim($input['contact'] ?? '');

if ($contact === '') {
    json_response(['success' => false, 'message' => 'Contact is required.'], 400);
}

$type = detect_contact_type($contact);
if ($type === null) {
    json_response(['success' => false, 'message' => 'Enter a valid email address or PH mobile number.'], 422);
}

// Block if this contact is already registered to an account, on either the
// resident or driver table.
$column = $type === 'email' ? 'email' : 'phone';
foreach (['resident', 'driver'] as $table) {
    $check = $conn->prepare("SELECT id FROM `$table` WHERE `$column` = ? AND `$column` <> '' LIMIT 1");
    $check->bind_param('s', $contact);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        json_response([
            'success' => false,
            'message' => 'That ' . ($type === 'email' ? 'email' : 'number') . ' is already registered.',
        ], 409);
    }
}

$code = generate_otp_code();
store_verification_code($conn, $contact, $type, $code);

$sent = $type === 'email'
    ? send_email_otp($contact, $code)
    : send_sms_otp($contact, $code);

if (!$sent) {
    json_response(['success' => false, 'message' => 'Failed to send verification code. Please try again.'], 500);
}

json_response([
    'success' => true,
    'message' => 'Verification code sent via ' . ($type === 'email' ? 'email' : 'SMS') . '.',
    'contact_type' => $type,
]);
