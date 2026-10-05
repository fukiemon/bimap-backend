<?php
// api/config.php
//
// On Render, set these as Environment Variables (Render > your service > Environment)
// so real secrets are never committed to GitHub.

// Brevo SMTP login (looks like xxxx@smtp-brevo.com)
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'your-brevo-login@smtp-brevo.com');

// Brevo SMTP key
define('MAIL_APP_PASSWORD', getenv('MAIL_APP_PASSWORD') ?: 'your-brevo-smtp-key');

// The sender address you verified in Brevo (shown as "From" on the emails)
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'yourgmail@gmail.com');

// Semaphore SMS (unused for now, safe to leave)
define('SEMAPHORE_API_KEY', getenv('SEMAPHORE_API_KEY') ?: '');
