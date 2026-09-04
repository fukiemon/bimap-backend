<?php
// BiMAP Database Configuration
// Local (XAMPP): uses defaults below
// Production (Render + Aiven): set env vars DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, DB_SSL

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'bimapcapstone_db');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_SSL', getenv('DB_SSL') ?: 'false');

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
        die('<div style="font-family:sans-serif;padding:40px;background:#fee;border:1px solid red;margin:20px;">
            <h2>Database Connection Failed</h2>
            <p>' . mysqli_connect_error() . '</p>
        </div>');
    }
} else {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die('<div style="font-family:sans-serif;padding:40px;background:#fee;border:1px solid red;margin:20px;">
            <h2>Database Connection Failed</h2>
            <p>' . $conn->connect_error . '</p>
            <p>Make sure XAMPP MySQL is running and you have imported <strong>bimap_db.sql</strong> in phpMyAdmin.</p>
        </div>');
    }
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
        header('Location: ../index.php');
        exit;
    }
}
?>
