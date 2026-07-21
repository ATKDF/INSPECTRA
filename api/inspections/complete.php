<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(APP_URL.'/admin/inspections.php'); }
if (!csrf_verify()) { redirect(APP_URL.'/admin/inspections.php?error='.urlencode('Security error')); }

$inspId = (int)$_POST['inspection_id'];
$inspection = DB::fetchOne("SELECT i.*, b.customer_id, b.status as booking_status FROM inspections i JOIN bookings b ON i.booking_id=b.id WHERE i.id=?", [$inspId]);

if (!$inspection) { redirect(APP_URL.'/admin/inspections.php?error='.urlencode('Inspection not found')); }

// Validate: need at least some photos
$photoCount = DB::fetchOne("SELECT COUNT(*) c FROM inspection_photos WHERE inspection_id=?", [$inspId])['c'];
if ($photoCount < 1) {
    redirect(APP_URL.'/admin/inspection-flow.php?id='.$inspId.'&error='.urlencode('Please capture at least one photo before completing.'));
}

// Validate: the customer must have acknowledged (signed) this specific inspection before it can be locked
$customerSigned = DB::fetchOne("SELECT 1 FROM signatures WHERE inspection_id=? AND role='customer'", [$inspId]);
if (!$customerSigned) {
    redirect(APP_URL.'/admin/inspection-flow.php?id='.$inspId.'&error='.urlencode('This inspection cannot be completed until the customer has signed.'));
}

DB::beginTransaction();
try {
    $completedAt = date('Y-m-d H:i:s');
    DB::update('inspections', [
        'status'       => 'completed',
        'completed_at' => $completedAt,
        'updated_at'   => $completedAt,
    ], 'id=?', [$inspId]);

    // If return inspection completed, update booking to completed
    if ($inspection['type'] === 'return') {
        DB::update('bookings', ['status'=>'completed', 'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$inspection['booking_id']]);
        DB::update('vehicles', ['status'=>'available'], 'id IN (SELECT vehicle_id FROM bookings WHERE id=?)', [$inspection['booking_id']]);
    }

    // If pickup inspection completed, update booking to active
    if ($inspection['type'] === 'pickup' && $inspection['booking_status'] === 'paid') {
        DB::update('bookings', ['status'=>'active', 'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$inspection['booking_id']]);
        DB::update('vehicles', ['status'=>'rented'], 'id IN (SELECT vehicle_id FROM bookings WHERE id=?)', [$inspection['booking_id']]);
    }

    // Generate share token for report
    $shareToken = Auth::generateToken(32);
    DB::insert('inspection_reports', [
        'uuid'          => Auth::generateUUID(),
        'inspection_id' => $inspId,
        'report_url'    => APP_URL.'/api/reports/generate.php?inspection_id='.$inspId,
        'share_token'   => $shareToken,
        'generated_at'  => date('Y-m-d H:i:s'),
    ]);

    // Notify customer
    $booking = DB::fetchOne("SELECT b.booking_ref, v.make, v.model FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id = ?", [$inspection['booking_id']]);
    $inspectionType = ucfirst($inspection['type']);
    create_notification($inspection['customer_id'], 'inspection_completed', 'Inspection Complete',
        "$inspectionType inspection has been completed for your rental.\n\n📋 Type: $inspectionType\n📅 Completed: " . format_datetime($completedAt) . "\n🚗 Vehicle: {$booking['make']} {$booking['model']}\n📌 Booking: {$booking['booking_ref']}",
        ['inspection_id'=>$inspId, 'booking_id'=>$inspection['booking_id']]);

    // If this completion is the second of the pair, the comparison is now ready — notify separately
    $siblingType = $inspection['type'] === 'pickup' ? 'return' : 'pickup';
    $sibling = DB::fetchOne("SELECT status FROM inspections WHERE booking_id=? AND type=?", [$inspection['booking_id'], $siblingType]);
    if ($sibling && $sibling['status'] === 'completed') {
        create_notification($inspection['customer_id'], 'comparison_ready', 'Trip Comparison Ready',
            "Both your pickup and return inspections for {$booking['make']} {$booking['model']} (Booking {$booking['booking_ref']}) are complete. You can now view the full side-by-side comparison.",
            ['booking_id' => $inspection['booking_id']]);
    }

    // Log completion activity
    DB::insert('inspection_activity', [
        'inspection_id' => $inspId,
        'user_id'       => Auth::id(),
        'action'        => 'Inspection completed',
        'detail'        => 'Type: ' . ucfirst($inspection['type']) . ', Status: Completed',
        'latitude'      => '',
        'longitude'     => '',
        'created_at'    => date('Y-m-d H:i:s'),
    ]);

    DB::commit();
    redirect(APP_URL.'/admin/inspection-flow.php?id='.$inspId.'&success='.urlencode('Inspection completed successfully!'));
} catch (Exception $e) {
    DB::rollback();
    redirect(APP_URL.'/admin/inspection-flow.php?id='.$inspId.'&error='.urlencode('Failed to complete inspection.'));
}
