<?php
// POST /api/login.php
// Body: { "email": "...", "password": "...", "user_type": "resident" | "driver" }
// ("email" also accepts a phone number - we match against either column)
//
// Returns: { success: true, token: "...", user: {...} }

require_once __DIR__ . '/../includes/api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed. Use POST.', 405);
}

$body = get_json_body();
// Accept whichever of these was actually provided (non-empty), in this
// priority order, rather than short-circuiting on a key that exists but is blank.
$identifier = '';
foreach (['email', 'identifier', 'phone'] as $key) {
    if (trim((string)($body[$key] ?? '')) !== '') { $identifier = trim($body[$key]); break; }
}
$password   = $body['password'] ?? '';
$userType   = strtolower(trim($body['user_type'] ?? 'resident'));

if ($identifier === '' || $password === '') {
    json_error('Email/phone and password are required.');
}

if (!in_array($userType, ['resident', 'driver'], true)) {
    json_error('user_type must be "resident" or "driver".');
}

$table = $userType; // 'resident' or 'driver' — both table names are safe, whitelisted above

$stmt = $conn->prepare("SELECT * FROM `$table` WHERE email = ? OR phone = ? LIMIT 1");
$stmt->bind_param('ss', $identifier, $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    json_error('Invalid credentials.', 401);
}

if ($userType === 'driver' && (int)($user['is_verified'] ?? 0) === 0) {
    json_error('Your driver account is still pending admin approval.', 403);
}

unset($user['password']); // never send the hash back

$token = generate_token([
    'user_type' => $userType,
    'user_id'   => (int)$user['id'],
]);

json_ok([
    'token' => $token,
    'user'  => $user,
]);
