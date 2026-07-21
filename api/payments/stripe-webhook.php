<?php
/**
 * Stripe Webhook Handler
 * Point your Stripe webhook to: https://yourdomain.com/inspectra/api/payments/stripe-webhook.php
 * Events to listen for: checkout.session.completed, payment_intent.payment_failed
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ── Verify webhook signature ─────────────────────────────────────────────────
if (STRIPE_WEBHOOK_SECRET && $sigHeader) {
    $parts    = explode(',', $sigHeader);
    $ts       = '';
    $v1       = '';
    foreach ($parts as $part) {
        [$k, $v] = explode('=', $part, 2);
        if ($k === 't') $ts = $v;
        if ($k === 'v1') $v1 = $v;
    }
    $signedPayload = $ts . '.' . $payload;
    $expected      = hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET);
    if (!hash_equals($expected, $v1)) {
        http_response_code(400);
        exit('Invalid signature');
    }
}

$event = json_decode($payload, true);
if (!$event) { http_response_code(400); exit('Bad JSON'); }

switch ($event['type']) {

    case 'checkout.session.completed':
        $session   = $event['data']['object'];
        $bookingId = (int)($session['metadata']['booking_id'] ?? 0);
        $amount    = $session['amount_total'] / 100;

        if ($bookingId && $session['payment_status'] === 'paid') {
            $existing = DB::fetchOne("SELECT id FROM payments WHERE provider_reference = ?", [$session['id']]);
            $existing = DB::fetchOne("SELECT id FROM payments WHERE provider_reference = ?", [$session['id']]);
            $booking = DB::fetchOne("SELECT * FROM bookings WHERE id = ?", [$bookingId]);
            if ($booking) {
                $ghs_paid = convert_usd_to_ghs($amount);
                if ($existing) {
                    DB::update('payments', [
                        'status' => 'completed',
                        'provider_transaction_id' => $session['payment_intent'],
                        'paid_at' => date('Y-m-d H:i:s'),
                        'ghs_equivalent' => $ghs_paid,
                    ], 'id = ?', [$existing['id']]);
                } else {
                    DB::insert('payments', [
                        'uuid' => bin2hex(random_bytes(18)),
                        'booking_id' => $bookingId,
                        'user_id' => $booking['customer_id'],
                        'amount' => $amount,
                        'ghs_equivalent' => $ghs_paid,
                        'currency' => strtoupper($session['currency']),
                        'payment_type' => 'booking',
                        'payment_method' => 'stripe',
                        'provider_reference' => $session['id'],
                        'provider_transaction_id' => $session['payment_intent'],
                        'status' => 'completed',
                        'paid_at' => date('Y-m-d H:i:s'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $current_paid = (float) $booking['amount_paid'];
                $new_paid = $current_paid + $ghs_paid;
                DB::update('bookings', ['payment_status' => $new_paid >= $booking['total_amount'] ? 'paid' : 'partial', 'amount_paid' => $new_paid, 'status' => 'paid'], 'id = ?', [$bookingId]);
                $paymentStatus = $new_paid >= $booking['total_amount'] ? '✅ Full Payment' : '⚠️ Partial Payment';
                $message = "Your Stripe payment was confirmed!\n\n💰 Amount: " . format_amount($amount, 'USD') . " (≈₵" . number_format($ghs_paid, 2) . ")\n🌍 Currency Exchanged at: ₵" . number_format($ghs_paid / ($amount > 0 ? floatval($amount) : 1), 2) . " per USD\n\n{$paymentStatus}\n📌 Booking Ref: {$booking['booking_ref']}\n🚗 Vehicle: {$booking['vehicle_make']} {$booking['vehicle_model']}\n📅 Duration: {$booking['total_days']} day(s)";
                create_notification($booking['customer_id'], 'payment_success', 'Payment Confirmed', $message, ['booking_id' => $bookingId]);
            }

        }
        break;

    case 'payment_intent.payment_failed':
        $pi        = $event['data']['object'];
        $reference = $pi['id'] ?? '';
        if ($reference) {
            DB::query("UPDATE payments SET status = 'failed' WHERE provider_transaction_id = ?", [$reference]);
        }
        break;
}

http_response_code(200);
echo 'ok';
