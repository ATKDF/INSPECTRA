<?php
// api/disputes/message.php — either party (the customer who raised the dispute,
// or an admin) adds a message to an already-filed dispute's conversation thread.
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    redirect(APP_URL . '/customer/bookings.php');
}

$disputeId = (int)($_POST['dispute_id'] ?? 0);
$message   = trim($_POST['message'] ?? '');

$dispute = DB::fetchOne("SELECT d.*, b.booking_ref, b.customer_id FROM disputes d JOIN bookings b ON d.booking_id=b.id WHERE d.dispute_id=?", [$disputeId]);
if (!$dispute) {
    redirect(APP_URL . '/customer/bookings.php?error=' . urlencode('Dispute not found.'));
}

$isAdmin = Auth::role() === 'admin';
$isOwner = Auth::role() === 'customer' && (int)$dispute['customer_id'] === Auth::id();
$backUrl = $isAdmin
    ? APP_URL . '/admin/inspection-comparison.php?booking_id=' . $dispute['booking_id']
    : APP_URL . '/customer/inspection-comparison.php?booking_id=' . $dispute['booking_id'];

if (!$isAdmin && !$isOwner) {
    redirect(APP_URL . '/customer/bookings.php?error=' . urlencode('You do not have access to this dispute.'));
}
if (!in_array($dispute['status'], ['open', 'under_review', 'escalated'], true)) {
    redirect($backUrl . '&error=' . urlencode('This dispute is already closed and no longer accepts replies.'));
}
if ($message === '') {
    redirect($backUrl . '&error=' . urlencode('Please enter a message before sending.'));
}

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

DB::insert('dispute_messages', [
    'dispute_id'  => $disputeId,
    'sender_id'   => Auth::id(),
    'message'     => sanitize($message),
    'attachments' => !empty($evidenceUrls) ? json_encode($evidenceUrls) : null,
    'is_internal' => 0,
    'created_at'  => date('Y-m-d H:i:s'),
]);

// Bumping updated_at keeps "last activity" accurate without changing status.
DB::update('disputes', ['updated_at' => date('Y-m-d H:i:s')], 'dispute_id=?', [$disputeId]);

if ($isAdmin) {
    create_notification($dispute['customer_id'], 'dispute_message', 'New Reply on Your Dispute',
        Auth::name() . " replied to your dispute \"{$dispute['subject']}\" for booking {$dispute['booking_ref']}:\n\n{$message}",
        ['booking_id' => $dispute['booking_id'], 'dispute_id' => $disputeId]);
} else {
    foreach (DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
        create_notification($adm['id'], 'dispute_message', 'New Reply on a Dispute',
            Auth::name() . " replied to the dispute \"{$dispute['subject']}\" for booking {$dispute['booking_ref']}:\n\n{$message}",
            ['booking_id' => $dispute['booking_id'], 'dispute_id' => $disputeId]);
    }
}

redirect($backUrl . '&success=' . urlencode('Message sent.'));
