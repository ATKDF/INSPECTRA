<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'New Damage Reports';
$activePage = 'damage';

$severity  = $_GET['severity'] ?? '';
$q         = trim($_GET['q'] ?? '');
$startDate = $_GET['start'] ?? '';
$endDate   = $_GET['end'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;

// A return-inspection damage counts as "new" only when no pickup-inspection
// record shares its zone+damage_type — same rule as compare_inspections().
// Both sides must be status='completed' (not just return) since a damage
// list can still change while an inspection is in_progress; showing this
// list before both are locked would make "new" a moving target.
$where = "WHERE i.type='return' AND i.status='completed'
  AND EXISTS (SELECT 1 FROM inspections ip WHERE ip.booking_id=i.booking_id AND ip.type='pickup' AND ip.status='completed')
  AND NOT EXISTS (
      SELECT 1 FROM damage_reports dr2 JOIN inspections i2 ON dr2.inspection_id=i2.id
      WHERE i2.booking_id=i.booking_id AND i2.type='pickup' AND i2.status='completed'
      AND dr2.zone=dr.zone AND dr2.damage_type=dr.damage_type
  )";
$params = [];
if ($severity) { $where .= " AND dr.severity=?"; $params[] = $severity; }
if ($q) {
    $where .= " AND (b.booking_ref LIKE ? OR v.registration_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $s = "%$q%"; array_push($params, $s, $s, $s, $s);
}
if ($startDate) { $where .= " AND dr.created_at >= ?"; $params[] = $startDate . ' 00:00:00'; }
if ($endDate)   { $where .= " AND dr.created_at <= ?"; $params[] = $endDate . ' 23:59:59'; }

$joins = "FROM damage_reports dr
    JOIN inspections i ON dr.inspection_id=i.id
    JOIN bookings b ON i.booking_id=b.id
    JOIN vehicles v ON b.vehicle_id=v.id
    JOIN users u ON b.customer_id=u.id";

$total = DB::fetchOne("SELECT COUNT(*) c $joins $where", $params)['c'];
$stats = DB::fetchOne("SELECT COALESCE(SUM(dr.repair_cost),0) total_cost, SUM(dr.severity='severe') severe_cnt $joins $where", $params);

$dataParams = array_merge($params, [$perPage, ($page - 1) * $perPage]);
$damages = DB::fetchAll("
    SELECT dr.*, i.booking_id, b.booking_ref, v.make, v.model, v.registration_number, u.first_name, u.last_name,
           (SELECT status FROM disputes WHERE booking_id=i.booking_id AND category='damage' ORDER BY created_at DESC LIMIT 1) AS dispute_status
    $joins
    $where
    ORDER BY dr.created_at DESC
    LIMIT ? OFFSET ?
", $dataParams);

$pagination = paginate($total, $perPage, $page);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--danger),#f87171)"></div>
    <div class="stat-value"><?= number_format($total) ?></div>
    <div class="stat-label">New Damage Records</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--warn),#fbbf24)"></div>
    <div class="stat-value">₵<?= number_format($stats['total_cost'],0) ?></div>
    <div class="stat-label">Total Repair Cost</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#ef4444,#dc2626)"></div>
    <div class="stat-value"><?= (int)$stats['severe_cnt'] ?></div>
    <div class="stat-label">Severe Issues</div>
  </div>
</div>

<form method="GET" style="margin-bottom:16px">
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach (['' => 'All', 'minor' => 'Minor', 'moderate' => 'Moderate', 'severe' => 'Severe'] as $k => $label): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['severity' => $k, 'page' => 1])) ?>" class="chip <?= $severity === $k ? 'active' : '' ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="date" name="start" value="<?= e($startDate) ?>" style="padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12.5px">
      <span style="color:var(--text-tertiary);font-size:12px">to</span>
      <input type="date" name="end" value="<?= e($endDate) ?>" style="padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:12.5px">
      <div class="search-bar" style="max-width:280px">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input name="q" value="<?= e($q) ?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Customer, ref, or reg...">
      </div>
      <?php if ($severity): ?><input type="hidden" name="severity" value="<?= e($severity) ?>"><?php endif; ?>
      <button type="submit" class="action-btn">Filter</button>
      <?php if ($severity || $q || $startDate || $endDate): ?><a href="?" class="action-btn">Clear</a><?php endif; ?>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Evidence</th><th>Booking</th><th>Customer</th><th>Vehicle</th><th>Zone</th><th>Type</th><th>Severity</th><th>Est. Cost</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($damages)): ?>
        <tr><td colspan="11">
          <div class="empty-state">
            <div class="empty-state-icon">✅</div>
            <div class="empty-state-title">No new damages found</div>
            <div class="empty-state-desc"><?= ($severity || $q || $startDate || $endDate) ? 'No records match your filters.' : 'No new damage detected across any completed pickup/return pair.' ?></div>
          </div>
        </td></tr>
        <?php else: foreach ($damages as $d): ?>
        <tr>
          <td>
            <?php if ($d['photo_url']): ?>
            <a href="<?= e($d['photo_url']) ?>" target="_blank"><img src="<?= e($d['photo_url']) ?>" alt="Evidence" style="width:44px;height:44px;object-fit:cover;border-radius:8px;border:1px solid var(--border);display:block"></a>
            <?php else: ?>
            <div style="width:44px;height:44px;border-radius:8px;background:rgba(0,0,0,.04);display:flex;align-items:center;justify-content:center;color:var(--text-tertiary);font-size:16px">📷</div>
            <?php endif; ?>
          </td>
          <td><span style="font-family:var(--mono);font-size:12px"><?= e($d['booking_ref']) ?></span></td>
          <td style="font-size:13px"><?= e($d['first_name'].' '.$d['last_name']) ?></td>
          <td style="font-size:13px"><?= e($d['make'].' '.$d['model']) ?><br><span style="font-size:11px;font-family:var(--mono);color:var(--text-tertiary)"><?= e($d['registration_number']) ?></span></td>
          <td><?= e(ucfirst(str_replace('_',' ',$d['zone']))) ?></td>
          <td><?= e(ucfirst($d['damage_type'])) ?></td>
          <td>
            <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $d['severity']==='minor'?'background:rgba(245,158,11,0.1);color:#d97706':($d['severity']==='severe'?'background:rgba(239,68,68,0.1);color:#dc2626':'background:rgba(249,115,22,0.1);color:#ea580c') ?>">
              <?= ucfirst($d['severity']) ?>
            </span>
          </td>
          <td style="font-weight:<?= $d['repair_cost']>0?'600':'400' ?>"><?= $d['repair_cost']>0?'₵'.number_format($d['repair_cost'],2):'—' ?></td>
          <td>
            <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;background:rgba(239,68,68,0.1);color:#dc2626;font-weight:500">🚨 New</span>
            <?php if ($d['dispute_status']): $dl = match($d['dispute_status']) {
                'resolved' => ['✅ Resolved','#059669','rgba(16,185,129,.1)'],
                'closed' => ['✔️ Closed','#6b7280','rgba(107,114,128,.1)'],
                'escalated' => ['🔺 Escalated','#dc2626','rgba(239,68,68,.1)'],
                default => ['⚖️ Disputed','#d97706','rgba(245,158,11,.1)'],
            }; ?>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;background:<?= $dl[2] ?>;color:<?= $dl[1] ?>;margin-left:4px;display:inline-block;margin-top:4px"><?= $dl[0] ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($d['created_at']) ?></td>
          <td><a href="<?= APP_URL ?>/admin/inspection-comparison.php?booking_id=<?= $d['booking_id'] ?>" class="action-btn" style="font-size:11px">🔍 Compare</a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pagination['pages'] > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap">
  <?php if ($pagination['has_prev']): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="action-btn">← Prev</a>
  <?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($pagination['pages'], $page + 2); $i++): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="action-btn <?= $i === $page ? 'primary' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($pagination['has_next']): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="action-btn">Next →</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
