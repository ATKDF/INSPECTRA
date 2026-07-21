<?php
// api/disputes/file.php — customer files a dispute against the computed charge
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireCustomer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    redirect(APP_URL . '/customer/bookings.php');
}

$bookingId   = (int)($_POST['booking_id'] ?? 0);
$category    = $_POST['category'] ?? '';
$subject     = trim($_POST['subject'] ?? '');
$description = trim($_POST['description'] ?? '');

$allowedCategories = ['damage', 'overcharge', 'other'];
if (!in_array($category, $allowedCategories, true)) $category = 'other';

$status = get_comparison_status($bookingId, Auth::id());
if (!$status['booking']) {
    redirect(APP_URL . '/customer/bookings.php?error=' . urlencode('Booking not found.'));
}
if (!$status['ready']) {
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('This comparison is not ready yet.'));
}
$existingCharge = get_booking_charge_status($bookingId);
if (is_charge_finalized($existingCharge)) {
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('This charge has already been finalized and can no longer be disputed.'));
}
if (has_open_dispute($existingCharge)) {
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('You already have an open dispute on this charge — add to it from the conversation below instead of filing a new one.'));
}
if ($subject === '' || $description === '') {
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('A subject and description are both required.'));
}

$pickupDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=?", [$status['pickup']['id']]);
$dropoffDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=?", [$status['return']['id']]);
$comparison = compare_inspections($status['pickup'], $status['return'], $pickupDamages, $dropoffDamages);
$computedAmount = $comparison['total_charge'];

// Dispute whatever's currently on the table — the admin's latest proposed
// figure if they'd already approved/adjusted/waived one, otherwise the
// freshly computed total.
$disputedAmount = $existingCharge ? (float)$existingCharge['resolved_amount'] : $computedAmount;

DB::beginTransaction();
try {
    $disputeId = DB::insert('disputes', [
        'booking_id'  => $bookingId,
        'raised_by'   => Auth::id(),
        'subject'     => sanitize($subject),
        'description' => sanitize($description),
        'category'    => $category,
        'priority'    => 'medium',
        'status'      => 'open',
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ]);

    // Evidence photos become the dispute's first message, rather than a new column.
    $evidenceUrls = [];
    if (!empty($_FILES['evidence']['name'][0])) {
        $count = count($_FILES['evidence']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['evidence']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $file = [
                'name'     => $_FILES['evidence']['name'][$i],
                'type'     => $_FILES['evidence']['type'][$i],
                'tmp_name' => $_FILES['evidence']['tmp_name'][$i],
                'error'    => $_FILES['evidence']['error'][$i],
                'size'     => $_FILES['evidence']['size'][$i],
            ];
            $url = upload_file($file, 'disputes');
            if ($url) $evidenceUrls[] = $url;
        }
    }
    if (!empty($evidenceUrls)) {
        DB::insert('dispute_messages', [
            'dispute_id'  => $disputeId,
            'sender_id'   => Auth::id(),
            'message'     => 'Evidence submitted with the dispute.',
            'attachments' => json_encode($evidenceUrls),
            'is_internal' => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    $chargeData = [
        'computed_amount' => $computedAmount,
        'resolved_amount' => $disputedAmount,
        'status'          => 'disputed',
        'dispute_id'      => $disputeId,
    ];
    if ($existingCharge) {
        DB::update('booking_charges', $chargeData, 'booking_id=?', [$bookingId]);
    } else {
        $chargeData['uuid'] = Auth::generateUUID();
        $chargeData['booking_id'] = $bookingId;
        $chargeData['created_at'] = date('Y-m-d H:i:s');
        DB::insert('booking_charges', $chargeData);
    }

    log_audit(Auth::id(), 'create', 'dispute', $disputeId, [], ['booking_ref' => $status['booking']['booking_ref'], 'subject' => $subject, 'category' => $category]);

    foreach (DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
        create_notification($adm['id'], 'dispute_filed', 'Dispute Filed',
            "{$status['booking']['first_name']} {$status['booking']['last_name']} disputed the ₵" . number_format($disputedAmount, 2) . " charge for booking {$status['booking']['booking_ref']}: \"{$subject}\"",
            ['booking_id' => $bookingId, 'dispute_id' => $disputeId]);
    }

    DB::commit();
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&success=' . urlencode('Your dispute has been submitted.'));
} catch (Exception $e) {
    DB::rollback();
    redirect(APP_URL . '/customer/inspection-comparison.php?booking_id=' . $bookingId . '&error=' . urlencode('Failed to submit your dispute.'));
}
