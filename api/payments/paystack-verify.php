<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$reference = $_GET['reference'] ?? '';
$bookingId = (int)($_GET['booking_id'] ?? 0);

if (!$reference || !$bookingId) redirect(APP_URL.'/customer/bookings.php?error='.urlencode('Invalid payment data'));

$booking = DB::fetchOne("SELECT * FROM bookings WHERE id=? AND customer_id=?", [$bookingId, Auth::id()]);
if (!$booking) redirect(APP_URL.'/customer/bookings.php?error='.urlencode('Booking not found'));

// Verify with Paystack
$ch = curl_init("https://api.paystack.co/transaction/verify/$reference");
curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.PAYSTACK_SECRET_KEY,'Cache-Control: no-cache'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true]);
$response = curl_exec($ch); curl_close($ch);
$result = json_decode($response, true);

if ($result && $result['status'] && $result['data']['status']==='success') {
    $amount = $result['data']['amount'] / 100;

    DB::beginTransaction();
    try {
        $paymentId = DB::insert('payments', ['uuid'=>Auth::generateUUID(),'booking_id'=>$bookingId,'user_id'=>Auth::id(),'amount'=>$amount,'currency'=>$result['data']['currency']??'GHS','payment_type'=>'booking','payment_method'=>'paystack','provider_reference'=>$reference,'provider_transaction_id'=>$result['data']['id'],'status'=>'completed','paid_at'=>date('Y-m-d H:i:s'),'created_at'=>date('Y-m-d H:i:s')]);

        $totalPaid  = $booking['amount_paid'] + $amount;
        $isFullyPaid = $totalPaid >= $booking['total_amount'];
        $newStatus   = $isFullyPaid ? 'paid' : 'paid'; // Even partial keeps 'paid' status
        $paymentStatus = $isFullyPaid ? 'paid' : 'partial';

        DB::update('bookings', ['payment_status'=>$paymentStatus,'amount_paid'=>$totalPaid,'status'=>$newStatus,'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$bookingId]);

        log_audit(Auth::id(), 'create', 'payment', $paymentId, [], ['booking_ref' => $booking['booking_ref'], 'amount' => $amount, 'method' => 'paystack', 'fully_paid' => $isFullyPaid]);

        // Get vehicle details for notification
        $vehicle = DB::fetchOne("SELECT make, model FROM vehicles WHERE id=?", [$booking['vehicle_id']]);
        $vehicleInfo = $vehicle['make'] . ' ' . $vehicle['model'];
        $bookingInfo = "Booking Ref: {$booking['booking_ref']}\n🚗 Vehicle: $vehicleInfo\n📅 Duration: {$booking['total_days']} day(s)\n💰 Amount: ₵" . number_format($amount, 2);

        // Notify admins
        foreach (DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
            create_notification($adm['id'],'payment_received','Payment Received',"Payment of ₵" . number_format($amount, 2) . " received via Paystack.\n\n" . $bookingInfo,['booking_id'=>$bookingId,'payment_id'=>$paymentId]);
        }

        // Notify customer with receipt link
        $statusMsg = $isFullyPaid ? "Your booking is now fully paid! ✓" : "Balance remaining: ₵" . number_format($booking['total_amount']-$totalPaid,2);
        create_notification(Auth::id(),'payment_success','Payment Successful',"Your Paystack payment of ₵" . number_format($amount, 2) . " was successful!\n\n" . $bookingInfo . "\n\n" . $statusMsg,['booking_id'=>$bookingId,'payment_id'=>$paymentId,'receipt_url'=>APP_URL.'/api/receipts/generate.php?payment_id='.$paymentId]);

        DB::commit();
        redirect(APP_URL.'/customer/bookings.php?id='.$bookingId.'&success='.urlencode('Payment of ₵'.number_format($amount,2).' successful! '.($isFullyPaid?'Booking fully paid.':'Partial payment recorded.')));
    } catch (Exception $e) {
        DB::rollback();
        redirect(APP_URL.'/customer/bookings.php?id='.$bookingId.'&error='.urlencode('Payment recorded but update failed. Please contact support.'));
    }
} else {
    redirect(APP_URL.'/customer/bookings.php?id='.$bookingId.'&error='.urlencode('Payment verification failed. Please try again.'));
}
