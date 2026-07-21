<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Refunds';
$activePage = 'refunds';
$custId = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $bookingId = (int)$_POST['booking_id'];
    $booking = DB::fetchOne("SELECT * FROM bookings WHERE id=? AND customer_id=?", [$bookingId, $custId]);
    $payment  = DB::fetchOne("SELECT * FROM payments WHERE booking_id=? AND status='completed' ORDER BY id DESC LIMIT 1", [$bookingId]);
    if ($booking && $payment) {
        $existing = DB::fetchOne("SELECT id FROM refund_requests WHERE booking_id=? AND status IN ('pending','approved')", [$bookingId]);
        if (!$existing) {
            DB::insert('refund_requests', ['uuid'=>Auth::generateUUID(),'booking_id'=>$bookingId,'payment_id'=>$payment['id'],'requested_by'=>$custId,'amount'=>(float)$_POST['amount'],'reason'=>sanitize($_POST['reason']),'status'=>'pending','created_at'=>date('Y-m-d H:i:s')]);
            redirect(APP_URL.'/customer/refunds.php?success='.urlencode('Refund request submitted!'));
        } else {
            redirect(APP_URL.'/customer/refunds.php?error='.urlencode('You already have a pending refund for this booking.'));
        }
    }
}

$refunds = DB::fetchAll("SELECT r.*, b.booking_ref, b.total_amount,
    (SELECT status FROM booking_charges WHERE booking_id=r.booking_id) as charge_status,
    (SELECT resolved_amount FROM booking_charges WHERE booking_id=r.booking_id) as charge_amount
    FROM refund_requests r JOIN bookings b ON r.booking_id=b.id WHERE r.requested_by=? ORDER BY r.created_at DESC", [$custId]);
$bookingId = $_GET['booking_id'] ?? '';
$paidBookings = DB::fetchAll("SELECT b.* FROM bookings b WHERE b.customer_id=? AND b.payment_status='paid' AND b.status NOT IN ('cancelled')", [$custId]);

require_once __DIR__ . '/../includes/customer-header.php';
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:20px">
  <button class="btn btn-primary" onclick="openModal('refundRequestModal')">+ Request Refund</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Booking</th><th>Amount</th><th>Reason</th><th>Status</th><th>Note</th></tr></thead>
      <tbody>
        <?php if (empty($refunds)): ?>
        <tr><td colspan="6" style="text-align:center;padding:50px;color:var(--text-secondary)">No refund requests yet</td></tr>
        <?php else: foreach ($refunds as $r): ?>
        <tr>
          <td style="font-size:12px"><?= format_date($r['created_at']) ?></td>
          <td>
            <span style="font-family:var(--mono);font-size:12px"><?= e($r['booking_ref']) ?></span>
            <?php if ($r['charge_status'] === 'disputed'): ?>
            <div style="margin-top:3px"><a href="<?= APP_URL ?>/customer/inspection-comparison.php?booking_id=<?= $r['booking_id'] ?>" style="font-size:10.5px;color:var(--danger);text-decoration:none">⚖️ Your dispute is pending</a></div>
            <?php elseif ($r['charge_status']): ?>
            <div style="margin-top:3px"><a href="<?= APP_URL ?>/customer/inspection-comparison.php?booking_id=<?= $r['booking_id'] ?>" style="font-size:10.5px;color:#d97706;text-decoration:none">💰 <?= ucfirst($r['charge_status']) ?> charge: ₵<?= number_format($r['charge_amount'], 2) ?></a></div>
            <?php endif; ?>
          </td>
          <td style="font-weight:600">₵<?= number_format($r['amount'],2) ?></td>
          <td style="font-size:12px;color:var(--text-secondary);max-width:180px"><?= e(substr($r['reason'],0,60)) ?>...</td>
          <td><?= status_badge($r['status']) ?></td>
          <td style="font-size:12px;color:var(--text-tertiary)"><?= e($r['admin_note']??'—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Request Refund Modal -->
<div class="modal-overlay" id="refundRequestModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Request a Refund</span>
      <button class="modal-close" onclick="closeModal('refundRequestModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Booking</label>
          <select class="form-input" name="booking_id" required onchange="setMaxRefund(this)">
            <option value="">Select booking</option>
            <?php foreach ($paidBookings as $bk): ?>
            <option value="<?= $bk['id'] ?>" data-total="<?= $bk['total_amount'] ?>">
              <?= e($bk['booking_ref']) ?> — ₵<?= number_format($bk['total_amount'],2) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Refund Amount (₵)</label>
          <input class="form-input" type="number" name="amount" id="refundAmtInput" step="0.01" required>
          <div class="form-hint">Max: <span id="maxRefundAmt">—</span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Reason for refund *</label>
          <textarea class="form-input" name="reason" rows="4" required placeholder="Please explain why you're requesting a refund..."></textarea>
        </div>
        <div style="font-size:12px;color:var(--text-secondary);padding:10px;background:rgba(0,0,0,0.03);border-radius:var(--radius-sm)">
          ℹ️ Refunds are reviewed by our team within 2–3 business days.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('refundRequestModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<script>
function setMaxRefund(sel) {
  const opt = sel.options[sel.selectedIndex];
  const max = opt.dataset.total || 0;
  document.getElementById('maxRefundAmt').textContent = '₵' + parseFloat(max).toFixed(2);
  document.getElementById('refundAmtInput').max = max;
}
<?php if ($bookingId): ?>document.addEventListener('DOMContentLoaded',()=>openModal('refundRequestModal'));<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
