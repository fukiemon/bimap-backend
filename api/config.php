<?php
// api/config.php
//
// Delete the old "cofig.php" typo file once this one is in place — a stray
// misspelled file won't be required by anything, but it's confusing to
// leave around.
//
// Local placeholders below — on Render, set these three as real
// Environment Variables instead (Render → your service → Environment tab)
// and let getenv() pick them up, so you never commit real secrets to GitHub.

define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'yourgmail@gmail.com');
define('MAIL_APP_PASSWORD', getenv('MAIL_APP_PASSWORD') ?: 'your-16-char-app-password');
define('SEMAPHORE_API_KEY', getenv('SEMAPHORE_API_KEY') ?: 'your-semaphore-key');
