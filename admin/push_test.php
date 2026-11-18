<?php
// admin/push_test.php  -- TEMPORARY diagnostic page. Delete it after testing.
// Usage (while logged in as admin):
//   /admin/push_test.php?type=resident&id=3
//   /admin/push_test.php?type=driver&id=5
// It prints why a push does or doesn't work. It never prints the private key.

session_start();
require_once '../includes/db.php';
requireAdminLogin();
require_once '../includes/push.php';

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

header('Content-Type: text/plain; charset=utf-8');

$type = (($_GET['type'] ?? 'resident') === 'driver') ? 'driver' : 'resident';
$id   = (int)($_GET['id'] ?? 0);

echo "== 1. Firebase credentials ==\n";
$file = '/etc/secrets/firebase-credentials.json';
echo "Secret file exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";
if (file_exists($file)) {
    $j = json_decode((string)file_get_contents($file), true);
    if (!is_array($j)) {
        echo "File is NOT valid JSON. Re-paste the full contents (from the first { to the last }).\n";
    } else {
        echo "Valid JSON: YES\n";
        echo "project_id: "   . ($j['project_id']   ?? '(missing)') . "\n";
        echo "client_email: " . ($j['client_email'] ?? '(missing)') . "\n";
        echo "has private_key: " . (!empty($j['private_key']) ? 'YES' : 'NO') . "\n";
    }
} else {
    echo "FIREBASE_CREDENTIALS variable set: " . (getenv('FIREBASE_CREDENTIALS') ? 'YES' : 'NO') . "\n";
}

echo "\n== 2. Saved phone tokens ($type" . ($id ? " #$id" : ', all') . ") ==\n";
$tokens = getDeviceTokens($conn, $type, $id ?: null);
echo "Tokens found: " . count($tokens) . "\n";
if (!$tokens) {
    echo "No token saved for this user. Log in on the phone as this user first.\n";
    exit;
}

echo "\n== 3. Sending a test push ==\n";
try {
    $messaging = firebase_messaging();
    if (!$messaging) {
        echo "firebase_messaging() returned null: credentials were not found or not readable.\n";
        exit;
    }
    foreach (array_unique($tokens) as $t) {
        $label = '...' . substr($t, -8);
        try {
            $msg = CloudMessage::withTarget('token', $t)
                ->withNotification(Notification::create('BiMAP test', 'If you see this, push works.'))
                ->withHighestPossiblePriority();
            $messaging->send($msg);
            echo "token $label: SENT OK (Firebase accepted it)\n";
        } catch (Throwable $e) {
            echo "token $label: FAILED\n  " . get_class($e) . "\n  " . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    echo "FAILED to start Firebase:\n  " . get_class($e) . "\n  " . $e->getMessage() . "\n";
}
