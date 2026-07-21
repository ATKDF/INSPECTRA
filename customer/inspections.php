<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'My Inspections';
$activePage = 'inspections';
$custId = Auth::id();

$inspections = DB::fetchAll("SELECT i.*, b.booking_ref, v.make, v.model, v.registration_number, (SELECT COUNT(*) FROM damage_reports WHERE inspection_id=i.id) as damage_count FROM inspections i JOIN bookings b ON i.booking_id=b.id JOIN vehicles v ON b.vehicle_id=v.id WHERE b.customer_id=? ORDER BY i.created_at DESC", [$custId]);

require_once __DIR__ . '/../includes/customer-header.php';
?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Booking</th><th>Vehicle</th><th>Type</th><th>Status</th><th>Damages</th><th>Date</th><th>Report</th></tr></thead>
      <tbody>
        <?php if (empty($inspections)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <div class="empty-state-title">No inspections yet</div>
            <div class="empty-state-desc">Inspections will appear here after your first rental</div>
          </div>
        </td></tr>
        <?php else: foreach ($inspections as $i): ?>
        <tr>
          <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($i['booking_ref']) ?></span></td>
          <td><?= e($i['make'].' '.$i['model']) ?><br><span style="font-size:11px;font-family:var(--mono);color:var(--text-tertiary)"><?= e($i['registration_number']) ?></span></td>
          <td><span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $i['type']==='pickup'?'background:rgba(59,110,248,0.1);color:var(--accent)':'background:rgba(249,115,22,0.1);color:#f97316' ?>"><?= ucfirst($i['type']) ?></span></td>
          <td><?= status_badge($i['status']) ?></td>
          <td>
            <?php if ($i['damage_count']>0): ?>
            <span style="color:var(--danger);font-weight:600">⚠️ <?= $i['damage_count'] ?></span>
            <?php else: ?>
            <span style="color:var(--success)">✅ None</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($i['created_at']) ?></td>
          <td>
            <?php if ($i['status']==='completed'): ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $i['id'] ?>" target="_blank" class="action-btn primary" style="font-size:11px;padding:6px 12px">📄 Report</a>
              <a href="<?= APP_URL ?>/customer/inspection-activity.php?id=<?= $i['id'] ?>" target="_blank" class="action-btn" style="font-size:11px;padding:6px 12px;background:rgba(102,126,234,0.1);color:var(--accent)">📋 Activity</a>
            </div>
            <?php else: ?>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <a href="<?= APP_URL ?>/customer/inspection-flow.php?id=<?= $i['id'] ?>" class="action-btn primary" style="font-size:11px;padding:6px 12px">👁️ View &amp; Sign</a>
              <a href="<?= APP_URL ?>/customer/inspection-activity.php?id=<?= $i['id'] ?>" target="_blank" class="action-btn" style="font-size:11px;padding:6px 12px;background:rgba(102,126,234,0.1);color:var(--accent)">📋 Activity</a>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
