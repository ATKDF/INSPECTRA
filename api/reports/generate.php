<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$inspId = (int)($_GET['inspection_id'] ?? 0);
$inspection = DB::fetchOne("SELECT i.*, b.booking_ref, b.pickup_date, b.return_date, b.total_amount, v.make, v.model, v.registration_number, v.year, v.category, cu.first_name, cu.last_name, cu.email, cu.phone FROM inspections i JOIN bookings b ON i.booking_id=b.id JOIN vehicles v ON b.vehicle_id=v.id JOIN users cu ON b.customer_id=cu.id WHERE i.id=?", [$inspId]);
if (!$inspection) { http_response_code(404); echo 'Not found'; exit; }

// Check access
if (Auth::role() === 'customer') {
    $booking = DB::fetchOne("SELECT customer_id FROM bookings WHERE id=?", [$inspection['booking_id']]);
    if ($booking['customer_id'] !== Auth::id()) { http_response_code(403); echo 'Forbidden'; exit; }
}

$photos   = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=? ORDER BY photo_type", [$inspId]);
$damages  = DB::fetchAll("SELECT * FROM damage_reports WHERE inspection_id=? ORDER BY created_at", [$inspId]);
$sigs     = DB::fetchAll("SELECT * FROM signatures WHERE inspection_id=?", [$inspId]);
$agentSig = null; $custSig = null;
foreach ($sigs as $s) { if ($s['role']==='agent') $agentSig=$s; if ($s['role']==='customer') $custSig=$s; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inspection Report — <?= e($inspection['booking_ref']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;color:#0f1117;background:#f8f9fc;-webkit-font-smoothing:antialiased;}
.report{max-width:900px;margin:0 auto;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,0.1);}
.report-header{background:linear-gradient(135deg,#1a1f36,#2d3561);color:#fff;padding:40px 48px;}
.report-title{font-size:28px;font-weight:700;letter-spacing:-0.6px;margin-bottom:6px;}
.report-subtitle{font-size:14px;opacity:0.7;}
.report-meta{display:flex;gap:32px;margin-top:28px;flex-wrap:wrap;}
.meta-item .label{font-size:10px;text-transform:uppercase;letter-spacing:0.8px;opacity:0.6;margin-bottom:3px;}
.meta-item .val{font-size:14px;font-weight:500;}
.section{padding:36px 48px;border-bottom:1px solid rgba(0,0,0,0.07);}
.section-title{font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:0.6px;color:#6b7280;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
.info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
.info-cell{padding:14px 16px;background:#f8f9fc;border-radius:10px;}
.info-cell .lbl{font-size:10.5px;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;margin-bottom:4px;}
.info-cell .v{font-size:14px;font-weight:500;}
.photos-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;}
.photo-item{border-radius:10px;overflow:hidden;background:#e0e7ff;aspect-ratio:4/3;position:relative;}
.photo-item img{width:100%;height:100%;object-fit:cover;}
.photo-label{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.7);color:#fff;padding:6px 10px;font-size:11px;font-weight:500;}
.photo-timestamp{font-size:9px;opacity:0.7;}
.damage-table{width:100%;border-collapse:collapse;font-size:13px;}
.damage-table th{text-align:left;padding:10px 14px;font-size:10.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;background:#f8f9fc;border-bottom:1px solid rgba(0,0,0,0.07);}
.damage-table td{padding:12px 14px;border-bottom:1px solid rgba(0,0,0,0.05);vertical-align:middle;}
.severity-badge{display:inline-flex;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:500;}
.sig-block{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
.sig-item{border:1px solid rgba(0,0,0,0.1);border-radius:10px;padding:20px;text-align:center;}
.sig-img{max-height:80px;max-width:100%;margin:12px auto 0;}
.sig-name{font-size:14px;font-weight:600;margin-bottom:3px;}
.sig-role{font-size:12px;color:#6b7280;}
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
  <strong style="font-size:14px">Inspection Report</strong>
  <span style="flex:1"></span>
  <button class="btn btn-primary" onclick="window.print()">🖨️ Print / Save PDF</button>
  <a href="javascript:history.back()" class="btn btn-ghost">← Back</a>
</div>

<div class="report">
  <!-- Header -->
  <div class="report-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:0.6;margin-bottom:6px">INSPECTRA — VEHICLE CONDITION REPORT</div>
        <div class="report-title"><?= ucfirst($inspection['type']) ?> Inspection</div>
        <div class="report-subtitle"><?= e($inspection['year'].' '.$inspection['make'].' '.$inspection['model']) ?> &bull; <?= e($inspection['registration_number']) ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-size:22px;font-weight:700;font-family:monospace"><?= e($inspection['booking_ref']) ?></div>
        <div style="font-size:11px;opacity:0.6;margin-top:4px">Generated <?= date('M d, Y H:i') ?></div>
        <div style="margin-top:10px">
          <?php if ($inspection['status']==='completed'): ?>
          <span style="background:rgba(16,185,129,0.25);color:#6ee7b7;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600">✓ COMPLETED</span>
          <?php else: ?>
          <span style="background:rgba(245,158,11,0.25);color:#fde68a;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600">IN PROGRESS</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="report-meta">
      <div class="meta-item"><div class="label">Customer</div><div class="val"><?= e($inspection['first_name'].' '.$inspection['last_name']) ?></div></div>
      <div class="meta-item"><div class="label">Email</div><div class="val"><?= e($inspection['email']) ?></div></div>
      <div class="meta-item"><div class="label">Pickup Date</div><div class="val"><?= format_date($inspection['pickup_date'],'M d, Y') ?></div></div>
      <div class="meta-item"><div class="label">Return Date</div><div class="val"><?= format_date($inspection['return_date'],'M d, Y') ?></div></div>
      <div class="meta-item"><div class="label">Rental Value</div><div class="val">₵<?= number_format($inspection['total_amount'],2) ?></div></div>
    </div>
  </div>

  <!-- Vehicle Info -->
  <div class="section">
    <div class="section-title">🚗 Vehicle Details</div>
    <div class="info-grid">
      <div class="info-cell"><div class="lbl">Make & Model</div><div class="v"><?= e($inspection['make'].' '.$inspection['model']) ?></div></div>
      <div class="info-cell"><div class="lbl">Registration</div><div class="v" style="font-family:monospace"><?= e($inspection['registration_number']) ?></div></div>
      <div class="info-cell"><div class="lbl">Category</div><div class="v"><?= ucfirst($inspection['category']) ?></div></div>
      <?php if ($inspection['overall_condition']): ?>
      <div class="info-cell"><div class="lbl">Condition</div><div class="v"><?= ucfirst($inspection['overall_condition']) ?></div></div>
      <?php endif; ?>
      <?php if ($inspection['fuel_level']): ?>
      <div class="info-cell"><div class="lbl">Fuel Level</div><div class="v"><?= ucfirst(str_replace('_',' ',$inspection['fuel_level'])) ?></div></div>
      <?php endif; ?>
      <?php if ($inspection['mileage_at_inspection']): ?>
      <div class="info-cell"><div class="lbl">Mileage</div><div class="v"><?= number_format($inspection['mileage_at_inspection']) ?> km</div></div>
      <?php endif; ?>
    </div>
    <?php if ($inspection['agent_notes']): ?>
    <div style="margin-top:16px;padding:14px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 8px 8px 0;font-size:13px">
      <strong>Agent Notes:</strong> <?= e($inspection['agent_notes']) ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Photos -->
  <?php if (!empty($photos)): ?>
  <div class="section">
    <div class="section-title">📸 Inspection Photos (<?= count($photos) ?>)</div>
    <div class="photos-grid">
      <?php foreach ($photos as $p): ?>
      <div class="photo-item">
        <img src="<?= e($p['photo_url']) ?>" alt="<?= ucfirst($p['photo_type']) ?>" onerror="this.style.display='none'">
        <div class="photo-label">
          <?= ucfirst($p['photo_type']) ?><br>
          <span class="photo-timestamp"><?= format_datetime($p['taken_at']) ?></span>
          <?php if ($p['latitude']): ?><span class="photo-timestamp"> &bull; <?= round($p['latitude'],4) ?>,<?= round($p['longitude'],4) ?></span><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Damage Reports -->
  <div class="section">
    <div class="section-title">⚠️ Damage Report (<?= count($damages) ?> item<?= count($damages)!=1?'s':'' ?>)</div>
    <?php if (empty($damages)): ?>
    <div style="text-align:center;padding:32px;background:rgba(16,185,129,0.06);border-radius:10px;color:#059669;font-weight:500">✅ No damages recorded for this inspection.</div>
    <?php else: ?>
    <table class="damage-table">
      <thead><tr><th>Zone</th><th>Type</th><th>Severity</th><th>Description</th><th>Repair Est.</th></tr></thead>
      <tbody>
        <?php foreach ($damages as $d): ?>
        <tr>
          <td><?= e(ucfirst(str_replace('_',' ',$d['zone']))) ?></td>
          <td><?= e(ucfirst($d['damage_type'])) ?></td>
          <td>
            <span class="severity-badge" style="<?= $d['severity']==='minor'?'background:rgba(245,158,11,0.1);color:#d97706':($d['severity']==='severe'?'background:rgba(239,68,68,0.1);color:#dc2626':'background:rgba(249,115,22,0.1);color:#ea580c') ?>">
              <?= ucfirst($d['severity']) ?>
            </span>
          </td>
          <td style="color:#6b7280"><?= e($d['description'] ?: '—') ?></td>
          <td><?= $d['repair_cost']>0?'₵'.number_format($d['repair_cost'],2):'—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php $totalRepair=array_sum(array_column($damages,'repair_cost')); if ($totalRepair>0): ?>
        <tr style="background:#fef2f2">
          <td colspan="4" style="font-weight:600;text-align:right">Total Estimated Repair Cost</td>
          <td style="font-weight:700;color:#dc2626">₵<?= number_format($totalRepair,2) ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Signatures -->
  <div class="section">
    <div class="section-title">✍️ Signatures</div>
    <div class="sig-block">
      <div class="sig-item">
        <div class="sig-role">Agent / Company</div>
        <?php if ($agentSig): ?>
        <img class="sig-img" src="<?= e($agentSig['signature_url']) ?>" alt="Agent signature">
        <div class="sig-date">Signed <?= format_datetime($agentSig['signed_at']) ?></div>
        <?php else: ?>
        <div style="height:60px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;margin:12px 0">Signature pending</div>
        <?php endif; ?>
      </div>
      <div class="sig-item">
        <div class="sig-role">Customer — <?= e($inspection['first_name'].' '.$inspection['last_name']) ?></div>
        <?php if ($custSig): ?>
        <img class="sig-img" src="<?= e($custSig['signature_url']) ?>" alt="Customer signature">
        <div class="sig-date">Signed <?= format_datetime($custSig['signed_at']) ?></div>
        <?php else: ?>
        <div style="height:60px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:13px;margin:12px 0">Signature pending</div>
        <?php endif; ?>
      </div>
    </div>
    <div style="margin-top:16px;font-size:11.5px;color:#9ca3af;line-height:1.6">
      By signing, both parties confirm that the vehicle condition recorded above is accurate and agree to the terms set out in the rental agreement.
    </div>
  </div>

  <!-- Footer -->
  <div class="report-footer">
    <span>Inspectra &bull; Vehicle Condition Report &bull; <?= e($inspection['booking_ref']) ?></span>
    <span>Generated <?= date('d M Y, H:i') ?> WAT</span>
  </div>
</div>

</body>
</html>
