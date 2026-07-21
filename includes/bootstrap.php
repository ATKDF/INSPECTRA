<?php
// =============================================
// INSPECTRA — Bootstrap (include this first)
// =============================================

require_once __DIR__ . '/../config.php';

// Load Composer dependencies (PHPMailer, etc)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

Auth::startSession();
