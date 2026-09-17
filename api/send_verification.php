<?php
/**
 * POST /api/send_verification.php
 * Body (JSON): { "contact": "09171234567" }  OR  { "contact": "user@gmail.com" }
 *
 * Detects whether the contact is a phone number or an email, generates a
 * 6-digit OTP, stores it, and sends it via SMS (Semaphore) or email (Gmail SMTP).
 */

require_once '../includes/db.php';
require_once 'includes/verification_helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$contact = trim($input['contact'] ?? '');

if ($contact === '') {
    json_response(['success' => false, 'message' => 'Contact is required.'], 400);
}

$type = detect_contact_type($contact);
if ($type === null) {
    json_response(['success' => false, 'message' => 'Enter a valid email address or PH mobile number.'], 422);
}

// Optional: block if this contact is already registered to an account.
// Adjust table/column names to match your actual users table.
$check = $conn->prepare("SELECT id FROM users WHERE " . ($type === 'email' ? "email" : "phone") . " = ? LIMIT 1");
$check->bind_param("s", $contact);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    json_response(['success' => false, 'message' => 'That ' . ($type === 'email' ? 'email' : 'number') . ' is already registered.'], 409);
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
