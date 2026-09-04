<?php
// GET  /api/reports.php               -> list the logged-in resident's own reports
// GET  /api/reports.php?id=5          -> a single report (must belong to the resident)
// POST /api/reports.php                -> create a new complaint/report
//   Body: { "concern", "barangay", "waste_type", "location_str",
//            "photo_base64": "data:image/jpeg;base64,..." }  (photo optional)
//
// Requires: Authorization: Bearer <token> (resident)

require_once __DIR__ . '/../includes/api_helpers.php';

$auth = require_auth();
if ($auth['user_type'] !== 'resident') {
    json_error('Only residents can submit or view reports through this endpoint.', 403);
}
$residentId = (int) $auth['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM complaint WHERE id = ? AND resident_id = ?");
        $stmt->bind_param('ii', $id, $residentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) json_error('Report not found.', 404);
        json_ok(['report' => $row]);
    }

    $stmt = $conn->prepare("SELECT * FROM complaint WHERE resident_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    json_ok(['reports' => $rows]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body = get_json_body();
    $concern = trim($body['concern'] ?? '');
    if ($concern === '') json_error('Field "concern" is required.');

    // Look up the resident's own name/phone/barangay so the admin panel (which
    // reads resident_name/resident_phone/barangay directly off the complaint
    // row) shows accurate info even if the resident didn't resend it.
    $stmt = $conn->prepare("SELECT first_name, last_name, phone, barangay FROM resident WHERE id = ?");
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $resident = $stmt->get_result()->fetch_assoc();
    if (!$resident) json_error('Resident account not found.', 404);

    $residentName  = trim($resident['first_name'] . ' ' . $resident['last_name']) ?: 'Unknown Resident';
    $residentPhone = $resident['phone'] ?: 'No phone';
    $barangay      = trim($body['barangay'] ?? $resident['barangay'] ?? '') ?: 'Not specified';
    $wasteType     = trim($body['waste_type'] ?? '') ?: null;
    $locationStr   = trim($body['location_str'] ?? '') ?: null;

    // Mobile apps typically send photos as base64 rather than multipart file
    // uploads, so we store it straight into complaint.photo_data (the column
    // already reserved for this) rather than writing a file to disk.
    $photoData = $body['photo_base64'] ?? null;
    if ($photoData && preg_match('/^data:image\/\w+;base64,/', $photoData)) {
        // keep the data URI as-is - it's ready to drop straight into an <img src="">
    } elseif ($photoData) {
        $photoData = 'data:image/jpeg;base64,' . $photoData;
    }

    $stmt = $conn->prepare(
        "INSERT INTO complaint (resident_id, resident_name, resident_phone, barangay, concern, photo_data, waste_type, location_str, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
    );
    $stmt->bind_param('isssssss', $residentId, $residentName, $residentPhone, $barangay, $concern, $photoData, $wasteType, $locationStr);
    $stmt->execute();
    $newId = $conn->insert_id;

    json_ok(['report' => [
        'id' => $newId, 'resident_id' => $residentId, 'resident_name' => $residentName,
        'resident_phone' => $residentPhone, 'barangay' => $barangay, 'concern' => $concern,
        'waste_type' => $wasteType, 'location_str' => $locationStr, 'status' => 'Pending',
    ]], 201);

} else {
    json_error('Method not allowed. Use GET or POST.', 405);
}
