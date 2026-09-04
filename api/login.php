<?php
// POST /api/login.php
// Body: { "email": "...", "password": "..." }
// ("email" also accepts a phone number - matched against either column)
//
// user_type is optional. If omitted, we try resident first, then driver.
// Pass it explicitly if you want to skip the guesswork (e.g. from a
// role-specific login screen later).
//
// Returns: { success: true, token: "...", user: { id, name, email, role, barangay } }

require_once __DIR__ . '/../includes/api_helpers.php';

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

// Try each candidate table in order until we find a matching, verified account.
$candidates = $requestedType !== '' ? [$requestedType] : ['resident', 'driver'];

$user = null;
$userType = null;

foreach ($candidates as $table) {
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE email = ? OR phone = ? LIMIT 1");
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
    json_error('Invalid credentials.', 401);
}

if ($userType === 'driver' && (int)($user['is_verified'] ?? 0) === 0) {
    json_error('Your driver account is still pending admin approval.', 403);
}

$token = generate_token([
    'user_type' => $userType,
    'user_id'   => (int)$user['id'],
]);

// Shape the response to exactly match AppUser.fromJson's expected keys:
// id, name, email, role, barangay (address is not used yet, left null).
json_ok([
    'token' => $token,
    'user'  => [
        'id'       => (int)$user['id'],
        'name'     => trim($user['first_name'] . ' ' . $user['last_name']),
        'email'    => $user['email'],
        'role'     => $userType,
        'barangay' => $userType === 'driver'
            ? ($user['assigned_barangay'] ?? null)
            : ($user['barangay'] ?? null),
    ],
]);
