<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireCustomer();
$pageTitle = 'My Dashboard';
$activePage = 'dashboard';
$custId = Auth::id();

$activeBookings = DB::fetchAll("SELECT b.*, v.make, v.model, v.registration_number, v.category FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id WHERE b.customer_id=? AND b.status IN ('active','booked','paid') ORDER BY b.pickup_date ASC", [$custId]);
$totalBookings = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE customer_id=?", [$custId])['c'];
$totalSpent = DB::fetchOne("SELECT COALESCE(SUM(p.amount),0) total FROM payments p JOIN bookings b ON p.booking_id=b.id WHERE b.customer_id=? AND p.status='completed'", [$custId])['total'];
$completedTrips = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE customer_id=? AND status='completed'", [$custId])['c'];
$pendingInspections = DB::fetchOne("SELECT COUNT(*) c FROM inspections i JOIN bookings b ON i.booking_id=b.id WHERE b.customer_id=? AND i.status='pending'", [$custId])['c'];
$recentNotifications = get_unread_notifications($custId);

require_once __DIR__ . '/../includes/customer-header.php';
?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,var(--accent),#6366f1);border-radius:var(--radius);padding:28px 32px;margin-bottom:24px;color:white;position:relative;overflow:hidden">
  <div style="position:absolute;right:-20px;top:-20px;width:200px;height:200px;background:rgba(255,255,255,0.07);border-radius:50%"></div>
  <div style="position:absolute;right:60px;bottom:-40px;width:150px;height:150px;background:rgba(255,255,255,0.05);border-radius:50%"></div>
  <div style="position:relative">
    <div style="font-size:22px;font-weight:600;margin-bottom:6px">Welcome back, <?= e($currentUser['first_name']) ?>! 👋</div>
    <div style="font-size:14px;opacity:0.85;margin-bottom:20px">Here's what's happening with your rentals.</div>
    <a href="<?= APP_URL ?>/customer/browse.php" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,0.2);color:white;padding:10px 20px;border-radius:var(--radius-sm);text-decoration:none;font-size:13px;font-weight:500;backdrop-filter:blur(10px);transition:background 0.15s" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
      🚗 Browse Vehicles
    </a>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-icon" style="background:rgba(59,110,248,0.1)">
      <svg fill="none" stroke="#3b6ef8" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/></svg>
    </div>
    <div class="stat-value"><?= $totalBookings ?></div>
    <div class="stat-label">Total Bookings</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--success),#34d399)"></div>
    <div class="stat-icon" style="background:rgba(16,185,129,0.1)">
      <svg fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5"/></svg>
    </div>
    <div class="stat-value"><?= $completedTrips ?></div>
    <div class="stat-label">Completed Trips</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent3),#fb923c)"></div>
    <div class="stat-icon" style="background:rgba(249,115,22,0.1)">
      <svg fill="none" stroke="#f97316" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/></svg>
    </div>
    <div class="stat-value">₵<?= number_format($totalSpent,0) ?></div>
    <div class="stat-label">Total Spent</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#6366f1,#8b5cf6)"></div>
    <div class="stat-icon" style="background:rgba(99,102,241,0.1)">
      <svg fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="stat-value"><?= $pendingInspections ?></div>
    <div class="stat-label">Pending Inspections</div>
  </div>
</div>

<!-- Active Rentals -->
<?php if (!empty($activeBookings)): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div>
      <div class="section-title">🚗 Active & Upcoming Rentals</div>
      <div class="section-sub">Your current rental activity</div>
    </div>
    <a href="<?= APP_URL ?>/customer/bookings.php" class="action-btn">View all</a>
  </div>
  <div class="vehicle-grid">
    <?php foreach ($activeBookings as $b): ?>
    <div class="card" style="padding:20px;cursor:pointer" onclick="window.location='<?= APP_URL ?>/customer/bookings.php?id=<?= $b['id'] ?>'">
      <div style="display:flex;align-items:flex-start;justify-content:flex-end;margin-bottom:16px">
        <?= status_badge($b['status']) ?>
      </div>
      <div style="font-weight:600;font-size:15px;margin-bottom:3px"><?= e($b['make'].' '.$b['model']) ?></div>
      <div style="font-size:12px;font-family:var(--mono);color:var(--text-secondary);margin-bottom:14px"><?= e($b['registration_number']) ?></div>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);margin-bottom:8px">
        <span>📅 Pickup: <strong style="color:var(--text)"><?= format_date($b['pickup_date'],'M d') ?></strong></span>
        <span>Return: <strong style="color:var(--text)"><?= format_date($b['return_date'],'M d') ?></strong></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid var(--border)">
        <span style="font-size:12px;color:var(--text-secondary)"><?= $b['total_days'] ?> days</span>
        <span style="font-weight:600;color:var(--accent)">₵<?= number_format($b['total_amount'],2) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Pending Inspections Section -->
<?php 
$pendingInspDetails = DB::fetchAll("
    SELECT i.*, b.id as booking_id, b.booking_ref, v.make, v.model, v.registration_number,
           COUNT(DISTINCT ip.id) as photo_count,
           COUNT(DISTINCT dr.id) as damage_count,
           MAX(CASE WHEN s.role='customer' THEN 1 ELSE 0 END) as customer_signed
    FROM inspections i
    JOIN bookings b ON i.booking_id = b.id
    JOIN vehicles v ON b.vehicle_id = v.id
    LEFT JOIN inspection_photos ip ON i.id = ip.inspection_id
    LEFT JOIN damage_reports dr ON i.id = dr.inspection_id
    LEFT JOIN signatures s ON i.id = s.inspection_id
    WHERE b.customer_id = ? AND i.status IN ('pending','in_progress')
    GROUP BY i.id
    ORDER BY i.created_at DESC
", [$custId]);
?>
<?php if (!empty($pendingInspDetails)): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div>
      <div class="section-title">⏳ Your Pending Inspections</div>
      <div class="section-sub">Follow along in real time and sign when ready</div>
    </div>
    <a href="<?= APP_URL ?>/customer/inspections.php" class="action-btn">View all</a>
  </div>
  <div class="vehicle-grid">
    <?php foreach ($pendingInspDetails as $insp): ?>
    <div class="card" style="padding:20px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <div style="font-size:14px;font-weight:600"><?= ucfirst($insp['type']) ?> Inspection</div>
        <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:500;<?= $insp['type']==='pickup'?'background:rgba(59,110,248,0.1);color:var(--accent)':'background:rgba(249,115,22,0.1);color:#f97316' ?>"><?= ucfirst($insp['type']) ?></span>
      </div>
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        <?= e($insp['make'].' '.$insp['model']) ?> • <?= e($insp['registration_number']) ?>
      </div>
      <div style="background:var(--surface2);border-radius:var(--radius-sm);padding:12px;margin-bottom:12px;font-size:12px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span>📸 Photos: <strong><?= $insp['photo_count'] ?>/6</strong></span>
          <span>🗺️ Damage: <strong><?= $insp['damage_count'] ?></strong></span>
        </div>
        <div style="display:flex;justify-content:space-between">
          <span>✍️ Signed: <?= $insp['customer_signed']?'<strong style="color:var(--success)">✓</strong>':'<strong style="color:var(--text-tertiary)">✗</strong>' ?></span>
          <span>📋 Status: <strong style="color:var(--warn)"><?= ucfirst($insp['status']) ?></strong></span>
        </div>
      </div>
      <a href="<?= APP_URL ?>/customer/inspection-flow.php?id=<?= $insp['id'] ?>" class="btn btn-primary" style="width:100%;text-align:center;font-size:12px;padding:10px">
        👁️ View & Sign →
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Completed Inspections (with Comparison) Section -->
<?php 
$completedBookings = DB::fetchAll("
    SELECT 
        b.id as booking_id,
        b.booking_ref,
        v.make, v.model, v.registration_number,
        COUNT(DISTINCT CASE WHEN i.type='pickup' THEN i.id END) as pickup_id,
        COUNT(DISTINCT CASE WHEN i.type='return' THEN i.id END) as return_id
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN inspections i ON b.id = i.booking_id
    WHERE b.customer_id = ? AND i.status = 'completed'
    GROUP BY b.id
    HAVING COUNT(DISTINCT i.type) = 2
    ORDER BY b.updated_at DESC
    LIMIT 5
", [$custId]);
?>
<?php if (!empty($completedBookings)): ?>
<div style="margin-bottom:24px">
  <div class="section-header">
    <div>
      <div class="section-title">🔍 Completed Inspections</div>
      <div class="section-sub">Compare your pickup and return inspections</div>
    </div>
    <a href="<?= APP_URL ?>/customer/inspections.php" class="action-btn">View all</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
    <?php foreach ($completedBookings as $booking): ?>
    <div class="card" style="padding:20px">
      <div style="font-weight:600;margin-bottom:8px"><?= e($booking['booking_ref']) ?></div>
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:12px">
        <?= e($booking['make'].' '.$booking['model']) ?> • <?= e($booking['registration_number']) ?>
      </div>
      <a href="<?= APP_URL ?>/customer/inspection-comparison.php?booking_id=<?= $booking['booking_id'] ?>" class="btn btn-ghost" style="width:100%;text-align:center;font-size:12px;padding:10px">
        🔍 Compare Inspections →
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Recent notifications -->
<?php if (!empty($recentNotifications)): ?>
<div class="section-header">
  <div class="section-title">🔔 Recent Notifications</div>
  <a href="<?= APP_URL ?>/customer/notifications.php" class="action-btn">View all</a>
</div>
<div class="card">
  <?php foreach (array_slice($recentNotifications,0,4) as $n): ?>
  <div class="activity-item" style="display:flex;align-items:flex-start;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border)">
    <div style="width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:5px;flex-shrink:0"></div>
    <div style="flex:1">
      <div style="font-size:13px;font-weight:500"><?= e($n['title']) ?></div>
      <div style="font-size:12px;color:var(--text-secondary)"><?= e($n['message']) ?></div>
    </div>
    <div style="font-size:11px;color:var(--text-tertiary)"><?= time_ago($n['created_at']) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-state-icon">🚀</div>
    <div class="empty-state-title">You're all set!</div>
    <div class="empty-state-desc">No pending actions. Ready to book your next trip?<br>
      <a href="<?= APP_URL ?>/customer/browse.php" style="color:var(--accent);text-decoration:none;font-weight:500;margin-top:8px;display:inline-block">Browse vehicles →</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
