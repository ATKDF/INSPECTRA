<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Admin Users';
$activePage = 'admin-users';

$search = sanitize($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$admins = DB::fetchAll("SELECT u.*, (SELECT COUNT(*) FROM notifications WHERE user_id=u.id) as notif_count FROM users u WHERE u.role='admin' " . ($search ? "AND CONCAT(first_name, ' ',last_name, ' ',email) LIKE '%$search%'" : "") . ($status ? " AND is_active=" . ($status=='active'?1:0) : "") . " ORDER BY u.created_at DESC");

if ($_POST && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $data = [
            'first_name' => sanitize($_POST['first_name']),
            'last_name' => sanitize($_POST['last_name']),
            'email' => sanitize($_POST['email']),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'is_active' => (int)($_POST['is_active'] ?? 1),
        ];
        if ($_POST['password']) $data['password_hash'] = Auth::hashPassword($_POST['password']);
        if ($action === 'create') {
            $data['role'] = 'admin';
            $data['uuid'] = Auth::generateUUID();
            $data['is_verified'] = 1;
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = DB::insert('users', $data);
            log_audit(Auth::id(), 'create', 'user', $id, [], ['email' => $data['email'], 'role' => 'admin']);
            $loginUrl = APP_URL . '/admin/dashboard.php';
            $message = "Welcome to Inspectra Admin Panel! 🎉\n\n👤 Your admin account has been created.\n\n🔐 Login Credentials:\n📧 Email: {$data['email']}\n\nYour password has been set by the administrator.\n\n🌐 Login: $loginUrl\n\n🔒 After first login, ensure you change your password for security.";
            create_notification($id, 'welcome_admin', 'Welcome!', $message);
        } else {
            $id = (int)$_POST['id'];
            $data['updated_at'] = date('Y-m-d H:i:s');
            DB::update('users', $data, 'id=?', [$id]);
            log_audit(Auth::id(), 'update', 'user', $id, [], ['email' => $data['email']]);
        }
        redirect($_SERVER['PHP_SELF'] . '?success=' . urlencode($action === 'create' ? 'Admin created!' : 'Admin updated!'));
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $user = DB::fetchOne('SELECT is_active FROM users WHERE id=? AND role="admin"', [$id]);
        if ($user) {
            $newStatus = $user['is_active'] ? 0 : 1;
            DB::update('users', ['is_active' => $newStatus, 'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$id]);
            log_audit(Auth::id(), 'update', 'user', $id, ['is_active' => $user['is_active']], ['is_active' => $newStatus]);
        }
    } elseif ($action === 'reset_password') {
        $id = (int)$_POST['id'];
        $pass = substr(Auth::generateUUID(), 0, 8);
        $hash = Auth::hashPassword($pass);
        DB::update('users', ['password_hash' => $hash, 'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$id]);
        log_audit(Auth::id(), 'update', 'user', $id, [], ['password_reset_by_admin' => true]);
        $message = "Your admin password has been reset by a system administrator.\n\n🔐 Temporary Password: $pass\n\n⚠️ Important: Change your password immediately after the next login for security.\n\n🌐 Login: " . APP_URL . "/admin/dashboard.php";
        create_notification($id, 'password_reset', 'Password Reset', $message, []);
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div style="background:#d1fae5;color:#065f46;padding:12px 16px;border:1px solid #a7f3d0;border-radius:8px;margin-bottom:20px;font-weight:500"><?= e($_GET['success']) ?></div>
<?php endif; ?>

<div class="page-actions" style="display:flex;gap:12px;align-items:center;margin-bottom:20px">
  <button class="btn btn-primary" onclick="showModal()">➕ Add New Admin</button>
  <div style="display:flex;gap:8px;flex:1">
    <input style="padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px;flex:1" placeholder="Search admins..." value="<?= e($search) ?>" id="searchInput" oninput="debounceSearch(this.value)">
    <select style="padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px" id="statusFilter" onchange="applyFilters()">
      <option value="">All Status</option>
      <option value="active" <?= $status=='active'?'selected':'' ?>>Active</option>
      <option value="inactive" <?= $status=='inactive'?'selected':'' ?>>Inactive</option>
    </select>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Admin</th><th>Email</th><th>Phone</th><th>Notifs</th><th>Joined</th><th>Last Login</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($admins)): ?>
        <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--text-secondary)">No admins found</td></tr>
        <?php else: foreach ($admins as $admin): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar" style="width:36px;height:36px;font-size:13px"><?= strtoupper(substr($admin['first_name'],0,1) . substr($admin['last_name'],0,1)) ?></div>
              <span style="font-weight:500"><?= e($admin['first_name'].' '.$admin['last_name']) ?></span>
            </div>
          </td>
          <td style="font-size:13px"><?= e($admin['email']) ?></td>
          <td style="font-size:13px"><?= e($admin['phone'] ?? '—') ?></td>
          <td style="text-align:center"><?= $admin['notif_count'] ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($admin['created_at']) ?></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= $admin['last_login_at'] ? format_datetime($admin['last_login_at']) : 'Never' ?></td>
          <td>
            <label class="toggle-switch">
              <input type="checkbox" <?= $admin['is_active'] ? 'checked' : '' ?> onchange="toggleStatus(<?= $admin['id'] ?>)">
              <span class="slider"></span>
            </label>
          </td>
          <td>
            <div style="display:flex;gap:6px">
              <button class="btn-ghost" onclick="editAdmin(<?= json_encode($admin) ?>)" title="Edit">✏️</button>
              <button class="btn-ghost" onclick="resetPassword(<?= $admin['id'] ?>)" title="Reset Password">🔑</button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div id="adminModal" class="modal" style="display:none">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="modalTitle">Add New Admin</h3>
      <button class="close-btn" onclick="hideModal()">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" id="editId">
      <input type="hidden" name="action" value="create">
      <div class="form-group"><label>First Name *</label><input class="form-input" name="first_name" required></div>
      <div class="form-group"><label>Last Name *</label><input class="form-input" name="last_name" required></div>
      <div class="form-group"><label>Email *</label><input type="email" class="form-input" name="email" required></div>
      <div class="form-group"><label>Phone</label><input class="form-input" name="phone"></div>
      <div class="form-group"><label>Password * (min 8 chars)</label><input type="password" class="form-input" name="password" id="formPassword" required></div>
      <div class="form-group">
        <label>Status</label>
        <label class="toggle-switch">
          <input type="checkbox" name="is_active" checked>
          <span class="slider"></span>
        </label>
      </div>
      <div style="display:flex;gap:12px">
        <button type="submit" class="btn btn-primary">Save Admin</button>
        <button type="button" class="btn btn-ghost" onclick="hideModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<style>
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:1000;}
.modal-content{background:white;border-radius:12px;padding:24px;max-width:480px;width:90vw;max-height:90vh;overflow:auto;}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.close-btn{background:none;border:none;font-size:24px;cursor:pointer;color:var(--text-secondary);}
.toggle-switch{position:relative;display:inline-block;width:44px;height:24px;}
.toggle-switch input{opacity:0;width:0;height:0;}
.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#cbd5e1;transition:.3s;border-radius:24px;}
.slider:before{position:absolute;content:"";height:20px;width:20px;left:2px;bottom:2px;background:white;transition:.3s;border-radius:50%;}
input:checked + .slider{background:var(--success);}
input:checked + .slider:before{transform:translateX(20px);}
.btn-ghost{background:none;border:1px solid var(--border);color:var(--text);padding:4px 8px;border-radius:4px;font-size:12px;cursor:pointer;}
.btn-ghost:hover{background:var(--hover-bg);}
</style>

<script>
let searchTimeout;
function debounceSearch(val) {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => window.location = '?search=' + encodeURIComponent(val), 300);
}
function applyFilters() {
  const s = document.getElementById('searchInput').value;
  const st = document.getElementById('statusFilter').value;
  let url = new URLSearchParams(window.location.search);
  if (s) url.set('search', s); else url.delete('search');
  if (st) url.set('status', st); else url.delete('status');
  window.location.search = url.toString();
}
function showModal() {
  document.getElementById('modalTitle').textContent = 'Add New Admin';
  document.querySelector('form').reset();
  document.getElementById('editId').value = '';
  document.querySelector('[name=action]').value = 'create';
  document.getElementById('adminModal').style.display = 'flex';
}
function editAdmin(admin) {
  document.getElementById('editId').value = admin.id;
  document.querySelector('[name=action]').value = 'update';
  document.getElementById('modalTitle').textContent = 'Edit Admin';
  document.querySelector('[name=first_name]').value = admin.first_name;
  document.querySelector('[name=last_name]').value = admin.last_name;
  document.querySelector('[name=email]').value = admin.email;
  document.querySelector('[name=phone]').value = admin.phone || '';
  document.querySelector('[name=is_active]').checked = !!admin.is_active;
  document.getElementById('adminModal').style.display = 'flex';
}
function hideModal() {
  document.getElementById('adminModal').style.display = 'none';
}
function toggleStatus(id) {
  if (!confirm('Toggle admin status?')) return;
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'csrf_token=<?= csrf_token() ?>&action=toggle_status&id=' + id
  }).then(r => r.text()).then(() => location.reload());
}
function resetPassword(id) {
  if (!confirm('Reset password (random 8 chars)? Notification sent.')) return;
  fetch('', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'csrf_token=<?= csrf_token() ?>&action=reset_password&id=' + id
  }).then(r => r.text()).then(() => showToast('Password reset & notified'));
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?> 

