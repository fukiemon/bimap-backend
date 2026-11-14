<?php
// BiMAP Database Configuration
// Local (XAMPP): uses defaults below
// Production (Render + Aiven): set env vars DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, DB_SSL

// ---------------------------------------------------------------------
// Never show errors / file paths / database details to visitors.
// Details go to the server log instead (Render -> Logs).
// ---------------------------------------------------------------------
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function bimap_fail_response(): void {
    if (!headers_sent()) http_response_code(500);
    $isApi = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
    if ($isApi) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Server error. Please try again later.']);
    } else {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
           . '<title>Something went wrong</title></head>'
           . '<body style="font-family:sans-serif;padding:40px;text-align:center;color:#333">'
           . '<h2>Something went wrong</h2>'
           . '<p>Please try again in a moment. If the problem continues, contact the system administrator.</p>'
           . '</body></html>';
    }
    exit;
}

// Any uncaught error: log the real reason privately, show a generic page.
set_exception_handler(function ($e) {
    error_log('BiMAP uncaught: ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    bimap_fail_response();
});

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'bimapcapstone_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_SSL', getenv('DB_SSL') ?: 'false');

try {
    if (DB_SSL === 'true') {
        // Aiven (and most managed MySQL hosts) require SSL
        $conn = mysqli_init();
        mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        mysqli_ssl_set($conn, null, null, null, null, null);
        $connected = mysqli_real_connect(
            $conn,
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME,
            (int) DB_PORT,
            null,
            MYSQLI_CLIENT_SSL
        );
        if (!$connected) {
            error_log('BiMAP DB connection failed: ' . mysqli_connect_error());
            bimap_fail_response();
        }
    } else {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if ($conn->connect_error) {
            error_log('BiMAP DB connection failed: ' . $conn->connect_error);
            bimap_fail_response();
        }
    }
} catch (Throwable $e) {
    error_log('BiMAP DB connection exception: ' . $e->getMessage());
    bimap_fail_response();
}

$conn->set_charset("utf8mb4");

// Helper: time ago
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 6) return floor($diff->d/7) . ' week' . (floor($diff->d/7) > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// Session check helper
function requireAdminLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ../index.php?login=1');
        exit;
    }
}
?>
