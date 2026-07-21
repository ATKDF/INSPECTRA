<?php
/**
 * Booking detail view for admin - full record with timeline, payments, inspections
 */
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';

$bookingId = (int)($_GET['id'] ?? 0);
if (!$bookingId) redirect(APP_URL . '/admin/bookings.php');

$booking = DB::fetchOne("
    SELECT b.*,
           v.make, v.model, v.registration_number, v.year, v.category, v.fuel_type, v.transmission, v.seats,
           u.first_name, u.last_name, u.email, u.phone,
           a.first_name AS agent_fname, a.last_name AS agent_lname
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id  = v.id
    JOIN users    u ON b.customer_id = u.id
    LEFT JOIN users a ON b.agent_id  = a.id
    WHERE b.id = ?", [$bookingId]);

if (!$booking) redirect(APP_URL . '/admin/bookings.php?error=' . urlencode('Booking not found'));

$payments    = DB::fetchAll("SELECT * FROM payments    WHERE booking_id = ? ORDER BY created_at DESC", [$bookingId]);
$inspections = DB::fetchAll("SELECT * FROM inspections WHERE booking_id = ? ORDER BY created_at ASC",  [$bookingId]);
$refunds     = DB::fetchAll("SELECT * FROM refund_requests WHERE booking_id = ? ORDER BY created_at DESC", [$bookingId]);

$pageTitle  = 'Booking ' . $booking['booking_ref'];
$activePage = 'bookings';

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div style="margin-bottom:20px">
    <a href="<?= APP_URL ?>/admin/bookings.php" style="color:var(--accent);text-decoration:none;font-size:13px">← All Bookings</a>
</div>

<div class="grid-cols-60-40">
  <!-- Left -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Booking Summary -->
    <div class="card" style="padding:28px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px">
        <div>
          <div style="font-size:20px;font-weight:600"><?= e($booking['make'].' '.$booking['model']) ?></div>
          <div style="font-family:var(--mono);font-size:12px;color:var(--text-secondary)"><?= e($booking['registration_number']) ?></div>
        </div>
        <div style="text-align:right">
          <?= status_badge($booking['status']) ?>
          <div style="font-family:var(--mono);font-size:13px;font-weight:600;margin-top:6px"><?= e($booking['booking_ref']) ?></div>
        </div>
      </div>

      <div class="grid-2" style="gap:14px;margin-bottom:20px">
        <div style="padding:14px;background:var(--surface);border-radius:var(--radius-sm)">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-tertiary);margin-bottom:4px">Customer</div>
          <div style="font-weight:500"><?= e($booking['first_name'].' '.$booking['last_name']) ?></div>
          <div style="font-size:12px;color:var(--text-secondary)"><?= e($booking['email']) ?></div>
          <?php if ($booking['phone']): ?><div style="font-size:12px;color:var(--text-secondary)"><?= e($booking['phone']) ?></div><?php endif; ?>
        </div>
        <div style="padding:14px;background:var(--surface);border-radius:var(--radius-sm)">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-tertiary);margin-bottom:4px">Dates</div>
          <div style="font-weight:500"><?= format_date($booking['pickup_date'],'M d, Y') ?> → <?= format_date($booking['return_date'],'M d, Y') ?></div>
          <div style="font-size:12px;color:var(--text-secondary)"><?= $booking['total_days'] ?> day<?= $booking['total_days']!=1?'s':'' ?></div>
        </div>
      </div>

      <!-- Financials -->
      <div style="border-top:1px solid var(--border);padding-top:18px">
        <?php foreach ([
            'Daily Rate'       => '₵' . number_format($booking['daily_rate'],2),
            'Subtotal'         => '₵' . number_format($booking['subtotal'],2),
            'Deposit'          => '₵' . number_format($booking['deposit_amount'],2),
            'Discount'         => $booking['discount_amount'] > 0 ? '-₵'.number_format($booking['discount_amount'],2) : '₵0.00',
            'Tax'              => '₵' . number_format($booking['tax_amount'],2),
        ] as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
          <span style="color:var(--text-secondary)"><?= $label ?></span><span><?= $val ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:16px;border-top:1px solid var(--border);padding-top:10px;margin-top:4px">
          <span>Total</span><span style="color:var(--accent)">₵<?= number_format($booking['total_amount'],2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:6px">
          <span style="color:var(--text-secondary)">Amount Paid</span>
          <span style="color:var(--success);font-weight:600">₵<?= number_format($booking['amount_paid'],2) ?></span>
        </div>
        <?php if ($booking['amount_refunded'] > 0): ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:4px">
          <span style="color:var(--text-secondary)">Refunded</span>
          <span style="color:var(--danger)">₵<?= number_format($booking['amount_refunded'],2) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Inspections -->
    <?php if (!empty($inspections)): ?>
    <div class="card" style="padding:24px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div class="section-title">Inspections</div>
        <?php 
        $hasPickup = false;
        $hasDropoff = false;
        foreach ($inspections as $i) {
          if ($i['type'] === 'pickup') $hasPickup = true;
          if ($i['type'] === 'return') $hasDropoff = true;
        }
        if ($hasPickup && $hasDropoff): ?>
        <a href="<?= APP_URL ?>/admin/inspection-comparison.php?booking_id=<?= $bookingId ?>" class="btn btn-ghost" style="font-size:11px;padding:6px 12px">
          🔍 Compare Inspections
        </a>
        <?php endif; ?>
      </div>
      <?php foreach ($inspections as $insp): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:10px">
        <div>
          <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;margin-right:10px;<?= $insp['type']==='pickup'?'background:rgba(59,110,248,0.1);color:var(--accent)':'background:rgba(249,115,22,0.1);color:#f97316' ?>"><?= ucfirst($insp['type']) ?></span>
          <?= status_badge($insp['status']) ?>
          <div style="font-size:11px;color:var(--text-tertiary);margin-top:4px"><?= format_datetime($insp['created_at']) ?></div>
        </div>
        <a href="<?= APP_URL ?>/admin/inspection-flow.php?id=<?= $insp['id'] ?>" class="action-btn primary">
          <?= $insp['status']==='completed'?'View':'Continue' ?> →
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Payments -->
    <?php if (!empty($payments)): ?>
    <div class="card" style="overflow:hidden">
      <div style="padding:18px 20px;border-bottom:1px solid var(--border)"><div class="section-title">Payments</div></div>
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead><tr>
          <th style="padding:10px 16px;text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-tertiary);background:rgba(0,0,0,.018)">Date</th>
          <th style="padding:10px 16px;text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-tertiary);background:rgba(0,0,0,.018)">Method</th>
          <th style="padding:10px 16px;text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-tertiary);background:rgba(0,0,0,.018)">Amount</th>
          <th style="padding:10px 16px;text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-tertiary);background:rgba(0,0,0,.018)">Status</th>
        </tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
          <tr>
            <td style="padding:12px 16px;border-bottom:1px solid var(--border)"><?= format_date($p['created_at']) ?></td>
            <td style="padding:12px 16px;border-bottom:1px solid var(--border)"><?= ucfirst($p['payment_method']) ?></td>
            <td style="padding:12px 16px;border-bottom:1px solid var(--border);font-weight:600">₵<?= number_format($p['amount'],2) ?></td>
            <td style="padding:12px 16px;border-bottom:1px solid var(--border)"><?= status_badge($p['status']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Quick Actions -->
    <div class="card" style="padding:22px">
      <div class="section-title" style="margin-bottom:14px">Quick Actions</div>
      <a href="<?= APP_URL ?>/admin/inspections.php?booking_id=<?= $booking['id'] ?>" class="btn btn-primary" style="width:100%;justify-content:center;text-decoration:none;margin-bottom:10px;display:flex">
        🔍 Manage Inspection
      </a>
      <?php if ($booking['status'] === 'booked'): ?>
      <form method="POST" action="<?= APP_URL ?>/admin/bookings.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
        <input type="hidden" name="status" value="cancelled">
        <button type="submit" class="btn btn-ghost" style="width:100%;justify-content:center" onclick="return confirm('Cancel this booking?')">
          Cancel Booking
        </button>
      </form>
      <?php endif; ?>
    </div>

    <!-- Notes -->
    <?php if ($booking['notes'] || $booking['special_requests']): ?>
    <div class="card" style="padding:22px">
      <div class="section-title" style="margin-bottom:12px">Notes</div>
      <?php if ($booking['notes']): ?><div style="font-size:13px;color:var(--text-secondary);margin-bottom:10px"><strong>Notes:</strong> <?= e($booking['notes']) ?></div><?php endif; ?>
      <?php if ($booking['special_requests']): ?><div style="font-size:13px;color:var(--text-secondary)"><strong>Special requests:</strong> <?= e($booking['special_requests']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Refund requests -->
    <?php if (!empty($refunds)): ?>
    <div class="card" style="padding:22px">
      <div class="section-title" style="margin-bottom:12px">Refund Requests</div>
      <?php foreach ($refunds as $r): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--surface);border-radius:var(--radius-sm);margin-bottom:8px;font-size:13px">
        <div>
          <div style="font-weight:500">₵<?= number_format($r['amount'],2) ?></div>
          <div style="font-size:11px;color:var(--text-secondary)"><?= e(substr($r['reason'],0,50)) ?></div>
        </div>
        <?= status_badge($r['status']) ?>
      </div>
      <?php endforeach; ?>
      <a href="<?= APP_URL ?>/admin/refunds.php" class="action-btn" style="display:block;text-align:center;margin-top:6px;text-decoration:none">Manage Refunds →</a>
    </div>
    <?php endif; ?>

    <!-- Created info -->
    <div class="card" style="padding:18px">
      <div style="font-size:12px;color:var(--text-secondary);line-height:1.8">
        <div>📅 Created: <?= format_datetime($booking['created_at']) ?></div>
        <div>🔄 Updated: <?= format_datetime($booking['updated_at']) ?></div>
        <?php if ($booking['agent_fname']): ?><div>👤 Agent: <?= e($booking['agent_fname'].' '.$booking['agent_lname']) ?></div><?php endif; ?>
        <?php if ($booking['cancelled_at']): ?><div>❌ Cancelled: <?= format_datetime($booking['cancelled_at']) ?></div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
