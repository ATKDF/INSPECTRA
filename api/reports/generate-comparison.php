<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$status = get_comparison_status($bookingId);
$booking = $status['booking'];
if (!$booking) { http_response_code(404); echo 'Not found'; exit; }

// Check access
if (Auth::role() === 'customer' && $booking['customer_id'] !== Auth::id()) {
    http_response_code(403); echo 'Forbidden'; exit;
}

if (!$status['ready']) {
    http_response_code(409);
    echo 'This comparison report is not available yet — both the pickup and return inspections must be completed and acknowledged first.';
    exit;
}

$pickupInsp = $status['pickup'];
$dropoffInsp = $status['return'];

$pickupPhotos = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=?", [$pickupInsp['id']]);
$dropoffPhotos = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=?", [$dropoffInsp['id']]);
$pPhotoByType = []; foreach ($pickupPhotos as $p) $pPhotoByType[$p['photo_type']] = $p;
$rPhotoByType = []; foreach ($dropoffPhotos as $p) $rPhotoByType[$p['photo_type']] = $p;
$photoZones = ['front'=>'Front','rear'=>'Rear','left'=>'Left Side','right'=>'Right Side','interior'=>'Interior','dashboard'=>'Dashboard'];

$pickupDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=? ORDER BY zone", [$pickupInsp['id']]);
$dropoffDamages = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=? ORDER BY zone", [$dropoffInsp['id']]);

$comparison = compare_inspections($pickupInsp, $dropoffInsp, $pickupDamages, $dropoffDamages);
$newDamages = $comparison['damages']['new'];
$resolvedDamages = $comparison['damages']['resolved'];
$persistentDamages = $comparison['damages']['persistent'];

$sigs = DB::fetchAll("SELECT * FROM signatures WHERE inspection_id IN (?, ?)", [$pickupInsp['id'], $dropoffInsp['id']]);
$sigByInspRole = [];
foreach ($sigs as $s) $sigByInspRole[$s['inspection_id'] . '_' . $s['role']] = $s;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Comparison Report — <?= e($booking['booking_ref']) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;color:#0f1117;background:#f8f9fc;-webkit-font-smoothing:antialiased;}
.report{max-width:960px;margin:0 auto;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,0.1);}
.report-header{background:linear-gradient(135deg,#1a1f36,#2d3561);color:#fff;padding:40px 48px;}
.report-title{font-size:28px;font-weight:700;letter-spacing:-0.6px;margin-bottom:6px;}
.report-subtitle{font-size:14px;opacity:0.7;}
.report-meta{display:flex;gap:32px;margin-top:28px;flex-wrap:wrap;}
.meta-item .label{font-size:10px;text-transform:uppercase;letter-spacing:0.8px;opacity:0.6;margin-bottom:3px;}
.meta-item .val{font-size:14px;font-weight:500;}
.section{padding:36px 48px;border-bottom:1px solid rgba(0,0,0,0.07);}
.section-title{font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:0.6px;color:#6b7280;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.stat-col{padding:16px 18px;background:#f8f9fc;border-radius:10px;}
.stat-col-head{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;margin-bottom:10px;}
.stat-row{display:flex;justify-content:space-between;font-size:13px;padding:5px 0;}
.zone-pair{margin-bottom:18px;border:1px solid rgba(0,0,0,0.08);border-radius:10px;overflow:hidden;}
.zone-pair-head{padding:10px 16px;font-size:12.5px;font-weight:600;background:#f8f9fc;border-bottom:1px solid rgba(0,0,0,0.06);}
.zone-pair-imgs{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#000;}
.zone-pair-imgs .side{position:relative;}
.zone-pair-imgs img{width:100%;height:260px;object-fit:contain;background:#000;display:block;}
.zone-pair-imgs .no-photo{height:260px;background:#eef1fa;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px;}
.zone-pair-imgs .side-label{position:absolute;top:6px;left:6px;background:rgba(0,0,0,.6);color:#fff;font-size:10px;padding:2px 8px;border-radius:10px;}
.damage-table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;}
.damage-table th{text-align:left;padding:10px 14px;font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;background:#f8f9fc;border-bottom:1px solid rgba(0,0,0,0.07);}
.damage-table td{padding:12px 14px;border-bottom:1px solid rgba(0,0,0,0.05);vertical-align:middle;}
.tag{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;}
.sig-block{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px;}
.sig-item{border:1px solid rgba(0,0,0,0.1);border-radius:10px;padding:20px;text-align:center;}
.sig-img{max-height:70px;max-width:100%;margin:10px auto 0;}
.sig-role{font-size:12px;color:#6b7280;font-weight:600;}
.sig-date{font-size:11px;color:#9ca3af;margin-top:4px;}
.report-footer{padding:24px 48px;background:#f8f9fc;display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#9ca3af;}
.no-print-bar{background:#fff;border-bottom:1px solid rgba(0,0,0,0.08);padding:14px 24px;display:flex;gap:12px;align-items:center;position:sticky;top:0;z-index:10;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;}
.btn-primary{background:#3b6ef8;color:#fff;}
.btn-ghost{background:rgba(0,0,0,0.06);color:#374151;text-decoration:none;}
@media print{.no-print-bar{display:none!important;}.report{box-shadow:none;}}
</style>
</head>
<body>

<div class="no-print-bar">
  <strong style="font-size:14px">Comparison Report</strong>
  <span style="flex:1"></span>
  <button class="btn btn-primary" onclick="window.print()">🖨️ Print / Save PDF</button>
  <a href="javascript:history.back()" class="btn btn-ghost">← Back</a>
</div>

<div class="report">
  <div class="report-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:0.6;margin-bottom:6px">INSPECTRA — PICKUP VS RETURN COMPARISON</div>
        <div class="report-title">Trip Comparison Report</div>
        <div class="report-subtitle"><?= e($booking['year'].' '.$booking['make'].' '.$booking['model']) ?> &bull; <?= e($booking['registration_number']) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:22px;font-weight:700;font-family:monospace"><?= e($booking['booking_ref']) ?></div>
        <div style="font-size:11px;opacity:0.6;margin-top:4px">Generated <?= date('M d, Y H:i') ?></div>
      </div>
    </div>
    <div class="report-meta">
      <div class="meta-item"><div class="label">Customer</div><div class="val"><?= e($booking['first_name'].' '.$booking['last_name']) ?></div></div>
      <div class="meta-item"><div class="label">Pickup Date</div><div class="val"><?= format_date($booking['pickup_date'],'M d, Y') ?></div></div>
      <div class="meta-item"><div class="label">Return Date</div><div class="val"><?= format_date($booking['return_date'],'M d, Y') ?></div></div>
      <div class="meta-item"><div class="label">Rental Value</div><div class="val">₵<?= number_format($booking['total_amount'],2) ?></div></div>
    </div>
  </div>

  <!-- Condition Comparison -->
  <div class="section">
    <div class="section-title">📊 Condition Comparison</div>
    <div class="stat-grid">
      <div class="stat-col">
        <div class="stat-col-head">At Pickup</div>
        <div class="stat-row"><span>Condition</span><strong><?= ucfirst($pickupInsp['overall_condition'] ?? 'N/A') ?></strong></div>
        <div class="stat-row"><span>Fuel</span><strong><?= ucfirst(str_replace('_',' ',$pickupInsp['fuel_level'] ?? 'N/A')) ?></strong></div>
        <div class="stat-row"><span>Mileage</span><strong><?= $pickupInsp['mileage_at_inspection'] ? number_format($pickupInsp['mileage_at_inspection']).' km' : 'N/A' ?></strong></div>
        <div class="stat-row"><span>Damages</span><strong><?= count($pickupDamages) ?></strong></div>
      </div>
      <div class="stat-col">
        <div class="stat-col-head">At Return</div>
        <div class="stat-row"><span>Condition</span><strong><?= ucfirst($dropoffInsp['overall_condition'] ?? 'N/A') ?></strong></div>
        <div class="stat-row"><span>Fuel</span><strong><?= ucfirst(str_replace('_',' ',$dropoffInsp['fuel_level'] ?? 'N/A')) ?></strong></div>
        <div class="stat-row"><span>Mileage</span><strong><?= $dropoffInsp['mileage_at_inspection'] ? number_format($dropoffInsp['mileage_at_inspection']).' km' : 'N/A' ?></strong></div>
        <div class="stat-row"><span>Damages</span><strong><?= count($dropoffDamages) ?></strong></div>
      </div>
    </div>
    <div style="margin-top:16px;font-size:13px;color:#374151">
      Distance driven: <strong><?= number_format($comparison['mileage']['difference']) ?> km</strong>
      &nbsp;&bull;&nbsp; Total estimated deduction: <strong style="color:#dc2626">₵<?= number_format($comparison['total_charge'],2) ?></strong>
    </div>
  </div>

  <!-- Photo Comparison -->
  <div class="section">
    <div class="section-title">📸 Photo Comparison</div>
    <?php foreach ($photoZones as $zoneKey => $label): ?>
    <?php $pPhoto = $pPhotoByType[$zoneKey] ?? null; $rPhoto = $rPhotoByType[$zoneKey] ?? null; ?>
    <div class="zone-pair">
      <div class="zone-pair-head"><?= $label ?></div>
      <div class="zone-pair-imgs">
        <div class="side">
          <?php if ($pPhoto): ?><img src="<?= e($pPhoto['photo_url']) ?>" alt="Pickup <?= e($label) ?>"><span class="side-label">Pickup</span>
          <?php else: ?><div class="no-photo">No pickup photo</div><?php endif; ?>
        </div>
        <div class="side">
          <?php if ($rPhoto): ?><img src="<?= e($rPhoto['photo_url']) ?>" alt="Return <?= e($label) ?>"><span class="side-label">Return</span>
          <?php else: ?><div class="no-photo">No return photo</div><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Damage Summary -->
  <div class="section">
    <div class="section-title">⚠️ Damage Summary</div>
    <?php if (empty($newDamages) && empty($resolvedDamages) && empty($persistentDamages)): ?>
    <div style="text-align:center;padding:32px;background:rgba(16,185,129,0.06);border-radius:10px;color:#059669;font-weight:500">✅ No damages recorded. Vehicle maintained in excellent condition.</div>
    <?php else: ?>
    <table class="damage-table">
      <thead><tr><th>Zone</th><th>Type</th><th>Severity</th><th>Status</th><th>Est. Cost</th></tr></thead>
      <tbody>
        <?php foreach ($newDamages as $d): ?>
        <tr><td><?= e(ucfirst(str_replace('_',' ',$d['zone']))) ?></td><td><?= e(ucfirst($d['damage_type'])) ?></td><td><?= e(ucfirst($d['severity'])) ?></td>
        <td><span class="tag" style="background:rgba(239,68,68,0.1);color:#dc2626">New</span></td>
        <td><?= $d['repair_cost']>0?'₵'.number_format($d['repair_cost'],2):'—' ?></td></tr>
        <?php endforeach; ?>
        <?php foreach ($persistentDamages as $d): ?>
        <tr><td><?= e(ucfirst(str_replace('_',' ',$d['zone']))) ?></td><td><?= e(ucfirst($d['damage_type'])) ?></td><td><?= e(ucfirst($d['severity'])) ?></td>
        <td><span class="tag" style="background:rgba(245,158,11,0.1);color:#d97706">Persistent</span></td>
        <td>—</td></tr>
        <?php endforeach; ?>
        <?php foreach ($resolvedDamages as $d): ?>
        <tr><td><?= e(ucfirst(str_replace('_',' ',$d['zone']))) ?></td><td><?= e(ucfirst($d['damage_type'])) ?></td><td><?= e(ucfirst($d['severity'])) ?></td>
        <td><span class="tag" style="background:rgba(34,197,94,0.1);color:#059669">Resolved</span></td>
        <td>—</td></tr>
        <?php endforeach; ?>
        <?php if ($comparison['total_charge'] > 0): ?>
        <tr style="background:#fef2f2">
          <td colspan="4" style="font-weight:600;text-align:right">Total Estimated Deduction</td>
          <td style="font-weight:700;color:#dc2626">₵<?= number_format($comparison['total_charge'],2) ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Signatures -->
  <div class="section">
    <div class="section-title">✍️ Signatures</div>
    <div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px">Pickup Inspection</div>
    <div class="sig-block">
      <div class="sig-item">
        <div class="sig-role">Agent</div>
        <?php $s = $sigByInspRole[$pickupInsp['id'].'_agent'] ?? null; if ($s): ?>
        <img class="sig-img" src="<?= e($s['signature_url']) ?>" alt="Agent signature"><div class="sig-date">Signed <?= format_datetime($s['signed_at']) ?></div>
        <?php else: ?><div style="height:50px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px">—</div><?php endif; ?>
      </div>
      <div class="sig-item">
        <div class="sig-role">Customer</div>
        <?php $s = $sigByInspRole[$pickupInsp['id'].'_customer'] ?? null; if ($s): ?>
        <img class="sig-img" src="<?= e($s['signature_url']) ?>" alt="Customer signature"><div class="sig-date">Signed <?= format_datetime($s['signed_at']) ?></div>
        <?php else: ?><div style="height:50px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px">—</div><?php endif; ?>
      </div>
    </div>
    <div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:10px">Return Inspection</div>
    <div class="sig-block">
      <div class="sig-item">
        <div class="sig-role">Agent</div>
        <?php $s = $sigByInspRole[$dropoffInsp['id'].'_agent'] ?? null; if ($s): ?>
        <img class="sig-img" src="<?= e($s['signature_url']) ?>" alt="Agent signature"><div class="sig-date">Signed <?= format_datetime($s['signed_at']) ?></div>
        <?php else: ?><div style="height:50px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px">—</div><?php endif; ?>
      </div>
      <div class="sig-item">
        <div class="sig-role">Customer</div>
        <?php $s = $sigByInspRole[$dropoffInsp['id'].'_customer'] ?? null; if ($s): ?>
        <img class="sig-img" src="<?= e($s['signature_url']) ?>" alt="Customer signature"><div class="sig-date">Signed <?= format_datetime($s['signed_at']) ?></div>
        <?php else: ?><div style="height:50px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px">—</div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="report-footer">
    <span>Inspectra &bull; Trip Comparison Report &bull; <?= e($booking['booking_ref']) ?></span>
    <span>Generated <?= date('d M Y, H:i') ?> WAT</span>
  </div>
</div>

</body>
</html>
