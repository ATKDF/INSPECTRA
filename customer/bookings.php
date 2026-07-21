<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle  = 'My Bookings';
$activePage = 'bookings';
$custId     = Auth::id();

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $bid    = (int)$_POST['booking_id'];
    $booking = DB::fetchOne("SELECT * FROM bookings WHERE id=? AND customer_id=?", [$bid, $custId]);

    if ($booking) {
        if ($action === 'cancel' && in_array($booking['status'], ['booked'])) {
            DB::update('bookings', ['status'=>'cancelled','cancellation_reason'=>sanitize($_POST['reason']??'Customer cancelled'),'cancelled_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$bid]);
            DB::update('vehicles', ['status'=>'available'], 'id=?', [$booking['vehicle_id']]);
            log_audit($custId, 'update', 'booking', $bid, ['status' => $booking['status']], ['status' => 'cancelled', 'reason' => sanitize($_POST['reason'] ?? 'Customer cancelled')]);

            // Notify admins about the cancellation
            $vehicle = DB::fetchOne('SELECT make, model FROM vehicles WHERE id=?', [$booking['vehicle_id']]);
            $vehicleInfo = $vehicle['make'] . ' ' . $vehicle['model'];
            foreach(DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
                create_notification($adm['id'], 'booking_cancelled', 'Booking Cancelled', "Booking {$booking['booking_ref']} by " . Auth::name() . " has been cancelled.\n\n🚗 Vehicle: $vehicleInfo\n📅 Dates: " . format_date($booking['pickup_date']) . " - " . format_date($booking['return_date']) . "\n💰 Amount: ₵" . number_format($booking['total_amount'], 2), ['booking_id'=>$bid, 'vehicle'=>$vehicleInfo, 'cancellation_date'=>date('Y-m-d H:i:s')]);
            }
            
            redirect(APP_URL.'/customer/bookings.php?success='.urlencode('Booking cancelled.'));
        }

        if ($action === 'modify' && in_array($booking['status'], ['booked','paid'])) {
            $newPickup = sanitize($_POST['new_pickup_date'] ?? '');
            $newReturn = sanitize($_POST['new_return_date'] ?? '');
            $newPickupTime = sanitize($_POST['new_pickup_time'] ?? '');
            $newPickupLoc = sanitize($_POST['new_pickup_location'] ?? '');
            $newReturnTime = sanitize($_POST['new_return_time'] ?? '');

            if (empty($newPickup) || empty($newReturn) || empty($newPickupTime) || empty($newPickupLoc) || empty($newReturnTime)) {
                redirect(APP_URL.'/customer/bookings.php?id='.$bid.'&error='.urlencode('All fields are required.'));
            }

            // Basic validation: pickup before return
            $pickupDT = strtotime($newPickup . ' ' . $newPickupTime);
            $returnDT = strtotime($newReturn . ' ' . $newReturnTime);
            if ($pickupDT >= $returnDT) {
                redirect(APP_URL.'/customer/bookings.php?id='.$bid.'&error='.urlencode('Pickup must be before return date/time.'));
            }

            if (!check_vehicle_availability($booking['vehicle_id'], $newPickup, $newReturn, $bid)) {
                redirect(APP_URL.'/customer/bookings.php?id='.$bid.'&error='.urlencode('New dates are not available for this vehicle.'));
            }

            $days = (int)ceil(($returnDT - $pickupDT)/86400);
            $calc = calculate_booking_amounts($booking['daily_rate'], $days);

            DB::update('bookings', [
                'pickup_date' => $newPickup,
                'pickup_time' => $newPickupTime,
                'pickup_location' => $newPickupLoc,
                'return_date' => $newReturn,
                'return_time' => $newReturnTime,
                'total_days' => $days,
                'subtotal' => $calc['subtotal'],
                'tax_amount' => $calc['taxAmount'],
                'service_charge' => $calc['serviceCharge'],
                'total_amount' => $calc['totalAmount'],
                'initial_deposit' => $calc['initialDeposit'],
                'deposit_amount' => $calc['initialDeposit'],
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id=?', [$bid]);
            
            // Notify admins about the modification
            $vehicle = DB::fetchOne('SELECT make, model FROM vehicles WHERE id=?', [$booking['vehicle_id']]);
            $vehicleInfo = $vehicle['make'] . ' ' . $vehicle['model'];
            foreach(DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
                create_notification($adm['id'], 'booking_modified', 'Booking Modified', "Booking {$booking['booking_ref']} has been modified by customer " . Auth::name() . ".\n\n🚗 Vehicle: $vehicleInfo\n📅 New Dates: " . format_date($newPickup) . " - " . format_date($newReturn) . "\n📊 New Duration: " . $days . " day(s)\n💰 New Total: ₵" . number_format($calc['totalAmount'], 2), ['booking_id'=>$bid, 'vehicle'=>$vehicleInfo, 'new_days'=>$days, 'new_total'=>$calc['totalAmount'], 'modified_date'=>date('Y-m-d H:i:s')]);
            }

            redirect(APP_URL.'/customer/bookings.php?id='.$bid.'&success='.urlencode('Booking updated successfully.'));
        }

        if ($action === 'refund_cancel' && in_array($booking['status'], ['paid', 'returned']) && ($booking['amount_refunded'] ?? 0) < $booking['amount_paid']) {
            $refund_amount = (float)($_POST['refund_amount'] ?? 0);
            if ($refund_amount < 1 || $refund_amount > $booking['amount_paid']) {
                redirect(APP_URL.'/customer/bookings.php?id='.$bid.'&error='.urlencode('Refund amount must be between ₵1 and ₵' . number_format($booking['amount_paid'], 2) . '.'));
            }
            $payment = DB::fetchOne("SELECT * FROM payments WHERE booking_id=? AND status='completed' ORDER BY id DESC LIMIT 1", [$bid]);
            if ($payment) {
                $refundId = DB::insert('refund_requests', ['uuid'=>Auth::generateUUID(),'booking_id'=>$bid,'payment_id'=>$payment['id'],'requested_by'=>$custId,'amount'=>$refund_amount,'reason'=>sanitize($_POST['reason']??'Customer requested refund'),'status'=>'pending','created_at'=>date('Y-m-d H:i:s')]);
                
                $isFullRefund = abs($refund_amount - $booking['amount_paid']) < 0.01;
                
                // Notify customer
                create_notification($custId, 'refund_requested', 'Refund Request Submitted', 'Your ' . ($isFullRefund ? 'full' : 'partial') . ' refund request for ₵' . number_format($refund_amount, 2) . ' has been submitted and is pending approval.', ['refund_id' => $refundId, 'booking_id' => $bid]);
                
                // Notify admins
                foreach(DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
                    create_notification($adm['id'], 'refund_requested', 'Refund Request Pending', 'Customer ' . Auth::name() . ' requested ' . ($isFullRefund ? 'full' : 'partial') . ' refund of ₵' . number_format($refund_amount, 2) . ' for booking ' . $booking['booking_ref'], ['refund_id' => $refundId, 'booking_id' => $bid]);
                }
                
                if ($isFullRefund) {
                    DB::update('bookings', ['status'=>'cancelled','cancellation_reason'=>'Full refund requested','cancelled_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')], 'id=?', [$bid]);
                    DB::update('vehicles', ['status'=>'available'], 'id=?', [$booking['vehicle_id']]);
                    redirect(APP_URL.'/customer/bookings.php?success='.urlencode('Full refund of ₵' . number_format($refund_amount, 2) . ' requested. Booking cancelled. Pending admin review.'));
                } else {
                    redirect(APP_URL.'/customer/bookings.php?success='.urlencode('Partial refund of ₵' . number_format($refund_amount, 2) . ' requested. Pending admin review.'));
                }
            }
        }
    }
}

// ── List / Detail ─────────────────────────────────────────────────────────────
$status   = $_GET['status'] ?? '';
$where    = "WHERE b.customer_id = ?";
$params   = [$custId];
if ($status) { $where .= " AND b.status = ?"; $params[] = $status; }

$bookings = DB::fetchAll("SELECT b.*, v.make, v.model, v.registration_number, v.category FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id $where ORDER BY b.created_at DESC", $params);

$detail = null;

$pickupLocations = array_map('trim', explode(',', get_setting('pickup_locations', 'Osu,Tema')));
if (isset($_GET['id'])) {
    $detail = DB::fetchOne("SELECT b.*, v.make, v.model, v.registration_number, v.category, v.fuel_type, v.transmission, v.seats, v.daily_rate as vrate FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id WHERE b.id=? AND b.customer_id=?", [(int)$_GET['id'], $custId]);
    if ($detail) {
        $pickupInspection = DB::fetchOne("SELECT * FROM inspections WHERE booking_id=? AND type='pickup'",  [$detail['id']]);
        $returnInspection = DB::fetchOne("SELECT * FROM inspections WHERE booking_id=? AND type='return'",  [$detail['id']]);
        $latestPayment    = DB::fetchOne("SELECT * FROM payments WHERE booking_id=? AND status='completed' ORDER BY paid_at DESC LIMIT 1", [$detail['id']]);
        $existingReview   = DB::fetchOne("SELECT * FROM reviews WHERE booking_id=?", [$detail['id']]);
    }
}

require_once __DIR__ . '/../includes/customer-header.php';
?>

<?php if ($detail): ?>
<!-- ─── DETAIL VIEW ─────────────────────────────────────────────────── -->
<div style="margin-bottom:18px">
  <a href="<?= APP_URL ?>/customer/bookings.php" style="color:var(--accent);text-decoration:none;font-size:13px">← Back to Bookings</a>
</div>

<div class="grid-cols-60-40">
  <!-- LEFT -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Booking summary card -->
    <div class="card" style="padding:28px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:22px">
        <div>
          <div style="font-size:20px;font-weight:600"><?= e($detail['make'].' '.$detail['model']) ?></div>
          <div style="font-size:12px;font-family:var(--mono);color:var(--text-secondary)"><?= e($detail['registration_number']) ?></div>
        </div>
        <div style="text-align:right">
          <?= status_badge($detail['status']) ?>
          <div style="font-size:12px;font-family:var(--mono);color:var(--text-tertiary);margin-top:4px"><?= e($detail['booking_ref']) ?></div>
        </div>
      </div>

      <!-- Dates -->
      <div class="grid-2" style="gap:14px;margin-bottom:20px">
        <div style="padding:14px;background:rgba(59,110,248,.05);border-radius:var(--radius-sm)">
          <div style="font-size:10px;color:var(--text-tertiary);text-transform:uppercase;margin-bottom:4px">Pickup</div>
          <div style="font-weight:600"><?= format_date($detail['pickup_date'],'D, M d Y') ?></div>
          <?php if ($detail['pickup_time']): ?><div style="font-size:12px;color:var(--text-secondary)"><?= e($detail['pickup_time']) ?></div><?php endif; ?>
          <?php if ($detail['pickup_location']): ?><div style="font-size:11.5px;color:var(--text-tertiary);margin-top:3px">📍 <?= e($detail['pickup_location']) ?><?= $detail['pickup_city']?', '.e($detail['pickup_city']):'' ?></div><?php endif; ?>
        </div>
        <div style="padding:14px;background:rgba(59,110,248,.05);border-radius:var(--radius-sm)">
          <div style="font-size:10px;color:var(--text-tertiary);text-transform:uppercase;margin-bottom:4px">Return</div>
          <div style="font-weight:600"><?= format_date($detail['return_date'],'D, M d Y') ?></div>
          <?php if ($detail['return_time']): ?><div style="font-size:12px;color:var(--text-secondary)"><?= e($detail['return_time']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Cost breakdown -->
      <div style="border-top:1px solid var(--border);padding-top:18px">
        <?php
        $rows = [
          [$detail['total_days'].' day(s) × ₵'.number_format($detail['daily_rate'],2), '₵'.number_format($detail['subtotal'],2)],
        ];
        if (($detail['tax_amount']??0)>0)      $rows[] = ['Tax',           '₵'.number_format($detail['tax_amount'],2)];
        if (($detail['service_charge']??0)>0)   $rows[] = ['Service Charge','₵'.number_format($detail['service_charge'],2)];
        foreach ($rows as [$label,$val]): ?>
        <div style="display:flex;justify-content:space-between;margin-bottom:9px;font-size:13px">
          <span style="color:var(--text-secondary)"><?= $label ?></span><span><?= $val ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;font-weight:700;font-size:16px;border-top:1px solid var(--border);padding-top:10px;margin-top:4px">
          <span>Total</span><span style="color:var(--accent)">₵<?= number_format($detail['total_amount'],2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:8px">
          <span style="color:var(--text-secondary)">Amount Paid</span>
          <span style="font-weight:600;color:var(--success)">₵<?= number_format($detail['amount_paid'],2) ?></span>
        </div>
        <?php if ($detail['amount_paid'] < $detail['total_amount'] && in_array($detail['status'],['paid','active'])): ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:4px">
          <span style="color:var(--text-secondary)">Balance Remaining</span>
          <span style="font-weight:600;color:var(--warn)">₵<?= number_format($detail['total_amount']-$detail['amount_paid'],2) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($detail['amount_refunded']>0): ?>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-top:4px">
          <span style="color:var(--text-secondary)">Refunded</span>
          <span style="font-weight:600;color:var(--danger)">₵<?= number_format($detail['amount_refunded'],2) ?></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Deposit info when unpaid -->
      <?php if ($detail['payment_status']==='unpaid'): ?>
      <div style="margin-top:14px;padding:12px 14px;background:rgba(59,110,248,.06);border-radius:var(--radius-sm);border:1px solid rgba(59,110,248,.2);font-size:12.5px">
        <div style="font-weight:600;color:var(--accent);margin-bottom:3px">💳 Payment Required</div>
        <div style="color:var(--text-secondary)">Minimum deposit: <strong style="color:var(--accent)">₵<?= number_format($detail['initial_deposit']??$detail['total_amount']*0.5,2) ?></strong> · You may pay up to ₵<?= number_format($detail['total_amount'],2) ?>.</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Inspections panel -->
    <?php if ($pickupInspection || $returnInspection): ?>
    <div class="card" style="padding:22px">
      <div class="section-title" style="margin-bottom:14px">🔍 Inspections</div>
      <?php foreach ([['Pickup','pickup',$pickupInspection],['Return','return',$returnInspection]] as [$label,$type,$insp]): ?>
      <?php if ($insp): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:9px">
        <div>
          <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $type==='pickup'?'background:rgba(59,110,248,.1);color:var(--accent)':'background:rgba(249,115,22,.1);color:#f97316' ?>"><?= $label ?></span>
          <span style="margin-left:8px"><?= status_badge($insp['status']) ?></span>
          <div style="font-size:11px;color:var(--text-tertiary);margin-top:3px"><?= format_datetime($insp['created_at']) ?></div>
        </div>
        <?php if ($insp['status']==='completed'): ?>
        <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $insp['id'] ?>" target="_blank" class="action-btn primary">📄 Report</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>

      <!-- Before/After comparison -->
      <?php if ($pickupInspection && $returnInspection && $pickupInspection['status']==='completed' && $returnInspection['status']==='completed'): ?>
      <a href="<?= APP_URL ?>/customer/inspection-comparison.php?booking_id=<?= $detail['id'] ?>" class="btn btn-ghost" style="width:100%;justify-content:center;text-decoration:none;margin-top:4px">
        🔄 Compare Pickup vs Return
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- RIGHT: Actions sidebar -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <?php
    // Reusable "pay remaining balance" card — shown for any booking that still owes
    // money, regardless of status, so customers can always top up until fully paid.
    $balanceDue = round($detail['total_amount'] - $detail['amount_paid'], 2);
    $showBalanceCard = $balanceDue > 0 && in_array($detail['status'], ['paid', 'active', 'returned', 'completed'], true);
    $balanceCardHtml = '';
    if ($showBalanceCard):
        $paystackEnabled = get_setting('paystack_enabled', '1');
        $stripeEnabled = get_setting('stripe_enabled', '0');
        ob_start();
    ?>
    <div class="card" style="padding:22px">
      <div class="section-title" style="margin-bottom:14px">💳 Pay Remaining Balance</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        Balance remaining: <strong style="color:var(--accent);font-size:16px">₵<?= number_format($balanceDue, 2) ?></strong>
        <span style="color:var(--text-tertiary)">(of ₵<?= number_format($detail['total_amount'], 2) ?> total)</span>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-size:12.5px;font-weight:500;margin-bottom:6px;display:block">Payment Amount (₵)</label>
        <input type="range" id="balPayAmtSlider" min="1" max="<?= $balanceDue ?>" step="1" value="<?= $balanceDue ?>"
               style="width:100%;margin-bottom:8px" oninput="document.getElementById('balPayAmtDisplay').textContent='₵'+parseFloat(this.value).toFixed(2)">
        <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--text-secondary)">
          <span>Min: ₵1.00</span>
          <strong id="balPayAmtDisplay" style="color:var(--accent)">₵<?= number_format($balanceDue, 2) ?></strong>
          <span>Max: ₵<?= number_format($balanceDue, 2) ?></span>
        </div>
      </div>
      <?php if ($paystackEnabled === '1'): ?>
      <button onclick="payBalanceWithPaystack()" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:10px">💳 Pay with Paystack</button>
      <?php endif; ?>
      <?php if ($stripeEnabled === '1'): ?>
      <a href="<?= APP_URL ?>/api/payments/stripe-checkout.php?booking_id=<?= $detail['id'] ?>" class="btn btn-ghost" style="width:100%;justify-content:center;text-decoration:none;margin-bottom:10px;display:flex">💳 Pay with Stripe</a>
      <?php endif; ?>
      <div style="font-size:11px;color:var(--text-tertiary);text-align:center">🔒 Secured payment</div>
    </div>
    <?php
        $balanceCardHtml = ob_get_clean();
    endif;
    ?>

    <!-- ── BOOKED (unpaid) actions ── -->
    <?php if ($detail['status']==='booked' && $detail['payment_status']==='unpaid'): ?>
    <?php $paystackEnabled=get_setting('paystack_enabled','1'); $stripeEnabled=get_setting('stripe_enabled','0'); ?>
    <div class="card" style="padding:22px">
      <div class="section-title" style="margin-bottom:14px">💳 Complete Payment</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        Total: <strong style="color:var(--accent);font-size:16px">₵<?= number_format($detail['total_amount'],2) ?></strong><br>
        Minimum to pay: <strong>₵<?= number_format($detail['initial_deposit']??$detail['total_amount']*0.5,2) ?></strong>
      </div>

      <!-- Custom amount slider -->
      <?php $minDep = round($detail['initial_deposit']??$detail['total_amount']*0.5,2); $maxAmt = $detail['total_amount']; ?>
      <div style="margin-bottom:14px">
        <label style="font-size:12.5px;font-weight:500;margin-bottom:6px;display:block">Payment Amount (₵)</label>
        <input type="range" id="payAmtSlider" min="<?= $minDep ?>" max="<?= $maxAmt ?>" step="1" value="<?= $minDep ?>"
               style="width:100%;margin-bottom:8px" oninput="document.getElementById('payAmtDisplay').textContent='₵'+parseFloat(this.value).toFixed(2)">
        <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--text-secondary)">
          <span>Min: ₵<?= number_format($minDep,2) ?></span>
          <strong id="payAmtDisplay" style="color:var(--accent)">₵<?= number_format($minDep,2) ?></strong>
          <span>Max: ₵<?= number_format($maxAmt,2) ?></span>
        </div>
      </div>

      <?php if ($paystackEnabled==='1'): ?>
      <button onclick="payWithPaystack()" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:10px">💳 Pay with Paystack</button>
      <?php endif; ?>
      <?php if ($stripeEnabled==='1'): ?>
      <a href="<?= APP_URL ?>/api/payments/stripe-checkout.php?booking_id=<?= $detail['id'] ?>" class="btn btn-ghost" style="width:100%;justify-content:center;text-decoration:none;margin-bottom:10px;display:flex">💳 Pay with Stripe</a>
      <?php endif; ?>
      <div style="font-size:11px;color:var(--text-tertiary);text-align:center">🔒 Secured payment</div>
    </div>

    <div class="card" style="padding:18px">
      <div class="section-title" style="margin-bottom:12px">Other Actions</div>
      <button onclick="openModal('modifyModal')" class="btn btn-ghost" style="width:100%;justify-content:center;margin-bottom:8px">✏️ Modify Booking</button>
      <button onclick="openModal('cancelModal')" class="btn btn-ghost" style="width:100%;justify-content:center;color:var(--danger)">✕ Cancel Booking</button>
    </div>

    <!-- ── PAID (awaiting pickup) actions ── -->
    <?php elseif ($detail['status']==='paid'): ?>
    <div class="card" style="padding:22px">
      <div style="text-align:center;padding:10px 0 16px">
        <div style="font-size:36px;margin-bottom:10px">✅</div>
        <div style="font-weight:600;font-size:15px;margin-bottom:4px">Payment Received</div>
        <div style="font-size:13px;color:var(--text-secondary)">₵<?= number_format($detail['amount_paid'],2) ?> paid</div>
        <?php if ($detail['amount_paid'] < $detail['total_amount']): ?>
        <div style="margin-top:6px;font-size:12.5px;color:var(--warn)">Balance due: ₵<?= number_format($detail['total_amount']-$detail['amount_paid'],2) ?></div>
        <?php endif; ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php if ($latestPayment): ?><a href="<?= APP_URL ?>/api/receipts/generate.php?payment_id=<?= $latestPayment['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🧾 View Receipt</a><?php endif; ?>
        <button onclick="openModal('modifyModal')" class="btn btn-ghost" style="justify-content:center">✏️ Modify Booking</button>
        <?php if (($detail['amount_refunded'] ?? 0) < $detail['amount_paid']): ?>
        <button onclick="openModal('refundCancelModal')" class="btn btn-ghost" style="justify-content:center;color:var(--danger)">↩️ Request Refund & Cancel</button>
        <?php endif; ?>
      </div>
    </div>
    <?= $balanceCardHtml ?>

    <!-- ── ACTIVE (vehicle out) actions ── -->
    <?php elseif ($detail['status']==='active'): ?>
    <div class="card" style="padding:22px">
      <div style="text-align:center;margin-bottom:16px">
        <div style="font-size:32px;margin-bottom:8px">🚗</div>
        <div style="font-weight:600">Rental In Progress</div>
        <div style="font-size:12.5px;color:var(--text-secondary);margin-top:3px">Return by <?= format_date($detail['return_date'],'M d, Y') ?></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php if ($latestPayment): ?><a href="<?= APP_URL ?>/api/receipts/generate.php?payment_id=<?= $latestPayment['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🧾 View Receipt</a><?php endif; ?>
        <a href="<?= APP_URL ?>/api/agreement/generate.php?booking_id=<?= $detail['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">📋 View Agreement</a>
        <?php if ($pickupInspection && $pickupInspection['status']==='completed'): ?>
        <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $pickupInspection['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🔍 View Pickup Inspection</a>
        <?php endif; ?>
      </div>
    </div>
    <?= $balanceCardHtml ?>

    <!-- ── RETURNED actions ── -->
    <?php elseif ($detail['status']==='returned'): ?>
    <div class="card" style="padding:22px">
      <div style="text-align:center;margin-bottom:16px">
        <div style="font-size:32px;margin-bottom:8px">🔄</div>
        <div style="font-weight:600">Vehicle Returned</div>
        <div style="font-size:12.5px;color:var(--text-secondary);margin-top:3px">Inspection in progress</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php if ($latestPayment): ?><a href="<?= APP_URL ?>/api/receipts/generate.php?payment_id=<?= $latestPayment['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🧾 View Receipt</a><?php endif; ?>
        <a href="<?= APP_URL ?>/api/agreement/generate.php?booking_id=<?= $detail['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">📋 View Agreement</a>
        <?php if ($pickupInspection): ?><a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $pickupInspection['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🔍 Pickup Inspection</a><?php endif; ?>
        <?php if ($returnInspection): ?><a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $returnInspection['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🔍 Return Inspection</a><?php endif; ?>
        <?php if ($pickupInspection && $returnInspection && $pickupInspection['status']==='completed' && $returnInspection['status']==='completed'): ?><a href="<?= APP_URL ?>/customer/inspection-comparison.php?booking_id=<?= $detail['id'] ?>" class="btn btn-primary" style="justify-content:center;text-decoration:none">🔄 Compare Inspections</a><?php endif; ?>
      </div>
    </div>
    <?= $balanceCardHtml ?>

    <!-- ── COMPLETED actions ── -->
    <?php elseif ($detail['status']==='completed'): ?>
    <div class="card" style="padding:22px">
      <div style="text-align:center;margin-bottom:16px">
        <div style="font-size:32px;margin-bottom:8px">🏁</div>
        <div style="font-weight:600">Trip Completed</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php if ($latestPayment): ?><a href="<?= APP_URL ?>/api/receipts/generate.php?payment_id=<?= $latestPayment['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🧾 View Receipt</a><?php endif; ?>
        <a href="<?= APP_URL ?>/api/agreement/generate.php?booking_id=<?= $detail['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">📋 View Agreement</a>
        <?php if ($pickupInspection): ?><a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $pickupInspection['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🔍 Pickup Inspection</a><?php endif; ?>
        <?php if ($returnInspection): ?><a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $returnInspection['id'] ?>" target="_blank" class="btn btn-ghost" style="justify-content:center;text-decoration:none">🔍 Return Inspection</a><?php endif; ?>
        <?php if ($pickupInspection && $returnInspection && $pickupInspection['status']==='completed' && $returnInspection['status']==='completed'): ?><a href="<?= APP_URL ?>/customer/inspection-comparison.php?booking_id=<?= $detail['id'] ?>" class="btn btn-primary" style="justify-content:center;text-decoration:none">🔄 Compare Inspections</a><?php endif; ?>
        <?php if ($existingReview): ?>
        <a href="<?= APP_URL ?>/customer/review.php?booking_id=<?= $detail['id'] ?>" class="btn btn-ghost" style="justify-content:center;text-decoration:none"><?= render_stars((float)$existingReview['rating'], 13) ?> &nbsp;Your Review</a>
        <?php else: ?>
        <a href="<?= APP_URL ?>/customer/review.php?booking_id=<?= $detail['id'] ?>" class="btn btn-primary" style="justify-content:center;text-decoration:none">⭐ Leave a Review</a>
        <?php endif; ?>
      </div>
    </div>
    <?= $balanceCardHtml ?>

    <!-- ── CANCELLED ── -->
    <?php elseif ($detail['status']==='cancelled'): ?>
    <div class="card" style="padding:22px;text-align:center">
      <div style="font-size:32px;margin-bottom:10px">❌</div>
      <div style="font-weight:600;margin-bottom:4px">Booking Cancelled</div>
      <?php if ($detail['cancellation_reason']): ?><div style="font-size:12.5px;color:var(--text-secondary)"><?= e($detail['cancellation_reason']) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modify Modal -->
<div class="modal-overlay" id="modifyModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">✏️ Modify Booking</span><button class="modal-close" onclick="closeModal('modifyModal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="modify">
      <input type="hidden" name="booking_id" value="<?= $detail['id'] ?>">
      <div class="modal-body">
<div style="padding:10px 14px;background:rgba(245,158,11,.07);border-radius:var(--radius-sm);margin-bottom:16px;font-size:12.5px;color:#d97706">⚠️ Modify pickup/return dates, times, and pickup area. Vehicle cannot be changed.</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">New Pickup Date *</label><input class="form-input" type="date" name="new_pickup_date" id="modPickupDate" value="<?= e($detail['pickup_date']) ?>" min="<?= date('Y-m-d') ?>" required onchange="updatePreview()"></div>
          <div class="form-group"><label class="form-label">Pickup Time *</label><input class="form-input" type="time" name="new_pickup_time" id="modPickupTime" value="<?= e($detail['pickup_time'] ?? '09:00') ?>" required onchange="updatePreview()"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">New Return Date *</label><input class="form-input" type="date" name="new_return_date" id="modReturnDate" value="<?= e($detail['return_date']) ?>" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" required onchange="updatePreview()"></div>
          <div class="form-group"><label class="form-label">Return Time *</label><input class="form-input" type="time" name="new_return_time" id="modReturnTime" value="<?= e($detail['return_time'] ?? '17:00') ?>" required onchange="updatePreview()"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Pickup Area *</label>
            <select class="form-input" name="new_pickup_location" required onchange="updatePreview()">
              <option value="">Select pickup area...</option>
              <?php foreach ($pickupLocations as $loc): ?>
              <option value="<?= e($loc) ?>" <?= ($detail['pickup_location'] ?? '') === $loc ? 'selected' : '' ?>><?= e($loc) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" id="previewSection" style="background:var(--surface-alt);padding:16px;border-radius:var(--radius);border:1px solid var(--border)">
            <div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px">📊 New Booking Preview</div>
            <div id="daysPreview" style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span>Days:</span><span>—</span></div>
            <div style="display:flex;justify-content:space-between;font-weight:600;font-size:15px;border-top:1px solid var(--border);padding-top:8px">
              <span>Total:</span><span id="totalPreview" style="color:var(--accent)">₵0.00</span>
            </div>
            <div id="availStatus" style="margin-top:8px;font-size:12px;padding:6px 10px;border-radius:12px;display:none"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modifyModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Booking</button>
      </div>
    </form>

    <script>
    let vehicleDailyRate = <?= (float)($detail['vrate'] ?? $detail['daily_rate'] ?? 300) ?>;
    let bookingId = <?= (int)$detail['id'] ?>;

    function updatePreview() {
      const pickupDate = document.getElementById('modPickupDate')?.value;
      const pickupTime = document.getElementById('modPickupTime')?.value;
      const returnDate = document.getElementById('modReturnDate')?.value;
      const returnTime = document.getElementById('modReturnTime')?.value;

      if (!pickupDate || !pickupTime || !returnDate || !returnTime) {
        document.getElementById('previewSection').style.opacity = '0.5';
        return;
      }

      const pickupDT = new Date(pickupDate + 'T' + pickupTime);
      const returnDT = new Date(returnDate + 'T' + returnTime);

      const statusEl = document.getElementById('availStatus');
      if (pickupDT >= returnDT) {
        statusEl.textContent = '❌ Pickup after return';
        statusEl.style.background = 'rgba(239,68,68,.1)';
        statusEl.style.color = 'var(--danger)';
        statusEl.style.display = 'block';
        document.getElementById('totalPreview').textContent = 'Invalid';
        return;
      }

      const msDiff = returnDT.getTime() - pickupDT.getTime();
      const days = Math.ceil(msDiff / (1000 * 60 * 60 * 24));
      document.querySelector('#daysPreview span:last-child').textContent = days + ' days';

      const subtotal = (vehicleDailyRate * days).toFixed(2);
      const taxRate = <?= (float)get_setting('tax_rate', '0') ?>;
      const svcRate = <?= (float)get_setting('service_charge_rate', '0') ?>;
      const tax = parseFloat(subtotal) * (taxRate / 100);
      const svc = parseFloat(subtotal) * (svcRate / 100);
      const total = parseFloat(subtotal) + tax + svc;
      document.getElementById('totalPreview').textContent = '₵' + total.toFixed(2);

      // Live availability check
      fetch(`<?= APP_URL ?>/api/vehicles/check-availability.php?vid=<?= $detail['vehicle_id'] ?>&pickup=${encodeURIComponent(pickupDate)}&return=${encodeURIComponent(returnDate)}&exclude=${bookingId}`)
        .then(r => r.json())
        .then(data => {
          if (data.available) {
            statusEl.textContent = '✅ Vehicle available';
            statusEl.style.background = 'rgba(34,197,94,.1)';
            statusEl.style.color = 'var(--success)';
          } else {
            statusEl.textContent = '⚠️ Vehicle not available';
            statusEl.style.background = 'rgba(245,158,11,.1)';
            statusEl.style.color = '#d97706';
          }
          statusEl.style.display = 'block';
          document.getElementById('previewSection').style.opacity = '1';
        }).catch(err => {
          statusEl.textContent = 'ℹ️ Check server';
          statusEl.style.background = 'rgba(156,163,175,.2)';
          statusEl.style.color = 'var(--text-secondary)';
          statusEl.style.display = 'block';
          document.getElementById('previewSection').style.opacity = '1';
        });
    }

    document.querySelector('#modifyModal form').addEventListener('submit', function(e) {
      const pickupDate = document.getElementById('modPickupDate').value;
      const pickupTime = document.getElementById('modPickupTime').value;
      const returnDate = document.getElementById('modReturnDate').value;
      const returnTime = document.getElementById('modReturnTime').value;
      const pickupDT = new Date(pickupDate + 'T' + pickupTime);
      const returnDT = new Date(returnDate + 'T' + returnTime);
      if (pickupDT >= returnDT) {
        e.preventDefault();
        alert('Pickup must be before return date/time.');
        return false;
      }
    });

    // Init preview
    setTimeout(updatePreview, 500);
    </script>
  </div>
</div>

<!-- Cancel Modal (no payment yet) -->
<div class="modal-overlay" id="cancelModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">Cancel Booking</span><button class="modal-close" onclick="closeModal('cancelModal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" name="booking_id" value="<?= $detail['id'] ?>">
      <div class="modal-body">
        <div style="padding:12px;background:rgba(239,68,68,.06);border-radius:var(--radius-sm);margin-bottom:14px;font-size:12.5px;color:var(--danger)">⚠️ This cannot be undone. Since no payment has been made, cancellation is free.</div>
        <div class="form-group"><label class="form-label">Reason (optional)</label><textarea class="form-input" name="reason" rows="3" placeholder="Why are you cancelling?"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('cancelModal')">Keep Booking</button>
        <button type="submit" class="btn btn-danger">Cancel Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- Refund + Cancel Modal (paid) -->
<div class="modal-overlay" id="refundCancelModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">↩️ Request Refund & Cancel</span><button class="modal-close" onclick="closeModal('refundCancelModal')">✕</button></div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="refund_cancel">
      <input type="hidden" name="booking_id" value="<?= $detail['id'] ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Refund Amount (₵) *</label>
          <input class="form-input" type="number" name="refund_amount" id="refundAmt" step="0.01" min="1" max="<?= $detail['amount_paid'] ?>" value="" required>
          <div class="form-hint" style="font-size:12px;color:var(--text-secondary);margin-top:4px">
            Min: ₵1.00 | Max: <strong>₵<?= number_format($detail['amount_paid'],2) ?></strong> (of ₵<?= number_format($detail['amount_paid'],2) ?> paid)
          </div>
          <div id="refundError" style="display:none;font-size:12px;color:var(--danger);margin-top:4px;padding:6px;background:rgba(239,68,68,.07);border-radius:var(--radius-sm)">Amount exceeds maximum allowed.</div>
        </div>
        <div style="padding:12px;background:rgba(245,158,11,.07);border-radius:var(--radius-sm);margin-bottom:14px;font-size:12.5px;color:#d97706">
          ℹ️ Refund requests are reviewed within 2–3 business days. Full refund will also cancel booking.
        </div>
        <div class="form-group"><label class="form-label">Reason *</label><textarea class="form-input" name="reason" rows="3" placeholder="Why are you requesting a refund?" required></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('refundCancelModal')">Keep Booking</button>
        <button type="submit" class="btn btn-danger" id="refundSubmitBtn">Submit Refund Request</button>
      </div>
    </form>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const refundInput = document.getElementById('refundAmt');
        const refundError = document.getElementById('refundError');
        const refundSubmitBtn = document.getElementById('refundSubmitBtn');
        const maxAllowed = parseFloat(refundInput.max);

        if (refundInput) {
          refundInput.addEventListener('input', function() {
            const val = parseFloat(this.value) || 0;
            if (val < 1 || val > maxAllowed) {
              this.style.borderColor = 'var(--danger)';
              refundError.style.display = 'block';
              refundSubmitBtn.disabled = true;
            } else {
              this.style.borderColor = '';
              refundError.style.display = 'none';
              refundSubmitBtn.disabled = false;
            }
          });
        }
      });
    </script>
    </form>
  </div>
</div>

<script>
function payWithPaystack() {
  const amount = parseFloat(document.getElementById('payAmtSlider').value);
  PaystackPop.setup({
    key: '<?= PAYSTACK_PUBLIC_KEY ?>',
    email: '<?= e(Auth::email()) ?>',
    amount: Math.round(amount * 100),
    currency: 'GHS',
    ref: '<?= $detail['booking_ref'] ?>_' + Date.now(),
    metadata: { booking_id: <?= $detail['id'] ?>, booking_ref: '<?= $detail['booking_ref'] ?>' },
    onClose: () => showToast('Payment window closed','error'),
    callback: r => { window.location = '<?= APP_URL ?>/api/payments/paystack-verify.php?reference='+r.reference+'&booking_id=<?= $detail['id'] ?>'; }
  }).openIframe();
}

function payBalanceWithPaystack() {
  const amount = parseFloat(document.getElementById('balPayAmtSlider').value);
  PaystackPop.setup({
    key: '<?= PAYSTACK_PUBLIC_KEY ?>',
    email: '<?= e(Auth::email()) ?>',
    amount: Math.round(amount * 100),
    currency: 'GHS',
    ref: '<?= $detail['booking_ref'] ?>_' + Date.now(),
    metadata: { booking_id: <?= $detail['id'] ?>, booking_ref: '<?= $detail['booking_ref'] ?>' },
    onClose: () => showToast('Payment window closed','error'),
    callback: r => { window.location = '<?= APP_URL ?>/api/payments/paystack-verify.php?reference='+r.reference+'&booking_id=<?= $detail['id'] ?>'; }
  }).openIframe();
}
</script>
<script src="https://js.paystack.co/v1/inline.js"></script>

<?php else: ?>
<!-- ─── BOOKING LIST ─────────────────────────────────────────────────── -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach ([''=>'All','booked'=>'Booked','paid'=>'Paid','active'=>'Active','returned'=>'Returned','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$label): ?>
  <a href="?status=<?= $k ?>" class="chip <?= $status===$k?'active':'' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Ref</th><th>Vehicle</th><th>Pickup</th><th>Return</th><th>Days</th><th>Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($bookings)): ?>
        <tr><td colspan="9">
          <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">No bookings yet</div>
            <div class="empty-state-desc"><a href="<?= APP_URL ?>/customer/browse.php" style="color:var(--accent);text-decoration:none">Browse vehicles to make your first booking →</a></div>
          </div>
        </td></tr>
        <?php else: foreach ($bookings as $b): ?>
        <tr>
          <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($b['booking_ref']) ?></span></td>
          <td>
            <div>
              <div style="font-weight:500;font-size:13px"><?= e($b['make'].' '.$b['model']) ?></div>
              <div style="font-size:11px;font-family:var(--mono);color:var(--text-tertiary)"><?= e($b['registration_number']) ?></div>
            </div>
          </td>
          <td style="font-size:12px"><?= format_date($b['pickup_date']) ?></td>
          <td style="font-size:12px"><?= format_date($b['return_date']) ?></td>
          <td style="text-align:center"><?= $b['total_days'] ?></td>
          <td style="font-weight:600">₵<?= number_format($b['total_amount'],2) ?></td>
          <td><?= status_badge($b['status']) ?></td>
          <td>
            <?php if ($b['payment_status']==='unpaid'): ?>
            <span class="badge badge-returned">Unpaid</span>
            <?php elseif ($b['payment_status']==='paid'): ?>
            <span class="badge badge-active">Paid</span>
            <?php elseif ($b['payment_status']==='partial'): ?>
            <span class="badge badge-paid">Partial</span>
            <?php else: ?>
            <span class="badge badge-returned"><?= ucfirst($b['payment_status']) ?></span>
            <?php endif; ?>
          </td>
          <td><a href="?id=<?= $b['id'] ?>" class="action-btn primary">View →</a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
