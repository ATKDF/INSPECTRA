<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Trip History';
$activePage = 'trips';

$trips = DB::fetchAll("SELECT b.*, v.make, v.model, v.registration_number, u.first_name, u.last_name, (SELECT COUNT(*) FROM inspections WHERE booking_id=b.id) as insp_count, (SELECT COUNT(*) FROM damage_reports dr JOIN inspections i ON dr.inspection_id=i.id WHERE i.booking_id=b.id) as damage_count FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id JOIN users u ON b.customer_id=u.id WHERE b.status='completed' ORDER BY b.updated_at DESC");

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Booking Ref</th><th>Customer</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Days</th><th>Revenue</th><th>Damages</th><th>Inspections</th></tr></thead>
      <tbody>
        <?php if (empty($trips)): ?>
        <tr><td colspan="9" style="text-align:center;padding:50px;color:var(--text-secondary)">No completed trips yet</td></tr>
        <?php else: foreach ($trips as $t): ?>
        <tr>
          <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($t['booking_ref']) ?></span></td>
          <td><?= e($t['first_name'].' '.$t['last_name']) ?></td>
          <td><?= e($t['make'].' '.$t['model']) ?><br><span style="font-size:11px;font-family:var(--mono);color:var(--text-tertiary)"><?= e($t['registration_number']) ?></span></td>
          <td style="font-size:12px"><?= format_date($t['pickup_date']) ?></td>
          <td style="font-size:12px"><?= format_date($t['return_date']) ?></td>
          <td style="text-align:center"><?= $t['total_days'] ?></td>
          <td style="font-weight:600">₵<?= number_format($t['amount_paid'],2) ?></td>
          <td>
            <?php if ($t['damage_count']>0): ?>
            <span style="color:var(--danger);font-weight:600">⚠️ <?= $t['damage_count'] ?></span>
            <?php else: ?>
            <span style="color:var(--success)">✅ None</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center"><?= $t['insp_count'] ?>/2</td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
