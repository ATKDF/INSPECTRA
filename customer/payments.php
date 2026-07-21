<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Payment History';
$activePage = 'payments';
$custId = Auth::id();

$payments = DB::fetchAll("SELECT p.*, b.booking_ref FROM payments p JOIN bookings b ON p.booking_id=b.id WHERE b.customer_id=? ORDER BY p.created_at DESC", [$custId]);
$totalPaid = DB::fetchOne("SELECT COALESCE(SUM(amount),0) total FROM payments p JOIN bookings b ON p.booking_id=b.id WHERE b.customer_id=? AND p.status='completed' AND p.payment_type != 'refund'", [$custId])['total'];
$totalRefunded = DB::fetchOne("SELECT COALESCE(SUM(amount),0) total FROM payments p JOIN bookings b ON p.booking_id=b.id WHERE b.customer_id=? AND p.payment_type='refund'", [$custId])['total'];

require_once __DIR__ . '/../includes/customer-header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--success),#34d399)"></div>
  <div class="stat-value">₵<?= number_format($totalPaid,2) ?></div>
    <div class="stat-label">Total Paid (GHS equiv)</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--danger),#f87171)"></div>
    <div class="stat-value">₵<?= number_format($totalRefunded,2) ?></div>
    <div class="stat-label">Total Refunded</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-value"><?= count($payments) ?></div>
    <div class="stat-label">Transactions</div>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Booking</th><th>Type</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="6" style="text-align:center;padding:50px;color:var(--text-secondary)">No payment history yet</td></tr>
        <?php else: foreach ($payments as $p): ?>
        <tr>
          <td style="font-size:12px"><?= format_datetime($p['created_at'],'M d, Y') ?></td>
          <td><span style="font-family:var(--mono);font-size:12px"><?= e($p['booking_ref']) ?></span></td>
          <td style="font-size:12px"><?= ucfirst($p['payment_type']) ?></td>
          <td><span style="padding:2px 8px;background:rgba(0,0,0,0.05);border-radius:12px;font-size:11px"><?= ucfirst($p['payment_method']) ?></span></td>
          <td style="font-weight:600;<?= $p['payment_type']==='refund'?'color:var(--success)':'' ?>"><?= $p['payment_type']==='refund'?'+':'' ?><?= format_amount((float)$p['amount'], $p['currency']) ?><?= !empty($p['ghs_equivalent']) && $p['currency']=='USD' ? ' (≈'.format_currency($p['ghs_equivalent']).')' : '' ?></td>
          <td><?= status_badge($p['status']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
