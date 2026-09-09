<?php
// GET  /api/profile.php  -> the logged-in resident's or driver's own profile
// POST /api/profile.php  -> update profile info and/or picture
//   Body: { "first_name", "last_name", "email", "phone",
//            "barangay" (resident only) | "location" (driver only),
//            "profile_pic_base64": "data:image/jpeg;base64,..." }  (all optional —
//            only fields present in the body are changed)
//
// Requires: Authorization: Bearer <token> (resident or driver)
//
// Read-only fields (barangay assignment for drivers, license info,
// is_verified, etc.) are intentionally NOT editable here — this endpoint
// only touches the same profile fields admin/settings.php lets an admin
// edit about themselves.

require_once __DIR__ . '/../includes/api_helpers.php';

$auth = require_auth();
$userType = $auth['user_type']; // 'resident' or 'driver'
if (!in_array($userType, ['resident', 'driver'], true)) {
    json_error('Unrecognized account type.', 403);
}
$userId = (int) $auth['user_id'];
$table = $userType; // whitelisted above, safe to interpolate

// Builds an absolute URL for a stored relative path (e.g.
// 'uploads/profile_pics/resident_12_....jpg') so the Flutter app can drop
// it straight into Image.network without knowing the backend's folder
// layout. Falls back to http if HTTPS isn't detected (e.g. local XAMPP).
function public_url($relativePath) {
    if (!$relativePath) return null;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host/" . ltrim($relativePath, '/');
}

// Same convention as api/register.php's save_base64_image(): decodes a
// (optionally data-URI-prefixed) base64 image, writes it under /uploads,
// and returns the relative path to store in the DB.
function save_base64_image($base64, $dir, $prefix) {
    if (!$base64) return null;
    if (preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) {
        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $base64 = substr($base64, strpos($base64, ',') + 1);
    } else {
        $ext = 'jpg';
    }
    $data = base64_decode($base64, true);
    if ($data === false) return null;
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $fullDir = __DIR__ . '/../uploads/' . $dir;
    if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);
    file_put_contents($fullDir . '/' . $filename, $data);
    return 'uploads/' . $dir . '/' . $filename;
}

function fetch_profile_row($conn, $table, $userId) {
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        unset($row['password']);
        $row['profile_pic_url'] = public_url($row['profile_pic'] ?? null);
    }
    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $row = fetch_profile_row($conn, $table, $userId);
    if (!$row) json_error('Account not found.', 404);
    json_ok(['user' => $row]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body = get_json_body();

    $current = fetch_profile_row($conn, $table, $userId);
    if (!$current) json_error('Account not found.', 404);

    // Only touch fields the client actually sent, so a partial update
    // (e.g. picture only) doesn't blank out everything else.
    $firstName = array_key_exists('first_name', $body) ? trim($body['first_name']) : $current['first_name'];
    $lastName  = array_key_exists('last_name', $body) ? trim($body['last_name']) : $current['last_name'];
    $email     = array_key_exists('email', $body) ? trim($body['email']) : ($current['email'] ?? '');
    $phone     = array_key_exists('phone', $body) ? trim($body['phone']) : ($current['phone'] ?? '');

    if ($firstName === '' || $lastName === '') {
        json_error('First name and last name are required.');
    }
    if ($email === '' && $phone === '') {
        json_error('Either email or phone is required.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Please enter a valid email address.');
    }

    // Prevent silently colliding with another account's login identifier.
    if ($email !== '' || $phone !== '') {
        $stmt = $conn->prepare(
            "SELECT id FROM `$table` WHERE id != ? AND ((email != '' AND email = ?) OR (phone != '' AND phone = ?)) LIMIT 1"
        );
        $stmt->bind_param('iss', $userId, $email, $phone);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            json_error('That email or phone is already used by another account.', 409);
        }
    }

    $extraCol = null;
    $extraVal = null;
    if ($table === 'resident') {
        $extraCol = 'barangay';
        $extraVal = array_key_exists('barangay', $body) ? trim($body['barangay']) : ($current['barangay'] ?? '');
    } else { // driver
        $extraCol = 'location';
        $extraVal = array_key_exists('location', $body) ? trim($body['location']) : ($current['location'] ?? '');
    }

    $profilePicPath = $current['profile_pic'] ?? null;
    if (array_key_exists('profile_pic_base64', $body) && $body['profile_pic_base64']) {
        $newPath = save_base64_image($body['profile_pic_base64'], 'profile_pics', $table);
        if ($newPath) {
            // Clean up the old picture file, if any (same as admin/settings.php).
            if ($profilePicPath && is_file(__DIR__ . '/../' . $profilePicPath)) {
                @unlink(__DIR__ . '/../' . $profilePicPath);
            }
            $profilePicPath = $newPath;
        }
    }

    $stmt = $conn->prepare(
        "UPDATE `$table` SET first_name = ?, last_name = ?, email = ?, phone = ?, `$extraCol` = ?, profile_pic = ? WHERE id = ?"
    );
    $stmt->bind_param('ssssssi', $firstName, $lastName, $email, $phone, $extraVal, $profilePicPath, $userId);
    $stmt->execute();

    $row = fetch_profile_row($conn, $table, $userId);
    json_ok(['user' => $row]);

} else {
    json_error('Method not allowed. Use GET or POST.', 405);
}
