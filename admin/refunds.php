<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle  = 'Refunds';
$activePage = 'refunds';

$refunds = DB::fetchAll("SELECT r.*, b.booking_ref, b.total_amount, b.amount_paid, u.first_name, u.last_name, u.email,
    (SELECT p.id FROM payments p WHERE p.booking_id=r.booking_id AND p.payment_type='refund' AND p.status='refunded' ORDER BY p.id DESC LIMIT 1) as refund_payment_id,
    (SELECT status FROM booking_charges WHERE booking_id=r.booking_id) as charge_status,
    (SELECT resolved_amount FROM booking_charges WHERE booking_id=r.booking_id) as charge_amount
    FROM refund_requests r JOIN bookings b ON r.booking_id=b.id JOIN users u ON r.requested_by=u.id ORDER BY FIELD(r.status,'pending','approved') DESC, r.created_at DESC");

$pendingCount = count(array_filter($refunds, fn($r) => $r['status'] === 'pending'));

require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($pendingCount > 0): ?>
<div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:13px">
  ⚠️ <strong><?= $pendingCount ?> refund request<?= $pendingCount>1?'s':'' ?></strong> awaiting your review.
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Customer</th><th>Booking</th><th>Amount</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($refunds)): ?>
        <tr><td colspan="7" style="text-align:center;padding:50px;color:var(--text-secondary)">No refund requests</td></tr>
        <?php else: foreach ($refunds as $r): ?>
        <tr style="<?= $r['status']==='pending'?'background:rgba(245,158,11,.025)':'' ?>">
          <td style="font-size:12px"><?= format_date($r['created_at']) ?></td>
          <td>
            <div style="font-weight:500"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
            <div style="font-size:11px;color:var(--text-tertiary)"><?= e($r['email']) ?></div>
          </td>
          <td>
            <span style="font-family:var(--mono);font-size:12px"><?= e($r['booking_ref']) ?></span>
            <?php if ($r['charge_status'] === 'disputed'): ?>
            <div style="margin-top:3px">
              <a href="<?= APP_URL ?>/admin/inspection-comparison.php?booking_id=<?= $r['booking_id'] ?>" style="font-size:10.5px;color:var(--danger);text-decoration:none" title="A post-return charge on this booking is disputed and not yet resolved">
                ⚖️ Charge disputed — not yet resolved
              </a>
            </div>
            <?php elseif ($r['charge_status']): ?>
            <div style="margin-top:3px">
              <a href="<?= APP_URL ?>/admin/inspection-comparison.php?booking_id=<?= $r['booking_id'] ?>" style="font-size:10.5px;color:#d97706;text-decoration:none" title="See the recorded post-return charge decision for this booking">
                💰 <?= ucfirst($r['charge_status']) ?> charge on record: ₵<?= number_format($r['charge_amount'], 2) ?>
              </a>
            </div>
            <?php endif; ?>
          </td>
          <td style="font-weight:600">₵<?= number_format($r['amount'],2) ?></td>
          <td style="max-width:200px;font-size:12px;color:var(--text-secondary)"><?= e(substr($r['reason'],0,80)) ?><?= strlen($r['reason'])>80?'…':'' ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td>
            <?php if ($r['status']==='pending'): ?>
            <div style="display:flex;gap:6px">
              <button class="action-btn primary" onclick="processRefund(<?= $r['id'] ?>,'approve')">✅ Approve</button>
              <button class="action-btn danger"  onclick="processRefund(<?= $r['id'] ?>,'reject')">✕ Reject</button>
            </div>
            <?php elseif (in_array($r['status'],['approved','processed']) && $r['refund_payment_id']): ?>
            <a href="<?= APP_URL ?>/api/receipts/generate.php?payment_id=<?= $r['refund_payment_id'] ?>" target="_blank" class="action-btn primary">🧾 Receipt</a>
            <?php else: ?>
            <span style="font-size:12px;color:var(--text-tertiary)"><?= $r['admin_note']?e(substr($r['admin_note'],0,40)):'Processed' ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Process Refund Modal -->
<div class="modal-overlay" id="refundModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title" id="refundModalTitle">Process Refund</span>
      <button class="modal-close" onclick="closeModal('refundModal')">✕</button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/api/refunds/process.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="refund_id"  id="refundIdInput">
      <input type="hidden" name="action"     id="refundActionInput">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Admin Note (optional)</label>
          <textarea class="form-input" name="admin_note" rows="3" placeholder="Reason for approval or rejection…"></textarea>
        </div>
        <div id="approveNote" style="display:none;padding:12px;background:rgba(16,185,129,.07);border-radius:var(--radius-sm);font-size:12.5px;color:#059669">
          ✅ Approving will immediately process the refund, update the booking, and send the customer a receipt notification.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('refundModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="refundSubmitBtn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function processRefund(id, action) {
  document.getElementById('refundIdInput').value  = id;
  document.getElementById('refundActionInput').value = action;
  document.getElementById('refundModalTitle').textContent = action==='approve' ? '✅ Approve Refund' : '✕ Reject Refund';
  document.getElementById('refundSubmitBtn').className   = 'btn ' + (action==='approve' ? 'btn-primary' : 'btn-danger');
  document.getElementById('refundSubmitBtn').textContent = action==='approve' ? 'Approve & Process' : 'Reject Request';
  document.getElementById('approveNote').style.display   = action==='approve' ? 'block' : 'none';
  openModal('refundModal');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
