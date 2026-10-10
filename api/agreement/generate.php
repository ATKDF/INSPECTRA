<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) { http_response_code(404); echo 'Not found'; exit; }

$booking = DB::fetchOne("SELECT b.*, v.make, v.model, v.registration_number, v.year, v.category, v.daily_rate, cu.first_name, cu.last_name, cu.email, cu.phone, cu.id as cust_id, ag.first_name as agent_fname, ag.last_name as agent_lname FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id JOIN users cu ON b.customer_id=cu.id LEFT JOIN users ag ON b.agent_id=ag.id WHERE b.id=?", [$bookingId]);
if (!$booking) { http_response_code(404); echo 'Booking not found'; exit; }

// Access control
if (Auth::role()==='customer' && $booking['cust_id']!=Auth::id()) { http_response_code(403); echo 'Forbidden'; exit; }

$pickupInspection = DB::fetchOne("SELECT i.*, (SELECT COUNT(*) FROM damage_reports WHERE inspection_id=i.id) as damages FROM inspections i WHERE i.booking_id=? AND i.type='pickup'", [$bookingId]);
$agentSig  = $pickupInspection ? DB::fetchOne("SELECT * FROM signatures WHERE inspection_id=? AND role='agent'",  [$pickupInspection['id']]) : null;
$custSig   = $pickupInspection ? DB::fetchOne("SELECT * FROM signatures WHERE inspection_id=? AND role='customer'",[$pickupInspection['id']]) : null;
$damages   = $pickupInspection ? DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=?",[$pickupInspection['id']]) : [];
$company   = ['name'=>get_setting('company_name','Inspectra Rentals'),'email'=>get_setting('company_email','hello@inspectra.com'),'phone'=>get_setting('company_phone','+233 30 000 0000')];
$agentRef  = APP_URL . '/api/agreement/generate.php?booking_id=' . $bookingId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rental Agreement — <?= e($booking['booking_ref']) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:#f8f9fc;color:#0f1117;-webkit-font-smoothing:antialiased;}
.no-print{background:#fff;border-bottom:1px solid rgba(0,0,0,.08);padding:12px 24px;display:flex;gap:10px;align-items:center;position:sticky;top:0;z-index:10;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;text-decoration:none;}
.doc{max-width:780px;margin:32px auto 60px;background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.08);overflow:hidden;}
.doc-header{padding:40px 48px 32px;border-bottom:3px solid #0f1117;}
.doc-title{font-size:26px;font-weight:700;letter-spacing:-.6px;margin-bottom:4px;}
.doc-subtitle{font-size:13px;color:#6b7280;margin-bottom:20px;}
.doc-meta{display:flex;gap:32px;flex-wrap:wrap;}
.meta-item .label{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:#9ca3af;margin-bottom:2px;}
.meta-item .val{font-size:14px;font-weight:600;}
.doc-body{padding:36px 48px;}
h2{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#374151;border-bottom:1.5px solid #e5e7eb;padding-bottom:8px;margin:28px 0 14px;}
h2:first-child{margin-top:0;}
p{font-size:13.5px;line-height:1.75;margin-bottom:10px;color:#374151;}
.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:4px;}
.info-cell{background:#f8f9fc;border-radius:8px;padding:12px 14px;}
.info-cell .lbl{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;margin-bottom:3px;}
.info-cell .v{font-size:13.5px;font-weight:500;}
.clause{background:#f8f9fc;border-left:3px solid #3b6ef8;padding:12px 16px;border-radius:0 6px 6px 0;margin-bottom:10px;font-size:13px;line-height:1.65;}
.clause strong{color:#1a1f36;}
.sig-block{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:20px;}
.sig-box{border:1.5px solid #e5e7eb;border-radius:10px;padding:20px;text-align:center;}
.sig-img{max-height:80px;max-width:100%;margin:10px auto;display:block;border:1px solid #e5e7eb;border-radius:6px;background:#fff;padding:4px;}
.sig-name{font-size:14px;font-weight:600;margin-bottom:3px;}
.sig-role{font-size:11.5px;color:#9ca3af;}
.sig-date{font-size:11px;color:#6b7280;margin-top:4px;}
.damage-table{width:100%;border-collapse:collapse;font-size:13px;}
.damage-table th{text-align:left;padding:9px 12px;background:#f8f9fc;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;color:#9ca3af;border-bottom:1px solid #e5e7eb;}
.damage-table td{padding:10px 12px;border-bottom:1px solid #f3f4f6;}
.doc-footer{padding:20px 48px;background:#f8f9fc;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;font-size:11.5px;color:#9ca3af;}
@media print{.no-print{display:none!important;}.doc{margin:0;border-radius:0;box-shadow:none;}}
</style>
</head>
<body>
<div class="no-print">
  <strong>Rental Agreement — <?= e($booking['booking_ref']) ?></strong>
  <span style="flex:1"></span>
  <button class="btn" style="background:#1a1f36;color:#fff" onclick="window.print()">🖨️ Print / Save PDF</button>
  <a href="javascript:history.back()" class="btn" style="background:rgba(0,0,0,.06);color:#374151">← Back</a>
</div>

<div class="doc">
  <div class="doc-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
      <div>
        <div class="doc-title">Vehicle Rental Agreement</div>
        <div class="doc-subtitle"><?= e($company['name']) ?> · <?= e($company['email']) ?> · <?= e($company['phone']) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:14px;font-weight:700;font-family:monospace"><?= e($booking['booking_ref']) ?></div>
        <div style="font-size:12px;color:#9ca3af;margin-top:3px">Generated <?= date('d M Y, H:i') ?></div>
      </div>
    </div>
    <div class="doc-meta" style="margin-top:20px">
      <div class="meta-item"><div class="label">Customer</div><div class="val"><?= e($booking['first_name'].' '.$booking['last_name']) ?></div></div>
      <div class="meta-item"><div class="label">Vehicle</div><div class="val"><?= e($booking['year'].' '.$booking['make'].' '.$booking['model']) ?></div></div>
      <div class="meta-item"><div class="label">Registration</div><div class="val" style="font-family:monospace"><?= e($booking['registration_number']) ?></div></div>
      <div class="meta-item"><div class="label">Pickup Date</div><div class="val"><?= format_date($booking['pickup_date'],'d M Y') ?> <?= e($booking['pickup_time']??'') ?></div></div>
      <div class="meta-item"><div class="label">Return Date</div><div class="val"><?= format_date($booking['return_date'],'d M Y') ?> <?= e($booking['return_time']??'') ?></div></div>
      <div class="meta-item"><div class="label">Duration</div><div class="val"><?= $booking['total_days'] ?> Day<?= $booking['total_days']>1?'s':'' ?></div></div>
    </div>
  </div>

  <div class="doc-body">
    <h2>1. Parties to the Agreement</h2>
    <div class="info-grid">
      <div class="info-cell"><div class="lbl">Renter (Customer)</div><div class="v"><?= e($booking['first_name'].' '.$booking['last_name']) ?></div></div>
      <div class="info-cell"><div class="lbl">Email</div><div class="v"><?= e($booking['email']) ?></div></div>
      <div class="info-cell"><div class="lbl">Phone</div><div class="v"><?= e($booking['phone']??'—') ?></div></div>
      <?php if($booking['id_document_type']??''): ?>
      <div class="info-cell"><div class="lbl">ID Type</div><div class="v"><?= e($booking['id_document_type']) ?></div></div>
      <div class="info-cell"><div class="lbl">ID Number</div><div class="v"><?= e($booking['id_document_number']??'') ?></div></div>
      <?php endif; ?>
      <div class="info-cell"><div class="lbl">Rental Company</div><div class="v"><?= e($company['name']) ?></div></div>
    </div>

    <h2>2. Vehicle Details</h2>
    <div class="info-grid">
      <div class="info-cell"><div class="lbl">Vehicle</div><div class="v"><?= e($booking['year'].' '.$booking['make'].' '.$booking['model']) ?></div></div>
      <div class="info-cell"><div class="lbl">Registration</div><div class="v" style="font-family:monospace"><?= e($booking['registration_number']) ?></div></div>
      <div class="info-cell"><div class="lbl">Category</div><div class="v"><?= ucfirst($booking['category']??'') ?></div></div>
    </div>

    <h2>3. Rental Period & Location</h2>
    <div class="info-grid">
      <div class="info-cell"><div class="lbl">Pickup</div><div class="v"><?= format_date($booking['pickup_date'],'d M Y') ?> <?= e($booking['pickup_time']??'') ?></div></div>
      <div class="info-cell"><div class="lbl">Return</div><div class="v"><?= format_date($booking['return_date'],'d M Y') ?> <?= e($booking['return_time']??'') ?></div></div>
      <div class="info-cell"><div class="lbl">Pickup Area</div><div class="v"><?= e($booking['pickup_location']??'—') ?></div></div>
      <?php if($booking['pickup_city']??''): ?><div class="info-cell"><div class="lbl">City</div><div class="v"><?= e($booking['pickup_city']) ?></div></div><?php endif; ?>
      <?php if($booking['pickup_street']??''): ?><div class="info-cell"><div class="lbl">Street</div><div class="v"><?= e($booking['pickup_street']) ?></div></div><?php endif; ?>
      <?php if($booking['pickup_gps']??''): ?><div class="info-cell"><div class="lbl">GPS</div><div class="v"><?= e($booking['pickup_gps']) ?></div></div><?php endif; ?>
    </div>

    <h2>4. Financial Terms</h2>
    <div class="info-grid">
      <div class="info-cell"><div class="lbl">Daily Rate</div><div class="v">₵<?= number_format($booking['daily_rate'],2) ?></div></div>
      <div class="info-cell"><div class="lbl">Subtotal (<?= $booking['total_days'] ?>d)</div><div class="v">₵<?= number_format($booking['subtotal'],2) ?></div></div>
      <?php if(($booking['tax_amount']??0)>0): ?><div class="info-cell"><div class="lbl">Tax</div><div class="v">₵<?= number_format($booking['tax_amount'],2) ?></div></div><?php endif; ?>
      <?php if(($booking['service_charge']??0)>0): ?><div class="info-cell"><div class="lbl">Service Charge</div><div class="v">₵<?= number_format($booking['service_charge'],2) ?></div></div><?php endif; ?>
      <div class="info-cell" style="border:1.5px solid #3b6ef8"><div class="lbl">Total Amount</div><div class="v" style="color:#3b6ef8">₵<?= number_format($booking['total_amount'],2) ?></div></div>
      <div class="info-cell"><div class="lbl">Amount Paid</div><div class="v" style="color:#059669">₵<?= number_format($booking['amount_paid'],2) ?></div></div>
    </div>

    <h2>5. Vehicle Condition at Pickup</h2>
    <?php if($pickupInspection): ?>
    <div class="info-grid" style="margin-bottom:16px">
      <div class="info-cell"><div class="lbl">Overall Condition</div><div class="v"><?= ucfirst($pickupInspection['overall_condition']??'Not assessed') ?></div></div>
      <div class="info-cell"><div class="lbl">Fuel Level</div><div class="v"><?= ucfirst(str_replace('_',' ',$pickupInspection['fuel_level']??'—')) ?></div></div>
      <div class="info-cell"><div class="lbl">Mileage</div><div class="v"><?= $pickupInspection['mileage_at_inspection']?number_format($pickupInspection['mileage_at_inspection']).' km':'—' ?></div></div>
    </div>
    <?php if(empty($damages)): ?>
    <p style="color:#059669;font-weight:500">✅ No pre-existing damages recorded at time of pickup.</p>
    <?php else: ?>
    <p>The following pre-existing damages were documented at pickup:</p>
    <table class="damage-table">
      <thead><tr><th>Zone</th><th>Type</th><th>Severity</th><th>Description</th></tr></thead>
      <tbody>
        <?php foreach($damages as $d): ?>
        <tr><td><?= e(ucfirst(str_replace('_',' ',$d['zone']))) ?></td><td><?= e(ucfirst($d['damage_type'])) ?></td><td><?= e(ucfirst($d['severity'])) ?></td><td><?= e($d['description']??'—') ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <?php else: ?>
    <p style="color:#9ca3af">Pickup inspection not yet completed.</p>
    <?php endif; ?>

    <h2>6. Terms & Conditions</h2>
    <?php
    $clauses = [
      'The renter agrees to return the vehicle in the same condition as received, subject to normal wear and tear.',
      'The renter is responsible for any damages caused during the rental period that are not pre-documented in the pickup inspection.',
      'The vehicle must be returned by the agreed return date and time. Late returns may incur additional daily charges.',
      'The renter must possess a valid driving licence for the vehicle category at all times during the rental.',
      'Fuel policy: the vehicle must be returned with the same fuel level as at pickup.',
      'The renter agrees not to use the vehicle for any illegal purposes, or to allow any unauthorised driver to operate the vehicle.',
      'The rental company reserves the right to inspect the vehicle at any reasonable time during the rental period.',
      'Any disputes will be resolved using the photographic and digital evidence captured during the inspection process.',
    ];
    foreach($clauses as $i=>$c): ?>
    <div class="clause"><strong><?= $i+1 ?>.</strong> <?= $c ?></div>
    <?php endforeach; ?>
    <?php if($booking['special_requests']??''): ?><p><strong>Special Conditions:</strong> <?= e($booking['special_requests']) ?></p><?php endif; ?>

    <h2>7. Signatures</h2>
    <p>By signing below, both parties confirm they have read, understood and agree to the terms of this rental agreement and that the vehicle condition stated above is accurate.</p>
    <div class="sig-block">
      <div class="sig-box">
        <div class="sig-name"><?= e($booking['first_name'].' '.$booking['last_name']) ?></div>
        <div class="sig-role">Customer / Renter</div>
        <?php if($custSig): ?>
          <img src="<?= e($custSig['signature_url']) ?>" class="sig-img" alt="Customer signature">
          <div class="sig-date">Signed: <?= format_datetime($custSig['signed_at']) ?></div>
        <?php else: ?>
          <div style="height:60px;border:1.5px dashed #e5e7eb;border-radius:6px;margin:12px 0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#9ca3af">Signature pending</div>
        <?php endif; ?>
      </div>
      <div class="sig-box">
        <div class="sig-name"><?= $booking['agent_fname'] ? e($booking['agent_fname'].' '.$booking['agent_lname']) : e($company['name']) ?></div>
        <div class="sig-role">Rental Agent / Company</div>
        <?php if($agentSig): ?>
          <img src="<?= e($agentSig['signature_url']) ?>" class="sig-img" alt="Agent signature">
          <div class="sig-date">Signed: <?= format_datetime($agentSig['signed_at']) ?></div>
        <?php else: ?>
          <div style="height:60px;border:1.5px dashed #e5e7eb;border-radius:6px;margin:12px 0;display:flex;align-items:center;justify-content:center;font-size:12px;color:#9ca3af">Signature pending</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="doc-footer">
    <span><?= e($company['name']) ?> · Booking <?= e($booking['booking_ref']) ?> · Generated <?= date('d M Y, H:i') ?></span>
    <span>Document Hash: <?= substr(hash('sha256',$booking['booking_ref'].$booking['total_amount'].$booking['pickup_date']),0,16) ?>…</span>
  </div>
</div>
</body>
</html>
