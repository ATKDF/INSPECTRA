<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error'=>'Method not allowed'], 405); }

$inspId = (int)($_POST['inspection_id'] ?? 0);
$role   = trim($_POST['role'] ?? '');

if (!$inspId || !in_array($role, ['agent','customer'])) {
    json_response(['error'=>'Invalid parameters: missing inspection_id or role'], 400);
}

// ═══ STRICT ROLE-BASED SIGNATURE VALIDATION ═══
// Admin users can ONLY sign as 'agent' (for themselves)
// Customers can ONLY sign as 'customer' (for themselves)
$userRole = Auth::role();

if ($userRole === 'admin') {
    if ($role !== 'agent') {
        json_response(['error'=>'Unauthorized: Admins can only sign as agent (themselves)'], 403);
    }
} elseif ($userRole === 'customer') {
    if ($role !== 'customer') {
        json_response(['error'=>'Unauthorized: Customers can only sign as customer (themselves)'], 403);
    }

    // Verify customer owns this inspection
    $inspection = DB::fetchOne("
        SELECT b.customer_id FROM inspections i
        JOIN bookings b ON i.booking_id=b.id
        WHERE i.id=?
    ", [$inspId]);
    if (!$inspection || $inspection['customer_id'] !== Auth::id()) {
        json_response(['error'=>'Unauthorized: You do not own this inspection'], 403);
    }

    // Customer may only acknowledge/sign after the agent has completed their own signature
    $agentSigned = DB::fetchOne("SELECT 1 FROM signatures WHERE inspection_id=? AND role='agent'", [$inspId]);
    if (!$agentSigned) {
        json_response(['error'=>'The agent must complete their signature before you can sign.'], 403);
    }
} else {
    json_response(['error'=>'Unauthorized: Invalid user role'], 403);
}

if (empty($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
    json_response(['error'=>'No signature data'], 400);
}

$destDir = UPLOAD_PATH . 'signatures/';
if (!is_dir($destDir)) mkdir($destDir, 0755, true);

$filename = 'sig_' . $inspId . '_' . $role . '_' . time() . '.png';
$destPath = $destDir . $filename;

if (!move_uploaded_file($_FILES['signature']['tmp_name'], $destPath)) {
    json_response(['error'=>'Failed to save signature'], 500);
}

$url = UPLOAD_URL . 'signatures/' . $filename;

// Remove any existing signature for this role/inspection
DB::query("DELETE FROM signatures WHERE inspection_id=? AND role=?", [$inspId, $role]);

DB::insert('signatures', [
    'inspection_id' => $inspId,
    'user_id'       => Auth::id(),
    'role'          => $role,
    'signature_url' => $url,
    'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'signed_at'     => date('Y-m-d H:i:s'),
]);

// Log activity
DB::insert('inspection_activity', [
    'inspection_id' => $inspId,
    'user_id'       => Auth::id(),
    'action'        => ucfirst($role) . ' signature recorded',
    'detail'        => 'Signature authenticated and stored securely',
    'latitude'      => sanitize($_POST['geo_lat'] ?? ''),
    'longitude'     => sanitize($_POST['geo_lng'] ?? ''),
    'created_at'    => date('Y-m-d H:i:s'),
]);

// Notify the customer that it's their turn to review and sign
if ($role === 'agent') {
    $booking = DB::fetchOne("
        SELECT b.customer_id, b.booking_ref, i.type, v.make, v.model
        FROM inspections i
        JOIN bookings b ON i.booking_id = b.id
        JOIN vehicles v ON b.vehicle_id = v.id
        WHERE i.id = ?
    ", [$inspId]);
    if ($booking) {
        $inspectionType = ucfirst($booking['type']);
        create_notification($booking['customer_id'], 'inspection_ready_for_signature', 'Inspection Ready for Your Signature',
            "$inspectionType inspection for {$booking['make']} {$booking['model']} (Booking {$booking['booking_ref']}) is ready for your review and signature.",
            ['inspection_id' => $inspId, 'booking_ref' => $booking['booking_ref']]);
    }
}

json_response(['success'=>true, 'url'=>$url, 'message' => ucfirst($role) . ' signature saved successfully']);
