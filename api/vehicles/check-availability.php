<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$vid = (int)($_GET['vid'] ?? 0);
$pickup = sanitize($_GET['pickup'] ?? '');
$return = sanitize($_GET['return'] ?? '');
$exclude = (int)($_GET['exclude'] ?? 0);

if (!$vid || !$pickup || !$return) {
  json_response(['available' => false, 'error' => 'Missing parameters'], 400);
}

$available = check_vehicle_availability($vid, $pickup, $return, $exclude);

json_response(['available' => $available]);
?>

