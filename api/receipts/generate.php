<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$paymentId  = (int)($_GET['payment_id'] ?? 0);
$bookingId  = (int)($_GET['booking_id'] ?? 0);

if ($paymentId) {
    $payment = DB::fetchOne("SELECT p.*, b.booking_ref, b.pickup_date, b.return_date, b.total_days, b.daily_rate, b.subtotal, b.tax_amount, b.service_charge, b.total_amount, b.initial_deposit, b.amount_paid, b.amount_refunded, b.customer_id, b.id AS bid, v.make, v.model, v.registration_number, v.year, cu.first_name, cu.last_name, cu.email, cu.phone FROM payments p JOIN bookings b ON p.booking_id=b.id JOIN vehicles v ON b.vehicle_id=v.id JOIN users cu ON b.customer_id=cu.id WHERE p.id=?", [$paymentId]);
} elseif ($bookingId) {
    // Latest completed payment for this booking
    $payment = DB::fetchOne("SELECT p.*, b.booking_ref, b.pickup_date, b.return_date, b.total_days, b.daily_rate, b.subtotal, b.tax_amount, b.service_charge, b.total_amount, b.initial_deposit, b.amount_paid, b.amount_refunded, b.customer_id, b.id AS bid, v.make, v.model, v.registration_number, v.year, cu.first_name, cu.last_name, cu.email, cu.phone FROM payments p JOIN bookings b ON p.booking_id=b.id JOIN vehicles v ON b.vehicle_id=v.id JOIN users cu ON b.customer_id=cu.id WHERE p.booking_id=? AND p.status='completed' ORDER BY p.paid_at DESC LIMIT 1", [$bookingId]);
}

if (!$payment) { http_response_code(404); echo 'Receipt not found.'; exit; }

// Access control: customer can only see their own receipts
if (Auth::role() === 'customer' && $payment['customer_id'] != Auth::id()) { http_response_code(403); echo 'Forbidden'; exit; }

$company = ['name' => get_setting('company_name','Inspectra Rentals'), 'email' => get_setting('company_email','hello@inspectra.com'), 'phone' => get_setting('company_phone','+233 30 000 0000')];
$isRefund = $payment['payment_type'] === 'refund';
$receiptNum = 'RCP-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $isRefund?'Refund Receipt':'Payment Receipt' ?> — <?= e($receiptNum) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=DM+Mono:wght@400&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:#f0f4ff;color:#0f1117;-webkit-font-smoothing:antialiased;}
.no-print{background:#fff;border-bottom:1px solid rgba(0,0,0,.08);padding:12px 24px;display:flex;gap:10px;align-items:center;position:sticky;top:0;z-index:10;}
.no-print .btn{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;}
.receipt{max-width:640px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.1);}
.receipt-header{background:<?= $isRefund?'linear-gradient(135deg,#dc2626,#ef4444)':'linear-gradient(135deg,#1a1f36,#2d3561)' ?>;color:#fff;padding:36px 40px;}
.rh-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;}
.company-name{font-size:20px;font-weight:700;letter-spacing:-.4px;}
.receipt-type{font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:.65;margin-top:3px;}
.receipt-num{font-size:22px;font-weight:700;font-family:'DM Mono',monospace;text-align:right;}
.receipt-date{font-size:12px;opacity:.65;margin-top:3px;text-align:right;}
.amount-hero{text-align:center;padding:12px 0;}
.amount-label{font-size:12px;text-transform:uppercase;letter-spacing:.8px;opacity:.65;margin-bottom:6px;}
.amount-value{font-size:48px;font-weight:700;letter-spacing:-1.5px;}
.receipt-body{padding:32px 40px;}
.section{margin-bottom:24px;}
.section-title{font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:#9ca3af;margin-bottom:12px;}
.row{display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid rgba(0,0,0,.05);font-size:13px;}
.row:last-child{border-bottom:none;}
.row-label{color:#6b7280;}
.row-val{font-weight:500;}
.total-row{display:flex;justify-content:space-between;align-items:center;padding:13px 0;font-size:16px;font-weight:700;border-top:2px solid #e5e7eb;margin-top:4px;}
.total-val{color:<?= $isRefund?'#dc2626':'#3b6ef8' ?>;}
.status-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:30px;font-size:12px;font-weight:600;}
.status-ok{background:rgba(16,185,129,.1);color:#059669;}
.status-refund{background:rgba(239,68,68,.1);color:#dc2626;}
.receipt-footer{padding:20px 40px;background:#f8f9fc;border-top:1px solid #e5e7eb;text-align:center;font-size:12px;color:#9ca3af;}
.qr-placeholder{width:80px;height:80px;background:#f0f4ff;border:1px solid #e5e7eb;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:10px;color:#9ca3af;text-align:center;margin:0 auto 12px;}
@media print{.no-print{display:none!important;}.receipt{margin:0;border-radius:0;box-shadow:none;}}
</style>
</head>
<body>
<div class="no-print">
  <strong style="font-size:14px"><?= $isRefund?'Refund Receipt':'Payment Receipt' ?></strong>
  <span style="flex:1"></span>
  <button class="btn" style="background:#3b6ef8;color:#fff" onclick="window.print()">🖨️ Print / Save PDF</button>
  <a href="javascript:history.back()" class="btn" style="background:rgba(0,0,0,.06);color:#374151">← Back</a>
</div>

<div class="receipt">
  <div class="receipt-header">
    <div class="rh-top">
      <div>
        <div class="company-name"><?= e($company['name']) ?></div>
        <div class="receipt-type"><?= $isRefund?'Refund Receipt':'Official Payment Receipt' ?></div>
      </div>
      <div>
        <div class="receipt-num"><?= e($receiptNum) ?></div>
        <div class="receipt-date"><?= format_datetime($payment['paid_at']??$payment['created_at'],'d M Y, H:i') ?> WAT</div>
      </div>
    </div>
    <div class="amount-hero">
      <div class="amount-label"><?= $isRefund?'Amount Refunded':'Amount Paid' ?></div>
      <div class="amount-value"><?= $payment['currency'] === 'USD' ? format_amount($payment['amount'], 'USD') : '₵' . number_format($payment['amount'],2) ?></div>
        <?php if($payment['currency'] === 'USD' && $payment['ghs_equivalent'] > 0): ?>
          <div style="font-size:16px;font-weight:500;opacity:0.9;margin-top:4px">≈ ₵<?= number_format($payment['ghs_equivalent'],2) ?></div>
        <?php endif; ?>
      <div style="margin-top:12px">
        <span class="status-pill <?= $payment['status']==='completed'||$payment['status']==='refunded'?'status-ok':'status-refund' ?>">
          <?= $payment['status']==='completed'||$payment['status']==='refunded'?'✓':'●' ?> <?= ucfirst($payment['status']) ?>
        </span>
      </div>
    </div>
  </div>

  <div class="receipt-body">
    <!-- Parties -->
    <div class="section">
      <div class="section-title">Customer</div>
      <div class="row"><span class="row-label">Name</span><span class="row-val"><?= e($payment['first_name'].' '.$payment['last_name']) ?></span></div>
      <div class="row"><span class="row-label">Email</span><span class="row-val"><?= e($payment['email']) ?></span></div>
      <?php if($payment['phone']): ?><div class="row"><span class="row-label">Phone</span><span class="row-val"><?= e($payment['phone']) ?></span></div><?php endif; ?>
    </div>

    <!-- Rental details -->
    <div class="section">
      <div class="section-title">Rental Details</div>
      <div class="row"><span class="row-label">Booking Ref</span><span class="row-val" style="font-family:'DM Mono',monospace;font-size:12.5px"><?= e($payment['booking_ref']) ?></span></div>
      <div class="row"><span class="row-label">Vehicle</span><span class="row-val"><?= e($payment['year'].' '.$payment['make'].' '.$payment['model']) ?> · <?= e($payment['registration_number']) ?></span></div>
      <div class="row"><span class="row-label">Period</span><span class="row-val"><?= format_date($payment['pickup_date'],'M d') ?> → <?= format_date($payment['return_date'],'M d, Y') ?> (<?= $payment['total_days'] ?> days)</span></div>
    </div>

    <!-- Cost breakdown -->
    <div class="section">
      <div class="section-title">Cost Breakdown</div>
      <div class="row"><span class="row-label">Daily Rate × <?= $payment['total_days'] ?> days</span><span class="row-val">₵<?= number_format($payment['subtotal'],2) ?></span></div>
      <?php if($payment['tax_amount']>0): ?><div class="row"><span class="row-label">Tax</span><span class="row-val">₵<?= number_format($payment['tax_amount'],2) ?></span></div><?php endif; ?>
      <?php if(($payment['service_charge']??0)>0): ?><div class="row"><span class="row-label">Service Charge</span><span class="row-val">₵<?= number_format($payment['service_charge'],2) ?></span></div><?php endif; ?>
      <div class="total-row"><span>Total Booking Amount</span><span class="total-val">₵<?= number_format($payment['total_amount'],2) ?></span></div>
      <div class="row" style="padding:6px 0"><span class="row-label">Total Paid</span><span class="row-val" style="color:#059669;font-weight:600">₵<?= number_format($payment['amount_paid'],2) ?></span></div>
      <?php if($payment['amount_refunded']>0): ?><div class="row" style="padding:6px 0"><span class="row-label">Total Refunded</span><span class="row-val" style="color:#dc2626">₵<?= number_format($payment['amount_refunded'],2) ?></span></div><?php endif; ?>
    </div>

    <!-- Payment info -->
    <div class="section">
      <div class="section-title">Payment Information</div>
      <div class="row"><span class="row-label">This Payment</span><span class="row-val" style="font-weight:700;color:var(--accent)">
        <?= $payment['currency'] === 'USD' ? format_amount($payment['amount'], 'USD') : '₵' . number_format($payment['amount'],2) ?>
        <?php if($payment['currency'] === 'USD' && $payment['ghs_equivalent'] > 0): ?> (≈₵<?= number_format($payment['ghs_equivalent'],2) ?>)<?php endif; ?>
      </span></div>
      <div class="row"><span class="row-label">Method</span><span class="row-val"><?= ucfirst($payment['payment_method']) ?></span></div>
      <div class="row"><span class="row-label">Type</span><span class="row-val"><?= ucfirst($payment['payment_type']) ?></span></div>
      <?php if($payment['provider_reference']): ?><div class="row"><span class="row-label">Transaction Ref</span><span class="row-val" style="font-family:'DM Mono',monospace;font-size:11.5px"><?= e($payment['provider_reference']) ?></span></div><?php endif; ?>
      <div class="row"><span class="row-label">Date & Time</span><span class="row-val"><?= format_datetime($payment['paid_at']??$payment['created_at'],'d M Y, H:i:s') ?></span></div>
    </div>
  </div>

  <div class="receipt-footer">
    <div><?= e($company['name']) ?> · <?= e($company['email']) ?> · <?= e($company['phone']) ?></div>
    <div style="margin-top:6px">This is an official receipt. Please keep for your records.</div>
  </div>
</div>
</body>
</html>
