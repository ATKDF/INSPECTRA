<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireCustomer();

$bookingId = (int)($_GET['booking_id'] ?? 0);

// ═══ NO BOOKING CHOSEN — show a picker of this customer's own bookings ═══
if (!$bookingId) {
    $pageTitle = 'Inspection Comparison';
    $activePage = 'inspection-comparison';

    $bookingsWithInspections = DB::fetchAll("
        SELECT DISTINCT b.id, b.booking_ref, b.pickup_date, b.return_date, b.updated_at,
               v.make, v.model, v.registration_number
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id=v.id
        JOIN inspections i ON b.id=i.booking_id
        WHERE b.customer_id=?
        ORDER BY b.updated_at DESC
    ", [Auth::id()]);

    $readyBookings = [];
    $pendingBookings = [];
    foreach ($bookingsWithInspections as $b) {
        $st = get_comparison_status($b['id'], Auth::id());
        $row = $b + ['pickup' => $st['pickup'], 'return' => $st['return']];
        if ($st['ready']) $readyBookings[] = $row; else $pendingBookings[] = $row;
    }

    require_once __DIR__ . '/../includes/customer-header.php';
    ?>
    <div class="card" style="padding:24px;margin-bottom:24px">
      <div style="font-size:20px;font-weight:700">🔍 Inspection Comparison</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:6px">Bookings with both pickup and return inspections completed are ready for a side-by-side review.</div>
    </div>

    <?php if (empty($readyBookings) && empty($pendingBookings)): ?>
    <div class="card" style="padding:48px;text-align:center">
      <div style="font-size:40px;margin-bottom:12px">🔎</div>
      <div style="font-size:16px;font-weight:600;margin-bottom:8px">No inspections yet</div>
      <div style="color:var(--text-secondary);font-size:13px">Once your rental has a pickup or return inspection, it will appear here.</div>
    </div>
    <?php else: ?>

    <?php if (!empty($readyBookings)): ?>
    <div class="card" style="padding:20px;margin-bottom:20px">
      <div class="section-title" style="margin-bottom:14px">Ready for comparison</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
        <?php foreach ($readyBookings as $b): ?>
        <a href="<?= APP_URL ?>/customer/inspection-comparison.php?booking_id=<?= $b['id'] ?>" class="card" style="padding:16px;text-decoration:none;color:inherit;display:block;border:1px solid var(--border)">
          <div style="font-weight:600;font-size:13.5px"><?= e($b['booking_ref']) ?></div>
          <div style="font-size:12.5px;color:var(--text-secondary);margin-top:4px"><?= e($b['make'].' '.$b['model']) ?> · <span style="font-family:var(--mono)"><?= e($b['registration_number']) ?></span></div>
          <div style="font-size:11px;color:var(--text-tertiary);margin-top:6px"><?= format_date($b['pickup_date']) ?> → <?= format_date($b['return_date']) ?></div>
          <div style="margin-top:10px;font-size:12px;color:var(--success);font-weight:500">✅ Ready — view comparison →</div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($pendingBookings)): ?>
    <div class="card" style="padding:20px">
      <div class="section-title" style="margin-bottom:14px">In progress</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
        <?php foreach ($pendingBookings as $b): ?>
        <div class="card" style="padding:16px;border:1px solid var(--border)">
          <div style="font-weight:600;font-size:13.5px"><?= e($b['booking_ref']) ?></div>
          <div style="font-size:12.5px;color:var(--text-secondary);margin-top:4px"><?= e($b['make'].' '.$b['model']) ?> · <span style="font-family:var(--mono)"><?= e($b['registration_number']) ?></span></div>
          <div style="margin-top:10px;font-size:11.5px;display:flex;justify-content:space-between">
            <span>Pickup</span><span style="color:var(--text-tertiary)"><?= e(inspection_readiness_label($b['pickup'])) ?></span>
          </div>
          <div style="margin-top:4px;font-size:11.5px;display:flex;justify-content:space-between">
            <span>Return</span><span style="color:var(--text-tertiary)"><?= e(inspection_readiness_label($b['return'])) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    <?php
    require_once __DIR__ . '/../includes/customer-footer.php';
    exit;
}

$status = get_comparison_status($bookingId, Auth::id());
$booking = $status['booking'];
if (!$booking) redirect(APP_URL . '/customer/bookings.php');

$pageTitle = 'Inspection Comparison — ' . $booking['booking_ref'];
$activePage = 'inspection-comparison';

$pickupInsp = $status['pickup'];
$dropoffInsp = $status['return'];

// ═══ NOT YET READY (FR24) ═══
if (!$status['ready']) {
    require_once __DIR__ . '/../includes/customer-header.php';
    ?>
    <div class="card" style="padding:48px;text-align:center">
        <div style="font-size:48px;margin-bottom:16px">🔍</div>
        <div style="font-size:18px;font-weight:600;margin-bottom:8px">Comparison Not Yet Available</div>
        <div style="color:var(--text-secondary);margin-bottom:28px">Both the pickup and return inspections must be completed and acknowledged before you can view the comparison.</div>
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
        <a href="<?= APP_URL ?>/customer/inspections.php" class="btn btn-primary">← My Inspections</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/customer-footer.php';
    exit;
}

// ═══ READY — gather everything ═══
$pickupPhotos = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=?", [$pickupInsp['id']]);
$dropoffPhotos = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=?", [$dropoffInsp['id']]);
$pPhotoByType = []; foreach ($pickupPhotos as $p) $pPhotoByType[$p['photo_type']] = $p;
$rPhotoByType = []; foreach ($dropoffPhotos as $p) $rPhotoByType[$p['photo_type']] = $p;
$photoZones = ['front'=>'🚗 Front','rear'=>'🔙 Rear','left'=>'◀️ Left Side','right'=>'▶️ Right Side','interior'=>'🪑 Interior','dashboard'=>'🎛️ Dashboard'];

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

require_once __DIR__ . '/../includes/customer-header.php';
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
      <div style="font-size:20px;font-weight:700">🔍 Trip Comparison Report</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:6px">
        <strong><?= e($booking['make'].' '.$booking['model']) ?></strong> •
        <span style="font-family:var(--mono)"><?= e($booking['registration_number']) ?></span> •
        Ref: <strong><?= e($booking['booking_ref']) ?></strong>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="<?= APP_URL ?>/api/reports/generate-comparison.php?booking_id=<?= $bookingId ?>" target="_blank" class="btn btn-ghost" style="font-size:12px">📄 Download Comparison Report</a>
      <a href="<?= APP_URL ?>/customer/bookings.php" class="btn btn-ghost" style="font-size:12px">← My Bookings</a>
    </div>
  </div>
</div>

<!-- Booking Overview -->
<div class="card" style="padding:20px;margin-bottom:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:12px">📋 Trip Summary</div>
  <div class="comparison-grid" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
    <div class="insp-section">
      <div class="insp-label">Rental Period</div>
      <div style="font-weight:600;margin-top:4px"><?= format_date($booking['pickup_date']) ?></div>
      <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px">to <?= format_date($booking['return_date']) ?></div>
    </div>
    <div class="insp-section">
      <div class="insp-label">Vehicle</div>
      <div style="font-weight:600;margin-top:4px"><?= e($booking['year'].' '.$booking['make']) ?></div>
      <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px"><?= e($booking['type']) ?></div>
    </div>
    <div class="insp-section">
      <div class="insp-label">Trip Total</div>
      <div style="font-weight:600;margin-top:4px;font-family:var(--mono)">₵<?= number_format((float)$booking['total_amount'], 2) ?></div>
      <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px"><?= e($booking['status']) ?></div>
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
  <div style="font-size:14px;font-weight:600;margin-bottom:16px">📊 Vehicle Condition Comparison</div>

  <div class="comparison-grid">
    <!-- PICKUP -->
    <div>
      <div class="insp-header">📸 AT PICKUP</div>
      <div class="insp-section">
        <div class="insp-item">
          <span class="insp-label">Condition</span>
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
      </div>
    </div>

    <!-- DROPOFF -->
    <div>
      <div class="insp-header">📸 AT RETURN</div>
      <div class="insp-section">
        <div class="insp-item">
          <span class="insp-label">Condition</span>
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
      </div>
    </div>
  </div>
</div>

<!-- Automated Analysis Summary -->
<div class="card" style="padding:20px;margin-bottom:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:12px">🤖 Automated Analysis</div>

  <div class="summary-box">
    <div class="summary-stat">
      <span class="summary-label">📍 Distance Driven</span>
      <span class="summary-value <?= $comparison['mileage']['status'] === 'warning' ? 'text-warning' : '' ?>">
        <?= number_format($comparison['mileage']['difference']) ?> km
        <?php if ($comparison['mileage']['status'] === 'warning'): ?> ⚠️ <?php endif; ?>
      </span>
    </div>
    <div class="summary-stat">
      <span class="summary-label">⛽ Fuel Status</span>
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
      <span class="summary-label">🚗 Condition Change</span>
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
      <span class="summary-label">🔧 New Damages</span>
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
      Total Potential Charges: ₵<?= number_format($comparison['total_charge'], 2) ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Damage Comparison -->
<div class="card" style="padding:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:16px">⚠️ Damage Summary</div>

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
        <div class="damage-cost">💰 Estimated repair: ₵<?= number_format((float)($dmg['repair_cost'] ?? 0), 2) ?></div>
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
        <div style="font-size:10px;color:var(--success);margin-top:4px">✓ Pre-existing damage, not caused during rental</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- PERSISTENT DAMAGES -->
  <?php if (!empty($persistentDamages)): ?>
  <div class="damage-comparison">
    <div style="font-weight:600;color:var(--warning);margin-bottom:10px">⚠️ PERSISTING DAMAGE (<?= count($persistentDamages) ?>)</div>
    <div class="damage-list">
      <?php foreach ($persistentDamages as $dmg): ?>
      <div class="damage-card persistent">
        <div class="damage-zone">
          <?= e(ucfirst(str_replace('_', ' ', $dmg['zone']))) ?>
        </div>
        <div class="damage-type">
          <?= e(ucfirst($dmg['damage_type'])) ?> • <?= e(ucfirst($dmg['severity'])) ?>
        </div>
        <div style="font-size:10px;color:var(--warning);margin-top:4px">Also noted at return - pre-existing damage</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- NONE -->
  <?php if (empty($newDamages) && empty($resolvedDamages) && empty($persistentDamages)): ?>
  <div style="text-align:center;padding:40px;color:var(--text-secondary)">
    <div style="font-size:40px;margin-bottom:10px">✅</div>
    <div style="font-size:14px;font-weight:600">Perfect Trip!</div>
    <div style="font-size:12px;margin-top:4px">No new damage reported. Vehicle maintained in excellent condition.</div>
  </div>
  <?php endif; ?>
</div>

<!-- Damage Location Diagram -->
<div class="card" style="padding:20px;margin-top:20px">
  <div style="font-size:14px;font-weight:600;margin-bottom:6px">🗺️ Damage Location Comparison</div>
  <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:16px">Where damage was recorded on the vehicle, before and after.</div>
  <div class="comparison-grid">
    <?php foreach (['pickup' => 'AT PICKUP', 'return' => 'AT RETURN'] as $side => $sideLabel): ?>
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
    <div style="font-size:13px;font-weight:600;margin-bottom:10px">Your Disputes</div>
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
      <?php render_dispute_thread($dispute, 'customer'); ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php
  $chargeFinalized = is_charge_finalized($bookingCharge);
  $openDispute = has_open_dispute($bookingCharge);
  $currentAmount = $bookingCharge ? (float)$bookingCharge['resolved_amount'] : (float)$comparison['total_charge'];
  ?>

  <?php if ($chargeFinalized): ?>
  <!-- Truly finalized: customer accepted, or the dispute was formally resolved/closed -->
  <div style="padding:16px;background:rgba(16,185,129,.05);border:1px solid rgba(16,185,129,.2);border-radius:6px">
    <div style="font-size:13px;font-weight:600;color:var(--success);margin-bottom:8px">
      <?= $bookingCharge['status'] === 'accepted' ? '✅ You accepted this charge' : '✅ This charge is fully resolved' ?>
    </div>
    <div style="font-size:16px;font-weight:700;margin-bottom:6px">₵<?= number_format($bookingCharge['resolved_amount'], 2) ?></div>
    <?php if ((float)$bookingCharge['computed_amount'] != (float)$bookingCharge['resolved_amount']): ?>
    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Originally computed as ₵<?= number_format($bookingCharge['computed_amount'], 2) ?></div>
    <?php endif; ?>
    <?php if ($bookingCharge['decision_note']): ?>
    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px"><strong>Note:</strong> <?= e($bookingCharge['decision_note']) ?></div>
    <?php endif; ?>
    <div style="font-size:11px;color:var(--text-tertiary)"><?= format_datetime($bookingCharge['decided_at']) ?></div>
    <div style="font-size:11.5px;color:var(--text-secondary);margin-top:8px;padding-top:8px;border-top:1px solid rgba(16,185,129,.2)">This matter is fully settled — no further action is needed or possible here.</div>
  </div>

  <?php elseif ($bookingCharge || $comparison['total_charge'] > 0): ?>
  <!-- Still open — a decision may exist, but nothing here is final yet -->
  <?php if ($bookingCharge): ?>
  <div style="padding:14px 16px;background:rgba(59,110,248,.04);border:1px solid rgba(59,110,248,.15);border-radius:6px;margin-bottom:12px">
    <div style="font-size:12.5px;font-weight:600;color:var(--accent);margin-bottom:6px">
      <?= match ($bookingCharge['status']) {
          'approved' => '✅ Admin approved this charge',
          'adjusted' => '✏️ Admin adjusted this charge',
          'waived'   => '🚫 Admin waived this charge',
          'disputed' => '⚖️ This charge is under dispute',
          default    => 'Charge decision',
      } ?>
    </div>
    <div style="font-size:16px;font-weight:700;margin-bottom:4px">₵<?= number_format($bookingCharge['resolved_amount'], 2) ?></div>
    <?php if ((float)$bookingCharge['computed_amount'] != (float)$bookingCharge['resolved_amount']): ?>
    <div style="font-size:11.5px;color:var(--text-secondary)">Originally computed as ₵<?= number_format($bookingCharge['computed_amount'], 2) ?></div>
    <?php endif; ?>
    <?php if ($bookingCharge['decision_note']): ?>
    <div style="font-size:11.5px;color:var(--text-secondary);margin-top:4px"><strong>Note:</strong> <?= e($bookingCharge['decision_note']) ?></div>
    <?php endif; ?>
    <div style="font-size:10.5px;color:var(--text-tertiary);margin-top:6px">ℹ️ This is not final. You can still accept it below, or raise a dispute if you have concerns.</div>
  </div>
  <?php else: ?>
  <div style="font-size:13px;font-weight:600;color:var(--danger);margin-bottom:4px">💰 Potential Charges: ₵<?= number_format($comparison['total_charge'], 2) ?></div>
  <div style="font-size:12px;color:var(--text-secondary);margin-bottom:12px">If you disagree with any charges or believe the damage was pre-existing, you can file a dispute with supporting evidence.</div>
  <?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <button type="button" onclick="openAcceptModal(<?= $currentAmount ?>)" class="btn btn-success" style="font-size:12px">✅ Accept Charges</button>
    <?php if (!$openDispute): ?>
    <button type="button" onclick="openModal('disputeModal')" class="btn btn-warning" style="font-size:12px">⚖️ File Dispute</button>
    <?php else: ?>
    <span style="font-size:11.5px;color:var(--text-tertiary)">You have an open dispute — reply in the conversation above to add more, or accept the charge to close it out.</span>
    <?php endif; ?>
  </div>
  <div style="font-size:11px;color:var(--text-tertiary);margin-top:8px">You can accept or dispute this charge at any time — even after an admin decision — until it's fully resolved.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Accept Charge Modal -->
<div class="modal-overlay" id="acceptModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title">Confirm Charge Acceptance</div>
      <button class="modal-close" onclick="closeModal('acceptModal')">&times;</button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/api/charges/accept.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
      <div class="modal-body">
        <div style="text-align:center;margin-bottom:16px">
          <div style="font-size:13px;color:var(--text-secondary);margin-bottom:6px">You're about to accept a charge of</div>
          <div style="font-size:28px;font-weight:700;color:var(--accent)" id="acceptModalAmount">₵0.00</div>
        </div>
        <div style="padding:12px 14px;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:6px;font-size:12.5px;color:#92400e">
          ⚠️ By confirming, you're saying that you have <strong>no disputes or concerns</strong> about this amount. This action is final — once accepted, you won't be able to raise a dispute on this charge afterward.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('acceptModal')">Cancel</button>
        <button type="submit" class="btn btn-success">✅ Yes, I Accept — No Disputes</button>
      </div>
    </form>
  </div>
</div>

<!-- Dispute Modal -->
<div class="modal-overlay" id="disputeModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <div class="modal-title">File a Dispute</div>
      <button class="modal-close" onclick="closeModal('disputeModal')">&times;</button>
    </div>
    <form id="disputeForm" method="POST" action="<?= APP_URL ?>/api/disputes/file.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="booking_id" value="<?= $bookingId ?>">

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">What's this about?</label>
          <select class="form-input" name="category" required>
            <option value="damage">Damage Assessment</option>
            <option value="overcharge" selected>Charge Amount</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Subject</label>
          <input class="form-input" type="text" name="subject" placeholder="Brief summary of your dispute" required>
        </div>

        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-input" name="description" rows="4" placeholder="Explain your dispute in detail..." required></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Supporting Evidence (Photos)</label>
          <input type="file" name="evidence[]" accept="image/*" multiple style="display:none" id="evidenceInput" onchange="previewEvidence(this)">
          <div style="display:flex;gap:8px;margin-bottom:8px">
            <button type="button" class="btn btn-ghost" onclick="document.getElementById('evidenceInput').click()" style="font-size:12px">
              📷 Upload Photos
            </button>
          </div>
          <div id="evidencePreview" style="display:flex;gap:8px;flex-wrap:wrap"></div>
        </div>

        <div style="font-size:11.5px;color:var(--text-tertiary);padding:10px 12px;background:var(--surface);border-radius:6px">
          ℹ️ Our team will review and respond — you'll be able to keep the conversation going until it's resolved. Filing a dispute doesn't stop you from accepting the charge later if you change your mind.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('disputeModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Dispute</button>
      </div>
    </form>
  </div>
</div>

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

function openAcceptModal(amount) {
  document.getElementById('acceptModalAmount').textContent = '₵' + parseFloat(amount).toFixed(2);
  openModal('acceptModal');
}

function previewEvidence(input) {
  const preview = document.getElementById('evidencePreview');
  preview.innerHTML = '';

  if (input.files) {
    Array.from(input.files).forEach(file => {
      const reader = new FileReader();
      reader.onload = function(e) {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.width = '60px';
        img.style.height = '60px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '4px';
        img.style.border = '1px solid var(--border)';
        preview.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  }
}
</script>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
