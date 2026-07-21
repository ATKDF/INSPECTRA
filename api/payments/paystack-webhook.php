<?php
/**
 * Paystack Webhook Handler
 * Point your Paystack webhook to: https://yourdomain.com/inspectra/api/payments/paystack-webhook.php
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$payload = file_get_contents('php://input');

// Verify Paystack signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$expected  = hash_hmac('sha512', $payload, PAYSTACK_SECRET_KEY);

if (!hash_equals($expected, $signature)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
if (!$event) { http_response_code(400); exit('Bad JSON'); }

if ($event['event'] === 'charge.success') {
    $data      = $event['data'];
    $reference = $data['reference'];
    $amount    = $data['amount'] / 100;

    // Extract booking ID from metadata or reference
    $bookingRef = $data['metadata']['booking_ref'] ?? '';
    $booking = $bookingRef
        ? DB::fetchOne("SELECT * FROM bookings WHERE booking_ref = ?", [$bookingRef])
        : null;

    if ($booking) {
        $existing = DB::fetchOne("SELECT id FROM payments WHERE provider_reference = ?", [$reference]);
        if (!$existing) {
            DB::insert('payments', [
                'uuid'               => bin2hex(random_bytes(18)),
                'booking_id'         => $booking['id'],
                'user_id'            => $booking['customer_id'],
                'amount'             => $amount,
                'currency'           => $data['currency'] ?? 'GHS',
                'payment_type'       => 'booking',
                'payment_method'     => 'paystack',
                'provider_reference' => $reference,
                'status'             => 'completed',
                'paid_at'            => date('Y-m-d H:i:s'),
                'created_at'         => date('Y-m-d H:i:s'),
            ]);
            DB::update('bookings', ['payment_status' => 'paid', 'amount_paid' => $amount, 'status' => 'paid'], 'id = ?', [$booking['id']]);
            $message = "Your Paystack payment of ₵" . number_format($amount, 2) . " has been confirmed!\n\n✅ Payment Successful\n📌 Booking Ref: {$booking['booking_ref']}\n🚗 Vehicle: {$booking['vehicle_make']} {$booking['vehicle_model']}\n📅 Duration: {$booking['total_days']} day(s)\n💰 Total Amount: ₵" . number_format($booking['total_amount'], 2);
            create_notification($booking['customer_id'], 'payment_success', 'Payment Confirmed', $message, ['booking_id' => $booking['id']]);
        }
    }
}

http_response_code(200);
echo 'ok';
