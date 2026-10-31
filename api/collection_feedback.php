<?php
// GET  /api/collection_feedback.php  -> has this resident already answered today?
//        Returns: { success, answered: bool, collected: "yes"|"no"|null }
// POST /api/collection_feedback.php  -> save today's answer (ONE per resident per day)
//   Body: { "collected": "yes" | "no", "notes": "..." (optional) }
//        Returns: { success, already_answered: bool, collected: "yes"|"no" }
//
// Requires: Authorization: Bearer <token> (resident)
// Needs feedback_migration.sql to have been run (feedback_date + unique key).

require_once __DIR__ . '/../includes/api_helpers.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // DB errors throw exceptions

$APP_TZ = 'Asia/Manila'; // "today" always means today in Malita

$auth = require_auth();
if ($auth['user_type'] !== 'resident') {
    json_error('Only residents can send collection feedback.', 403);
}
$residentId = (int) $auth['user_id'];
$today = (new DateTime('now', new DateTimeZone($APP_TZ)))->format('Y-m-d');

function todays_answer($conn, $residentId, $today) {
    $stmt = $conn->prepare("SELECT collected FROM feedback WHERE resident_id = ? AND feedback_date = ? LIMIT 1");
    $stmt->bind_param('is', $residentId, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['collected'] : null;
}

try {
    /* ---------- GET: already answered today? ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $answer = todays_answer($conn, $residentId, $today);
        json_ok([
            'answered'  => $answer !== null,
            'collected' => $answer,          // 'yes' | 'no' | null
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Method not allowed. Use GET or POST.', 405);
    }

    /* ---------- POST: save the answer ---------- */
    $body = get_json_body();

    $raw = $body['collected'] ?? null;
    if     (in_array($raw, ['yes', 'true', '1', 1, true],  true)) $collected = 'yes';
    elseif (in_array($raw, ['no',  'false', '0', 0, false], true)) $collected = 'no';
    else json_error('Field "collected" must be "yes" or "no".');

    $notes = trim((string)($body['notes'] ?? ''));

    // Take the name and barangay from the resident's own record, not from the app.
    $stmt = $conn->prepare("SELECT first_name, last_name, barangay FROM resident WHERE id = ?");
    $stmt->bind_param('i', $residentId);
    $stmt->execute();
    $resident = $stmt->get_result()->fetch_assoc();
    if (!$resident) json_error('Resident account not found.', 404);

    $residentName = trim($resident['first_name'] . ' ' . $resident['last_name']) ?: 'Unknown Resident';
    $barangay     = (string) ($resident['barangay'] ?? '');

    // Friendly early exit if they already answered today
    $existing = todays_answer($conn, $residentId, $today);
    if ($existing !== null) {
        json_ok(['already_answered' => true, 'collected' => $existing]);
    }

    try {
        $ins = $conn->prepare(
            "INSERT INTO feedback (resident_id, resident_name, barangay, collected, notes, feedback_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param('isssss', $residentId, $residentName, $barangay, $collected, $notes, $today);
        $ins->execute();

        json_ok(['already_answered' => false, 'collected' => $collected]);
    } catch (mysqli_sql_exception $e) {
        // 1062 = duplicate key: a double tap or second device got there first.
        if ((int) $e->getCode() === 1062) {
            $existing = todays_answer($conn, $residentId, $today);
            json_ok(['already_answered' => true, 'collected' => $existing]);
        }
        throw $e;
    }
} catch (Throwable $e) {
    error_log('collection_feedback.php: ' . $e->getMessage());
    json_error('Could not process feedback.', 500);
}
