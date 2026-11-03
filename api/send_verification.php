<?php
/**
 * POST /api/send_verification.php
 * Body (JSON): { "contact": "09171234567" }  OR  { "contact": "user@gmail.com" }
 *
 * Detects whether the contact is a phone number or an email, generates a
 * 6-digit OTP, stores it, and sends it via SMS (Semaphore) or email (Brevo SMTP).
 *
 * SECURITY: only one code can be requested per contact every 60 seconds.
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

// Cooldown: codes live for 10 minutes, so an unverified code whose expiry is
// still more than 9 minutes away was created less than 60 seconds ago.
$purpose = 'registration';
$cool = $conn->prepare(
    "SELECT id FROM verification_codes
     WHERE contact = ? AND purpose = ? AND verified_at IS NULL
       AND expires_at > DATE_ADD(NOW(), INTERVAL 9 MINUTE)
     LIMIT 1"
);
$cool->bind_param('ss', $contact, $purpose);
$cool->execute();
if ($cool->get_result()->fetch_assoc()) {
    json_response(['success' => false, 'message' => 'Please wait a minute before requesting another code.'], 429);
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
