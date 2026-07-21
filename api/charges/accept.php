<?php
// api/charges/accept.php — customer accepts the computed post-return charge as-is
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireCustomer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    redirect(APP_URL . '/customer/bookings.php');
}

$bookingId = (int)($_POST['booking_id'] ?? 0);

$status = get_comparison_status($bookingId, Auth::id());
if (!$status['booking']) {
    redirect(APP_URL . '/customer/bookings.php?error=' . urlencode('Booking not found.'));
}
if (!$status['ready']) {
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('This comparison is not ready yet.'));
}

$existing = get_booking_charge_status($bookingId);
if (is_charge_finalized($existing)) {
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('This charge has already been finalized.'));
}

$pickupDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=?", [$status['pickup']['id']]);
$dropoffDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=?", [$status['return']['id']]);
$comparison = compare_inspections($status['pickup'], $status['return'], $pickupDamages, $dropoffDamages);
$computedAmount = $comparison['total_charge'];

// Accept whatever amount is currently on the table — the admin's latest
// approved/adjusted/waived figure if one exists, otherwise the freshly
// computed total. Never re-charges more than what was actually proposed.
$acceptedAmount = $existing ? (float)$existing['resolved_amount'] : $computedAmount;

if ($computedAmount <= 0 && !$existing) {
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('There is no charge to accept.'));
}

DB::beginTransaction();
try {
    $data = [
        'computed_amount' => $computedAmount,
        'resolved_amount' => $acceptedAmount,
        'status'          => 'accepted',
        'decided_by'      => Auth::id(),
        'decided_at'      => date('Y-m-d H:i:s'),
    ];
    if ($existing) {
        DB::update('booking_charges', $data, 'booking_id=?', [$bookingId]);
    } else {
        $data['uuid'] = Auth::generateUUID();
        $data['booking_id'] = $bookingId;
        $data['created_at'] = date('Y-m-d H:i:s');
        DB::insert('booking_charges', $data);
    }

    // The customer's own acceptance is a genuine resolution of any dispute they
    // raised — close it out too, so the thread doesn't keep inviting replies on
    // a matter the customer just settled themselves.
    if (has_open_dispute($existing)) {
        DB::update('disputes', [
            'status'      => 'resolved',
            'resolution'  => 'Customer accepted the charge, closing this dispute.',
            'resolved_at' => date('Y-m-d H:i:s'),
        ], 'dispute_id=?', [$existing['dispute']['dispute_id']]);
    }

    log_audit(Auth::id(), 'update', 'booking_charge', $bookingId, ['status' => $existing['status'] ?? null], ['status' => 'accepted', 'resolved_amount' => $acceptedAmount]);

    foreach (DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
        create_notification($adm['id'], 'charge_accepted', 'Customer Accepted Charges',
            "{$status['booking']['first_name']} {$status['booking']['last_name']} accepted a ₵" . number_format($acceptedAmount, 2) . " charge for booking {$status['booking']['booking_ref']}. This is now final.",
            ['booking_id' => $bookingId]);
    }

    DB::commit();
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&success=' . urlencode('You have accepted the charge.'));
} catch (Exception $e) {
    DB::rollback();
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('Failed to record your decision.'));
}
