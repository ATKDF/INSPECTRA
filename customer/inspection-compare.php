<?php
// Retired in favor of the canonical comparison page, which now includes
// the photo/zone side-by-side view this page used to be the only source of.
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireCustomer();

$bookingId = (int)($_GET['booking_id'] ?? 0);
redirect(APP_URL . '/customer/inspection-comparison.php' . ($bookingId ? '?booking_id=' . $bookingId : ''));
