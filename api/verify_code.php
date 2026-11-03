<?php
// POST /api/verify_code.php
// SECURITY: a code allows at most 5 wrong guesses (column: verification_codes.attempts).
// After that the person has to request a new code.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/verification_helpers.php';

const OTP_MAX_ATTEMPTS = 5;

$input   = json_decode(file_get_contents('php://input'), true);
$contact = trim($input['contact'] ?? '');
$code    = trim($input['code'] ?? '');
$purpose = 'registration';

if ($contact === '' || $code === '') {
    json_response(['success' => false, 'message' => 'Contact and code are required.'], 400);
}

$stmt = $conn->prepare(
    "SELECT id, code, attempts FROM verification_codes
     WHERE contact = ? AND purpose = ?
       AND verified_at IS NULL
       AND expires_at > NOW()
     ORDER BY id DESC
     LIMIT 1"
);
$stmt->bind_param('ss', $contact, $purpose);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    json_response(['success' => false, 'message' => 'This code has expired or was not found. Please request a new one.'], 400);
}

if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) {
    json_response(['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'], 429);
}

if (!hash_equals((string) $row['code'], $code)) {
    $bump = $conn->prepare("UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?");
    $bump->bind_param('i', $row['id']);
    $bump->execute();

    $left = OTP_MAX_ATTEMPTS - ((int) $row['attempts'] + 1);
    $msg = $left > 0
        ? "Incorrect verification code. $left attempt" . ($left === 1 ? '' : 's') . ' left.'
        : 'Too many incorrect attempts. Please request a new code.';
    json_response(['success' => false, 'message' => $msg], 400);
}

$update = $conn->prepare("UPDATE verification_codes SET verified_at = NOW() WHERE id = ?");
$update->bind_param('i', $row['id']);
$update->execute();

json_response(['success' => true, 'message' => 'Verified.']);
