<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Audit Log';
$activePage = 'audit_log';

$action = $_GET['action'] ?? '';
$role   = $_GET['role'] ?? '';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where = "WHERE 1";
$params = [];
if ($action) { $where .= " AND al.action=?"; $params[] = $action; }
if ($role)   { $where .= " AND u.role=?"; $params[] = $role; }
if ($q) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR al.entity_type LIKE ?)";
    $s = "%$q%"; array_push($params, $s, $s, $s, $s);
}

$joins = "FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id";

$total = DB::fetchOne("SELECT COUNT(*) c $joins $where", $params)['c'];
$stats = DB::fetchOne("
    SELECT
        SUM(u.role='admin') admin_cnt,
        SUM(u.role='customer') customer_cnt,
        SUM(al.created_at >= CURDATE()) today_cnt
    $joins
");

$dataParams = array_merge($params, [$perPage, ($page - 1) * $perPage]);
$logs = DB::fetchAll("
    SELECT al.*, u.first_name, u.last_name, u.email, u.role
    $joins
    $where
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
", $dataParams);

$pagination = paginate($total, $perPage, $page);

$actionColor = [
    'create'   => 'background:rgba(16,185,129,0.1);color:var(--success)',
    'update'   => 'background:rgba(59,110,248,0.1);color:var(--accent)',
    'delete'   => 'background:rgba(239,68,68,0.1);color:var(--danger)',
    'login'    => 'background:rgba(107,114,128,0.1);color:#6b7280',
    'register' => 'background:rgba(139,92,246,0.1);color:#8b5cf6',
];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-value"><?= number_format($total) ?></div>
    <div class="stat-label">Total Events</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#8b5cf6,#a78bfa)"></div>
    <div class="stat-value"><?= (int)($stats['admin_cnt'] ?? 0) ?></div>
    <div class="stat-label">Admin Actions</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div>
    <div class="stat-value"><?= (int)($stats['customer_cnt'] ?? 0) ?></div>
    <div class="stat-label">Customer Actions</div>
  </div>
</div>

<form method="GET" style="margin-bottom:16px">
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?<?= http_build_query(array_merge($_GET, ['role' => '', 'page' => 1])) ?>" class="chip <?= $role === '' ? 'active' : '' ?>">All Roles</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['role' => 'admin', 'page' => 1])) ?>" class="chip <?= $role === 'admin' ? 'active' : '' ?>">Admin</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['role' => 'customer', 'page' => 1])) ?>" class="chip <?= $role === 'customer' ? 'active' : '' ?>">Customer</a>
      <span style="width:1px;background:var(--border);margin:0 4px"></span>
      <a href="?<?= http_build_query(array_merge($_GET, ['action' => '', 'page' => 1])) ?>" class="chip <?= $action === '' ? 'active' : '' ?>">All Actions</a>
      <?php foreach (['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete', 'login' => 'Login', 'register' => 'Register'] as $val => $label): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['action' => $val, 'page' => 1])) ?>" class="chip <?= $action === $val ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <div class="search-bar" style="max-width:280px">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input name="q" value="<?= e($q) ?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Name, email, or entity...">
      <?php if ($action): ?><input type="hidden" name="action" value="<?= e($action) ?>"><?php endif; ?>
      <?php if ($role): ?><input type="hidden" name="role" value="<?= e($role) ?>"><?php endif; ?>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th style="width:130px">Time</th><th>User</th><th>Role</th><th>Action</th><th>Entity</th><th>Details</th><th style="width:110px">IP Address</th></tr></thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">No activity yet</div>
            <div class="empty-state-desc">Major admin and customer actions across the platform will appear here.</div>
          </div>
        </td></tr>
        <?php else: foreach ($logs as $log): ?>
        <tr>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_datetime($log['created_at']) ?></td>
          <td>
            <?php if ($log['user_id']): ?>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="avatar" style="width:28px;height:28px;font-size:11px"><?= strtoupper(mb_substr($log['first_name'] ?? '?', 0, 1)) ?></div>
              <div>
                <div style="font-size:13px;font-weight:500"><?= e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'Deleted user') ?></div>
                <div style="font-size:11px;color:var(--text-tertiary)"><?= e($log['email'] ?? '') ?></div>
              </div>
            </div>
            <?php else: ?>
            <span style="color:var(--text-tertiary);font-size:12.5px">System</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($log['role']): ?>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;<?= $log['role'] === 'admin' ? 'background:rgba(139,92,246,0.1);color:#8b5cf6' : 'background:rgba(245,158,11,0.1);color:#d97706' ?>"><?= ucfirst($log['role']) ?></span>
            <?php else: ?>
            <span style="color:var(--text-tertiary)">—</span>
            <?php endif; ?>
          </td>
          <td><span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $actionColor[$log['action']] ?? 'background:rgba(107,114,128,0.1);color:#6b7280' ?>"><?= ucfirst($log['action']) ?></span></td>
          <td style="font-size:12.5px"><strong><?= e(ucfirst(str_replace('_', ' ', $log['entity_type']))) ?></strong><?= $log['entity_id'] ? ' #' . $log['entity_id'] : '' ?></td>
          <td style="font-family:var(--mono);font-size:11px;color:var(--text-secondary);max-width:260px;white-space:pre-wrap">
            <?php
            $old = json_decode($log['old_data'] ?? '', true) ?? [];
            $new = json_decode($log['new_data'] ?? '', true) ?? [];
            if (!empty($new)) {
                $parts = [];
                foreach ($new as $k => $v) {
                    if (is_array($v)) $v = json_encode($v);
                    $parts[] = "$k: " . (isset($old[$k]) && $old[$k] != $v ? "{$old[$k]} → " : '') . $v;
                }
                echo e(implode(', ', array_slice($parts, 0, 4)));
            } else {
                echo '—';
            }
            ?>
          </td>
          <td style="font-family:var(--mono);font-size:11px;color:var(--text-tertiary)"><?= e($log['ip_address'] ?? '—') ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pagination['pages'] > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap">
  <?php if ($pagination['has_prev']): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="action-btn">← Prev</a><?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($pagination['pages'], $page + 2); $i++): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="action-btn <?= $i === $page ? 'primary' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($pagination['has_next']): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="action-btn">Next →</a><?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
