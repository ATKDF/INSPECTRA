<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Payments';
$activePage = 'payments';

$status = $_GET['status'] ?? '';
$where = "WHERE 1"; $params = [];
if ($status) { $where .= " AND p.status = ?"; $params[] = $status; }

$payments = DB::fetchAll("SELECT p.*, b.booking_ref, u.first_name, u.last_name, u.email FROM payments p JOIN bookings b ON p.booking_id=b.id JOIN users u ON p.user_id=u.id $where ORDER BY p.created_at DESC LIMIT 100", $params);

$totalRevenue = DB::fetchOne("SELECT COALESCE(SUM(CASE WHEN currency='GHS' THEN amount WHEN currency='USD' AND ghs_equivalent>0 THEN ghs_equivalent ELSE amount*15 END),0) total FROM payments WHERE status='completed'")['total'];
$thisMonth = DB::fetchOne("SELECT COALESCE(SUM(CASE WHEN currency='GHS' THEN amount WHEN currency='USD' AND ghs_equivalent>0 THEN ghs_equivalent ELSE amount*15 END),0) total FROM payments WHERE status='completed' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())")['total'];
$totalRefunds = DB::fetchOne("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE status='refunded'")['total'];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--success),#34d399)"></div>
    <div class="stat-value">₵<?= number_format($totalRevenue,2) ?></div>
    <div class="stat-label">Total Revenue (GHS equiv)</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-value">₵<?= number_format($thisMonth,2) ?></div>
    <div class="stat-label">This Month (GHS equiv)</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--danger),#f87171)"></div>
    <div class="stat-value">₵<?= number_format($totalRefunds,2) ?></div>
    <div class="stat-label">Total Refunded</div>
  </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
  <?php foreach ([''=>'All','completed'=>'Completed','pending'=>'Pending','refunded'=>'Refunded','failed'=>'Failed'] as $k=>$label): ?>
  <a href="?status=<?=$k?>" class="chip <?= $status===$k?'active':'' ?>"><?=$label?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Booking</th><th>Customer</th><th>Method</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--text-secondary)">No payments found</td></tr>
        <?php else: foreach ($payments as $p): ?>
        <tr>
          <td style="font-size:12px"><?= format_datetime($p['created_at'],'M d, Y') ?></td>
          <td><span style="font-family:var(--mono);font-size:12px"><?= e($p['booking_ref']) ?></span></td>
          <td><?= e($p['first_name'].' '.$p['last_name']) ?></td>
          <td><span style="padding:3px 10px;border-radius:20px;font-size:11px;background:rgba(0,0,0,0.05)"><?= ucfirst($p['payment_method']) ?></span></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= ucfirst($p['payment_type']) ?></td>
          <td style="font-weight:600;<?= $p['payment_type']==='refund'?'color:var(--danger)':'' ?>">
            <?= $p['payment_type']==='refund'?'−':'' ?><?= format_amount((float)$p['amount'], $p['currency']) ?><?= !empty($p['ghs_equivalent']) && $p['currency']=='USD' ? ' (≈'.format_currency($p['ghs_equivalent']).')' : '' ?>
          </td>
          <td><?= status_badge($p['status']) ?></td>
          <td style="font-size:11px;font-family:var(--mono);color:var(--text-tertiary)"><?= e(substr($p['provider_reference']??'—',0,20)) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
