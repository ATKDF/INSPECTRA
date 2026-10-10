<?php
// Admin layout header - include at top of each admin page
// Usage: $pageTitle = 'Dashboard'; $activePage = 'dashboard'; require ADMIN_LAYOUT_HEADER;
if (!defined('INSPECTRA_ADMIN')) {
    header('Location: ' . APP_URL . '/admin/dashboard.php');
    exit;
}
Auth::requireAdmin();
$currentUser = Auth::user();
$headerNotifications = get_unread_notifications(Auth::id());
$notifCount = count($headerNotifications);
$comparisonCount = DB::fetchOne("\n    SELECT COUNT(*) c\n    FROM (\n      SELECT b.id\n      FROM bookings b\n      JOIN inspections i ON b.id = i.booking_id\n      WHERE i.status = 'completed'\n      GROUP BY b.id\n      HAVING COUNT(DISTINCT i.type) = 2\n    ) x\n")['c'] ?? 0;
$newMessagesCount = DB::fetchOne("SELECT COUNT(*) c FROM contact_messages WHERE status='new'")['c'] ?? 0;
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — Inspectra Admin</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
<style>
<?php include __DIR__ . '/../assets/css/app.css.php'; ?>
</style>
</head>
<body>
<div class="bg-mesh"></div>
<div class="toast-container" id="toastContainer"></div>

<!-- Notification Panel -->
<div class="notif-panel" id="notifPanel">
  <div class="notif-panel-header">
    <span class="notif-panel-title">Notifications</span>
    <span style="font-size:11px;color:var(--accent);cursor:pointer;" onclick="markAllRead()">Mark all read</span>
    <a href="<?= APP_URL ?>/admin/notifications.php" style="font-size:11px;color:var(--accent);text-decoration:none;">View all →</a>
  </div>
  <div class="notif-panel-list">
  <?php if (empty($headerNotifications)): ?>
  <div style="padding:24px;text-align:center;color:var(--text-secondary);font-size:13px;">No new notifications</div>
  <?php else: foreach ($headerNotifications as $n): ?>
  <div class="notif-item" onclick="readNotif(<?= $n['id'] ?>)">
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
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">
        <svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </div>
      <div>
        <div class="logo-text">Inspectra</div>
        <div class="logo-sub">Admin Panel</div>
      </div>
    </div>

    <div class="sidebar-section-label">Overview</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item <?= $activePage==='dashboard'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Dashboard
    </a>
    <a href="<?= APP_URL ?>/admin/bookings.php" class="nav-item <?= $activePage==='bookings'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Bookings
      <?php $bc = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE status='booked'")['c']; if ($bc>0): ?><span class="nav-badge"><?=$bc?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/rentals.php" class="nav-item <?= $activePage==='rentals'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
      Active Rentals
      <?php $rc = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE status='active'")['c']; if ($rc>0): ?><span class="nav-badge success"><?=$rc?></span><?php endif; ?>
    </a>

    <div class="sidebar-section-label">Fleet</div>
    <a href="<?= APP_URL ?>/admin/vehicles.php" class="nav-item <?= $activePage==='vehicles'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v5M17 21a2 2 0 100-4 2 2 0 000 4zM7 21a2 2 0 100-4 2 2 0 000 4z"/></svg>
      Vehicles
    </a>
    <a href="<?= APP_URL ?>/admin/inspections.php" class="nav-item <?= $activePage==='inspections'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Inspections
    </a>
    <a href="<?= APP_URL ?>/admin/inspection-comparison.php" class="nav-item <?= $activePage==='inspection-comparison'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      Inspection Comparison
      <?php if ($comparisonCount > 0): ?><span class="nav-badge success"><?= $comparisonCount ?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/damage-reports.php" class="nav-item <?= $activePage==='damage'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
      Damage Reports
    </a>
    <a href="<?= APP_URL ?>/admin/reviews.php" class="nav-item <?= $activePage==='reviews'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6 4.4 2.3 7.2L12 16.6 5.7 21l2.3-7.2-6-4.4h7.6z"/></svg>
      Reviews
    </a>

    <div class="sidebar-section-label">Finance</div>
    <a href="<?= APP_URL ?>/admin/payments.php" class="nav-item <?= $activePage==='payments'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
      Payments
    </a>
    <a href="<?= APP_URL ?>/admin/refunds.php" class="nav-item <?= $activePage==='refunds'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
      Refunds
      <?php $rfc = DB::fetchOne("SELECT COUNT(*) c FROM refund_requests WHERE status='pending'")['c']; if ($rfc>0): ?><span class="nav-badge warn"><?=$rfc?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/disputes.php" class="nav-item <?= $activePage==='disputes'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Disputes
      <?php $dc = DB::fetchOne("SELECT COUNT(*) c FROM disputes WHERE status='open'")['c']; if ($dc>0): ?><span class="nav-badge warn"><?=$dc?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/reports.php" class="nav-item <?= $activePage==='reports'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      Reports & Analytics
    </a>
    <a href="<?= APP_URL ?>/admin/trip-history.php" class="nav-item <?= $activePage==='trips'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      Trip History
    </a>

    <div class="sidebar-section-label">System</div>
    <a href="<?= APP_URL ?>/admin/customers.php" class="nav-item <?= $activePage==='customers'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Customers
    </a>
    <a href="<?= APP_URL ?>/admin/admin-users.php" class="nav-item <?= $activePage==='admin-users'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Admins
    </a>
    <a href="<?= APP_URL ?>/admin/notifications.php" class="nav-item <?= $activePage==='notifications'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
      Notifications
      <?php if ($notifCount>0): ?><span class="nav-badge"><?=$notifCount?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/messages.php" class="nav-item <?= $activePage==='messages'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Messages
      <?php if ($newMessagesCount>0): ?><span class="nav-badge warn"><?=$newMessagesCount?></span><?php endif; ?>
    </a>
    <a href="<?= APP_URL ?>/admin/settings.php" class="nav-item <?= $activePage==='settings'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
      Settings
    </a>
    <a href="<?= APP_URL ?>/admin/audit_log.php" class="nav-item <?= $activePage=='audit_log'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13v2a2 2 0 01-2 2H9a2 2 0 01-2-2V9a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2z"/></svg>
      Audit Log
    </a>
    <a href="<?= APP_URL ?>/admin/email-queue-monitor.php" class="nav-item <?= $activePage==='email-queue'?'active':'' ?>">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
      Email Queue
    </a>

    <div class="sidebar-bottom">
      <div class="user-card" onclick="window.location='<?= APP_URL ?>/admin/profile.php'">
        <div class="avatar"><?= $currentUser ? strtoupper((substr($currentUser['first_name']??'U',0,1)).(substr($currentUser['last_name']??'',0,1))) : 'A' ?></div>
        <div class="user-info">
          <div class="user-name"><?= $currentUser ? e($currentUser['first_name'].' '.$currentUser['last_name']) : 'Admin User' ?></div>
          <div class="user-role">Admin</div>
        </div>
        <svg class="user-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
      </div>
      <a href="<?= APP_URL ?>/auth/logout.php" style="display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;color:var(--text-secondary);border-radius:8px;transition:background 0.15s;text-decoration:none;" onmouseover="this.style.background='rgba(239,68,68,0.07)';this.style.color='#dc2626'" onmouseout="this.style.background='';this.style.color=''">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Sign out
      </a>
    </div>
  </aside>

  <!-- Main Content -->
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
        <div class="search-bar" style="width:220px" onclick="this.querySelector('input').focus()">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Search...">
        </div>
        <button class="notif-btn" onclick="toggleNotif()" id="notifToggle">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/></svg>
          <?php if ($notifCount>0): ?><div class="notif-dot"></div><?php endif; ?>
        </button>
      </div>
    </div>
    <div class="page-content">
