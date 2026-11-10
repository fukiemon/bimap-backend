<?php
// GET  /api/reports.php  -> the logged-in resident's own complaints
// POST /api/reports.php  -> submit a new complaint
//        JSON body: concern, waste_type, barangay,
//                   photo_base64 (optional data URI), video_base64 (optional data URI)
//
// Photos and videos are stored INSIDE the database as base64 data URIs,
// because files saved on Render's disk disappear on every restart/redeploy.

require_once __DIR__ . '/../includes/api_helpers.php';

$auth = require_auth();
if (($auth['user_type'] ?? '') === 'driver') {
    json_error('Only residents can file or view reports.', 403);
}
$residentId = (int)$auth['user_id'];

const BLOB_COLUMNS    = ['photo', 'photo_data', 'video_data'];
const MAX_PHOTO_BYTES = 6 * 1024 * 1024; // decoded size
const MAX_VIDEO_BYTES = 6 * 1024 * 1024; // decoded size

$ALLOWED_TYPES = [
    'missed_collection', 'illegal_dumping', 'overflowing_bin',
    'improper_segregation', 'burning_waste', 'blocked_drainage', 'other',
];

// Column names that really exist on `complaint`, so this file works whether
// the photo column is called photo_data or photo.
function complaint_columns($conn) {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM complaint");
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    return $cols;
}

// Validates a base64 data URI. Returns [value|null, errorMessage|null].
function check_data_uri($value, $mimeRegex, $maxBytes, $label) {
    $value = trim((string)$value);
    if ($value === '') return [null, null];

    if (!preg_match('#^data:(' . $mimeRegex . ');base64,#i', substr($value, 0, 80))) {
        return [null, "The $label format is not supported."];
    }
    $comma = strpos($value, ',');
    $raw = base64_decode(substr($value, $comma + 1), true);
    if ($raw === false || $raw === '') {
        return [null, "The $label could not be read. Please try again."];
    }
    if (strlen($raw) > $maxBytes) {
        return [null, "The $label is too large (max " . round($maxBytes / 1048576) . " MB)."];
    }
    return [$value, null];
}

$cols = complaint_columns($conn);

// ------------------------------------------------------------------ GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Never send the heavy photo/video data in the list, only flags saying
    // whether the report has one.
    $select = [];
    foreach ($cols as $c) {
        if (!in_array($c, BLOB_COLUMNS, true)) $select[] = "`$c`";
    }
    foreach (array_intersect(BLOB_COLUMNS, $cols) as $b) {
        $select[] = "(`$b` IS NOT NULL AND `$b` <> '') AS `has_$b`";
    }

    $stmt = $conn->prepare(
        "SELECT " . implode(', ', $select) . "
         FROM complaint
         WHERE resident_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param('i', $residentId);
    $stmt->execute();

    $reports = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $r['has_photo'] = !empty($r['has_photo_data']) || !empty($r['has_photo']);
        $r['has_video'] = !empty($r['has_video_data']);
        unset($r['has_photo_data'], $r['has_video_data']);
        $reports[] = $r;
    }
    json_ok(['reports' => $reports]);
}

// ------------------------------------------------------------------ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    json_error('Invalid request. The photo or video may be too large to upload.', 400);
}

$concern   = mb_substr(trim((string)($in['concern'] ?? '')), 0, 2000);
$wasteType = trim((string)($in['waste_type'] ?? ''));
$barangay  = mb_substr(trim((string)($in['barangay'] ?? '')), 0, 100);

if (!in_array($wasteType, $ALLOWED_TYPES, true)) json_error('Please choose a type of waste.', 400);
if ($barangay === '') json_error('Please select a barangay.', 400);
if ($wasteType === 'other' && $concern === '') json_error('Please describe the issue.', 400);

// Photo (accepts the older key names too)
$photoIn = $in['photo_base64'] ?? $in['photo_data'] ?? $in['photo'] ?? '';
[$photo, $photoErr] = check_data_uri($photoIn, 'image/(?:jpeg|png|gif|webp)', MAX_PHOTO_BYTES, 'photo');
if ($photoErr) json_error($photoErr, 400);

// Video
$videoIn = $in['video_base64'] ?? $in['video_data'] ?? '';
[$video, $videoErr] = check_data_uri($videoIn, 'video/(?:mp4|quicktime|3gpp|webm)', MAX_VIDEO_BYTES, 'video');
if ($videoErr) json_error($videoErr, 400);

$photoCol = in_array('photo_data', $cols, true) ? 'photo_data' : (in_array('photo', $cols, true) ? 'photo' : null);
if ($photo !== null && $photoCol === null) {
    json_error('Photo uploads are not set up on the server yet.', 500);
}
if ($video !== null && !in_array('video_data', $cols, true)) {
    json_error('Video uploads are not set up on the server yet. Add the video_data column first.', 500);
}

// The resident's name and phone are stored on the complaint too.
$p = $conn->prepare("SELECT first_name, last_name, phone FROM resident WHERE id = ? LIMIT 1");
$p->bind_param('i', $residentId);
$p->execute();
$res = $p->get_result()->fetch_assoc() ?: [];
$residentName  = trim(($res['first_name'] ?? '') . ' ' . ($res['last_name'] ?? ''));
$residentPhone = (string)($res['phone'] ?? '');

// Build the INSERT only from columns that exist.
$data = [];
$add = function ($col, $type, $val) use (&$data, $cols) {
    if (in_array($col, $cols, true)) $data[$col] = [$type, $val];
};
$add('resident_id',    'i', $residentId);
$add('resident_name',  's', $residentName !== '' ? $residentName : 'Resident');
$add('resident_phone', 's', $residentPhone);
$add('concern',        's', $concern);
$add('waste_type',     's', $wasteType);
$add('barangay',       's', $barangay);
$add('status',         's', 'Pending');
if ($photo !== null) $add($photoCol, 's', $photo);
if ($video !== null) $add('video_data', 's', $video);

foreach (['resident_id', 'concern', 'waste_type', 'barangay'] as $required) {
    if (!isset($data[$required])) json_error("Server setup problem: complaint.$required column is missing.", 500);
}

$names = array_keys($data);
$types = '';
$vals  = [];
foreach ($data as [$t, $v]) { $types .= $t; $vals[] = $v; }

try {
    $sql = "INSERT INTO complaint (`" . implode('`, `', $names) . "`) VALUES ("
         . implode(',', array_fill(0, count($names), '?')) . ")";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $newId = (int)$conn->insert_id;
} catch (Throwable $e) {
    error_log('reports.php insert failed: ' . $e->getMessage());
    json_error('Could not save your report. If it has a photo or video, try a smaller file.', 500);
}

// Activity log (never blocks the report if it fails)
try {
    $action = 'New complaint from ' . ($residentName !== '' ? $residentName : 'a resident');
    $l = $conn->prepare("INSERT INTO activity_log (action) VALUES (?)");
    $l->bind_param('s', $action);
    $l->execute();
} catch (Throwable $e) {
    // ignore
}

json_ok(['message' => 'Report submitted.', 'id' => $newId]);
