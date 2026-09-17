<?php
/**
 * POST /api/verify_code.php
 * Body (JSON): { "contact": "09171234567", "code": "123456" }
 */

require_once '../includes/db.php';
require_once 'includes/verification_helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$contact = trim($input['contact'] ?? '');
$code = trim($input['code'] ?? '');

if ($contact === '' || $code === '') {
    json_response(['success' => false, 'message' => 'Contact and code are required.'], 400);
}

$stmt = $conn->prepare(
    "SELECT id, code, attempts, expires_at FROM verification_codes
     WHERE contact = ? AND purpose = 'registration' AND verified_at IS NULL
     ORDER BY created_at DESC LIMIT 1"
);
$stmt->bind_param("s", $contact);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    json_response(['success' => false, 'message' => 'No pending verification for this contact. Request a new code.'], 404);
}

if (strtotime($row['expires_at']) < time()) {
    json_response(['success' => false, 'message' => 'Code expired. Request a new one.'], 410);
}

if ($row['attempts'] >= 5) {
    json_response(['success' => false, 'message' => 'Too many attempts. Request a new code.'], 429);
}

if (!hash_equals($row['code'], $code)) {
    $bump = $conn->prepare("UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?");
    $bump->bind_param("i", $row['id']);
    $bump->execute();
    json_response(['success' => false, 'message' => 'Incorrect code.'], 401);
}

$mark = $conn->prepare("UPDATE verification_codes SET verified_at = NOW() WHERE id = ?");
$mark->bind_param("i", $row['id']);
$mark->execute();

json_response(['success' => true, 'message' => 'Contact verified.']);
