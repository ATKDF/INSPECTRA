<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

// Stats
$totalVehicles = DB::fetchOne("SELECT COUNT(*) c FROM vehicles WHERE status != 'retired'")['c'];
$availableVehicles = DB::fetchOne("SELECT COUNT(*) c FROM vehicles WHERE status = 'available'")['c'];
$activeRentals = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE status = 'active'")['c'];
$pendingBookings = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE status = 'booked'")['c'];
$totalCustomers = DB::fetchOne("SELECT COUNT(*) c FROM users WHERE role = 'customer'")['c'];

$revenueThisMonth = DB::fetchOne("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE status='completed' AND MONTH(paid_at)=MONTH(NOW()) AND YEAR(paid_at)=YEAR(NOW())")['total'];
$revenueLastMonth = DB::fetchOne("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE status='completed' AND MONTH(paid_at)=MONTH(NOW()-INTERVAL 1 MONTH) AND YEAR(paid_at)=YEAR(NOW()-INTERVAL 1 MONTH)")['total'];

$pendingInspections = DB::fetchOne("SELECT COUNT(*) c FROM inspections WHERE status = 'pending'")['c'];
$pendingRefunds = DB::fetchOne("SELECT COUNT(*) c FROM refund_requests WHERE status = 'pending'")['c'];

$recentBookings = DB::fetchAll("SELECT b.*, v.make, v.model, v.registration_number, u.first_name, u.last_name FROM bookings b JOIN vehicles v ON b.vehicle_id = v.id JOIN users u ON b.customer_id = u.id ORDER BY b.created_at DESC LIMIT 8");

$recentActivity = DB::fetchAll("SELECT n.*, u.first_name, u.last_name FROM notifications n JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 6");

// Monthly revenue for chart (last 6 months)
$monthlyRevenue = DB::fetchAll("SELECT DATE_FORMAT(paid_at,'%b') as month, SUM(amount) as total FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY MONTH(paid_at), YEAR(paid_at) ORDER BY paid_at");

require_once __DIR__ . '/../includes/admin-header.php';
?>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-icon" style="background:rgba(59,110,248,0.1)">
      <svg fill="none" stroke="#3b6ef8" stroke-width="2" viewBox="0 0 24 24"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v5M17 21a2 2 0 100-4 2 2 0 000 4zM7 21a2 2 0 100-4 2 2 0 000 4z"/></svg>
    </div>
    <div class="stat-value"><?= $totalVehicles ?></div>
    <div class="stat-label">Total Vehicles</div>
    <div class="stat-change up">↑ <?= $availableVehicles ?> available</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--success),#34d399)"></div>
    <div class="stat-icon" style="background:rgba(16,185,129,0.1)">
      <svg fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <div class="stat-value"><?= $activeRentals ?></div>
    <div class="stat-label">Active Rentals</div>
    <div class="stat-change <?= $pendingBookings>0?'up':'' ?>"><?= $pendingBookings ?> pending confirmation</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent3),#fb923c)"></div>
    <div class="stat-icon" style="background:rgba(249,115,22,0.1)">
      <svg fill="none" stroke="#f97316" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
    </div>
    <div class="stat-value">₵<?= number_format($revenueThisMonth, 0) ?></div>
    <div class="stat-label">Revenue This Month</div>
    <div class="stat-change <?= $revenueThisMonth>=$revenueLastMonth?'up':'down' ?>">
      <?= $revenueThisMonth>=$revenueLastMonth?'↑':'↓' ?> vs last month ₵<?= number_format($revenueLastMonth,0) ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#6366f1,#8b5cf6)"></div>
    <div class="stat-icon" style="background:rgba(99,102,241,0.1)">
      <svg fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    </div>
    <div class="stat-value"><?= $totalCustomers ?></div>
    <div class="stat-label">Total Customers</div>
    <div class="stat-change up">↑ Growing</div>
  </div>
</div>

<!-- Alert banners -->
<?php if ($pendingRefunds > 0): ?>
<div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:13px;">
  ⚠️ <strong><?= $pendingRefunds ?> refund request<?= $pendingRefunds>1?'s':'' ?></strong> pending your review.
  <a href="<?= APP_URL ?>/admin/refunds.php" style="margin-left:auto;color:var(--accent);font-weight:500;text-decoration:none;">Review →</a>
</div>
<?php endif; ?>

<div class="grid-cols-60-40" style="margin-bottom:24px;">
  <!-- Recent Bookings -->
  <div class="card">
    <div style="padding:20px 20px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div class="section-title">Recent Bookings</div>
        <div class="section-sub">Latest rental activity</div>
      </div>
      <a href="<?= APP_URL ?>/admin/bookings.php" class="action-btn">View all</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Customer</th><th>Vehicle</th><th>Dates</th><th>Status</th><th>Amount</th>
        </tr></thead>
        <tbody>
          <?php if (empty($recentBookings)): ?>
          <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--text-secondary);">No bookings yet</td></tr>
          <?php else: foreach ($recentBookings as $b): ?>
          <tr onclick="window.location='<?= APP_URL ?>/admin/bookings.php?id=<?= $b['id'] ?>'" style="cursor:pointer">
            <td>
              <div style="font-weight:500;"><?= e($b['first_name'].' '.$b['last_name']) ?></div>
              <div style="font-size:11px;color:var(--text-tertiary)"><?= e($b['booking_ref']) ?></div>
            </td>
            <td><?= e($b['make'].' '.$b['model']) ?></td>
            <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($b['pickup_date'],'M d') ?> → <?= format_date($b['return_date'],'M d') ?></td>
            <td><?= status_badge($b['status']) ?></td>
            <td style="font-weight:600">₵<?= number_format($b['total_amount'],2) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Revenue Chart + Activity -->
  <div style="display:flex;flex-direction:column;gap:20px;">
    <div class="card">
      <div style="padding:16px 20px 0;display:flex;align-items:center;justify-content:space-between;">
        <div class="section-title">Revenue (6 Months)</div>
      </div>
      <div class="rev-chart-wrap">
        <?php
        $maxRev = max(array_column($monthlyRevenue, 'total') ?: [1]);
        ?>
        <div class="rev-bars">
          <?php if (empty($monthlyRevenue)): ?>
          <?php for ($i=0;$i<6;$i++): ?><div class="rev-bar" style="height:<?= rand(20,90) ?>%"></div><?php endfor; ?>
          <?php else: foreach ($monthlyRevenue as $r): ?>
          <div class="rev-bar" style="height:<?= ($r['total']/$maxRev)*100 ?>%" title="₵<?= number_format($r['total'],2) ?>"></div>
          <?php endforeach; endif; ?>
        </div>
        <div class="rev-labels">
          <?php if (empty($monthlyRevenue)): ?>
          <?php $months=array_reverse(['Jan','Feb','Mar','Apr','May','Jun']); foreach(array_slice($months,0,6) as $m): ?><div class="rev-label"><?=$m?></div><?php endforeach; ?>
          <?php else: foreach ($monthlyRevenue as $r): ?><div class="rev-label"><?= $r['month'] ?></div><?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
        <div class="section-title">Quick Stats</div>
      </div>
      <div style="padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <span style="font-size:13px;color:var(--text-secondary)">Inspections Pending</span>
          <span style="font-weight:600;color:var(--warn)"><?= $pendingInspections ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <span style="font-size:13px;color:var(--text-secondary)">Fleet Utilization</span>
          <span style="font-weight:600;color:var(--accent)"><?= $totalVehicles>0 ? round(($activeRentals/$totalVehicles)*100) : 0 ?>%</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <span style="font-size:13px;color:var(--text-secondary)">Pending Refunds</span>
          <span style="font-weight:600;color:var(--danger)"><?= $pendingRefunds ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:13px;color:var(--text-secondary)">In Maintenance</span>
          <span style="font-weight:600"><?= DB::fetchOne("SELECT COUNT(*) c FROM vehicles WHERE status='maintenance'")['c'] ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Pending Inspections Section -->
<?php 
$pastInspections = DB::fetchAll("
    SELECT i.*, b.booking_ref, b.customer_id, v.make, v.model, v.registration_number,
           COUNT(DISTINCT ip.id) as photo_count,
           COUNT(DISTINCT dr.id) as damage_count,
           MAX(CASE WHEN s.role='agent' THEN 1 ELSE 0 END) as agent_signed,
           MAX(CASE WHEN s.role='customer' THEN 1 ELSE 0 END) as customer_signed
    FROM inspections i
    JOIN bookings b ON i.booking_id = b.id
    JOIN vehicles v ON b.vehicle_id = v.id
    LEFT JOIN inspection_photos ip ON i.id = ip.inspection_id
    LEFT JOIN damage_reports dr ON i.id = dr.inspection_id
    LEFT JOIN signatures s ON i.id = s.inspection_id
    WHERE i.status IN ('pending','in_progress')
    GROUP BY i.id
    ORDER BY i.created_at DESC
    LIMIT 10
");
?>
<?php if (!empty($pastInspections)): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div>
      <div class="section-title">⏳ Pending Inspections</div>
      <div class="section-sub">Waiting for completion</div>
    </div>
    <a href="<?= APP_URL ?>/admin/inspections.php" class="action-btn">View all</a>
  </div>
  <div class="table-wrap">
    <table style="font-size:13px">
      <thead><tr>
        <th>Type</th><th>Booking</th><th>Vehicle</th><th>Photos</th><th>Damage</th><th>Signatures</th><th>Actions</th>
      </tr></thead>
      <tbody>
        <?php foreach ($pastInspections as $insp): ?>
        <tr onclick="window.location='<?= APP_URL ?>/admin/inspection-flow.php?id=<?= $insp['id'] ?>'" style="cursor:pointer">
          <td><span style="padding:3px 8px;border-radius:12px;font-size:11px;font-weight:500;<?= $insp['type']==='pickup'?'background:rgba(59,110,248,0.1);color:var(--accent)':'background:rgba(249,115,22,0.1);color:#f97316' ?>"><?= ucfirst($insp['type']) ?></span></td>
          <td style="font-weight:500"><?= e($insp['booking_ref']) ?></td>
          <td><?= e($insp['make'].' '.$insp['model']) ?></td>
          <td><?= $insp['photo_count'] ?: '0' ?>/6</td>
          <td><?= $insp['damage_count'] ?: '0' ?></td>
          <td>
            <span style="<?= $insp['agent_signed']?'color:var(--success)':'color:var(--text-tertiary)' ?>">Agent <?= $insp['agent_signed']?'✓':'✗' ?></span>
            <span style="<?= $insp['customer_signed']?'color:var(--success)':'color:var(--text-tertiary)' ?>">Cust <?= $insp['customer_signed']?'✓':'✗' ?></span>
          </td>
          <td onclick="event.stopPropagation()" style="text-align:right">
            <a href="<?= APP_URL ?>/admin/inspection-flow.php?id=<?= $insp['id'] ?>" class="action-btn" style="font-size:11px;padding:4px 8px">Continue →</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Completed Inspections (with Comparison) Section -->
<?php 
$completedInspections = DB::fetchAll("
    SELECT 
        b.id as booking_id,
        b.booking_ref,
        v.make, v.model, v.registration_number,
        COUNT(DISTINCT CASE WHEN i.type='pickup' THEN i.id END) as pickup_id,
        COUNT(DISTINCT CASE WHEN i.type='return' THEN i.id END) as return_id,
        COUNT(i.id) as inspection_count
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN inspections i ON b.id = i.booking_id
    WHERE i.status = 'completed'
    GROUP BY b.id
    HAVING COUNT(DISTINCT i.type) = 2  -- Both pickup and return completed
    ORDER BY b.updated_at DESC
    LIMIT 5
");
?>
<?php if (!empty($completedInspections)): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div>
      <div class="section-title">🔍 Compare Inspections</div>
      <div class="section-sub">Review pickup vs return inspections</div>
    </div>
    <a href="<?= APP_URL ?>/admin/bookings.php" class="action-btn">View all</a>
  </div>
  <div class="table-wrap">
    <table style="font-size:13px">
      <thead><tr>
        <th>Booking</th><th>Vehicle</th><th>Status</th><th>Action</th>
      </tr></thead>
      <tbody>
        <?php foreach ($completedInspections as $booking): ?>
        <tr>
          <td style="font-weight:500"><?= e($booking['booking_ref']) ?></td>
          <td><?= e($booking['make'].' '.$booking['model']) ?></td>
          <td><span style="color:var(--success);font-weight:500">✓ Both complete</span></td>
          <td onclick="event.stopPropagation()">
            <a href="<?= APP_URL ?>/admin/inspection-comparison.php?booking_id=<?= $booking['booking_id'] ?>" class="action-btn" style="font-size:11px;padding:4px 8px;background:rgba(16,185,129,0.1);color:var(--success)">Compare →</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
