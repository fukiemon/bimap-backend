<?php
// ============================================================================
// BiMAP API helpers
// Shared by everything under /api. Include this FIRST in every api/*.php file.
// ============================================================================

// ---- CORS + JSON headers ---------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Flutter (or a browser preflight) may send an OPTIONS request first.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

// ---- Response helpers -------------------------------------------------------
function json_ok($data = [], $status = 200) {
    http_response_code($status);
    echo json_encode(['success' => true] + $data, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

// Reads and decodes a JSON request body. Falls back to $_POST so the same
// endpoint also works with normal form-encoded requests.
function get_json_body() {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;
    }
    return $_POST;
}

// ---- Auth tokens ------------------------------------------------------------
// There is no dedicated token column/table in the existing schema, and mobile
// requests are stateless (no PHP session cookie), so we sign a small, self
// contained token instead of storing sessions server-side: HMAC-SHA256 over
// a JSON payload, base64url-encoded — i.e. a minimal JWT.
//
// IMPORTANT: change this secret before deploying to production, and set it
// via an environment variable (API_SECRET) rather than hardcoding it.
define('API_SECRET', getenv('API_SECRET') ?: 'bimap-dev-secret-change-me');
define('TOKEN_TTL_SECONDS', 60 * 60 * 24 * 30); // 30 days

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

// $payload must include at least: user_type ('resident'|'driver'), user_id
function generate_token(array $payload) {
    $payload['iat'] = time();
    $payload['exp'] = time() + TOKEN_TTL_SECONDS;
    $body = base64url_encode(json_encode($payload));
    $sig  = base64url_encode(hash_hmac('sha256', $body, API_SECRET, true));
    return $body . '.' . $sig;
}

// Returns the decoded payload array, or null if the token is missing/invalid/expired.
function verify_token($token) {
    if (!$token || strpos($token, '.') === false) return null;
    [$body, $sig] = explode('.', $token, 2);
    $expected = base64url_encode(hash_hmac('sha256', $body, API_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!is_array($payload) || !isset($payload['exp']) || $payload['exp'] < time()) return null;
    return $payload;
}

function get_bearer_token() {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';
    if (function_exists('apache_request_headers') && !$header) {
        foreach (apache_request_headers() as $k => $v) {
            if (strtolower($k) === 'authorization') { $header = $v; break; }
        }
    }
    if (preg_match('/Bearer\s+(.*)$/i', $header, $m)) return trim($m[1]);
    return null;
}

// Call at the top of any endpoint that requires login. Exits with 401 on failure,
// otherwise returns the token payload (['user_type' => ..., 'user_id' => ..., ...]).
//
// For drivers, the database is checked on EVERY request, so suspending (or
// deleting) a driver cuts them off immediately even though their token is
// still validly signed.
function require_auth() {
    global $conn;

    $token = get_bearer_token();
    $payload = verify_token($token);
    if (!$payload) {
        json_error('Unauthorized. Missing or invalid token.', 401);
    }

    if (($payload['user_type'] ?? '') === 'driver') {
        $driverId = (int)($payload['user_id'] ?? 0);
        $stmt = $conn->prepare("SELECT is_verified, is_suspended FROM driver WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $driverId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            json_error('Account no longer exists.', 401);
        }
        if ((int)$row['is_suspended'] === 1) {
            json_error('Your account has been suspended. Please contact the admin.', 403);
        }
        if ((int)$row['is_verified'] === 0) {
            json_error('Your driver account is still pending admin approval.', 403);
        }
    }

    return $payload;
}
