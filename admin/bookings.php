<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Bookings';
$activePage = 'bookings';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_booking') {
        $vehicleId = (int)$_POST['vehicle_id'];
        $vehicle = DB::fetchOne("SELECT * FROM vehicles WHERE id = ? AND status = 'available'", [$vehicleId]);
        if (!$vehicle) { redirect(APP_URL.'/admin/bookings.php?error='.urlencode('Vehicle unavailable')); }

        $pickupDate = $_POST['pickup_date'];
        $returnDate = $_POST['return_date'];
        $days = (int)((strtotime($returnDate)-strtotime($pickupDate))/86400);
        if ($days < 1) { redirect(APP_URL.'/admin/bookings.php?error='.urlencode('Invalid dates')); }

        $subtotal  = $days * $vehicle['daily_rate'];
        $deposit   = $vehicle['deposit_amount'];
        $total     = $subtotal + $deposit;

        // Find or create customer
        $customerEmail = sanitize($_POST['customer_email']);
        $customer = DB::fetchOne("SELECT * FROM users WHERE email = ?", [$customerEmail]);
        if (!$customer) {
            $custId = DB::insert('users', [
                'uuid'          => Auth::generateUUID(),
                'first_name'    => sanitize($_POST['customer_name']),
                'last_name'     => '',
                'email'         => $customerEmail,
                'phone'         => sanitize($_POST['customer_phone'] ?? ''),
                'password_hash' => Auth::hashPassword('Temp@' . substr(uniqid(),0,6)),
                'role'          => 'customer',
                'is_verified'   => 1,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } else {
            $custId = $customer['id'];
        }

        $bookingId = DB::insert('bookings', [
            'uuid'            => Auth::generateUUID(),
            'booking_ref'     => Auth::generateBookingRef(),
            'vehicle_id'      => $vehicleId,
            'customer_id'     => $custId,
            'agent_id'        => Auth::id(),
            'pickup_date'     => $pickupDate,
            'return_date'     => $returnDate,
            'total_days'      => $days,
            'daily_rate'      => $vehicle['daily_rate'],
            'subtotal'        => $subtotal,
            'deposit_amount'  => $deposit,
            'total_amount'    => $total,
            'status'          => 'booked',
            'payment_status'  => 'unpaid',
            'notes'           => sanitize($_POST['notes'] ?? ''),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Enhanced notification with vehicle details
        $vehicleInfo = $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['year'] . ')';
        create_notification($custId, 'booking_created', 'Booking Confirmed', 
            "Your booking #{$bookingId} is confirmed!\n\n🚗 Vehicle: $vehicleInfo\n📅 Duration: $days day(s)\n💰 Total: ₵" . number_format($total, 2),
            ['booking_id'=>$bookingId, 'vehicle_id'=>$vehicleId, 'days'=>$days, 'total'=>$total, 'vehicle'=>$vehicleInfo]
        );
        redirect(APP_URL.'/admin/bookings.php?success='.urlencode('Booking created successfully!'));
    }

    if ($action === 'update_status') {
        $bid = (int)$_POST['booking_id'];
        $newStatus = $_POST['status'];
        $booking = DB::fetchOne("SELECT * FROM bookings WHERE id = ?", [$bid]);
        if ($booking) {
            DB::update('bookings', ['status'=>$newStatus,'updated_at'=>date('Y-m-d H:i:s')], 'id = ?', [$bid]);
            // Update vehicle status accordingly
            if ($newStatus === 'active') DB::update('vehicles', ['status'=>'rented'], 'id = ?', [$booking['vehicle_id']]);
            if (in_array($newStatus,['returned','completed','cancelled'])) DB::update('vehicles', ['status'=>'available'], 'id = ?', [$booking['vehicle_id']]);
            
            // Send appropriate notification based on new status
            if ($newStatus === 'cancelled') {
                $vehicleInfo = "{$booking['vehicle_make']} {$booking['vehicle_model']}";
                create_notification($booking['customer_id'], 'booking_cancelled', 'Booking Cancelled', 
                    "Your booking #{$booking['id']} ({$booking['booking_ref']}) has been cancelled.\n\n🚗 Vehicle: $vehicleInfo\n📅 Original Dates: " . format_date($booking['pickup_date']) . " - " . format_date($booking['return_date']) . "\n💰 Total Amount: ₵" . number_format($booking['total_amount'], 2), 
                    ['booking_id'=>$bid]);
            } elseif ($newStatus === 'completed') {
                create_notification($booking['customer_id'], 'trip_completed', 'Trip Completed', 
                    "Your rental trip has been completed!\n\n📌 Booking Ref: {$booking['booking_ref']}\n🚗 Vehicle: {$booking['vehicle_make']} {$booking['vehicle_model']}\n✅ Thank you for using Inspectra!", 
                    ['booking_id'=>$bid]);
            } else {
                $statusMsg = ucfirst($newStatus);
                create_notification($booking['customer_id'], 'booking_updated', 'Booking Updated', 
                    "Your booking status has been updated.\n\n📌 Booking Ref: {$booking['booking_ref']}\n📊 New Status: {$statusMsg}\n🚗 Vehicle: {$booking['vehicle_make']} {$booking['vehicle_model']}" , 
                    ['booking_id'=>$bid]);
            }
        }
        redirect(APP_URL.'/admin/bookings.php?success='.urlencode('Status updated.'));
    }
}

$status = $_GET['status'] ?? '';
$q = $_GET['q'] ?? '';
$customerId = (int)($_GET['customer_id'] ?? 0);
$where = "WHERE 1";
$params = [];
if ($status) { $where .= " AND b.status = ?"; $params[] = $status; }
if ($customerId) { $where .= " AND b.customer_id = ?"; $params[] = $customerId; }
if ($q) { $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR b.booking_ref LIKE ? OR v.registration_number LIKE ?)"; $s="%$q%"; $params=array_merge($params,[$s,$s,$s,$s]); }

$bookings = DB::fetchAll("SELECT b.*, v.make, v.model, v.registration_number, u.first_name, u.last_name, u.email FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id JOIN users u ON b.customer_id=u.id $where ORDER BY b.created_at DESC", $params);
$filteredCustomer = $customerId ? DB::fetchOne("SELECT first_name, last_name FROM users WHERE id=?", [$customerId]) : null;

$availableVehicles = DB::fetchAll("SELECT * FROM vehicles WHERE status='available' ORDER BY make,model");

require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($filteredCustomer): ?>
<div style="display:flex;align-items:center;gap:10px;background:rgba(59,110,248,.06);border:1px solid rgba(59,110,248,.2);border-radius:var(--radius-sm);padding:10px 16px;margin-bottom:16px;font-size:13px">
  <span>👤 Showing bookings for <strong><?= e($filteredCustomer['first_name'] . ' ' . $filteredCustomer['last_name']) ?></strong></span>
  <a href="<?= APP_URL ?>/admin/bookings.php" style="margin-left:auto;color:var(--accent);text-decoration:none;font-weight:500">Clear filter ✕</a>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php foreach ([''=>'All','booked'=>'Booked','paid'=>'Paid','active'=>'Active','returned'=>'Returned','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$label): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['status' => $k])) ?>" class="chip <?= $status===$k?'active':'' ?>"><?=$label?></a>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary" onclick="openModal('newBookingModal')">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M12 5v14M5 12h14"/></svg>
    New Booking
  </button>
</div>

<form method="GET" style="margin-bottom:16px;">
  <div class="search-bar" style="max-width:360px;">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input name="q" value="<?= e($q) ?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Search by customer, ref, or reg...">
    <?php if ($status): ?><input type="hidden" name="status" value="<?=e($status)?>"><?php endif; ?>
    <?php if ($customerId): ?><input type="hidden" name="customer_id" value="<?= $customerId ?>"><?php endif; ?>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Ref</th><th>Customer</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Days</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($bookings)): ?>
        <tr><td colspan="9" style="text-align:center;padding:50px;color:var(--text-secondary);">No bookings found</td></tr>
        <?php else: foreach ($bookings as $b): ?>
        <tr>
          <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($b['booking_ref']) ?></span></td>
          <td>
            <div style="font-weight:500"><?= e($b['first_name'].' '.$b['last_name']) ?></div>
            <div style="font-size:11px;color:var(--text-tertiary)"><?= e($b['email']) ?></div>
          </td>
          <td><?= e($b['make'].' '.$b['model']) ?><br><span style="font-size:11px;color:var(--text-tertiary);font-family:var(--mono)"><?= e($b['registration_number']) ?></span></td>
          <td style="font-size:12px"><?= format_date($b['pickup_date']) ?></td>
          <td style="font-size:12px"><?= format_date($b['return_date']) ?></td>
          <td style="text-align:center"><?= $b['total_days'] ?></td>
          <td style="font-weight:600">₵<?= number_format($b['total_amount'],2) ?></td>
          <td><?= status_badge($b['status']) ?></td>
          <td>
            <div style="display:flex;gap:6px;align-items:center;">
              <a href="<?= APP_URL ?>/admin/booking-detail.php?id=<?= $b['id'] ?>" class="action-btn" title="View Details">👁️ View</a>
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <select name="status" class="action-btn" onchange="this.form.submit()" style="cursor:pointer;padding:5px 8px">
                  <?php foreach (['booked','paid','active','returned','completed','cancelled'] as $s): ?>
                  <option value="<?=$s?>" <?= $b['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <a href="<?= APP_URL ?>/admin/inspections.php?booking_id=<?= $b['id'] ?>" class="action-btn primary" title="Start Inspection">🔍</a>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New Booking Modal -->
<div class="modal-overlay" id="newBookingModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <span class="modal-title">Create New Booking</span>
      <button class="modal-close" onclick="closeModal('newBookingModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="create_booking">
      <div class="modal-body">
        <div style="background:rgba(59,110,248,0.05);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:20px;font-size:13px;color:var(--text-secondary)">
          📧 If the customer email doesn't exist, a new customer account will be created automatically.
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Customer Name *</label>
            <input class="form-input" name="customer_name" placeholder="Full name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Customer Phone</label>
            <input class="form-input" name="customer_phone" placeholder="+233 24 000 0000">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Customer Email *</label>
          <input class="form-input" type="email" name="customer_email" placeholder="customer@email.com" required>
        </div>
        <div class="form-group">
          <label class="form-label">Vehicle *</label>
          <select class="form-input" name="vehicle_id" required id="vehicleSelect" onchange="calcTotal()">
            <option value="">Select a vehicle</option>
            <?php foreach ($availableVehicles as $v): ?>
            <option value="<?= $v['id'] ?>" data-rate="<?= $v['daily_rate'] ?>" data-deposit="<?= $v['deposit_amount'] ?>">
              <?= e($v['year'].' '.$v['make'].' '.$v['model']) ?> — <?= e($v['registration_number']) ?> (₵<?= number_format($v['daily_rate'],0) ?>/day)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pickup Date *</label>
            <input class="form-input" type="date" name="pickup_date" id="pickupDate" min="<?= date('Y-m-d') ?>" required onchange="calcTotal()">
          </div>
          <div class="form-group">
            <label class="form-label">Return Date *</label>
            <input class="form-input" type="date" name="return_date" id="returnDate" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" required onchange="calcTotal()">
          </div>
        </div>
        <div id="totalCalc" style="display:none;background:rgba(59,110,248,0.05);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:16px;font-size:13px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Days</span><span id="calcDays">-</span></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Subtotal</span><span id="calcSubtotal">-</span></div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span>Deposit</span><span id="calcDeposit">-</span></div>
          <div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid var(--border);padding-top:8px;margin-top:4px;"><span>Total</span><span id="calcTotal">-</span></div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-input" name="notes" rows="2" placeholder="Additional notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('newBookingModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Booking</button>
      </div>
    </form>
  </div>
</div>

<script>
function calcTotal() {
  const sel = document.getElementById('vehicleSelect');
  const opt = sel.options[sel.selectedIndex];
  const pickup = document.getElementById('pickupDate').value;
  const ret = document.getElementById('returnDate').value;
  if (!opt.value || !pickup || !ret) return;
  const days = Math.ceil((new Date(ret)-new Date(pickup))/(1000*60*60*24));
  if (days < 1) return;
  const rate = parseFloat(opt.dataset.rate||0);
  const dep = parseFloat(opt.dataset.deposit||0);
  const sub = days * rate;
  document.getElementById('calcDays').textContent = days;
  document.getElementById('calcSubtotal').textContent = '₵' + sub.toFixed(2);
  document.getElementById('calcDeposit').textContent = '₵' + dep.toFixed(2);
  document.getElementById('calcTotal').textContent = '₵' + (sub+dep).toFixed(2);
  document.getElementById('totalCalc').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
