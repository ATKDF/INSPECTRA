<?php
// api/disputes/resolve.php — admin responds to a filed dispute, independent of the charge amount
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    redirect(APP_URL . '/admin/bookings.php');
}

$disputeId  = (int)($_POST['dispute_id'] ?? 0);
$newStatus  = $_POST['status'] ?? '';
$resolution = trim($_POST['resolution'] ?? '');

$allowedStatuses = ['under_review', 'resolved', 'closed', 'escalated'];
$dispute = DB::fetchOne("SELECT d.*, b.booking_ref FROM disputes d JOIN bookings b ON d.booking_id=b.id WHERE d.dispute_id=?", [$disputeId]);

if (!$dispute) {
    redirect(APP_URL . '/admin/bookings.php?error=' . urlencode('Dispute not found.'));
}
if (!in_array($newStatus, $allowedStatuses, true) || $resolution === '') {
    redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $dispute['booking_id'] . '&error=' . urlencode('A status and a response are both required.'));
}

$data = [
    'status'     => $newStatus,
    'resolution' => sanitize($resolution),
];
if (in_array($newStatus, ['resolved', 'closed'], true)) {
    $data['resolved_at'] = date('Y-m-d H:i:s');
}

DB::update('disputes', $data, 'dispute_id=?', [$disputeId]);

log_audit(Auth::id(), 'update', 'dispute', $disputeId, ['status' => $dispute['status']], ['status' => $newStatus, 'booking_ref' => $dispute['booking_ref']]);

create_notification($dispute['raised_by'], 'dispute_updated', 'Dispute Update',
    "Your dispute for booking {$dispute['booking_ref']} has been marked as " . str_replace('_', ' ', $newStatus) . ".\n\nResponse: {$resolution}",
    ['booking_id' => $dispute['booking_id'], 'dispute_id' => $disputeId]);

redirect(APP_URL . '/admin/inspection-comparison.php?booking_id=' . $dispute['booking_id'] . '&success=' . urlencode('Dispute updated.'));
