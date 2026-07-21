<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Customers';
$activePage = 'customers';

if ($_POST && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $user = DB::fetchOne('SELECT is_active FROM users WHERE id=? AND role="customer"', [$id]);
        if ($user) {
            $newStatus = $user['is_active'] ? 0 : 1;
            DB::update('users', ['is_active' => $newStatus, 'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$id]);
            log_audit(Auth::id(), 'update', 'user', $id, ['is_active' => $user['is_active']], ['is_active' => $newStatus]);
        }
        json_response(['success' => true]);
    } elseif ($action === 'reset_password') {
        $id = (int)$_POST['id'];
        $pass = substr(Auth::generateUUID(), 0, 8);
        $hash = Auth::hashPassword($pass);
        DB::update('users', ['password_hash' => $hash, 'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$id]);
        log_audit(Auth::id(), 'update', 'user', $id, [], ['password_reset_by_admin' => true]);
        $message = "Your password has been reset by an administrator.\n\n🔐 Temporary Password: $pass\n\n⚠️ Important: Please change your password immediately after logging in for security purposes.\n\n🌐 Login URL: " . APP_URL . "/auth/login.php";
        create_notification($id, 'password_reset', 'Password Reset', $message, []);
        json_response(['success' => true, 'message' => 'Password reset & notification sent']);
    }
}

$q = "SELECT u.*, (SELECT COUNT(*) FROM bookings WHERE customer_id=u.id) as total_bookings, (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN bookings b ON p.booking_id=b.id WHERE b.customer_id=u.id AND p.status='completed') as total_spent FROM users u WHERE u.role='customer'";
$params = [];
if ($search = sanitize($_GET['search'] ?? '')) { $q .= " AND CONCAT(first_name, ' ',last_name, ' ',email) LIKE ?"; $params[] = "%$search%"; }
if ($status = $_GET['status'] ?? '') { $q .= " AND is_active=?"; $params[] = ($status=='active' ? 1 : 0); }
$q .= " ORDER BY u.created_at DESC";
$customers = DB::fetchAll($q, $params);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="page-actions" style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
  <div style="display:flex;gap:8px;flex:1;min-width:300px">
    <input style="padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;flex:1" placeholder="Search customers..." value="<?= e($search) ?>" id="searchInput" oninput="debounceSearch(this.value)">
    <select style="padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px" id="statusFilter" onchange="applyFilters()">
      <option value="">All Status</option>
      <option value="active" <?= $status=='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $status=='inactive'?'selected':'' ?>>Inactive</option>
    </select>
    <button class="btn btn-ghost" onclick="exportCustomers()" style="white-space:nowrap">📊 Export CSV</button>
  </div>
  <div style="font-size:14px;font-weight:500;color:var(--text-secondary)">
    <?= count($customers) ?> customers
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Customer</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Total Spent</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($customers)): ?>
        <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--text-secondary)">No customers yet</td></tr>
        <?php else: foreach ($customers as $c): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar" style="width:32px;height:32px;font-size:12px"><?= strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1)) ?></div>
              <span style="font-weight:500"><?= e($c['first_name'].' '.$c['last_name']) ?></span>
            </div>
          </td>
          <td style="font-size:13px"><?= e($c['email']) ?></td>
          <td style="font-size:13px"><?= e($c['phone'] ?? '—') ?></td>
          <td style="text-align:center"><?= $c['total_bookings'] ?></td>
          <td style="font-weight:600">₵<?= number_format($c['total_spent'],2) ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($c['created_at']) ?></td>
          <td>
            <label class="toggle-switch">
              <input type="checkbox" <?= $c['is_active'] ? 'checked' : '' ?> onchange="toggleCustomer(<?= $c['id'] ?>)">
              <span class="slider"></span>
            </label>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="<?= APP_URL ?>/admin/customer-detail.php?id=<?= $c['id'] ?>" class="btn-ghost" style="text-decoration:none" title="View full details">🪪</a>
              <button class="btn-ghost" onclick="viewBookings(<?= $c['id'] ?>)" title="Bookings">📋</button>
              <button class="btn-ghost" onclick="resetPassword(<?= $c['id'] ?>)" title="Reset Password">🔑</button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.toggle-switch { position:relative; display:inline-block; width:44px; height:24px; }
.toggle-switch input { opacity:0; width:0; height:0; }
.slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0; background:#cbd5e1; transition:.3s; border-radius:24px; }
.slider:before { position:absolute; content:""; height:20px; width:20px; left:2px; bottom:2px; background:white; transition:.3s; border-radius:50%; }
input:checked + .slider { background:var(--success); }
input:checked + .slider:before { transform:translateX(20px); }
.btn-ghost { background:none; border:1px solid var(--border); color:var(--text); padding:4px 8px; border-radius:4px; font-size:12px; cursor:pointer; }
.btn-ghost:hover { background:var(--hover-bg); }
</style>

<script>
let searchTimeout;
function debounceSearch(val) {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => window.location.href = '?search=' + encodeURIComponent(val), 300);
}
function applyFilters() {
  const s = document.getElementById('searchInput').value;
  const st = document.getElementById('statusFilter').value;
  let url = new URLSearchParams(window.location.search);
  if (s) url.set('search', s); else url.delete('search');
  if (st) url.set('status', st); else url.delete('status');
  window.location.search = url.toString();
}
function toggleCustomer(id) {
  if (!confirm('Toggle customer status?')) return false;
  fetch('', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'csrf_token=<?= csrf_token() ?>&action=toggle_status&id=' + id
  }).then(r => r.json()).then(data => {
    if (data.success) location.reload();
  }).catch(() => showToast('Error', 'error'));
}
function resetPassword(id) {
  if (!confirm('Reset to random 8-char password? Notification sent.')) return;
  fetch('', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'csrf_token=<?= csrf_token() ?>&action=reset_password&id=' + id
  }).then(r => r.json()).then(data => {
    showToast(data.message);
  });
}
function viewBookings(id) {
  window.open('<?= APP_URL ?>/admin/bookings.php?customer_id=' + id, '_blank');
}
function exportCustomers() {
  let csv = 'Name,Email,Phone,Bookings,Spent,Joined,Status\\n';
  <?php foreach ($customers as $c): ?>
  csv += '<?= addslashes($c['first_name'].' '.$c['last_name']) ?>,<?= addslashes($c['email']) ?>,<?= addslashes($c['phone'] ?? '') ?>,<?= $c['total_bookings'] ?>,₵<?= $c['total_spent'] ?>,<?= format_date($c['created_at']) ?>,<?= $c['is_active'] ? 'Active' : 'Inactive' ?>\\n';
  <?php endforeach; ?>
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'customers.csv';
  a.click();
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>

