<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/verification_helpers.php';

$input   = json_decode(file_get_contents('php://input'), true);
$contact = trim($input['contact'] ?? '');
$code    = trim($input['code'] ?? '');
$purpose = 'registration';

if ($contact === '' || $code === '') {
    json_response(['success' => false, 'message' => 'Contact and code are required.'], 400);
}

$stmt = $conn->prepare(
    "SELECT id, code FROM verification_codes
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

if (!hash_equals((string) $row['code'], $code)) {
    json_response(['success' => false, 'message' => 'Incorrect verification code.'], 400);
}

$update = $conn->prepare("UPDATE verification_codes SET verified_at = NOW() WHERE id = ?");
$update->bind_param('i', $row['id']);
$update->execute();

json_response(['success' => true, 'message' => 'Verified.']);
