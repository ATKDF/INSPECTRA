<?php
// =============================================
// INSPECTRA — Configuration Template
// =============================================
// Copy this file to config.php and fill in real values.
// config.php is gitignored — never commit real credentials.

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inspectra_db');
define('DB_PORT', 3306);

define('APP_NAME', 'Inspectra');
define('APP_URL', 'http://localhost/inspectra');
define('APP_ENV', 'development'); // 'production' in live

// JWT / Session
define('JWT_SECRET', 'generate-a-long-random-string-here');
define('SESSION_LIFETIME', 86400 * 7); // 7 days

// File Uploads
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Stripe
define('STRIPE_PUBLIC_KEY', 'pk_test_your_key_here');
define('STRIPE_SECRET_KEY', 'sk_test_your_key_here');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret');

// Paystack
define('PAYSTACK_PUBLIC_KEY', 'pk_test_your_key_here');
define('PAYSTACK_SECRET_KEY', 'sk_test_your_key_here');

// Google OAuth
define('GOOGLE_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-client-secret');
define('GOOGLE_REDIRECT_URI', APP_URL . '/api/auth/google.php');  // Matches api/auth/google.php callback

// Email (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@inspectra.com');
define('SMTP_FROM_NAME', 'Inspectra');

// Timezone
date_default_timezone_set('Africa/Accra');

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
