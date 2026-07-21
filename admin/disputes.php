<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Dispute Management';
$activePage = 'disputes';

$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = "WHERE 1";
$params = [];
if ($status) { $where .= " AND d.status=?"; $params[] = $status; }
if ($q) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR d.subject LIKE ? OR b.booking_ref LIKE ?)";
    $s = "%$q%"; array_push($params, $s, $s, $s, $s);
}

$joins = "FROM disputes d
    JOIN bookings b ON d.booking_id=b.id
    JOIN users u ON d.raised_by=u.id
    JOIN vehicles v ON b.vehicle_id=v.id";

$total = DB::fetchOne("SELECT COUNT(*) c $joins $where", $params)['c'];
$stats = DB::fetchOne("
    SELECT
        SUM(d.status='open') open_cnt,
        SUM(d.status='under_review') review_cnt,
        SUM(d.status IN ('resolved','closed')) resolved_cnt,
        SUM(d.status='escalated') escalated_cnt
    $joins
");

$dataParams = array_merge($params, [$perPage, ($page - 1) * $perPage]);
$disputes = DB::fetchAll("
    SELECT d.*, b.booking_ref, u.first_name, u.last_name, u.email, v.make, v.model, v.year
    $joins
    $where
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
", $dataParams);

$pagination = paginate($total, $perPage, $page);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div>
    <div class="stat-value"><?= (int)($stats['open_cnt'] ?? 0) ?></div>
    <div class="stat-label">Open</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-value"><?= (int)($stats['review_cnt'] ?? 0) ?></div>
    <div class="stat-label">Under Review</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#10b981,#34d399)"></div>
    <div class="stat-value"><?= (int)($stats['resolved_cnt'] ?? 0) ?></div>
    <div class="stat-label">Resolved / Closed</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#ef4444,#f87171)"></div>
    <div class="stat-value"><?= (int)($stats['escalated_cnt'] ?? 0) ?></div>
    <div class="stat-label">Escalated</div>
  </div>
</div>

<form method="GET" style="margin-bottom:16px">
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?<?= http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])) ?>" class="chip <?= $status === '' ? 'active' : '' ?>">All</a>
      <?php foreach (['open' => 'Open', 'under_review' => 'Under Review', 'resolved' => 'Resolved', 'closed' => 'Closed', 'escalated' => 'Escalated'] as $val => $label): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['status' => $val, 'page' => 1])) ?>" class="chip <?= $status === $val ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <div class="search-bar" style="max-width:300px">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input name="q" value="<?= e($q) ?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Customer, subject, or booking ref...">
      <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Filed</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($disputes)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <div class="empty-state-icon">⚖️</div>
            <div class="empty-state-title">No disputes</div>
            <div class="empty-state-desc">Disputes filed by customers against a booking charge will appear here.</div>
          </div>
        </td></tr>
        <?php else: foreach ($disputes as $d): ?>
        <?php
        $statusColor = [
            'open' => 'background:rgba(245,158,11,0.1);color:#f59e0b',
            'under_review' => 'background:rgba(59,110,248,0.1);color:var(--accent)',
            'resolved' => 'background:rgba(16,185,129,0.1);color:var(--success)',
            'closed' => 'background:rgba(107,114,128,0.1);color:#6b7280',
            'escalated' => 'background:rgba(239,68,68,0.1);color:var(--danger)',
        ][$d['status']] ?? '';
        ?>
        <tr>
          <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($d['booking_ref']) ?></span></td>
          <td style="font-size:13px"><?= e($d['first_name'] . ' ' . $d['last_name']) ?></td>
          <td style="font-size:13px"><?= e($d['year'] . ' ' . $d['make'] . ' ' . $d['model']) ?></td>
          <td style="font-size:13px;max-width:200px"><?= e($d['subject']) ?></td>
          <td style="font-size:12.5px"><?= ucfirst($d['category']) ?></td>
          <td style="font-size:12.5px"><?= ucfirst($d['priority']) ?></td>
          <td><span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $statusColor ?>"><?= ucfirst(str_replace('_', ' ', $d['status'])) ?></span></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($d['created_at']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/admin/inspection-comparison.php?booking_id=<?= $d['booking_id'] ?>" class="action-btn primary" style="font-size:11px">⚖️ Manage</a>
          </td>
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
