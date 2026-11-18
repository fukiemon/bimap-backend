<?php
// includes/push.php
// Sends push notifications through Firebase Cloud Messaging (FCM).
// A failure here never breaks the page that calls it: errors are only logged.

require_once __DIR__ . '/../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/** Builds the Firebase messaging client once. Returns null if no credentials are set up. */
function firebase_messaging() {
    static $messaging = null;
    if ($messaging) return $messaging;

    // Preferred: Render "Secret File". Fallback: FIREBASE_CREDENTIALS environment variable.
    // The secret file only counts if the web server can actually READ it
    // (on Render it can exist but be root-only, which gives "Permission denied").
    $file = '/etc/secrets/firebase-credentials.json';
    if (is_readable($file)) {
        $credentials = $file;
    } else {
        $json = getenv('FIREBASE_CREDENTIALS');
        if (!$json) {
            if (file_exists($file)) {
                error_log('Push: secret file exists but is not readable by the web server, and FIREBASE_CREDENTIALS is not set.');
            }
            return null;
        }
        $credentials = json_decode($json, true);
        if (!is_array($credentials)) {
            error_log('Push: FIREBASE_CREDENTIALS is not valid JSON.');
            return null;
        }
    }

    $messaging = (new Factory)->withServiceAccount($credentials)->createMessaging();
    return $messaging;
}

/** All saved phone tokens for a user type, or for one specific user. */
function getDeviceTokens(mysqli $conn, string $userType, ?int $userId = null): array {
    if ($userId === null) {
        $stmt = $conn->prepare("SELECT token FROM device_token WHERE user_type = ?");
        $stmt->bind_param('s', $userType);
    } else {
        $stmt = $conn->prepare("SELECT token FROM device_token WHERE user_type = ? AND user_id = ?");
        $stmt->bind_param('si', $userType, $userId);
    }
    $stmt->execute();
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'token');
}

/** Sends one notification to a list of phone tokens. */
function sendPush(mysqli $conn, array $tokens, string $title, string $body, array $data = []): void {
    $tokens = array_values(array_unique(array_filter($tokens)));
    if (!$tokens) {
        error_log('Push skipped: no saved phone tokens for this recipient.');
        return;
    }

    try {
        $messaging = firebase_messaging();
        if (!$messaging) {
            error_log('Push skipped: Firebase credentials are not set up.');
            return;
        }

        // FCM data values must all be strings.
        $data = array_map('strval', $data);

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            ->withHighestPossiblePriority();

        foreach (array_chunk($tokens, 500) as $chunk) {
            $report = $messaging->sendMulticast($message, $chunk);

            // Remove tokens that no longer work (app uninstalled, etc.)
            $dead = array_merge($report->invalidTokens(), $report->unknownTokens());
            foreach ($dead as $badToken) {
                $del = $conn->prepare("DELETE FROM device_token WHERE token = ?");
                $del->bind_param('s', $badToken);
                $del->execute();
            }
        }
    } catch (Throwable $e) {
        error_log('Push failed: ' . $e->getMessage());
    }
}

/** Notifies ONE user's phone(s). $userType is 'resident' or 'driver'. */
function pushToUser(mysqli $conn, string $userType, int $userId, string $title, string $body, array $data = []): void {
    sendPush($conn, getDeviceTokens($conn, $userType, $userId), $title, $body, $data);
}

/** Notifies the right people for an announcement (residents, drivers, or everyone). */
function pushAnnouncement(mysqli $conn, string $target, string $title, string $message): void {
    $tokens = [];
    if ($target === 'residents' || $target === 'all') {
        $tokens = array_merge($tokens, getDeviceTokens($conn, 'resident'));
    }
    if ($target === 'drivers' || $target === 'all') {
        $tokens = array_merge($tokens, getDeviceTokens($conn, 'driver'));
    }
    sendPush($conn, $tokens, $title, mb_strimwidth($message, 0, 120, '…'), ['type' => 'announcement']);
}
