<?php
// POST /api/collection_feedback.php
// Body: { "collected": "yes" | "no", "notes": "..." }
//
// Requires: Authorization: Bearer <token> (resident)

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

$auth = require_auth();
if ($auth['user_type'] !== 'resident') {
    json_error('Only residents can submit collection feedback.', 403);
}
$residentId = (int) $auth['user_id'];

$body = get_json_body();
$collected = strtolower(trim($body['collected'] ?? ''));
$notes = trim($body['notes'] ?? '') ?: null;

if (!in_array($collected, ['yes', 'no'], true)) {
    json_error('Field "collected" must be "yes" or "no".');
}

$stmt = $conn->prepare("SELECT first_name, last_name, barangay FROM resident WHERE id = ?");
$stmt->bind_param('i', $residentId);
$stmt->execute();
$resident = $stmt->get_result()->fetch_assoc();
if (!$resident) json_error('Resident account not found.', 404);

$residentName = trim($resident['first_name'] . ' ' . $resident['last_name']);
$barangay = $resident['barangay'];

$stmt = $conn->prepare(
    "INSERT INTO feedback (resident_id, resident_name, barangay, collected, notes) VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param('issss', $residentId, $residentName, $barangay, $collected, $notes);
$stmt->execute();

json_ok(['message' => 'Feedback submitted.', 'id' => $conn->insert_id], 201);
