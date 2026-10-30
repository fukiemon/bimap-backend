<?php
/**
 * api/collection_feedback.php
 *
 * GET  -> has this resident already answered today? (and what was the answer)
 * POST -> save today's answer ('yes' / 'no', optional 'notes').
 *         Only ONE answer per resident per day.
 *
 * Needs feedback_migration.sql to be run first (feedback_date + unique key).
 * Responses follow the rest of your API: {"success": true, ...} or {"success": false, "error": "..."}
 */
require_once '../includes/db.php';

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // DB errors throw exceptions

$APP_TZ = 'Asia/Manila'; // "today" always means today in Malita

function respond($code, $data) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/* ------------------------------------------------------------------
   AUTH: CHECK THIS BLOCK AGAINST YOUR OTHER ENDPOINTS.
   I can't see how api/reports.php identifies the logged-in resident.
   This default assumes "Authorization: Bearer <token>" and a `resident`
   table with a `token` column. If reports.php does it differently, copy
   its lines here, and make the function return:
   ['id' => ..., 'name' => ..., 'barangay' => ...]
------------------------------------------------------------------- */
function current_resident($conn) {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/Bearer\s+(.+)/i', $h, $m)) return null;
    $token = trim($m[1]);

    $stmt = $conn->prepare("SELECT id, first_name, last_name, barangay FROM resident WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return null;

    return [
        'id'       => (int)$row['id'],
        'name'     => trim($row['first_name'] . ' ' . $row['last_name']),
        'barangay' => (string)($row['barangay'] ?? ''),
    ];
}

function todays_answer($conn, $residentId, $today) {
    $stmt = $conn->prepare("SELECT collected FROM feedback WHERE resident_id = ? AND feedback_date = ? LIMIT 1");
    $stmt->bind_param("is", $residentId, $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['collected'] : null;
}

try {
    $resident = current_resident($conn);
    if (!$resident) {
        respond(401, ['success' => false, 'error' => 'Not logged in.']);
    }
    $residentId = $resident['id'];
    $today = (new DateTime('now', new DateTimeZone($APP_TZ)))->format('Y-m-d');

    /* ---------- GET: already answered today? ---------- */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $answer = todays_answer($conn, $residentId, $today);
        respond(200, [
            'success'   => true,
            'answered'  => $answer !== null,
            'collected' => $answer,            // 'yes' | 'no' | null
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['success' => false, 'error' => 'Method not allowed.']);
    }

    /* ---------- POST: save the answer ---------- */
    $input = $_POST;
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
    }

    $raw = $input['collected'] ?? null;
    if     (in_array($raw, ['yes', 'true', '1', 1, true],  true)) $collected = 'yes';
    elseif (in_array($raw, ['no',  'false', '0', 0, false], true)) $collected = 'no';
    else respond(400, ['success' => false, 'error' => 'collected must be "yes" or "no".']);

    $notes = trim((string)($input['notes'] ?? ''));

    // Friendly early exit if they already answered today
    $existing = todays_answer($conn, $residentId, $today);
    if ($existing !== null) {
        respond(200, ['success' => true, 'already_answered' => true, 'collected' => $existing]);
    }

    try {
        $ins = $conn->prepare(
            "INSERT INTO feedback (resident_id, resident_name, barangay, collected, notes, feedback_date)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $ins->bind_param("isssss", $residentId, $resident['name'], $resident['barangay'], $collected, $notes, $today);
        $ins->execute();

        respond(200, ['success' => true, 'already_answered' => false, 'collected' => $collected]);
    } catch (mysqli_sql_exception $e) {
        // 1062 = duplicate key: a double tap or second device got there first.
        if ((int)$e->getCode() === 1062) {
            $existing = todays_answer($conn, $residentId, $today);
            respond(200, ['success' => true, 'already_answered' => true, 'collected' => $existing]);
        }
        throw $e;
    }
} catch (Throwable $e) {
    error_log('collection_feedback.php: ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'Could not process feedback.']);
}
