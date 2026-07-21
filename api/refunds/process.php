<?php
// api/refunds/process.php — called by admin to process approved refunds
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) { redirect(APP_URL.'/admin/refunds.php'); }

$rid    = (int)$_POST['refund_id'];
$action = $_POST['action']; // 'approve' or 'reject'
$req    = DB::fetchOne("SELECT * FROM refund_requests WHERE id=?", [$rid]);
if (!$req) redirect(APP_URL.'/admin/refunds.php?error='.urlencode('Refund not found'));

if ($action === 'approve') {
    DB::beginTransaction();
    try {
        DB::update('refund_requests',['status'=>'approved','admin_note'=>sanitize($_POST['admin_note']??''),'processed_by'=>Auth::id(),'processed_at'=>date('Y-m-d H:i:s')],'id=?',[$rid]);

        // Create refund payment record
        $refundPaymentId = DB::insert('payments',['uuid'=>Auth::generateUUID(),'booking_id'=>$req['booking_id'],'user_id'=>$req['requested_by'],'amount'=>$req['amount'],'payment_type'=>'refund','payment_method'=>'bank_transfer','status'=>'refunded','paid_at'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d H:i:s')]);

        // Update booking financials and status
        $booking = DB::fetchOne("SELECT * FROM bookings WHERE id=?",[$req['booking_id']]);
        $newRefunded = ($booking['amount_refunded']??0) + $req['amount'];
        $newPayStatus = $newRefunded >= $booking['amount_paid'] ? 'refunded' : 'partial_refund';
        DB::update('bookings',['amount_refunded'=>$newRefunded,'payment_status'=>$newPayStatus,'updated_at'=>date('Y-m-d H:i:s')],'id=?',[$req['booking_id']]);

        // Mark refund request as processed
        DB::update('refund_requests',['status'=>'processed'],'id=?',[$rid]);

        log_audit(Auth::id(), 'update', 'refund', $rid, ['status' => $req['status']], ['status' => 'processed', 'amount' => $req['amount']]);

        // Notify customer
        $booking = DB::fetchOne("SELECT booking_ref, v.make, v.model FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id = ?", [$req['booking_id']]);
        create_notification($req['requested_by'],'refund_processed','Refund Processed',"Your refund of ₵" . number_format($req['amount'], 2) . " has been approved and processed! ✓\n\n📌 Booking: {$booking['booking_ref']}\n🚗 Vehicle: {$booking['make']} {$booking['model']}\n💰 Amount: ₵" . number_format($req['amount'], 2),['booking_id'=>$req['booking_id'],'refund_payment_id'=>$refundPaymentId,'receipt_url'=>APP_URL.'/api/receipts/generate.php?payment_id='.$refundPaymentId]);

        DB::commit();
        redirect(APP_URL.'/admin/refunds.php?success='.urlencode('Refund approved and processed.'));
    } catch (Exception $e) {
        DB::rollback();
        redirect(APP_URL.'/admin/refunds.php?error='.urlencode('Error processing refund.'));
    }
}

if ($action === 'reject') {
    DB::update('refund_requests',['status'=>'rejected','admin_note'=>sanitize($_POST['admin_note']??''),'processed_by'=>Auth::id(),'processed_at'=>date('Y-m-d H:i:s')],'id=?',[$rid]);
    log_audit(Auth::id(), 'update', 'refund', $rid, ['status' => $req['status']], ['status' => 'rejected']);
    $booking = DB::fetchOne("SELECT booking_ref FROM bookings WHERE id = ?", [$req['booking_id']]);
    $adminNote = sanitize($_POST['admin_note'] ?? '');
    $message = "Your refund request has been reviewed and rejected.\n\n📌 Booking: {$booking['booking_ref']}\n💰 Amount Requested: ₵" . number_format($req['amount'], 2);
    if ($adminNote) {
        $message .= "\n📝 Admin Note: " . $adminNote;
    }
    create_notification($req['requested_by'],'refund_rejected','Refund Rejected',$message,[]);
    redirect(APP_URL.'/admin/refunds.php?success='.urlencode('Refund request rejected.'));
}
