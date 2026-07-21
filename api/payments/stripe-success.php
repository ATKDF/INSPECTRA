<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$sessionId = $_GET['session_id'] ?? '';
$bookingId = (int)($_GET['booking_id'] ?? 0);

if (!$sessionId || !$bookingId) {
    redirect(APP_URL . '/customer/bookings.php?error=' . urlencode('Invalid callback'));
}

$booking = DB::fetchOne("SELECT * FROM bookings WHERE id = ? AND customer_id = ?", [$bookingId, Auth::id()]);
if (!$booking) {
    redirect(APP_URL . '/customer/bookings.php?error=' . urlencode('Booking not found'));
}

// Retrieve session from Stripe to verify payment
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$session = json_decode($response, true);

if ($httpCode !== 200 || empty($session['payment_status'])) {
    redirect(APP_URL . '/customer/bookings.php?id=' . $bookingId . '&error=' . urlencode('Could not verify payment. Contact support.'));
}

if ($session['payment_status'] === 'paid') {
    $amount = $session['amount_total'] / 100;

    DB::beginTransaction();
    try {
        // Upsert payment record
        $existing = DB::fetchOne("SELECT id FROM payments WHERE provider_reference = ?", [$sessionId]);
        $ghs_paid = convert_usd_to_ghs($amount);
        if ($existing) {
            DB::update('payments', [
                'status'                  => 'completed',
                'provider_transaction_id' => $session['payment_intent'] ?? $sessionId,
                'paid_at'                 => date('Y-m-d H:i:s'),
                'ghs_equivalent'          => $ghs_paid,
            ], 'id = ?', [$existing['id']]);
        } else {
            DB::insert('payments', [
                'uuid'                    => Auth::generateUUID(),
                'booking_id'              => $bookingId,
                'user_id'                 => Auth::id(),
                'amount'                  => $amount,
                'ghs_equivalent'          => $ghs_paid,
                'currency'                => strtoupper($session['currency'] ?? 'USD'),
                'payment_type'            => 'booking',
                'payment_method'          => 'stripe',
                'provider_reference'      => $sessionId,
                'provider_transaction_id' => $session['payment_intent'] ?? '',
                'status'                  => 'completed',
                'paid_at'                 => date('Y-m-d H:i:s'),
                'created_at'              => date('Y-m-d H:i:s'),
            ]);
        }


        $current_paid = (float) $booking['amount_paid'];
        $new_paid = $current_paid + $ghs_paid;
        DB::update('bookings', [
            'payment_status' => $new_paid >= $booking['total_amount'] ? 'paid' : 'partial',
            'amount_paid'    => $new_paid,
            'status'         => 'paid',
            'updated_at'     => date('Y-m-d H:i:s'),
        ], 'id = ?', [$bookingId]);

        log_audit($booking['customer_id'], 'create', 'payment', null, [], ['booking_ref' => $booking['booking_ref'], 'amount_usd' => $amount, 'method' => 'stripe', 'fully_paid' => $new_paid >= $booking['total_amount']]);

        // Get vehicle details for notification
        $vehicle = DB::fetchOne("SELECT make, model FROM vehicles WHERE id=?", [$booking['vehicle_id']]);
        $vehicleInfo = $vehicle['make'] . ' ' . $vehicle['model'];
        $bookingInfo = "Booking Ref: {$booking['booking_ref']}\n🚗 Vehicle: $vehicleInfo\n📅 Duration: {$booking['total_days']} day(s)\n💰 Amount: " . format_amount($amount, 'USD') . " (≈₵" . number_format($ghs_paid, 2) . ")";

        // Notifications
        $admins = DB::fetchAll("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
        foreach ($admins as $admin) {
            create_notification($admin['id'], 'payment_received', 'Stripe Payment Received',
                "Payment of " . format_amount($amount, 'USD') . " (≈₵" . number_format($ghs_paid, 2) . ") received via Stripe.\n\n" . $bookingInfo,
                ['booking_id' => $bookingId]);

        }
        create_notification(Auth::id(), 'payment_success', 'Payment Successful',
            "Your Stripe payment of " . format_amount($amount, 'USD') . " (≈₵" . number_format($ghs_paid, 2) . ") was successful! ✓\n\n" . $bookingInfo . "\n\nYour booking is now confirmed.",
            ['booking_id' => $bookingId]);


        DB::commit();
        redirect(APP_URL . '/customer/bookings.php?id=' . $bookingId . '&success=' . urlencode('Payment successful! Your booking is confirmed.'));
    } catch (Exception $e) {
        DB::rollback();
        error_log('Stripe success handler error: ' . $e->getMessage());
        redirect(APP_URL . '/customer/bookings.php?id=' . $bookingId . '&error=' . urlencode('Payment recorded but an error occurred. Please contact support.'));
    }
} else {
    redirect(APP_URL . '/customer/bookings.php?id=' . $bookingId . '&error=' . urlencode('Payment not completed. Status: ' . $session['payment_status']));
}
