<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

if (Auth::role() !== 'admin') { json_response(['success' => false, 'error' => 'Unauthorized'], 403); }

$inspId = (int)($_POST['inspection_id'] ?? 0);
$condition = trim($_POST['overall_condition'] ?? '');
$fuelLevel = trim($_POST['fuel_level'] ?? '');

if (!$inspId || !$condition || !$fuelLevel) {
    json_response(['success' => false, 'error' => 'Missing required fields: inspection_id, overall_condition, fuel_level'], 400);
}

DB::update('inspections', [
    'overall_condition'    => sanitize($condition),
    'fuel_level'           => sanitize($fuelLevel),
    'mileage_at_inspection'=> (int)($_POST['mileage_at_inspection'] ?? 0),
    'agent_notes'          => sanitize($_POST['agent_notes'] ?? ''),
    'updated_at'           => date('Y-m-d H:i:s'),
], 'id=?', [$inspId]);

// Log activity
DB::insert('inspection_activity', [
    'inspection_id' => $inspId,
    'user_id'       => Auth::id(),
    'action'        => 'Vehicle condition recorded',
    'detail'        => 'Condition: ' . $condition . ', Fuel: ' . $fuelLevel . ', Mileage: ' . ($_POST['mileage_at_inspection'] ?? 'N/A'),
    'latitude'      => sanitize($_POST['geo_lat'] ?? ''),
    'longitude'     => sanitize($_POST['geo_lng'] ?? ''),
    'created_at'    => date('Y-m-d H:i:s'),
]);

json_response(['success' => true, 'message' => 'Condition saved successfully']);
