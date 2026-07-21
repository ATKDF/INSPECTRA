<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

if (Auth::role() !== 'admin') { json_response(['success' => false, 'error' => 'Unauthorized'], 403); }

$inspId = (int)($_POST['inspection_id'] ?? 0);
$zone = trim($_POST['zone'] ?? '');
$damageType = trim($_POST['damage_type'] ?? '');
$severity = trim($_POST['severity'] ?? '');
$repairCost = $_POST['repair_cost'] ?? '';

if (!$inspId || !$zone || !$damageType || !$severity) {
    json_response(['success' => false, 'error' => 'Missing required fields: inspection_id, zone, damage_type, severity'], 400);
}
if ($repairCost === '' || !is_numeric($repairCost) || (float)$repairCost <= 0) {
    json_response(['success' => false, 'error' => 'A repair cost estimate greater than ₵0.00 is required.'], 400);
}

$photoUrl = '';
if (!empty($_FILES['damage_photo']['name'])) {
    $photoUrl = upload_file($_FILES['damage_photo'], 'inspections') ?: '';
}

DB::insert('damage_reports', [
    'uuid'         => Auth::generateUUID(),
    'inspection_id'=> $inspId,
    'zone'         => sanitize($zone),
    'damage_type'  => sanitize($damageType),
    'severity'     => sanitize($severity),
    'description'  => sanitize($_POST['description'] ?? ''),
    'repair_cost'  => (float)$repairCost,
    'photo_url'    => $photoUrl,
    'created_at'   => date('Y-m-d H:i:s'),
]);

// Log activity
DB::insert('inspection_activity', [
    'inspection_id' => $inspId,
    'user_id'       => Auth::id(),
    'action'        => 'Damage recorded in zone: ' . $zone,
    'detail'        => 'Type: ' . $damageType . ', Severity: ' . $severity,
    'latitude'      => sanitize($_POST['geo_lat'] ?? ''),
    'longitude'     => sanitize($_POST['geo_lng'] ?? ''),
    'created_at'    => date('Y-m-d H:i:s'),
]);

json_response(['success' => true, 'message' => 'Damage recorded successfully']);
