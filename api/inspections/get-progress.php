<?php
/**
 * Get Inspection Progress for Real-Time Display
 * Shows stage completion status for both admin and customer views
 * Accessible by: Admin (any inspection), Customer (own bookings only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$inspId = (int)($_GET['inspection_id'] ?? 0);
if (!$inspId) {
    json_response(['error' => 'Missing inspection_id'], 400);
}

// ═══ AUTHORIZATION ═══
$inspection = DB::fetchOne("
    SELECT i.*, b.customer_id, b.booking_ref 
    FROM inspections i 
    JOIN bookings b ON i.booking_id = b.id 
    WHERE i.id = ?
", [$inspId]);

if (!$inspection) {
    json_response(['error' => 'Inspection not found'], 404);
}

// Customer can only view their own inspections
if (Auth::role() === 'customer' && $inspection['customer_id'] !== Auth::id()) {
    json_response(['error' => 'Unauthorized'], 403);
}

// ═══ GATHER STAGE DATA ═══
$result = [
    'inspection_id'  => $inspId,
    'type'           => $inspection['type'],
    'status'         => $inspection['status'],
    'is_locked'      => $inspection['status'] === 'completed',
    'booking_ref'    => $inspection['booking_ref'],
    'stages'         => []
];

// Stage 1: Photos (6 required photos)
$photos = DB::fetchAll("
    SELECT photo_type FROM inspection_photos 
    WHERE inspection_id = ? 
    GROUP BY photo_type
", [$inspId]);
$photoTypes = ['front', 'rear', 'left', 'right', 'interior', 'dashboard'];
$photosCaptured = count(array_intersect(array_column($photos, 'photo_type'), $photoTypes));
$result['stages'][] = [
    'stage'       => 1,
    'name'        => '📸 Photos',
    'status'      => $photosCaptured === count($photoTypes) ? 'complete' : 'pending',
    'progress'    => $photosCaptured,
    'total'       => count($photoTypes),
    'percentage'  => round(($photosCaptured / count($photoTypes)) * 100),
    'details'     => "{$photosCaptured}/" . count($photoTypes) . " photos captured"
];

// Stage 2: Damage Records
$damageCount = DB::fetchOne("
    SELECT COUNT(*) as cnt 
    FROM damage_reports 
    WHERE inspection_id = ?
", [$inspId])['cnt'] ?? 0;
$result['stages'][] = [
    'stage'       => 2,
    'name'        => '🗺️ Damage',
    'status'      => $damageCount > 0 ? 'complete' : 'pending',
    'progress'    => $damageCount,
    'total'       => '?',
    'details'     => $damageCount > 0 
        ? "{$damageCount} damage " . ($damageCount === 1 ? 'record' : 'records') 
        : 'No damage recorded'
];

// Stage 3: Condition Assessment
$condition = DB::fetchOne("
    SELECT overall_condition, fuel_level, mileage, notes 
    FROM inspections 
    WHERE id = ?
", [$inspId]);
$conditionComplete = !empty($condition['overall_condition']) && !empty($condition['fuel_level']);
$result['stages'][] = [
    'stage'       => 3,
    'name'        => '📋 Condition',
    'status'      => $conditionComplete ? 'complete' : 'pending',
    'details'     => $conditionComplete 
        ? "Condition: {$condition['overall_condition']}, Fuel: {$condition['fuel_level']}"
        : 'Condition assessment pending'
];

// Stage 4: Signatures (Both Agent and Customer signatures required)
$signatures = DB::fetchAll("
    SELECT role, signed_at, user_id FROM signatures 
    WHERE inspection_id = ?
", [$inspId]);
$sigsByRole = [];
foreach ($signatures as $s) {
    $sigsByRole[$s['role']] = $s;
}
$agentSigned = isset($sigsByRole['agent']);
$customerSigned = isset($sigsByRole['customer']);
$sigsComplete = $agentSigned && $customerSigned;
$result['stages'][] = [
    'stage'       => 4,
    'name'        => '✍️ Signatures',
    'status'      => $sigsComplete ? 'complete' : 'pending',
    'agent_signed' => $agentSigned,
    'customer_signed' => $customerSigned,
    'details'     => ($agentSigned ? '✅ Agent signed ' : '⏳ Agent pending ') . 
                     ($customerSigned ? '✅ Customer signed' : '⏳ Customer pending')
];

// Stage 5: Completion
$result['stages'][] = [
    'stage'       => 5,
    'name'        => '✅ Complete',
    'status'      => $inspection['status'] === 'completed' ? 'complete' : 'pending',
    'completed_at' => $inspection['completed_at'] ?? null,
    'details'     => $inspection['status'] === 'completed' 
        ? 'Completed on ' . format_datetime($inspection['completed_at'])
        : 'Awaiting completion confirmation'
];

// Calculate overall progress
$completeStages = count(array_filter($result['stages'], fn($s) => $s['status'] === 'complete'));
$result['overall_progress'] = round(($completeStages / count($result['stages'])) * 100);
$result['complete_count'] = $completeStages;
$result['total_stages'] = count($result['stages']);

json_response($result);
