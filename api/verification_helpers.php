<?php
/**
 * Shared helpers for the OTP verification flow.
 * Include this from send_verification.php, verify_code.php, register.php.
 *
 * Requires PHPMailer for email sending:
 *   composer require phpmailer/phpmailer
 *
 * Fill in your real credentials in config.php (see below) — never commit
 * real secrets to GitHub. Use environment variables on Render instead
 * (Render → your service → Environment tab) and read them with getenv().
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../vendor/autoload.php'; // PHPMailer, via composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Decide whether the submitted contact string is an email or a PH mobile number.
 * Returns 'email', 'sms', or null if it matches neither.
 */
function detect_contact_type(string $contact): ?string
{
    $contact = trim($contact);

    if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        return 'email';
    }

    // Accepts 09XXXXXXXXX, +639XXXXXXXXX, or 639XXXXXXXXX (PH mobile formats)
    if (preg_match('/^(?:\+?63|0)9\d{9}$/', preg_replace('/[\s\-]/', '', $contact))) {
        return 'sms';
    }

    return null;
}

/**
 * Normalize a PH mobile number to the 63XXXXXXXXXX format Semaphore expects.
 */
function normalize_ph_number(string $number): string
{
    $number = preg_replace('/[\s\-]/', '', $number);
    if (str_starts_with($number, '+63')) {
        return substr($number, 1); // drop the +
    }
    if (str_starts_with($number, '0')) {
        return '63' . substr($number, 1);
    }
    return $number; // already 63XXXXXXXXXX
}

function generate_otp_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Insert a fresh OTP row for this contact, invalidating any earlier unverified
 * codes for the same contact + purpose so old codes can't still be used.
 */
function store_verification_code(mysqli $conn, string $contact, string $type, string $code, string $purpose = 'registration', int $ttl_minutes = 10): void
{
    $stmt = $conn->prepare("DELETE FROM verification_codes WHERE contact = ? AND purpose = ? AND verified_at IS NULL");
    $stmt->bind_param("ss", $contact, $purpose);
    $stmt->execute();

    $stmt = $conn->prepare(
        "INSERT INTO verification_codes (contact, contact_type, code, purpose, expires_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))"
    );
    $stmt->bind_param("ssssi", $contact, $type, $code, $purpose, $ttl_minutes);
    $stmt->execute();
}

/**
 * Send the OTP by email using PHPMailer + Gmail SMTP.
 * Set MAIL_USERNAME / MAIL_APP_PASSWORD as environment variables on Render
 * (use a Gmail "App Password", not your normal Gmail password).
 */
function send_email_otp(string $toEmail, string $code): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(MAIL_USERNAME, 'BiMAP');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Your BiMAP verification code';
        $mail->Body    = "<p>Your BiMAP verification code is:</p><h2 style=\"letter-spacing:4px;\">{$code}</h2><p>This code expires in 10 minutes. If you didn't request this, you can ignore this email.</p>";
        $mail->AltBody = "Your BiMAP verification code is: {$code}. It expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email OTP send failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send the OTP by SMS using Semaphore (https://semaphore.co).
 * Set SEMAPHORE_API_KEY as an environment variable on Render.
 */
function send_sms_otp(string $toNumber, string $code): bool
{
    $number = normalize_ph_number($toNumber);
    $message = "Your BiMAP verification code is {$code}. It expires in 10 minutes.";

    $ch = curl_init('https://api.semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'apikey'  => SEMAPHORE_API_KEY,
            'number'  => $number,
            'message' => $message,
            // 'sendername' => 'BIMAP', // only if you've registered a sender name with Semaphore
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        error_log("SMS OTP send failed: HTTP {$httpCode} — " . var_export($response, true));
        return false;
    }
    return true;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
