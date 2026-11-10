<?php
// POST /api/login.php   (patched: returns the full profile)
// Body: { "email": "...", "password": "..." }
// ("email" also accepts a phone number - matched against either column)
//
// Email matching is BINARY (case-sensitive): "John@x.com" and "john@x.com"
// are different. Phone matching is unchanged.
//
// user_type is optional. If omitted, we try resident first, then driver.
//
// SECURITY: after 5 failed attempts for the same email/phone, login is
// blocked for 15 minutes (table: login_attempts).
//
// Returns: { success: true, token: "...", user: { id, name, email, role, barangay, ...profile } }
// The original keys (id, name, email, role, barangay) are unchanged so
// AppUser.fromJson keeps working. New keys are extra.
//
// NOTE: drivers no longer have an assigned_barangay column. For drivers,
// "barangay" now carries the driver's location text.

require_once __DIR__ . '/../includes/api_helpers.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

$body = get_json_body();

$identifier = '';
foreach (['email', 'identifier', 'phone'] as $key) {
    if (trim((string)($body[$key] ?? '')) !== '') { $identifier = trim($body[$key]); break; }
}
$password = $body['password'] ?? '';
$requestedType = strtolower(trim($body['user_type'] ?? ''));

if ($identifier === '' || $password === '') {
    json_error('Email/phone and password are required.');
}
if ($requestedType !== '' && !in_array($requestedType, ['resident', 'driver'], true)) {
    json_error('user_type must be "resident" or "driver".');
}

// ---- Lockout check (before any password is tested) ----
// The lockout key stays lowercased on purpose: it stops someone from
// dodging the lockout by varying the letter case of the same email.
$attemptKey = mb_substr(mb_strtolower($identifier), 0, 150);
$lockMinutes = LOGIN_LOCK_MINUTES;

$q = $conn->prepare(
    "SELECT COUNT(*) AS n FROM login_attempts
     WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
);
$q->bind_param('si', $attemptKey, $lockMinutes);
$q->execute();
$failed = (int) ($q->get_result()->fetch_assoc()['n'] ?? 0);

if ($failed >= LOGIN_MAX_ATTEMPTS) {
    json_error('Too many failed attempts. Please try again in ' . LOGIN_LOCK_MINUTES . ' minutes.', 429);
}

// Try each candidate table in order until we find a matching account.
$candidates = $requestedType !== '' ? [$requestedType] : ['resident', 'driver'];

$user = null;
$userType = null;

foreach ($candidates as $table) {
    // BINARY email = ?  -> exact, case-sensitive byte comparison for email.
    // phone = ?         -> normal comparison, as before.
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE BINARY email = ? OR phone = ? LIMIT 1");
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($password, $row['password'])) {
        $user = $row;
        $userType = $table;
        break;
    }
}

if (!$user) {
    // Record the failed attempt and clean out old records.
    $ins = $conn->prepare("INSERT INTO login_attempts (identifier) VALUES (?)");
    $ins->bind_param('s', $attemptKey);
    $ins->execute();
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");

    json_error('Invalid credentials.', 401);
}

// Correct password: forget earlier failures.
$clr = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ?");
$clr->bind_param('s', $attemptKey);
$clr->execute();

if ($userType === 'driver' && (int)($user['is_verified'] ?? 0) === 0) {
    json_error('Your driver account is still pending admin approval.', 403);
}

// NEW: block suspended drivers (checked after the password so the status
// is only revealed to someone who knows the correct password).
if ($userType === 'driver' && (int)($user['is_suspended'] ?? 0) === 1) {
    json_error('Your account has been suspended. Please contact the admin.', 403);
}

$token = generate_token([
    'user_type' => $userType,
    'user_id'   => (int)$user['id'],
]);

// ---- Profile payload (never include the password hash) ----
$middleName    = trim((string)($user['middle_name'] ?? ''));
$nameExtension = trim((string)($user['name_extension'] ?? ''));
$fullName = trim(implode(' ', array_filter(
    [$user['first_name'], $middleName, $user['last_name'], $nameExtension],
    fn($p) => trim((string)$p) !== ''
)));

$profile = [
    'id'          => (int)$user['id'],
    'name'        => trim($user['first_name'] . ' ' . $user['last_name']),
    'first_name'  => $user['first_name'],
    'last_name'   => $user['last_name'],
    'middle_name' => $middleName !== '' ? $middleName : null,
    'name_extension' => $nameExtension !== '' ? $nameExtension : null,
    'full_name'   => $fullName,   // first + middle + last + extension
    'email'       => $user['email'],
    'phone'       => $user['phone'] ?? null,
    'role'        => $userType,
    'profile_pic' => $user['profile_pic'] ?? null,
    'created_at'  => $user['created_at'] ?? null,
];

if ($userType === 'driver') {
    // Old rows may hold a base64 data URI in license_photo; don't ship that
    // (it can be megabytes). Only return it when it is a file path.
    $photo = (string)($user['license_photo'] ?? '');
    $photoPath = ($photo !== '' && strncmp($photo, 'data:', 5) !== 0) ? $photo : null;

    // The table has both driver_license and license_number; use whichever is filled.
    $licenseNo = ($user['driver_license'] ?? '') !== ''
        ? $user['driver_license']
        : ($user['license_number'] ?? null);

    // assigned_barangay was removed: the driver's location text now plays that role.
    $profile['barangay']          = $user['location'] ?? null;
    $profile['license_number']    = $licenseNo;
    $profile['license_photo']     = $photoPath;   // e.g. "uploads/licenses/ab12....jpg"
    $profile['location']          = $user['location'] ?? null;
} else {
    $profile['barangay']          = $user['barangay'] ?? null;
    $profile['household_address'] = $user['household_address'] ?? null;
    $profile['municipality']      = 'Malita';
    $profile['province']          = 'Davao Occidental';
}

json_ok([
    'token' => $token,
    'user'  => $profile,
]);
