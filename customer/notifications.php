<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Notifications';
$activePage = 'notifications';
$custId = Auth::id();

// Do NOT auto-mark all notifications as read on page load
// Users should explicitly mark notifications as read

$type = $_GET['type'] ?? '';
$read = $_GET['read'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;

$q = 'SELECT * FROM notifications WHERE user_id=?';
$params = [$custId];
if ($type) {
    $q .= ' AND type=?';
    $params[] = $type;
}
if ($read !== '') {
    $q .= ' AND is_read=?';
    $params[] = (int)$read;
}
$q .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
$params[] = $perPage;
$params[] = $offset;

$notifications = DB::fetchAll($q, $params);
$totalParams = [$custId];
if ($type) $totalParams[] = $type;
if ($read !== '') $totalParams[] = (int)$read;
$total = DB::fetchOne('SELECT COUNT(*) c FROM notifications WHERE user_id=?'.($type?' AND type=?':'').($read!==''?' AND is_read=?':''), $totalParams)['c'];
$pagination = paginate($total, $perPage, $page);

require_once __DIR__ . '/../includes/customer-header.php';
?>

<div class="page-actions" style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
  <div style="display:flex;gap:8px;flex:1">
    <select id="typeFilter" onchange="applyFilters()" style="padding:8px 12px;border:1px solid var(--border);border-radius:6px">
      <option value="">All Types</option>
      <option value="booking_created" <?php if ($type=='booking_created') echo 'selected'; ?>>New Booking</option>
      <option value="booking_updated" <?php if ($type=='booking_updated') echo 'selected'; ?>>Booking Updated</option>
      <option value="booking_modified" <?php if ($type=='booking_modified') echo 'selected'; ?>>Booking Modified</option>
      <option value="booking_cancelled" <?php if ($type=='booking_cancelled') echo 'selected'; ?>>Booking Cancelled</option>
      <option value="payment_success" <?php if ($type=='payment_success') echo 'selected'; ?>>Payment Confirmed</option>
      <option value="payment_received" <?php if ($type=='payment_received') echo 'selected'; ?>>Payment Notification</option>
      <option value="inspection_completed" <?php if ($type=='inspection_completed') echo 'selected'; ?>>Inspection Ready</option>
      <option value="trip_completed" <?php if ($type=='trip_completed') echo 'selected'; ?>>Trip Complete</option>
      <option value="refund_requested" <?php if ($type=='refund_requested') echo 'selected'; ?>>Refund Requested</option>
      <option value="refund_processed" <?php if ($type=='refund_processed') echo 'selected'; ?>>Refund Processed</option>
      <option value="refund_rejected" <?php if ($type=='refund_rejected') echo 'selected'; ?>>Refund Rejected</option>
    </select>
    <select id="readFilter" onchange="applyFilters()" style="padding:8px 12px;border:1px solid var(--border);border-radius:6px">
      <option value="">All Status</option>
      <option value="0" <?php if ($read=='0') echo 'selected'; ?>>Unread</option>
      <option value="1" <?php if ($read=='1') echo 'selected'; ?>>Read</option>
    </select>
  </div>
  <div style="font-size:14px;font-weight:500;color:var(--text-secondary)"><?= $total ?> total</div>
  <button class="btn btn-ghost" onclick="markAllRead()">Mark all read</button>
</div>

<div class="card">
  <?php if (empty($notifications)): ?>
  <div style="padding:60px;text-align:center;color:var(--text-secondary)">
    <div style="font-size:48px;margin-bottom:12px">🔔</div>
    <div style="font-size:18px;font-weight:500;margin-bottom:4px">No notifications match your filters</div>
    <div style="font-size:14px">Try adjusting type or read status</div>
  </div>
  <?php else: ?>
  <?php foreach ($notifications as $n): ?>
  <div class="notif-item-full" data-id="<?= $n['id'] ?>">
    <div class="notif-dot <?php if ($n['is_read']) echo 'read'; ?>"></div>
    <div class="notif-main">
      <div class="notif-title"><?= e($n['title']) ?></div>
      <div class="notif-message" style="white-space: pre-line; line-height: 1.6;"><?= e($n['message']) ?></div>
      <div class="notif-meta">
        <span class="notif-type badge badge-<?= $n['type'] ?>"><?= ucwords(str_replace('_',' ', $n['type'])) ?></span>
        <span><?= time_ago($n['created_at']) ?></span>
        <?php if ($n['is_read']): ?>
        <span class="read-label">✓ Read</span>
        <?php else: ?>
        <span class="unread-label" style="color:#667eea;font-weight:500">● New</span>
        <?php endif; ?>
      </div>
    </div>
    <?php 
    $data = [];
    if (isset($n['data'])) {
        $data = json_decode($n['data'], true);
    }
    ?>
    <div class="notif-actions">
      <button class="btn btn-ghost btn-sm" onclick="toggleReadStatus(<?= $n['id'] ?>, this)" title="Toggle read status">
        <?= $n['is_read'] ? '↩️ Mark Unread' : '✓ Mark Read' ?>
      </button>
      <?php if (isset($data['booking_id'])): ?>
        <a href="<?= APP_URL ?>/customer/bookings.php?id=<?= $data['booking_id'] ?>" class="btn btn-ghost btn-sm">View Booking</a>
      <?php endif; ?>
      <?php if (isset($data['inspection_id'])): ?>
        <a href="<?= APP_URL ?>/customer/inspections.php?id=<?= $data['inspection_id'] ?>" class="btn btn-ghost btn-sm">View Report</a>
      <?php endif; ?>
      <?php if (isset($data['refund_id'])): ?>
        <a href="<?= APP_URL ?>/customer/refunds.php?id=<?= $data['refund_id'] ?>" class="btn btn-ghost btn-sm">Refund Status</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($pagination['pages'] > 1): ?>
<div class="pagination" style="justify-content:center;margin-top:32px">
  <?php if ($pagination['has_prev']): ?>
    <a href="?page=<?= $page-1 ?>&type=<?= urlencode($type) ?>&read=<?= urlencode($read) ?>" class="pagination-link">← Prev</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page-2); $i <= min($pagination['pages'], $page+2); $i++): ?>
    <a href="?page=<?= $i ?>&type=<?= urlencode($type) ?>&read=<?= urlencode($read) ?>" class="pagination-link <?php if ($i==$page) echo 'active'; ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($pagination['has_next']): ?>
    <a href="?page=<?= $page+1 ?>&type=<?= urlencode($type) ?>&read=<?= urlencode($read) ?>" class="pagination-link">Next →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<style>
.notif-item-full { 
  display:flex; 
  align-items:flex-start; 
  gap:16px; 
  padding:20px; 
  border-bottom:1px solid var(--border); 
  cursor:pointer; 
  transition:background .15s; 
}
.notif-item-full:hover { 
  background:var(--hover-bg); 
}
.notif-dot { 
  width:10px; 
  height:10px; 
  border-radius:50%; 
  flex-shrink:0; 
  margin-top:8px; 
  background:var(--accent); 
}
.notif-dot.read { 
  background:var(--border); 
  opacity:0.5; 
}
.notif-main { 
  flex:1; 
}
.notif-title { 
  font-weight:600; 
  font-size:14px; 
  margin-bottom:4px; 
}
.notif-message { 
  color:var(--text-secondary); 
  font-size:13px; 
  line-height:1.5; 
}
.notif-meta { 
  display:flex; 
  gap:12px; 
  align-items:center; 
  margin-top:8px; 
  font-size:12px; 
  color:var(--text-tertiary); 
}
.notif-actions { 
  margin-left:auto; 
  display:flex; 
  gap:8px; 
}
.pagination { 
  display:flex; 
  gap:8px; 
  align-items:center; 
}
.pagination-link { 
  padding:8px 12px; 
  border:1px solid var(--border); 
  border-radius:6px; 
  text-decoration:none; 
  color:var(--text); 
  font-size:13px; 
}
.pagination-link.active { 
  background:var(--accent); 
  color:white; 
  border-color:var(--accent); 
}
</style>

<script>
function applyFilters() {
  const type = document.getElementById('typeFilter').value;
  const read = document.getElementById('readFilter').value;
  let url = new URLSearchParams(window.location.search);
  if (type) {
    url.set('type', type);
  } else {
    url.delete('type');
  }
  if (read !== '') {
    url.set('read', read);
  } else {
    url.delete('read');
  }
  url.delete('page'); // Reset to page 1
  window.location.search = url.toString();
}

function toggleReadStatus(notifId, button) {
  fetch('<?= APP_URL ?>/api/notifications/mark-read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ notification_id: notifId, toggle: true })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      // Update UI without full reload
      const item = document.querySelector(`[data-id="${notifId}"]`);
      const dot = item.querySelector('.notif-dot');
      const label = item.querySelector('.unread-label, .read-label');
      const newStatus = data.is_read;
      
      if (newStatus) {
        dot.classList.add('read');
        label.classList.remove('unread-label');
        label.classList.add('read-label');
        label.innerHTML = '✓ Read';
        button.textContent = '↩️ Mark Unread';
      } else {
        dot.classList.remove('read');
        label.classList.add('unread-label');
        label.classList.remove('read-label');
        label.innerHTML = '● New';
        label.style.color = '#667eea';
        label.style.fontWeight = '500';
        button.textContent = '✓ Mark Read';
      }
    }
  })
  .catch(err => console.error('Error:', err));
}

function markAllRead() {
  if (!confirm('Mark all notifications as read?')) return;
  fetch('<?= APP_URL ?>/api/notifications/mark-all-read.php', {
    method: 'POST'
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      location.reload();
    }
  })
  .catch(err => console.error('Error:', err));
}
</script>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>

