<?php
if (!defined('INSPECTRA_CUSTOMER')) {
    header('Location: ' . APP_URL . '/customer/dashboard.php');
    exit;
}
Auth::requireCustomer();
$currentUser = Auth::user();
$headerNotifications = get_unread_notifications(Auth::id());
$notifCount = count($headerNotifications);
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Inspectra</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
<style>
<?php include __DIR__ . '/../assets/css/app.css.php'; ?>
</style>
</head>
<body>
<div class="bg-mesh"></div>
<div class="toast-container" id="toastContainer"></div>

<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-header">
    <span class="notif-panel-title">Notifications</span>
    <span style="font-size:11px;color:var(--accent);cursor:pointer;" onclick="markAllRead()">Mark all read</span>
  </div>
  <div class="notif-panel-list">
  <?php if (empty($headerNotifications)): ?>
  <div style="padding:24px;text-align:center;color:var(--text-secondary);font-size:13px;">No new notifications</div>
  <?php else: foreach ($headerNotifications as $n): ?>
  <div class="notif-item">
    <div class="notif-item-dot"></div>
    <div>
      <div class="notif-item-title"><?= e($n['title']) ?></div>
      <div class="notif-item-desc"><?= e($n['message']) ?></div>
      <div class="notif-item-time"><?= time_ago($n['created_at']) ?></div>
    </div>
  </div>
  <?php endforeach; endif; ?>
  </div>
</div>

<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">
        <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </div>
      <div>
        <div class="logo-text">Inspectra</div>
        <div class="logo-sub">My Account</div>
      </div>
    </div>

    <div class="sidebar-section-label">Overview</div>
    <a href="<?= APP_URL ?>/customer/dashboard.php" class="nav-item <?= $activePage==='dashboard'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="<?= APP_URL ?>/customer/browse.php" class="nav-item <?= $activePage==='browse'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      Browse Vehicles
    </a>

    <div class="sidebar-section-label">Bookings</div>
    <a href="<?= APP_URL ?>/customer/bookings.php" class="nav-item <?= $activePage==='bookings'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      My Bookings
      <?php $pend=DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE customer_id=? AND status IN ('booked','active')",[Auth::id()])['c']; if($pend>0): ?><span class="nav-badge"><?=$pend?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/customer/bookings.php?status=paid" class="nav-item <?= $activePage==='paid_bookings'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
      Paid Bookings
    </a>

    <div class="sidebar-section-label">Finance</div>
    <a href="<?= APP_URL ?>/customer/payments.php" class="nav-item <?= $activePage==='payments'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
      Payment History
    </a>
    <a href="<?= APP_URL ?>/customer/refunds.php" class="nav-item <?= $activePage==='refunds'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
      Refunds
    </a>

    <div class="sidebar-section-label">History</div>
    <a href="<?= APP_URL ?>/customer/inspections.php" class="nav-item <?= $activePage==='inspections'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      My Inspections
      <?php $pendingSig=DB::fetchOne("SELECT COUNT(*) c FROM inspections i JOIN bookings b ON i.booking_id=b.id WHERE b.customer_id=? AND i.status='in_progress' AND EXISTS (SELECT 1 FROM signatures s WHERE s.inspection_id=i.id AND s.role='agent') AND NOT EXISTS (SELECT 1 FROM signatures s WHERE s.inspection_id=i.id AND s.role='customer')",[Auth::id()])['c']; if($pendingSig>0): ?><span class="nav-badge"><?=$pendingSig?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/customer/inspection-comparison.php" class="nav-item <?= $activePage==='inspection-comparison'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      Inspection Comparison
      <?php $readyCompare=DB::fetchOne("SELECT COUNT(*) c FROM (SELECT b.id FROM bookings b JOIN inspections i ON b.id=i.booking_id WHERE b.customer_id=? AND i.status='completed' GROUP BY b.id HAVING COUNT(DISTINCT i.type)=2) t",[Auth::id()])['c']; if($readyCompare>0): ?><span class="nav-badge"><?=$readyCompare?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/customer/trip-history.php" class="nav-item <?= $activePage==='trips'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      Trip History
    </a>
    <a href="<?= APP_URL ?>/customer/damage-reports.php" class="nav-item <?= $activePage==='damage'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      Damage Reports
    </a>

    <div class="sidebar-section-label">Account</div>
    <a href="<?= APP_URL ?>/customer/notifications.php" class="nav-item <?= $activePage==='notifications'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      Notifications
      <?php if ($notifCount>0): ?><span class="nav-badge"><?=$notifCount?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/customer/profile.php" class="nav-item <?= $activePage==='profile'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profile
    </a>

    <div class="sidebar-bottom">
      <div class="user-card">
        <div class="avatar" style="background:linear-gradient(135deg,var(--accent2),#059669)"><?= strtoupper(substr($currentUser['first_name'],0,1).substr($currentUser['last_name'],0,1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($currentUser['first_name'].' '.$currentUser['last_name']) ?></div>
          <div class="user-role">Customer</div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/auth/logout.php" style="display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;color:var(--text-secondary);border-radius:8px;transition:background 0.15s;text-decoration:none;" onmouseover="this.style.background='rgba(239,68,68,0.07)';this.style.color='#dc2626'" onmouseout="this.style.background='';this.style.color=''">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Sign out
      </a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <button class="notif-btn" onclick="toggleSidebar()" style="display:none" id="menuBtn">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
      </button>
      <div>
        <div class="topbar-title"><?= e($pageTitle) ?></div>
      </div>
      <div class="spacer"></div>
      <div class="topbar-actions">
        <a href="<?= APP_URL ?>/customer/browse.php" class="btn btn-primary" style="text-decoration:none">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M12 5v14M5 12h14"/></svg>
          Book a Vehicle
        </a>
        <button class="notif-btn" onclick="toggleNotif()" id="notifToggle">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
          <?php if ($notifCount>0): ?><div class="notif-dot"></div><?php endif; ?>
        </button>
      </div>
    </div>
    <div class="page-content">
