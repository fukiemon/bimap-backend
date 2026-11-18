<?php
// includes/driver_mail.php
// Emails a driver when the admin approves or rejects their registration.
// Uses the same Brevo SMTP setup as api/verification_helpers.php (MAIL_* settings in api/config.php).
// A failure here only returns false and is logged; it never stops the approval/rejection itself.

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer, via composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * @param string $status 'approved' or 'rejected'
 * @param string $reason optional text shown to the driver (rejections only)
 */
function send_driver_status_email(string $toEmail, string $name, string $status, string $reason = ''): bool
{
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('Driver status email skipped: no valid email on file.');
        return false;
    }

    $safeName   = htmlspecialchars(trim($name) !== '' ? trim($name) : 'Driver', ENT_QUOTES, 'UTF-8');
    $reason     = trim($reason);
    $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

    if ($status === 'approved') {
        $subject = 'Your BiMAP driver account has been approved';
        $html    = "<p>Hello {$safeName},</p>"
                 . "<p>Your BiMAP driver account has been <strong>approved by the admin</strong>. "
                 . "You can now log in to the BiMAP app.</p>"
                 . "<p>Thank you,<br>BiMAP</p>";
        $text    = "Hello {$name},\n\nYour BiMAP driver account has been approved by the admin. "
                 . "You can now log in to the BiMAP app.\n\nThank you,\nBiMAP";
    } else {
        $subject = 'Update on your BiMAP driver registration';
        $html    = "<p>Hello {$safeName},</p>"
                 . "<p>Your BiMAP driver account has been <strong>rejected by the admin</strong>.</p>"
                 . ($reason !== '' ? "<p><strong>Reason:</strong> {$safeReason}</p>" : '')
                 . "<p>If you think this is a mistake, you may register again with correct details.</p>"
                 . "<p>Thank you,<br>BiMAP</p>";
        $text    = "Hello {$name},\n\nYour BiMAP driver account has been rejected by the admin.\n"
                 . ($reason !== '' ? "Reason: {$reason}\n" : '')
                 . "If you think this is a mistake, you may register again with correct details.\n\nThank you,\nBiMAP";
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 2525;
        $mail->Timeout    = 10; // don't make the admin wait long if the mail server is slow
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, 'BiMAP');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Driver status email failed: ' . $mail->ErrorInfo);
        return false;
    } catch (Throwable $e) {
        error_log('Driver status email failed: ' . $e->getMessage());
        return false;
    }
}
