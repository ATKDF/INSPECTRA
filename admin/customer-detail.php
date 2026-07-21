<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Customer Details';
$activePage = 'customers';

$id = (int)($_GET['id'] ?? 0);
$customer = DB::fetchOne("SELECT * FROM users WHERE id=? AND role='customer'", [$id]);
if (!$customer) {
    redirect(APP_URL . '/admin/customers.php?error=' . urlencode('Customer not found.'));
}

$stats = DB::fetchOne("
    SELECT COUNT(*) total_bookings,
           SUM(status='completed') completed_trips,
           COALESCE((SELECT SUM(p.amount) FROM payments p JOIN bookings b2 ON p.booking_id=b2.id WHERE b2.customer_id=? AND p.status='completed'), 0) total_spent
    FROM bookings WHERE customer_id=?
", [$id, $id]);

$recentBookings = DB::fetchAll("
    SELECT b.*, v.make, v.model
    FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id
    WHERE b.customer_id=? ORDER BY b.created_at DESC LIMIT 5
", [$id]);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<a href="<?= APP_URL ?>/admin/customers.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--text-secondary);text-decoration:none;font-size:13px;margin-bottom:16px">← Back to Customers</a>

<div style="display:grid;grid-template-columns:1fr 1.4fr;gap:20px;align-items:start">
  <!-- Identity card -->
  <div class="card" style="padding:28px">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
      <?php if (!empty($customer['avatar'])): ?>
      <img src="<?= e($customer['avatar']) ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover" alt="Avatar">
      <?php else: ?>
      <div class="avatar" style="width:64px;height:64px;font-size:22px"><?= strtoupper(mb_substr($customer['first_name'],0,1).mb_substr($customer['last_name'],0,1)) ?></div>
      <?php endif; ?>
      <div>
        <div style="font-size:18px;font-weight:700"><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></div>
        <div style="font-size:11px;margin-top:4px">
          <span style="padding:3px 10px;border-radius:20px;font-weight:500;<?= $customer['is_active'] ? 'background:rgba(16,185,129,0.1);color:var(--success)' : 'background:rgba(239,68,68,0.1);color:var(--danger)' ?>"><?= $customer['is_active'] ? 'Active' : 'Inactive' ?></span>
        </div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:14px">
      <div>
        <div class="vehicle-meta-label" style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);letter-spacing:.5px;margin-bottom:3px">Email</div>
        <div style="font-size:13.5px"><?= e($customer['email']) ?></div>
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);letter-spacing:.5px;margin-bottom:3px">Phone</div>
        <div style="font-size:13.5px"><?= e($customer['phone'] ?: '—') ?></div>
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);letter-spacing:.5px;margin-bottom:3px">Date of Birth</div>
        <div style="font-size:13.5px"><?= $customer['date_of_birth'] ? format_date($customer['date_of_birth']) : '—' ?></div>
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);letter-spacing:.5px;margin-bottom:3px">Address</div>
        <div style="font-size:13.5px"><?= $customer['address'] || $customer['city'] ? e(trim(($customer['address'] ?? '') . ', ' . ($customer['city'] ?? ''), ', ')) : '—' ?></div>
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);letter-spacing:.5px;margin-bottom:3px">ID Document</div>
        <div style="font-size:13.5px"><?= $customer['id_document_type'] ? e($customer['id_document_type'] . ' — ' . ($customer['id_document_number'] ?: 'N/A')) : '—' ?></div>
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);letter-spacing:.5px;margin-bottom:3px">Emergency Contact</div>
        <div style="font-size:13.5px"><?= $customer['emergency_contact_name'] ? e($customer['emergency_contact_name'] . ($customer['emergency_contact_phone'] ? ' — ' . $customer['emergency_contact_phone'] : '')) : '—' ?></div>
      </div>
      <div>
        <div style="font-size:11px;text-transform:uppercase;color:var(--text-tertiary);letter-spacing:.5px;margin-bottom:3px">Member Since</div>
        <div style="font-size:13.5px"><?= format_date($customer['created_at']) ?></div>
      </div>
    </div>

    <div style="display:flex;gap:8px;margin-top:22px">
      <a href="<?= APP_URL ?>/admin/bookings.php?customer_id=<?= $id ?>" class="action-btn primary" style="flex:1;justify-content:center">📋 View Bookings</a>
    </div>
  </div>

  <!-- Stats + recent activity -->
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
      <div class="stat-card">
        <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
        <div class="stat-value"><?= (int)($stats['total_bookings'] ?? 0) ?></div>
        <div class="stat-label">Total Bookings</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:linear-gradient(90deg,#10b981,#34d399)"></div>
        <div class="stat-value"><?= (int)($stats['completed_trips'] ?? 0) ?></div>
        <div class="stat-label">Completed Trips</div>
      </div>
      <div class="stat-card">
        <div class="stat-card-accent" style="background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div>
        <div class="stat-value">₵<?= number_format($stats['total_spent'] ?? 0, 0) ?></div>
        <div class="stat-label">Total Spent</div>
      </div>
    </div>

    <div class="card">
      <div style="padding:18px 22px;border-bottom:1px solid var(--border);font-weight:600;font-size:14px">Recent Bookings</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Booking</th><th>Vehicle</th><th>Dates</th><th>Status</th><th>Amount</th></tr></thead>
          <tbody>
            <?php if (empty($recentBookings)): ?>
            <tr><td colspan="5">
              <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-title">No bookings yet</div>
              </div>
            </td></tr>
            <?php else: foreach ($recentBookings as $b): ?>
            <tr>
              <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($b['booking_ref']) ?></span></td>
              <td style="font-size:13px"><?= e($b['make'] . ' ' . $b['model']) ?></td>
              <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($b['pickup_date']) ?> → <?= format_date($b['return_date']) ?></td>
              <td><?= status_badge($b['status']) ?></td>
              <td style="font-weight:600">₵<?= number_format($b['total_amount'], 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
