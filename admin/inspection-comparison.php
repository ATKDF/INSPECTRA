<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireAdmin();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$pageTitle = 'Inspection Comparison';
$activePage = 'inspection-comparison';

if (!$bookingId) {
    $eligibleBookings = DB::fetchAll("\n        SELECT b.id AS booking_id, b.booking_ref, b.pickup_date, b.return_date, b.total_amount, b.status, b.updated_at,\n               v.make, v.model, v.registration_number, u.first_name, u.last_name\n        FROM bookings b\n        JOIN vehicles v ON b.vehicle_id=v.id\n        JOIN users u ON b.customer_id=u.id\n        JOIN inspections i ON b.id=i.booking_id\n        WHERE i.status='completed'\n        GROUP BY b.id, b.booking_ref, b.pickup_date, b.return_date, b.total_amount, b.status, b.updated_at,\n                 v.make, v.model, v.registration_number, u.first_name, u.last_name\n        HAVING COUNT(DISTINCT i.type) = 2\n        ORDER BY b.updated_at DESC\n    ");

    require_once __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="card" style="padding:24px;margin-bottom:24px">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <div style="font-size:20px;font-weight:700">🔍 Inspection Comparison</div>
          <div style="font-size:13px;color:var(--text-secondary);margin-top:6px">Bookings with both pickup and dropoff inspections completed, ready for side-by-side review.</div>
        </div>
        <a href="<?= APP_URL ?>/admin/bookings.php" class="btn btn-ghost" style="font-size:12px">View all bookings</a>
      </div>
    </div>

    <?php if (empty($eligibleBookings)): ?>
    <div class="card" style="padding:48px;text-align:center">
      <div style="font-size:40px;margin-bottom:12px">🔎</div>
      <div style="font-size:16px;font-weight:600;margin-bottom:8px">No completed comparisons yet</div>
      <div style="color:var(--text-secondary);font-size:13px">Once a booking has both pickup and return inspections complete, it will appear here.</div>
    </div>
    <?php else: ?>
    <div class="card" style="padding:20px">
      <div class="section-header" style="margin-bottom:18px">
        <div>
          <div class="section-title">Ready for comparison</div>
          <div class="section-sub">Select a booking to view the pickup vs return inspection report.</div>
        </div>
      </div>
      <div class="table-wrap">
        <table style="font-size:13px">
          <thead>
            <tr>
              <th>Booking</th>
              <th>Customer</th>
              <th>Vehicle</th>
              <th>Rental</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($eligibleBookings as $booking): ?>
            <tr>
              <td style="font-weight:500"><?= e($booking['booking_ref']) ?></td>
              <td><?= e($booking['first_name'].' '.$booking['last_name']) ?></td>
              <td><?= e($booking['make'].' '.$booking['model']) ?><br><span style="font-size:11px;color:var(--text-secondary);font-family:var(--mono)"><?= e($booking['registration_number']) ?></span></td>
              <td><?= format_date($booking['pickup_date']) ?> → <?= format_date($booking['return_date']) ?></td>
              <td><a href="<?= APP_URL ?>/admin/inspection-comparison.php?booking_id=<?= $booking['booking_id'] ?>" class="action-btn" style="font-size:11px;padding:4px 8px;background:rgba(16,185,129,0.1);color:var(--success)">Compare →</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
    <?php
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

// Get comparison data + gate (FR24)
$status = get_comparison_status($bookingId);
$booking = $status['booking'];
if (!$booking) redirect(APP_URL . '/admin/bookings.php');

$pageTitle = 'Inspection Comparison — ' . $booking['booking_ref'];
$activePage = 'inspection-comparison';

$pickupInsp = $status['pickup'];
$dropoffInsp = $status['return'];

if (!$status['ready']) {
    require_once __DIR__ . '/../includes/admin-header.php';
    ?>
    <div class="card" style="padding:48px;text-align:center">
        <div style="font-size:48px;margin-bottom:16px">🔍</div>
        <div style="font-size:18px;font-weight:600;margin-bottom:8px">Comparison Not Yet Available</div>
        <div style="color:var(--text-secondary);margin-bottom:28px">Both the pickup and return inspections must be completed and acknowledged before the comparison can be viewed.</div>
        <div style="max-width:420px;margin:0 auto 28px;text-align:left;background:var(--surface2);border-radius:var(--radius-sm);padding:18px">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px">
                <span style="font-weight:500">📸 Pickup</span>
                <span style="color:<?= $pickupInsp && $pickupInsp['status']==='completed' ? 'var(--success)' : 'var(--text-tertiary)' ?>">
                    <?= $pickupInsp && $pickupInsp['status']==='completed' ? '✅' : '⏳' ?> <?= e(inspection_readiness_label($pickupInsp)) ?>
                </span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;font-size:13px">
                <span style="font-weight:500">📸 Return</span>
                <span style="color:<?= $dropoffInsp && $dropoffInsp['status']==='completed' ? 'var(--success)' : 'var(--text-tertiary)' ?>">
                    <?= $dropoffInsp && $dropoffInsp['status']==='completed' ? '✅' : '⏳' ?> <?= e(inspection_readiness_label($dropoffInsp)) ?>
                </span>
            </div>
        </div>
        <a href="<?= APP_URL ?>/admin/booking-detail.php?id=<?= $bookingId ?>" class="btn btn-primary">← Back to Booking</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/admin-footer.php';
    exit;
}

// Get photos
$pickupPhotos = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=?", [$pickupInsp['id']]);
$dropoffPhotos = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=?", [$dropoffInsp['id']]);
$pPhotoByType = []; foreach ($pickupPhotos as $p) $pPhotoByType[$p['photo_type']] = $p;
$rPhotoByType = []; foreach ($dropoffPhotos as $p) $rPhotoByType[$p['photo_type']] = $p;
$photoZones = ['front'=>'🚗 Front','rear'=>'🔙 Rear','left'=>'◀️ Left Side','right'=>'▶️ Right Side','interior'=>'🪑 Interior','dashboard'=>'🎛️ Dashboard'];

// Get damages
$pickupDamages = DB::fetchAll("
    SELECT dr.*, u.first_name, u.last_name FROM damage_reports dr
    LEFT JOIN users u ON dr.recorded_by=u.id
    WHERE dr.inspection_id=?
", [$pickupInsp['id']]);

$dropoffDamages = DB::fetchAll("
    SELECT dr.*, u.first_name, u.last_name FROM damage_reports dr
    LEFT JOIN users u ON dr.recorded_by=u.id
    WHERE dr.inspection_id=?
", [$dropoffInsp['id']]);

// Automated comparison analysis
$comparison = compare_inspections($pickupInsp, $dropoffInsp, $pickupDamages, $dropoffDamages);

// Legacy variables for backward compatibility
$newDamages = $comparison['damages']['new'];
$resolvedDamages = $comparison['damages']['resolved'];
$persistentDamages = $comparison['damages']['persistent'];
$totalDeduction = $comparison['total_charge'];
$mileageIncrease = $comparison['mileage']['difference'];

// Zone sets for the damage-location diagram overlay
$pickupDamagedZones = array_unique(array_column($pickupDamages, 'zone'));
$newZones = array_unique(array_column($newDamages, 'zone'));
$persistentZones = array_unique(array_column($persistentDamages, 'zone'));
$resolvedZones = array_unique(array_column($resolvedDamages, 'zone'));
$damageZoneMap = damage_zone_map();

// Get existing disputes for this booking
$disputes = DB::fetchAll("SELECT * FROM disputes WHERE booking_id=? ORDER BY created_at DESC", [$bookingId]);

// Recorded decision (if any) on the computed charge for this booking
$bookingCharge = get_booking_charge_status($bookingId);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<style>
.comparison-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px}
.insp-section{padding:20px;background:rgba(59,110,248,.02);border:1px solid rgba(59,110,248,.1);border-radius:var(--radius-sm)}
.insp-header{font-size:14px;font-weight:600;margin-bottom:14px;display:flex;align-items:center;gap:8px;color:var(--text-primary)}
.insp-item{font-size:12px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;padding:8px;background:#fff;border-radius:4px}
.insp-label{color:var(--text-secondary);font-weight:500}
.insp-value{font-weight:600;font-family:var(--mono)}
.damage-comparison{margin-top:20px;padding:20px;background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm)}
.damage-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;margin-top:10px}
.damage-card{padding:12px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);border-radius:6px;border-left:4px solid rgba(239,68,68,.4)}
.damage-card.new{border-left-color:var(--danger)}
.damage-card.resolved{background:rgba(34,197,94,.04);border-color:rgba(34,197,94,.15);border-left-color:var(--success)}
.damage-card.persistent{background:rgba(245,158,11,.04);border-color:rgba(245,158,11,.15);border-left-color:var(--warning)}
.damage-zone{font-size:13px;font-weight:600;margin-bottom:4px}
.damage-type{font-size:11px;color:var(--text-secondary)}
.damage-cost{font-size:12px;font-weight:600;margin-top:6px;color:var(--danger)}
.damage-cost.none{color:var(--text-tertiary)}

.summary-box{padding:16px 20px;background:linear-gradient(135deg, rgba(59,110,248,.05) 0%, rgba(59,110,248,.02) 100%);border:1px solid rgba(59,110,248,.15);border-radius:var(--radius-sm);margin-bottom:16px}
.summary-stat{display:flex;justify-content:space-between;align-items:center;padding:8px 0;font-size:13px;border-bottom:1px solid rgba(59,110,248,.1)}
.summary-stat:last-child{border-bottom:none}
.summary-label{color:var(--text-secondary);font-weight:500}
.summary-value{font-weight:600;font-family:var(--mono);font-size:14px}
.deduction-total{padding:12px 16px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:6px;margin-top:12px}
.deduction-total strong{font-size:16px;color:var(--danger)}

/* Damage zone diagram overlay */
.diagram-zone{fill:rgba(59,110,248,.06);stroke:rgba(59,110,248,.2);stroke-width:1.5}
.diagram-zone.dmg-new{fill:rgba(239,68,68,.22);stroke:var(--danger);stroke-width:2}
.diagram-zone.dmg-persistent{fill:rgba(245,158,11,.22);stroke:var(--warning);stroke-width:2}
.diagram-zone.dmg-resolved{fill:rgba(34,197,94,.08);stroke:var(--success);stroke-width:2;stroke-dasharray:3,2}
.diagram-legend{display:flex;gap:16px;margin-top:12px;font-size:11.5px;flex-wrap:wrap;color:var(--text-secondary)}
.diagram-legend span{display:inline-flex;align-items:center;gap:5px}
.legend-dot{width:11px;height:11px;border-radius:3px;display:inline-block}

/* View mode toggle */
.view-mode-toggle{display:flex;gap:8px;flex-shrink:0}
.view-mode-btn{padding:7px 14px;border-radius:8px;font-size:12px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface2);color:var(--text-secondary);transition:all .15s}
.view-mode-btn:hover{border-color:var(--accent)}
.view-mode-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
#photoCompareCard[data-view-mode="slider"] .view-sidebyside{display:none}
#photoCompareCard[data-view-mode="sidebyside"] .view-slider{display:none}

/* Before/after slider */
.ba-slider{position:relative;height:380px;overflow:hidden;background:#000;user-select:none}
.ba-slider .ba-base,.ba-slider .ba-overlay img{width:100%;height:380px;object-fit:contain;display:block;background:#000}
.ba-slider .ba-overlay{position:absolute;inset:0;overflow:hidden}
.ba-slider .ba-handle{position:absolute;top:0;bottom:0;left:50%;width:36px;margin-left:-18px;cursor:ew-resize;display:flex;align-items:center;justify-content:center;z-index:3}
.ba-slider .ba-handle::before{content:'';position:absolute;top:0;bottom:0;left:50%;width:2px;background:#fff;box-shadow:0 0 4px rgba(0,0,0,.5)}
.ba-slider .ba-handle-grip{position:relative;width:32px;height:32px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;font-size:12px;color:#333}

/* Photo zone comparison */
.zone-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.zone-tab{padding:8px 14px;border-radius:20px;font-size:12.5px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface2);color:var(--text-secondary);transition:all .15s}
.zone-tab:hover{border-color:var(--accent)}
.zone-tab.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.zone-panel{display:none}
.zone-panel.active{display:block}
.compare-strip{display:grid;grid-template-columns:1fr 1fr;gap:2px;border-radius:var(--radius-sm);overflow:hidden;position:relative;background:#000}
.compare-side{position:relative}
.compare-side img{width:100%;height:380px;object-fit:contain;background:#000;display:block;cursor:zoom-in}
.compare-label{position:absolute;top:8px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;z-index:2}
.compare-label.before{left:8px;background:rgba(59,110,248,.85);color:#fff}
.compare-label.after{right:8px;background:rgba(239,68,68,.85);color:#fff}
.no-photo{height:380px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;color:var(--text-tertiary);font-size:13px}
.zone-card{margin-bottom:16px;overflow:hidden}
.zone-card-head{padding:14px 18px;border-bottom:1px solid var(--border);font-weight:500;font-size:13.5px}
.zone-card-foot{padding:10px 18px;display:flex;justify-content:space-between;font-size:11.5px;color:var(--text-tertiary)}
</style>

<!-- Header -->
<div class="card" style="padding:24px;margin-bottom:24px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div>
      <div style="font-size:20px;font-weight:700">🔍 Inspection Comparison Report</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:6px">
        <strong><?= e($booking['make'].' '.$booking['model']) ?></strong> • 
        <span style="font-family:var(--mono)"><?= e($booking['registration_number']) ?></span> • 
        <?= e($booking['first_name'].' '.$booking['last_name']) ?> • 
        Ref: <strong><?= e($booking['booking_ref']) ?></strong>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="<?= APP_URL ?>/api/reports/generate-comparison.php?booking_id=<?= $bookingId ?>" target="_blank" class="btn btn-ghost" style="font-size:12px">📄 Download Comparison Report</a>
      <a href="<?= APP_URL ?>/admin/booking-detail.php?id=<?= $bookingId ?>" class="btn btn-ghost" style="font-size:12px">← Back</a>
    </div>
  </div>
</div>

<!-- Booking Overview -->
<div class="card" style="padding:20px;margin-bottom:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:12px">📋 Booking Summary</div>
  <div class="comparison-grid" style="grid-template-columns:1fr 1fr 1fr 1fr;gap:12px">
    <div class="insp-section">
      <div class="insp-label">Customer</div>
      <div style="font-weight:600;margin-top:4px"><?= e($booking['first_name'].' '.$booking['last_name']) ?></div>
      <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px"><?= e($booking['email']) ?></div>
    </div>
    <div class="insp-section">
      <div class="insp-label">Rental Period</div>
      <div style="font-weight:600;margin-top:4px"><?= format_date($booking['pickup_date']) ?></div>
      <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px">to <?= format_date($booking['return_date']) ?></div>
    </div>
    <div class="insp-section">
      <div class="insp-label">Total Amount</div>
      <div style="font-weight:600;margin-top:4px;font-family:var(--mono)">₵<?= number_format((float)$booking['total_amount'], 2) ?></div>
      <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px"><?= e($booking['status']) ?></div>
    </div>
    <div class="insp-section">
      <div class="insp-label">Vehicle</div>
      <div style="font-weight:600;margin-top:4px"><?= e($booking['year'].' '.$booking['make']) ?></div>
      <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px"><?= e($booking['type']) ?></div>
    </div>
  </div>
</div>

<!-- Photo/Zone Comparison (FR23) -->
<div class="card" style="padding:20px;margin-bottom:20px" id="photoCompareCard" data-view-mode="slider">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:6px">
    <div>
      <div style="font-size:14px;font-weight:600">📸 Photo Comparison</div>
      <div style="font-size:12.5px;color:var(--text-secondary);margin-top:2px">Choose a zone to see the exact pickup and return photo — as a slider or side by side.</div>
    </div>
    <div class="view-mode-toggle" id="viewModeToggle">
      <button type="button" class="view-mode-btn active" data-mode="slider" onclick="setViewMode('slider')">🔀 Slider</button>
      <button type="button" class="view-mode-btn" data-mode="sidebyside" onclick="setViewMode('sidebyside')">▦ Side-by-Side</button>
    </div>
  </div>

  <div class="zone-tabs" id="zoneTabs" style="margin-top:14px">
    <div class="zone-tab active" data-zone="all" onclick="showZone('all')">All Zones</div>
    <?php foreach ($photoZones as $zoneKey => $label): ?>
    <div class="zone-tab" data-zone="<?= $zoneKey ?>" onclick="showZone('<?= $zoneKey ?>')"><?= $label ?></div>
    <?php endforeach; ?>
  </div>

  <?php foreach ($photoZones as $zoneKey => $label): ?>
  <?php $pPhoto = $pPhotoByType[$zoneKey] ?? null; $rPhoto = $rPhotoByType[$zoneKey] ?? null; ?>
  <div class="zone-panel active" data-zone-panel="<?= $zoneKey ?>">
    <div class="card zone-card">
      <div class="zone-card-head"><?= $label ?></div>
      <?php if ($pPhoto && $rPhoto): ?>
      <div class="view-slider">
        <div class="ba-slider" data-ba-slider>
          <img class="ba-base" src="<?= e($rPhoto['photo_url']) ?>" alt="Return <?= e($label) ?>">
          <span class="compare-label after" style="z-index:2">Return</span>
          <div class="ba-overlay">
            <img src="<?= e($pPhoto['photo_url']) ?>" alt="Pickup <?= e($label) ?>">
            <span class="compare-label before">Pickup</span>
          </div>
          <div class="ba-handle" data-ba-handle><div class="ba-handle-grip">↔</div></div>
        </div>
        <div style="padding:6px 18px 0;font-size:11px;color:var(--text-tertiary)">↔ Drag the handle to compare</div>
      </div>
      <div class="view-sidebyside">
        <div class="compare-strip">
          <div class="compare-side">
            <img src="<?= e($pPhoto['photo_url']) ?>" alt="Pickup <?= e($label) ?>" onclick="window.open('<?= e($pPhoto['photo_url']) ?>','_blank')">
            <span class="compare-label before">Pickup</span>
          </div>
          <div class="compare-side">
            <img src="<?= e($rPhoto['photo_url']) ?>" alt="Return <?= e($label) ?>" onclick="window.open('<?= e($rPhoto['photo_url']) ?>','_blank')">
            <span class="compare-label after">Return</span>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="compare-strip">
        <div class="compare-side">
          <?php if ($pPhoto): ?>
          <img src="<?= e($pPhoto['photo_url']) ?>" alt="Pickup <?= e($label) ?>" onclick="window.open('<?= e($pPhoto['photo_url']) ?>','_blank')">
          <span class="compare-label before">Pickup</span>
          <?php else: ?>
          <div class="no-photo">No pickup photo</div>
          <?php endif; ?>
        </div>
        <div class="compare-side">
          <?php if ($rPhoto): ?>
          <img src="<?= e($rPhoto['photo_url']) ?>" alt="Return <?= e($label) ?>" onclick="window.open('<?= e($rPhoto['photo_url']) ?>','_blank')">
          <span class="compare-label after">Return</span>
          <?php else: ?>
          <div class="no-photo">No return photo</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="zone-card-foot">
        <span>Pickup: <?= $pPhoto ? format_datetime($pPhoto['taken_at']) : '—' ?></span>
        <span>Return: <?= $rPhoto ? format_datetime($rPhoto['taken_at']) : '—' ?></span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Side-by-side Inspection Comparison -->
<div class="card" style="padding:20px;margin-bottom:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:16px">📊 Inspection Details Comparison</div>
  
  <div class="comparison-grid">
    <!-- PICKUP -->
    <div>
      <div class="insp-header">📸 PICKUP INSPECTION</div>
      <div class="insp-section">
        <div class="insp-item">
          <span class="insp-label">Status</span>
          <span class="insp-value"><?= ucfirst($pickupInsp['status']) ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Overall Condition</span>
          <span style="padding:3px 10px;border-radius:14px;font-size:11px;font-weight:500;background:rgba(59,110,248,.1);color:var(--accent)"><?= ucfirst($pickupInsp['overall_condition'] ?? 'N/A') ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Fuel Level</span>
          <span class="insp-value"><?= ucfirst(str_replace('_', ' ', $pickupInsp['fuel_level'] ?? 'N/A')) ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Mileage</span>
          <span class="insp-value"><?= $pickupInsp['mileage_at_inspection'] ? number_format($pickupInsp['mileage_at_inspection']).' km' : 'N/A' ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Damages</span>
          <span class="insp-value"><?= count($pickupDamages) ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Completed</span>
          <span class="insp-value"><?= $pickupInsp['completed_at'] ? format_datetime($pickupInsp['completed_at'], 'H:i d/M/Y') : '—' ?></span>
        </div>
      </div>
    </div>

    <!-- DROPOFF -->
    <div>
      <div class="insp-header">📸 DROPOFF INSPECTION</div>
      <div class="insp-section">
        <div class="insp-item">
          <span class="insp-label">Status</span>
          <span class="insp-value"><?= ucfirst($dropoffInsp['status']) ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Overall Condition</span>
          <span style="padding:3px 10px;border-radius:14px;font-size:11px;font-weight:500;background:rgba(249,115,22,.1);color:#f97316"><?= ucfirst($dropoffInsp['overall_condition'] ?? 'N/A') ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Fuel Level</span>
          <span class="insp-value"><?= ucfirst(str_replace('_', ' ', $dropoffInsp['fuel_level'] ?? 'N/A')) ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Mileage</span>
          <span class="insp-value"><?= $dropoffInsp['mileage_at_inspection'] ? number_format($dropoffInsp['mileage_at_inspection']).' km' : 'N/A' ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Damages</span>
          <span class="insp-value"><?= count($dropoffDamages) ?></span>
        </div>
        <div class="insp-item">
          <span class="insp-label">Completed</span>
          <span class="insp-value"><?= $dropoffInsp['completed_at'] ? format_datetime($dropoffInsp['completed_at'], 'H:i d/M/Y') : '—' ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Automated Analysis Summary -->
<div class="card" style="padding:20px;margin-bottom:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:12px">🤖 Automated Analysis</div>

  <div class="summary-box">
    <div class="summary-stat">
      <span class="summary-label">Mileage Increase</span>
      <span class="summary-value <?= $comparison['mileage']['status'] === 'warning' ? 'text-warning' : '' ?>">
        <?= number_format($comparison['mileage']['difference']) ?> km
        <?php if ($comparison['mileage']['status'] === 'warning'): ?> ⚠️ <?php endif; ?>
      </span>
    </div>
    <div class="summary-stat">
      <span class="summary-label">Fuel Status</span>
      <span class="summary-value <?= $comparison['fuel']['status'] === 'danger' ? 'text-danger' : ($comparison['fuel']['status'] === 'warning' ? 'text-warning' : '') ?>">
        <?php if ($comparison['fuel']['pickup'] === $comparison['fuel']['dropoff']): ?>
          ➡️ No change (<?= ucfirst(str_replace('_', ' ', $comparison['fuel']['pickup'])) ?>)
        <?php else: ?>
          <?= ucfirst(str_replace('_', ' ', $comparison['fuel']['pickup'])) ?> → <?= ucfirst(str_replace('_', ' ', $comparison['fuel']['dropoff'])) ?>
          <?php if ($comparison['fuel']['status'] !== 'ok'): ?> ⚠️ <?php endif; ?>
        <?php endif; ?>
      </span>
    </div>
    <div class="summary-stat">
      <span class="summary-label">Condition Change</span>
      <span class="summary-value <?= $comparison['condition']['status'] === 'warning' ? 'text-warning' : '' ?>">
        <?php
        $order = ['poor' => 0, 'fair' => 1, 'good' => 2, 'excellent' => 3];
        $p = $order[$comparison['condition']['pickup']] ?? 1;
        $d = $order[$comparison['condition']['dropoff']] ?? 1;
        if ($d < $p) echo '📉 Deteriorated';
        elseif ($d > $p) echo '📈 Improved';
        else echo '➡️ No change';
        if ($comparison['condition']['status'] === 'warning') echo ' ⚠️';
        ?>
      </span>
    </div>
    <div class="summary-stat">
      <span class="summary-label">New Damages</span>
      <span class="summary-value <?= count($comparison['damages']['new']) > 0 ? 'text-danger' : '' ?>">
        <?= count($comparison['damages']['new']) ?> detected
        <?php if (count($comparison['damages']['new']) > 0): ?> ⚠️ <?php endif; ?>
      </span>
    </div>
  </div>

  <?php if (!empty($comparison['issues'])): ?>
  <div style="margin-top:16px;padding:16px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.15);border-radius:6px">
    <div style="font-size:13px;font-weight:600;color:var(--danger);margin-bottom:8px">🚨 Issues Detected</div>
    <?php foreach ($comparison['issues'] as $issue): ?>
    <div style="font-size:12px;margin-bottom:6px;padding:8px;background:#fff;border-radius:4px;border-left:3px solid <?= $issue['severity'] === 'high' ? 'var(--danger)' : 'var(--warning)' ?>">
      <strong><?= e($issue['title']) ?></strong><br>
      <span style="color:var(--text-secondary)"><?= e($issue['description']) ?></span>
      <?php if ($issue['charge'] > 0): ?>
      <div style="margin-top:4px;font-weight:600;color:var(--danger)">Charge: ₵<?= number_format($issue['charge'], 2) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:12px;padding:12px;background:var(--danger);color:white;border-radius:4px;text-align:center;font-weight:600">
      Total Charges: ₵<?= number_format($comparison['total_charge'], 2) ?>
    </div>
  </div>
  <?php endif; ?>
</div>

  <div class="deduction-total">
    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:4px">TOTAL NEW DAMAGES DEDUCTION</div>
    <strong>₵<?= number_format($totalDeduction, 2) ?></strong>
  </div>
</div>

<!-- Damage Comparison -->
<div class="card" style="padding:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:16px">⚠️ Damage Analysis</div>
  
  <!-- NEW DAMAGES -->
  <?php if (!empty($newDamages)): ?>
  <div class="damage-comparison">
    <div style="font-weight:600;color:var(--danger);margin-bottom:10px">🆕 NEW DAMAGES (<?= count($newDamages) ?>)</div>
    <div class="damage-list">
      <?php foreach ($newDamages as $dmg): ?>
      <div class="damage-card new">
        <div class="damage-zone">
          <?= e(ucfirst(str_replace('_', ' ', $dmg['zone']))) ?>
        </div>
        <div class="damage-type">
          <?= e(ucfirst($dmg['damage_type'])) ?> • <?= e(ucfirst($dmg['severity'])) ?>
        </div>
        <?php if ($dmg['description']): ?>
        <div style="font-size:10px;color:var(--text-tertiary);margin-top:4px"><?= e($dmg['description']) ?></div>
        <?php endif; ?>
        <div class="damage-cost">💰 ₵<?= number_format((float)($dmg['repair_cost'] ?? 0), 2) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- RESOLVED DAMAGES -->
  <?php if (!empty($resolvedDamages)): ?>
  <div class="damage-comparison">
    <div style="font-weight:600;color:var(--success);margin-bottom:10px">✅ RESOLVED DAMAGES (<?= count($resolvedDamages) ?>)</div>
    <div class="damage-list">
      <?php foreach ($resolvedDamages as $dmg): ?>
      <div class="damage-card resolved">
        <div class="damage-zone">
          <?= e(ucfirst(str_replace('_', ' ', $dmg['zone']))) ?>
        </div>
        <div class="damage-type">
          <?= e(ucfirst($dmg['damage_type'])) ?> • <?= e(ucfirst($dmg['severity'])) ?>
        </div>
        <div style="font-size:10px;color:var(--success);margin-top:4px">Was at pickup, resolved by return</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- PERSISTENT DAMAGES -->
  <?php if (!empty($persistentDamages)): ?>
  <div class="damage-comparison">
    <div style="font-weight:600;color:var(--warning);margin-bottom:10px">⚠️ PERSISTENT DAMAGES (<?= count($persistentDamages) ?>)</div>
    <div class="damage-list">
      <?php foreach ($persistentDamages as $dmg): ?>
      <div class="damage-card persistent">
        <div class="damage-zone">
          <?= e(ucfirst(str_replace('_', ' ', $dmg['zone']))) ?>
        </div>
        <div class="damage-type">
          <?= e(ucfirst($dmg['damage_type'])) ?> • <?= e(ucfirst($dmg['severity'])) ?>
        </div>
        <div style="font-size:10px;color:var(--warning);margin-top:4px">Present at both pickup and return</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- NONE -->
  <?php if (empty($newDamages) && empty($resolvedDamages) && empty($persistentDamages)): ?>
  <div style="text-align:center;padding:40px;color:var(--text-secondary)">
    <div style="font-size:40px;margin-bottom:10px">✅</div>
    <div style="font-size:14px;font-weight:600">Perfect Condition Maintained</div>
    <div style="font-size:12px;margin-top:4px">No damages recorded. Vehicle returned in excellent condition.</div>
  </div>
  <?php endif; ?>
</div>

<!-- Damage Location Diagram -->
<div class="card" style="padding:20px;margin-top:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:6px">🗺️ Damage Location Comparison</div>
  <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:16px">Where damage was recorded on the vehicle, before and after.</div>
  <div class="comparison-grid">
    <?php foreach (['pickup' => 'PICKUP', 'return' => 'RETURN'] as $side => $sideLabel): ?>
    <div>
      <div class="insp-header">📸 <?= $sideLabel ?></div>
      <div style="background:rgba(248,249,252,.9);border-radius:var(--radius);padding:16px">
        <svg viewBox="0 0 400 260" xmlns="http://www.w3.org/2000/svg" style="width:100%;max-width:360px;display:block;margin:0 auto">
          <rect x="35" y="80" width="330" height="110" rx="14" fill="#e8edf5" stroke="#c5cde0" stroke-width="1.5"/>
          <path d="M105 80 L118 42 L282 42 L295 80Z" fill="#d5dced" stroke="#c5cde0" stroke-width="1.5"/>
          <rect x="122" y="45" width="62" height="33" rx="5" fill="#bfcae0" stroke="#aab8d4" stroke-width="1"/>
          <rect x="192" y="45" width="62" height="33" rx="5" fill="#bfcae0" stroke="#aab8d4" stroke-width="1"/>
          <circle cx="95"  cy="192" r="26" fill="#8899b4" stroke="#6b7a96" stroke-width="2"/>
          <circle cx="95"  cy="192" r="14" fill="#c5cde0"/>
          <circle cx="305" cy="192" r="26" fill="#8899b4" stroke="#6b7a96" stroke-width="2"/>
          <circle cx="305" cy="192" r="14" fill="#c5cde0"/>
          <?php foreach ($damageZoneMap as $zoneKey => $z):
            if ($side === 'pickup') {
                $cls = in_array($zoneKey, $pickupDamagedZones) ? 'dmg-persistent' : '';
            } else {
                if (in_array($zoneKey, $newZones)) $cls = 'dmg-new';
                elseif (in_array($zoneKey, $persistentZones)) $cls = 'dmg-persistent';
                elseif (in_array($zoneKey, $resolvedZones)) $cls = 'dmg-resolved';
                else $cls = '';
            }
            $cssClass = 'diagram-zone' . ($cls ? ' ' . $cls : '');
          ?>
          <?php if (isset($z['cx'])): ?>
          <circle class="<?= $cssClass ?>" cx="<?= $z['cx'] ?>" cy="<?= $z['cy'] ?>" r="<?= $z['r'] ?>"/>
          <?php else: ?>
          <rect class="<?= $cssClass ?>" x="<?= $z['x'] ?>" y="<?= $z['y'] ?>" width="<?= $z['w'] ?>" height="<?= $z['h'] ?>" rx="4"/>
          <?php endif; ?>
          <?php endforeach; ?>
        </svg>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="diagram-legend">
    <span><span class="legend-dot" style="background:rgba(239,68,68,.4);border:1.5px solid var(--danger)"></span> New damage</span>
    <span><span class="legend-dot" style="background:rgba(245,158,11,.4);border:1.5px solid var(--warning)"></span> Pre-existing / persistent</span>
    <span><span class="legend-dot" style="background:rgba(34,197,94,.15);border:1.5px dashed var(--success)"></span> Resolved (was damaged, now clear)</span>
  </div>
</div>

<!-- Dispute Resolution -->
<?php if (!empty($disputes) || $comparison['total_charge'] > 0 || $bookingCharge): ?>
<div class="card" style="padding:20px;margin-top:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:16px">⚖️ Dispute Resolution</div>

  <?php if (!empty($disputes)): ?>
  <div style="margin-bottom:20px">
    <div style="font-size:13px;font-weight:600;margin-bottom:10px">Existing Disputes</div>
    <?php foreach ($disputes as $dispute): ?>
    <div class="dispute-item" style="padding:12px;border:1px solid var(--border);border-radius:6px;margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
        <div>
          <strong style="color:<?= $dispute['status'] === 'resolved' ? 'var(--success)' : ($dispute['status'] === 'escalated' ? 'var(--danger)' : 'var(--warning)') ?>">
            <?= e($dispute['subject']) ?>
          </strong>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:2px">
            <?= e(ucfirst($dispute['category'])) ?> • <?= time_ago($dispute['created_at']) ?>
          </div>
        </div>
        <span class="badge <?= $dispute['status'] === 'resolved' ? 'badge-active' : ($dispute['status'] === 'escalated' ? 'badge-returned' : 'badge-paid') ?>">
          <?= ucfirst(str_replace('_', ' ', $dispute['status'])) ?>
        </span>
      </div>
      <div style="font-size:12px;color:var(--text-secondary)"><?= e($dispute['description']) ?></div>
      <?php render_dispute_progress($dispute['status']); ?>
      <?php if ($dispute['resolution']): ?>
      <div style="margin-top:8px;padding:8px;background:rgba(59,110,248,.05);border-radius:4px;font-size:12px">
        <strong>Admin Response:</strong> <?= e($dispute['resolution']) ?>
      </div>
      <?php endif; ?>
      <?php render_dispute_thread($dispute, 'admin'); ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  // Small reusable render of the Approve/Adjust/Waive action group. Available
  // whenever the charge is not yet finalized — including re-approving,
  // re-adjusting, or re-waiving something already decided once before.
  function render_admin_charge_actions(int $bookingId, float $totalCharge, bool $isRevision): void {
  ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="POST" action="<?= APP_URL ?>/api/charges/resolve.php" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
      <input type="hidden" name="action" value="approve">
      <button type="submit" class="btn btn-success" style="font-size:12px"><?= $isRevision ? '✅ Re-approve Charges' : '✅ Approve Charges' ?></button>
    </form>
    <button type="button" class="btn btn-warning" style="font-size:12px" onclick="toggleAdjustForm()">⚖️ <?= $isRevision ? 'Re-adjust' : 'Adjust' ?> Amount</button>
    <form method="POST" action="<?= APP_URL ?>/api/charges/resolve.php" style="display:inline" onsubmit="return confirm('Waive charges for this booking? The customer will be notified, and you can still change this later if needed.')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
      <input type="hidden" name="action" value="waive">
      <button type="submit" class="btn btn-ghost" style="font-size:12px">🚫 <?= $isRevision ? 'Waive Instead' : 'Waive Charges' ?></button>
    </form>
  </div>
  <form method="POST" action="<?= APP_URL ?>/api/charges/resolve.php" id="adjustForm" style="display:none;margin-top:14px;padding:14px;background:#fff;border:1px solid var(--border);border-radius:6px">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
    <input type="hidden" name="action" value="adjust">
    <div class="form-group">
      <label class="form-label">Adjusted Amount (₵)</label>
      <input class="form-input" type="number" name="amount" step="0.01" min="0" value="<?= number_format($totalCharge, 2, '.', '') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label">Reason (required)</label>
      <textarea class="form-input" name="note" rows="2" placeholder="Why is the amount being adjusted?" required></textarea>
    </div>
    <div style="display:flex;gap:8px">
      <button type="submit" class="btn btn-warning" style="font-size:12px">Save Adjusted Amount</button>
      <button type="button" class="btn btn-ghost" style="font-size:12px" onclick="toggleAdjustForm()">Cancel</button>
    </div>
  </form>
  <div style="font-size:11px;color:var(--text-tertiary);margin-top:10px">ℹ️ These stay available — approve, adjust, or waive as many times as needed — until the customer accepts or a dispute is formally resolved/closed below.</div>
  <?php
  }

  $chargeFinalized = is_charge_finalized($bookingCharge);
  $openDispute = has_open_dispute($bookingCharge);
  $currentTotal = $bookingCharge ? (float)$bookingCharge['resolved_amount'] : (float)$comparison['total_charge'];
  ?>

  <?php if ($chargeFinalized): ?>
  <!-- Truly finalized: customer accepted, or the dispute was formally resolved/closed -->
  <div style="padding:16px;background:rgba(16,185,129,.05);border:1px solid rgba(16,185,129,.2);border-radius:6px">
    <div style="font-size:13px;font-weight:600;color:var(--success);margin-bottom:8px">
      <?= $bookingCharge['status'] === 'accepted' ? '✅ Customer accepted the charge' : '✅ This charge is fully resolved' ?>
    </div>
    <div style="font-size:16px;font-weight:700;margin-bottom:6px">₵<?= number_format($bookingCharge['resolved_amount'], 2) ?></div>
    <?php if ((float)$bookingCharge['computed_amount'] != (float)$bookingCharge['resolved_amount']): ?>
    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Originally computed as ₵<?= number_format($bookingCharge['computed_amount'], 2) ?></div>
    <?php endif; ?>
    <?php if ($bookingCharge['decision_note']): ?>
    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px"><strong>Note:</strong> <?= e($bookingCharge['decision_note']) ?></div>
    <?php endif; ?>
    <div style="font-size:11px;color:var(--text-tertiary)">
      <?= $bookingCharge['decided_by_first_name'] ? e($bookingCharge['decided_by_first_name'] . ' ' . $bookingCharge['decided_by_last_name']) : 'Customer' ?> · <?= format_datetime($bookingCharge['decided_at']) ?>
    </div>
    <div style="font-size:11.5px;color:var(--text-secondary);margin-top:8px;padding-top:8px;border-top:1px solid rgba(16,185,129,.2)">This matter is fully settled — no further changes are possible here.</div>
  </div>

  <?php elseif ($bookingCharge || $comparison['total_charge'] > 0): ?>
  <!-- Still open — a decision may already exist, but nothing here is final yet -->
  <?php if ($bookingCharge): ?>
  <div style="padding:14px 16px;background:rgba(59,110,248,.04);border:1px solid rgba(59,110,248,.15);border-radius:6px;margin-bottom:14px">
    <div style="font-size:12.5px;font-weight:600;color:var(--accent);margin-bottom:6px">
      <?= match ($bookingCharge['status']) {
          'approved' => '✅ Currently approved',
          'adjusted' => '✏️ Currently adjusted',
          'waived'   => '🚫 Currently waived',
          'disputed' => '⚖️ Under dispute',
          default    => 'Current decision',
      } ?>
    </div>
    <div style="font-size:16px;font-weight:700;margin-bottom:4px">₵<?= number_format($bookingCharge['resolved_amount'], 2) ?></div>
    <?php if ((float)$bookingCharge['computed_amount'] != (float)$bookingCharge['resolved_amount']): ?>
    <div style="font-size:11.5px;color:var(--text-secondary)">Originally computed as ₵<?= number_format($bookingCharge['computed_amount'], 2) ?></div>
    <?php endif; ?>
    <?php if ($bookingCharge['decision_note']): ?>
    <div style="font-size:11.5px;color:var(--text-secondary);margin-top:4px"><strong>Note:</strong> <?= e($bookingCharge['decision_note']) ?></div>
    <?php endif; ?>
    <div style="font-size:10.5px;color:var(--text-tertiary);margin-top:6px">ℹ️ Not final yet — the customer can still accept or dispute this, and you can still revise it below.</div>
  </div>
  <?php else: ?>
  <div style="font-size:13px;font-weight:600;color:var(--danger);margin-bottom:12px">💰 Charges Computed: ₵<?= number_format($comparison['total_charge'], 2) ?></div>
  <?php endif; ?>

  <?php if ($openDispute): ?>
  <div style="padding:14px;background:rgba(245,158,11,.05);border:1px solid rgba(245,158,11,.2);border-radius:6px;margin-bottom:14px">
    <div style="font-size:12.5px;font-weight:600;color:#d97706;margin-bottom:10px">⚖️ Open dispute — respond below, or use the actions further down to revise the charge directly.</div>
    <form method="POST" action="<?= APP_URL ?>/api/disputes/resolve.php" style="padding:14px;background:#fff;border:1px solid var(--border);border-radius:6px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="dispute_id" value="<?= $bookingCharge['dispute']['dispute_id'] ?>">
      <div class="form-group">
        <label class="form-label">Update Status</label>
        <select class="form-input" name="status" required>
          <option value="under_review">Under Review</option>
          <option value="resolved">Resolved</option>
          <option value="escalated">Escalated</option>
          <option value="closed">Closed</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Response (required)</label>
        <textarea class="form-input" name="resolution" rows="2" placeholder="Explain the outcome to the customer..." required></textarea>
      </div>
      <div style="font-size:11px;color:#92400e;background:rgba(245,158,11,.08);padding:8px 10px;border-radius:4px;margin-bottom:10px">⚠️ Choosing <strong>Resolved</strong> or <strong>Closed</strong> formally ends this dispute and locks the charge — neither side will be able to change it afterward. Use <strong>Under Review</strong> or <strong>Escalated</strong> if you just want to reply for now.</div>
      <button type="submit" class="btn btn-primary" style="font-size:12px">Send Response</button>
    </form>
  </div>
  <?php endif; ?>

  <?php render_admin_charge_actions($bookingId, $currentTotal, (bool)$bookingCharge); ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function showZone(zone) {
  document.querySelectorAll('.zone-tab').forEach(t => t.classList.toggle('active', t.dataset.zone === zone));
  document.querySelectorAll('.zone-panel').forEach(p => {
    p.classList.toggle('active', zone === 'all' || p.dataset.zonePanel === zone);
  });
}

// ── Photo view mode: slider vs side-by-side, remembered across visits ───────
function setViewMode(mode) {
  const card = document.getElementById('photoCompareCard');
  card.dataset.viewMode = mode;
  document.querySelectorAll('.view-mode-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
  try { localStorage.setItem('inspectra_compare_view_mode', mode); } catch (e) {}
}
(function () {
  let saved = 'slider';
  try { saved = localStorage.getItem('inspectra_compare_view_mode') || 'slider'; } catch (e) {}
  setViewMode(saved);
})();

// ── Before/after slider ───────────────────────────────────────────────────
function initBaSlider(el) {
  const overlay = el.querySelector('.ba-overlay');
  const handle = el.querySelector('[data-ba-handle]');
  let dragging = false;
  function setPct(pct) {
    pct = Math.max(0, Math.min(100, pct));
    // clip-path is relative to the overlay's own (full-width) box, so the
    // pickup image inside always renders at full size — only the reveal
    // window shrinks/grows. (A width-based clip would also scale the image.)
    overlay.style.clipPath = `inset(0 ${100 - pct}% 0 0)`;
    handle.style.left = pct + '%';
  }
  function pctFromEvent(e) {
    const rect = el.getBoundingClientRect();
    const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
    return (x / rect.width) * 100;
  }
  handle.addEventListener('mousedown', () => dragging = true);
  handle.addEventListener('touchstart', () => dragging = true, {passive: true});
  window.addEventListener('mousemove', e => { if (dragging) setPct(pctFromEvent(e)); });
  window.addEventListener('touchmove', e => { if (dragging) setPct(pctFromEvent(e)); }, {passive: true});
  window.addEventListener('mouseup', () => dragging = false);
  window.addEventListener('touchend', () => dragging = false);
  setPct(50);
}
document.querySelectorAll('[data-ba-slider]').forEach(initBaSlider);

function toggleAdjustForm() {
  const f = document.getElementById('adjustForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
