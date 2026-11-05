<?php
/**
 * POST /api/register.php   (patched)
 *
 * JSON body sent by the Flutter app:
 *   user_type            'resident' | 'driver'
 *   first_name, last_name
 *   phone  ("+639XXXXXXXXX")  OR  email
 *   barangay, password
 *   resident only: household_address        (house no., street or landmark; used for the one-account-per-household rule)
 *   driver only:   license_number, license_photo_base64 (data URI), location
 *
 * The contact must already be verified (verify_code.php) within the last
 * 30 minutes, using the exact same contact string.
 *
 * Run bimap_migration.sql first (adds resident.household_address, household_key).
 */

require_once __DIR__ . '/../includes/db.php';          // must define $conn (mysqli)
require_once __DIR__ . '/verification_helpers.php';    // contact_is_verified(), json_response()
require_once __DIR__ . '/password_policy.php';         // validate_password_strength()
require_once __DIR__ . '/household_helpers.php';       // MALITA_BARANGAYS, canonical_barangay(), make_household_key()

// ============================ CONFIG ============================
$RESIDENT_COLS = [
    'first_name'    => 'first_name',
    'last_name'     => 'last_name',
    'email'         => 'email',
    'phone'         => 'phone',
    'barangay'      => 'barangay',
    'household_address' => 'household_address',
    'household_key' => 'household_key',
    'password'      => 'password',
];

$DRIVER_COLS = [
    'first_name'     => 'first_name',
    'last_name'      => 'last_name',
    'email'          => 'email',
    'phone'          => 'phone',
    // assigned_barangay was removed. The driver's barangay now lives inside
    // `location` (see below), so the barangay field itself is not saved.
    'barangay'       => null,
    'password'       => 'password',
    'license_number' => 'license_number',
    'driver_license' => 'driver_license',   // duplicate column; remove this line if you drop it (see migration)
    'license_photo'  => 'license_photo',    // stores the file path, see PHOTO_MODE
    'location'       => 'location',
    'is_verified'    => 'is_verified',      // 0 = pending admin approval (checked by login.php)
];

// How to save the license photo: 'file' = save into a folder and store the
// path, 'base64' = store the data URI text directly in the license_photo column.
$PHOTO_MODE    = 'file';
$UPLOAD_DIR_FS = __DIR__ . '/../uploads/licenses/';
$UPLOAD_DIR_DB = 'uploads/licenses/';
// ================================================================

function fail(string $msg, int $status = 400): void
{
    // The app reads 'error'; 'message' is included for consistency with the OTP files.
    json_response(['success' => false, 'error' => $msg, 'message' => $msg], $status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed.', 405);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    fail('Invalid request.');
}

$role      = trim($in['user_type'] ?? 'resident');
$firstName = trim($in['first_name'] ?? '');
$lastName  = trim($in['last_name'] ?? '');
$email     = trim($in['email'] ?? '');
$phone     = trim($in['phone'] ?? '');
$barangay  = trim($in['barangay'] ?? '');
$householdAddress = trim($in['household_address'] ?? '');
$password  = (string) ($in['password'] ?? '');
$licenseNo = trim($in['license_number'] ?? '');
$photoB64  = (string) ($in['license_photo_base64'] ?? '');
$location  = trim($in['location'] ?? '');

if (!in_array($role, ['resident', 'driver'], true)) {
    fail('Invalid account type.');
}
if ($firstName === '' || $lastName === '' || $barangay === '' || $password === '') {
    fail('Please fill in all required fields.');
}

// Only the 30 barangays of Malita are allowed.
$barangayCanon = canonical_barangay($barangay);
if ($barangayCanon === null) {
    fail('Please select a barangay in Malita, Davao Occidental.');
}
$barangay = $barangayCanon;

$pwError = validate_password_strength($password, [$firstName, $lastName, strtok($email, '@') ?: '', $phone]);
if ($pwError !== null) {
    fail($pwError);
}
if ($email === '' && $phone === '') {
    fail('Please provide an email or mobile number.');
}

// The contact string must match exactly what was verified.
if ($email !== '') {
    $contact = $email;
    $type    = 'email';
    if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        fail('Please enter a valid email address.');
    }
} else {
    $contact = $phone;
    $type    = 'sms';
}

if (!contact_is_verified($conn, $contact)) {
    fail('Please verify your ' . ($type === 'email' ? 'email' : 'mobile number') . ' first. Your code may have expired.', 403);
}

// Duplicate check on both tables.
$col = $type === 'email' ? 'email' : 'phone';
foreach (['resident', 'driver'] as $t) {
    $c = $conn->prepare("SELECT id FROM `$t` WHERE `$col` = ? AND `$col` <> '' LIMIT 1");
    $c->bind_param('s', $contact);
    $c->execute();
    if ($c->get_result()->fetch_assoc()) {
        fail('That ' . ($type === 'email' ? 'email' : 'number') . ' is already registered.', 409);
    }
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$savedFile = null;

if ($role === 'resident') {
    $table = 'resident';
    $cols  = $RESIDENT_COLS;

    if ($householdAddress === '') {
        fail('Please enter your household address or landmark.');
    }
    if (mb_strlen($householdAddress) < 3 || mb_strlen($householdAddress) > 200) {
        fail('Household address must be between 3 and 200 characters.');
    }

    // Household identity = barangay + household address (see household_helpers.php).
    $householdKey = make_household_key($barangay, $householdAddress);
    if ($householdKey === null) {
        fail('Please enter a valid household address or landmark.');
    }

    $h = $conn->prepare("SELECT id FROM resident WHERE household_key = ? LIMIT 1");
    $h->bind_param('s', $householdKey);
    $h->execute();
    if ($h->get_result()->fetch_assoc()) {
        fail('A resident account already exists for this household. Only one family member can register per household. Please ask the registered family member to log in, or contact your barangay.', 409);
    }

    $data = [
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'barangay'      => $barangay,
        'household_address' => $householdAddress,
        'household_key' => $householdKey,
        'password'      => $hash,
        $col            => $contact,
    ];
} else {
    $table = 'driver';
    $cols  = $DRIVER_COLS;

    if ($licenseNo === '') fail("Please enter your driver's license number.");
    if ($photoB64 === '')  fail('Please upload a photo of your license.');
    if ($location === '')  fail('Please provide your location.');

    // Make sure the barangay name is part of the saved location so residents
    // of that barangay can find this driver's truck.
    if (stripos($location, $barangay) === false) {
        $location = $barangay . ', ' . $location;
    }
    if (mb_strlen($location) > 200) {
        $location = mb_substr($location, 0, 200);
    }

    // Validate the photo (data URI, jpg/png, max 5MB).
    if (!preg_match('#^data:image/(png|jpe?g);base64,(.+)$#is', $photoB64, $m)) {
        fail('License photo must be a JPG or PNG.');
    }
    $ext   = strtolower($m[1]) === 'png' ? 'png' : 'jpg';
    $bytes = base64_decode($m[2], true);
    if ($bytes === false || $bytes === '') {
        fail('License photo could not be read.');
    }
    if (strlen($bytes) > 5 * 1024 * 1024) {
        fail('License photo must be under 5MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        fail('License photo must be a JPG or PNG.');
    }

    if ($PHOTO_MODE === 'file') {
        if (!is_dir($UPLOAD_DIR_FS) && !mkdir($UPLOAD_DIR_FS, 0755, true)) {
            error_log('register.php: cannot create ' . $UPLOAD_DIR_FS);
            fail('Could not save the license photo.', 500);
        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (file_put_contents($UPLOAD_DIR_FS . $name, $bytes) === false) {
            fail('Could not save the license photo.', 500);
        }
        $savedFile   = $UPLOAD_DIR_FS . $name;
        $photoValue  = $UPLOAD_DIR_DB . $name;
    } else {
        $photoValue = $photoB64;
    }

    $data = [
        'first_name'     => $firstName,
        'last_name'      => $lastName,
        'barangay'       => $barangay,
        'password'       => $hash,
        'license_number' => $licenseNo,
        'driver_license' => $licenseNo,
        'license_photo'  => $photoValue,
        'location'       => $location,
        'is_verified'    => 0,
        $col             => $contact,
    ];
}

// Build the INSERT from the column map (skips any column mapped to null).
$names = [];
$vals  = [];
foreach ($data as $key => $value) {
    if (!isset($cols[$key]) || $cols[$key] === null) continue;
    $names[] = '`' . $cols[$key] . '`';
    $vals[]  = $value;
}

$sql = "INSERT INTO `$table` (" . implode(',', $names) . ") VALUES (" . implode(',', array_fill(0, count($vals), '?')) . ")";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new mysqli_sql_exception($conn->error, $conn->errno);
    }
    $stmt->bind_param(str_repeat('s', count($vals)), ...$vals);
    if (!$stmt->execute()) {
        throw new mysqli_sql_exception($stmt->error, $stmt->errno);
    }
} catch (Throwable $e) {
    error_log('register.php insert failed: ' . $e->getMessage());
    if (!empty($savedFile)) @unlink($savedFile);

    // 1062 = duplicate key. The unique index is the final guard against two
    // people registering the same household at the same moment.
    if ((int) $e->getCode() === 1062) {
        if (str_contains($e->getMessage(), 'uq_resident_household')) {
            fail('A resident account already exists for this household. Only one family member can register per household.', 409);
        }
        fail('That ' . ($type === 'email' ? 'email' : 'number') . ' is already registered.', 409);
    }
    fail('Could not create the account. Please try again.', 500);
}

// The verification is used up; remove it so it can't be reused.
$del = $conn->prepare("DELETE FROM verification_codes WHERE contact = ? AND purpose = 'registration'");
$del->bind_param('s', $contact);
$del->execute();

json_response([
    'success' => true,
    'message' => $role === 'driver'
        ? 'Account created! Your driver application is pending admin approval.'
        : 'Account created successfully! You can now log in.',
]);
