<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Trip History';
$activePage = 'trips';
$custId = Auth::id();

$trips = DB::fetchAll("SELECT b.*, v.make, v.model, v.registration_number, v.category,
    (SELECT COUNT(*) FROM damage_reports dr JOIN inspections i ON dr.inspection_id=i.id WHERE i.booking_id=b.id) as damage_count,
    r.rating as review_rating
    FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id LEFT JOIN reviews r ON r.booking_id=b.id
    WHERE b.customer_id=? AND b.status='completed' ORDER BY b.updated_at DESC", [$custId]);

require_once __DIR__ . '/../includes/customer-header.php';
?>

<?php if (empty($trips)): ?>
<div class="card">
  <div class="empty-state">
    <div class="empty-state-icon">🗺️</div>
    <div class="empty-state-title">No completed trips yet</div>
    <div class="empty-state-desc"><a href="<?= APP_URL ?>/customer/browse.php" style="color:var(--accent);text-decoration:none">Start your first rental →</a></div>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Booking</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Days</th><th>Paid</th><th>Damages</th><th>Review</th></tr></thead>
      <tbody>
        <?php foreach ($trips as $t): ?>
        <tr>
          <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($t['booking_ref']) ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <span style="font-size:22px"></span>
              <div>
                <div style="font-size:13px;font-weight:500"><?= e($t['make'].' '.$t['model']) ?></div>
                <div style="font-size:11px;font-family:var(--mono);color:var(--text-tertiary)"><?= e($t['registration_number']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:12px"><?= format_date($t['pickup_date']) ?></td>
          <td style="font-size:12px"><?= format_date($t['return_date']) ?></td>
          <td style="text-align:center"><?= $t['total_days'] ?></td>
          <td style="font-weight:600">₵<?= number_format($t['amount_paid'],2) ?></td>
          <td>
            <?php if ($t['damage_count']>0): ?>
            <span style="color:var(--danger);font-weight:600">⚠️ <?= $t['damage_count'] ?></span>
            <?php else: ?>
            <span style="color:var(--success)">✅ Clean</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($t['review_rating']): ?>
            <a href="<?= APP_URL ?>/customer/review.php?booking_id=<?= $t['id'] ?>" style="text-decoration:none"><?= render_stars((float)$t['review_rating'], 13) ?></a>
            <?php else: ?>
            <a href="<?= APP_URL ?>/customer/review.php?booking_id=<?= $t['id'] ?>" class="action-btn primary" style="font-size:11px">⭐ Leave a Review</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
