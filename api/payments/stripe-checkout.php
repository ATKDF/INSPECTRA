<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$booking   = DB::fetchOne(
    "SELECT b.*, v.make, v.model FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id
     WHERE b.id = ? AND b.customer_id = ?",
    [$bookingId, Auth::id()]
);

if (!$booking) {
    redirect(APP_URL . '/customer/bookings.php?error=' . urlencode('Booking not found'));
}

$balanceDue = round((float) $booking['total_amount'] - (float) $booking['amount_paid'], 2);
if ($balanceDue <= 0) {
    redirect(APP_URL . '/customer/bookings.php?id=' . $bookingId . '&error=' . urlencode('This booking is already fully paid.'));
}

// ── Load Stripe via Composer if present, otherwise use raw HTTP ─────────────
$stripeAvailable = file_exists(__DIR__ . '/../../vendor/autoload.php');
if ($stripeAvailable) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Build line-item description
$description  = $booking['make'] . ' ' . $booking['model']
    . ' — ' . $booking['total_days'] . ' day(s)'
    . ' (' . format_date($booking['pickup_date']) . ' → ' . format_date($booking['return_date']) . ')';

// Convert the remaining GHS balance (not the full total) to USD for Stripe
$ghsTotal     = $balanceDue;
$usdTotal     = convert_to_usd($ghsTotal);
$amountCents  = (int) round($usdTotal * 100);   // Stripe expects cents in USD

$payload = [
    'payment_method_types' => ['card'],
    'line_items'           => [[
        'price_data' => [
            'currency'     => 'usd',           // Change to 'ghs' if your Stripe account supports it
            'unit_amount'  => $amountCents,
            'product_data' => [
                'name'        => 'Vehicle Rental — ' . $booking['booking_ref'],
                'description' => $description,
            ],
        ],
        'quantity' => 1,
    ]],
    'mode'                 => 'payment',
    'client_reference_id'  => (string) $bookingId,
    'customer_email'       => Auth::email(),
    'success_url'          => APP_URL . '/api/payments/stripe-success.php?session_id={CHECKOUT_SESSION_ID}&booking_id=' . $bookingId,
    'cancel_url'           => APP_URL . '/customer/bookings.php?id=' . $bookingId . '&error=' . urlencode('Payment was cancelled.'),
    'metadata'             => [
        'booking_id'  => $bookingId,
        'booking_ref' => $booking['booking_ref'],
        'user_id'     => Auth::id(),
    ],
];

// ── Call Stripe API ──────────────────────────────────────────────────────────
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_POSTFIELDS     => http_build_query($payload),   // Stripe REST uses form-encoded
    CURLOPT_SSL_VERIFYPEER => true,
]);

// Stripe needs nested arrays as `key[0][subkey]`, http_build_query handles this
$response = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$session = json_decode($response, true);

if ($httpCode === 200 && !empty($session['url'])) {
    // Store session ID so we can verify on success
        DB::query(
            "INSERT INTO payments (uuid, booking_id, user_id, amount, ghs_equivalent, currency, payment_type, payment_method, provider_reference, status, created_at)
             VALUES (?, ?, ?, ?, 0, 'USD', 'booking', 'stripe', ?, 'pending', NOW())
             ON DUPLICATE KEY UPDATE provider_reference = VALUES(provider_reference), amount = VALUES(amount), ghs_equivalent = 0",
            [Auth::generateUUID(), $bookingId, Auth::id(), $usdTotal, $session['id']]
        );

    redirect($session['url']);
} else {
    error_log('Stripe checkout failed: ' . $response);
    redirect(APP_URL . '/customer/bookings.php?id=' . $bookingId . '&error=' . urlencode('Stripe payment could not be initiated. Please try Paystack.'));
}
