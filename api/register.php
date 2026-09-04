<?php
// POST /api/register.php
//
// Resident body:
// { "user_type": "resident", "first_name", "last_name", "phone", "email",
//   "barangay", "password" }
//
// Driver body (needs admin approval before login works):
// { "user_type": "driver", "first_name", "last_name", "phone", "email",
//   "location", "driver_license", "license_number", "password",
//   "license_photo_base64": "data:image/jpeg;base64,..." }   // optional
//
// Returns: { success: true, token, user } for residents (auto-verified),
//          { success: true, pending_approval: true, user } for drivers.

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

$body = get_json_body();
$userType = strtolower(trim($body['user_type'] ?? 'resident'));

function require_fields(array $body, array $fields) {
    foreach ($fields as $f) {
        if (trim((string)($body[$f] ?? '')) === '') {
            json_error("Field '$f' is required.");
        }
    }
}

function email_or_phone_taken($conn, $table, $email, $phone) {
    $stmt = $conn->prepare("SELECT id FROM `$table` WHERE (email = ? AND email <> '') OR (phone = ? AND phone <> '') LIMIT 1");
    $stmt->bind_param('ss', $email, $phone);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

// Saves a base64 (optionally data-URI-prefixed) image to $dir and returns the
// relative path to store in the DB, or null if no image was supplied.
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

if ($userType === 'resident') {
    require_fields($body, ['first_name', 'last_name', 'password']);
    $firstName = trim($body['first_name']);
    $lastName  = trim($body['last_name']);
    $phone     = trim($body['phone'] ?? '');
    $email     = trim($body['email'] ?? '');
    $barangay  = trim($body['barangay'] ?? '');
    $password  = $body['password'];

    if ($email === '' && $phone === '') {
        json_error('Either email or phone is required.');
    }
    if (email_or_phone_taken($conn, 'resident', $email, $phone)) {
        json_error('An account with that email or phone already exists.', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Residents don't go through an admin approval step in this app (unlike
    // drivers), so we mark them verified immediately and let them log in right away.
    $stmt = $conn->prepare(
        "INSERT INTO resident (first_name, last_name, phone, email, barangay, password, is_verified)
         VALUES (?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->bind_param('ssssss', $firstName, $lastName, $phone, $email, $barangay, $hash);
    $stmt->execute();
    $newId = $conn->insert_id;

    $token = generate_token(['user_type' => 'resident', 'user_id' => $newId]);
    json_ok([
        'token' => $token,
        'user' => [
            'id' => $newId, 'first_name' => $firstName, 'last_name' => $lastName,
            'phone' => $phone, 'email' => $email, 'barangay' => $barangay, 'is_verified' => 1,
        ],
    ], 201);

} elseif ($userType === 'driver') {
    require_fields($body, ['first_name', 'last_name', 'password']);
    $firstName = trim($body['first_name']);
    $lastName  = trim($body['last_name']);
    $phone     = trim($body['phone'] ?? '');
    $email     = trim($body['email'] ?? '');
    $location  = trim($body['location'] ?? '');
    $license   = trim($body['driver_license'] ?? '');
    $licenseNo = trim($body['license_number'] ?? '');
    $password  = $body['password'];

    if ($email === '' && $phone === '') {
        json_error('Either email or phone is required.');
    }
    if (email_or_phone_taken($conn, 'driver', $email, $phone)) {
        json_error('An account with that email or phone already exists.', 409);
    }

    $licensePhotoPath = save_base64_image($body['license_photo_base64'] ?? null, 'driver_licenses', 'license');
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // is_verified defaults to 0 - an admin must approve the driver from
    // admin/driver_approvals.php before this account can log in.
    $stmt = $conn->prepare(
        "INSERT INTO driver (first_name, last_name, phone, email, location, driver_license, license_photo, license_number, password, is_verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
    );
    $stmt->bind_param('sssssssss', $firstName, $lastName, $phone, $email, $location, $license, $licensePhotoPath, $licenseNo, $hash);
    $stmt->execute();
    $newId = $conn->insert_id;

    json_ok([
        'pending_approval' => true,
        'message' => 'Registration received. An admin must approve your account before you can log in.',
        'user' => [
            'id' => $newId, 'first_name' => $firstName, 'last_name' => $lastName,
            'phone' => $phone, 'email' => $email, 'is_verified' => 0,
        ],
    ], 201);

} else {
    json_error('user_type must be "resident" or "driver".');
}
