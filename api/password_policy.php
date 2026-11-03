<?php
// Password rules shared by register.php and password_reset.php.
//
// validate_password_strength($password, $personalInfo = [])
//   Returns null if the password is acceptable,
//   or a human-readable error message if it is not.
//
// IMPORTANT: this file must start with "<?php" on the very first line
// and must NOT have a closing PHP tag, so nothing leaks into the JSON response.

const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 72;   // bcrypt only uses the first 72 bytes

function validate_password_strength(string $password, array $personalInfo = []): ?string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }

    if (strlen($password) > PASSWORD_MAX_LENGTH) {
        return 'Password must be at most ' . PASSWORD_MAX_LENGTH . ' characters long.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/\d/', $password)) {
        return 'Password must contain at least one number.';
    }

    // Must not contain the person's name or email name (3+ characters).
    $lowerPassword = mb_strtolower($password);
    foreach ($personalInfo as $item) {
        $item = mb_strtolower(trim((string) $item));
        if (mb_strlen($item) >= 3 && mb_strpos($lowerPassword, $item) !== false) {
            return 'Password must not contain your name or email.';
        }
    }

    return null;
}
