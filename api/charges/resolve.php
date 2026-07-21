<?php
// api/charges/resolve.php — admin finalizes a booking's post-return charge
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    redirect(APP_URL . '/admin/bookings.php');
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$action    = $_POST['action'] ?? '';

$status = get_comparison_status($bookingId);
if (!$status['booking']) {
    redirect(APP_URL . '/admin/bookings.php?error=' . urlencode('Booking not found.'));
}
if (!$status['ready']) {
    redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('This comparison is not ready yet.'));
}

$existing = get_booking_charge_status($bookingId);
if (is_charge_finalized($existing)) {
    redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('This charge is already finalized (customer accepted, or the dispute was closed) and can no longer be changed.'));
}

$pickupDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=?", [$status['pickup']['id']]);
$dropoffDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=?", [$status['return']['id']]);
$comparison = compare_inspections($status['pickup'], $status['return'], $pickupDamages, $dropoffDamages);
$computedAmount = $comparison['total_charge'];

$note = sanitize($_POST['note'] ?? '');

if ($action === 'approve') {
    $resolvedAmount = $computedAmount;
    $newStatus = 'approved';
} elseif ($action === 'waive') {
    $resolvedAmount = 0.00;
    $newStatus = 'waived';
} elseif ($action === 'adjust') {
    $resolvedAmount = (float)($_POST['amount'] ?? -1);
    if ($resolvedAmount < 0 || $note === '') {
        redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('An adjusted amount and a reason are both required.'));
    }
    $newStatus = 'adjusted';
} else {
    redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('Unknown action.'));
}

DB::beginTransaction();
try {
    $data = [
        'computed_amount' => $computedAmount,
        'resolved_amount' => $resolvedAmount,
        'status'          => $newStatus,
        'decision_note'   => $note ?: null,
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

    // Deliberately does NOT touch any linked dispute's status — this is just the
    // admin's current proposal. The dispute stays open until explicitly resolved
    // via api/disputes/resolve.php, and this decision itself stays revisable
    // (re-approve/re-adjust/re-waive) until is_charge_finalized() is true.

    log_audit(Auth::id(), 'update', 'booking_charge', $bookingId, ['status' => $existing['status'] ?? null], ['status' => $newStatus, 'resolved_amount' => $resolvedAmount]);

    $actionLabel = ['approve' => 'approved', 'adjust' => 'adjusted', 'waive' => 'waived'][$action];
    $message = "The charge for your booking {$status['booking']['booking_ref']} has been {$actionLabel} at ₵" . number_format($resolvedAmount, 2) . ".";
    if ($note) $message .= "\n\nNote: {$note}";
    $message .= "\n\nThis isn't final yet — you can still accept it or raise a dispute if you have concerns.";
    create_notification($status['booking']['customer_id'], 'charge_resolved', 'Charge Decision Updated', $message, ['booking_id' => $bookingId]);

    DB::commit();
    redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $bookingId . '&success=' . urlencode('Charge ' . $actionLabel . '. The customer can still accept or dispute it.'));
} catch (Exception $e) {
    DB::rollback();
    redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('Failed to record the decision.'));
}
