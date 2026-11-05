<?php
// GET  /api/profile.php  -> the logged-in resident's or driver's own profile
// POST /api/profile.php  -> update profile info and/or picture
//   Body (all optional, only fields present are changed):
//     first_name, last_name, email, phone,
//     barangay, household_address   (resident AND driver),
//     profile_pic_base64            ("data:image/jpeg;base64,...")
//
// Requires: Authorization: Bearer <token> (resident or driver)
//
// Residents: barangay must be one of the 30 barangays of Malita, and only one
// resident account is allowed per household (barangay + household address) -
// see household_helpers.php.
//
// Drivers: barangay + household_address are editable too. The driver's
// `location` text is rebuilt from them so it always matches what they see.
//
// Read-only fields (license number/photo, is_verified, etc.) are
// intentionally NOT editable here.

require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/household_helpers.php';

$auth = require_auth();
$userType = $auth['user_type']; // 'resident' or 'driver'
if (!in_array($userType, ['resident', 'driver'], true)) {
    json_error('Unrecognized account type.', 403);
}
$userId = (int) $auth['user_id'];
$table = $userType; // whitelisted above, safe to interpolate

function public_url($relativePath) {
    if (!$relativePath) return null;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$scheme://$host/" . ltrim($relativePath, '/');
}

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
        unset($row['household_key']); // internal, never sent to the app
        $row['profile_pic_url'] = public_url($row['profile_pic'] ?? null);

        if ($table === 'driver') {
            $photo = (string) ($row['license_photo'] ?? '');
            if ($photo !== '' && strncmp($photo, 'data:', 5) !== 0) {
                $row['license_photo_url'] = public_url($photo);
            } else {
                $row['license_photo_url'] = null;
            }
            unset($row['license_photo']);
        }
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

    $addressSent = array_key_exists('barangay', $body) || array_key_exists('household_address', $body);
    $newBarangay = array_key_exists('barangay', $body) ? trim($body['barangay']) : ($current['barangay'] ?? '');
    $newAddress  = array_key_exists('household_address', $body)
        ? trim($body['household_address'])
        : ($current['household_address'] ?? '');

    // Resident: barangay + household address (one account per household).
    if ($table === 'resident' && $addressSent) {
        $err = apply_resident_address_update($conn, $userId, $newBarangay, $newAddress);
        if ($err !== null) {
            json_error($err, 409);
        }
    }

    // Driver: barangay + address are editable; location text is rebuilt from them.
    $driverBarangay = $current['barangay'] ?? null;
    $driverAddress  = $current['household_address'] ?? null;
    $location       = $current['location'] ?? '';
    if ($table === 'driver' && $addressSent) {
        $canon = canonical_barangay($newBarangay);
        if ($canon === null) {
            json_error('Please select a barangay in Malita, Davao Occidental.');
        }
        if (mb_strlen($newAddress) < 3 || mb_strlen($newAddress) > 200) {
            json_error('Address / landmark must be between 3 and 200 characters.');
        }
        $driverBarangay = $canon;
        $driverAddress  = $newAddress;
        $location = $newAddress . ', Brgy. ' . $canon . ', Malita, Davao Occidental';
        if (mb_strlen($location) > 200) {
            $location = mb_substr($location, 0, 200);
        }
    }

    $profilePicPath = $current['profile_pic'] ?? null;
    if (array_key_exists('profile_pic_base64', $body) && $body['profile_pic_base64']) {
        $newPath = save_base64_image($body['profile_pic_base64'], 'profile_pics', $table);
        if ($newPath) {
            if ($profilePicPath && is_file(__DIR__ . '/../' . $profilePicPath)) {
                @unlink(__DIR__ . '/../' . $profilePicPath);
            }
            $profilePicPath = $newPath;
        }
    }

    if ($table === 'driver') {
        $stmt = $conn->prepare(
            "UPDATE `driver` SET first_name = ?, last_name = ?, email = ?, phone = ?, barangay = ?, household_address = ?, location = ?, profile_pic = ? WHERE id = ?"
        );
        $stmt->bind_param('ssssssssi', $firstName, $lastName, $email, $phone, $driverBarangay, $driverAddress, $location, $profilePicPath, $userId);
    } else {
        $stmt = $conn->prepare(
            "UPDATE `resident` SET first_name = ?, last_name = ?, email = ?, phone = ?, profile_pic = ? WHERE id = ?"
        );
        $stmt->bind_param('sssssi', $firstName, $lastName, $email, $phone, $profilePicPath, $userId);
    }
    $stmt->execute();

    $row = fetch_profile_row($conn, $table, $userId);
    json_ok(['user' => $row]);

} else {
    json_error('Method not allowed. Use GET or POST.', 405);
}
