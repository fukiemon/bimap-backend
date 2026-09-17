<?php
/**
 * Reads secrets from environment variables.
 *
 * Locally (XAMPP): set these in a .env file or your Apache vhost config,
 * or just hardcode them here temporarily for local testing (but don't
 * commit real secrets to GitHub).
 *
 * On Render: Dashboard → your web service → Environment → add:
 *   MAIL_USERNAME       = yourproject@gmail.com
 *   MAIL_APP_PASSWORD   = the 16-character Gmail App Password (not your login password)
 *   SEMAPHORE_API_KEY   = your Semaphore API key
 */

define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_APP_PASSWORD', getenv('MAIL_APP_PASSWORD') ?: '');
define('SEMAPHORE_API_KEY', getenv('SEMAPHORE_API_KEY') ?: '');
