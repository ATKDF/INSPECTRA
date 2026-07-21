<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';

$inspId = (int)($_GET['id'] ?? 0);
if (!$inspId) redirect(APP_URL . '/admin/inspections.php');

$inspection = DB::fetchOne("SELECT i.*, b.booking_ref, b.vehicle_id, b.customer_id, b.pickup_date, b.return_date, b.total_amount, v.make, v.model, v.registration_number, v.year, u.first_name, u.last_name, u.email, u.phone FROM inspections i JOIN bookings b ON i.booking_id=b.id JOIN vehicles v ON b.vehicle_id=v.id JOIN users u ON b.customer_id=u.id WHERE i.id=?", [$inspId]);
if (!$inspection) redirect(APP_URL . '/admin/inspections.php');

$isLocked  = is_inspection_locked($inspId);
$pageTitle = ucfirst($inspection['type']) . ' Inspection — ' . $inspection['booking_ref'];
$activePage = 'inspections';

$photos      = DB::fetchAll("SELECT * FROM inspection_photos WHERE inspection_id=? ORDER BY FIELD(photo_type,'front','rear','left','right','interior','dashboard','other')", [$inspId]);
$photosByType = [];
foreach ($photos as $p) $photosByType[$p['photo_type']] = $p;

$damages    = DB::fetchAll("SELECT dr.*, u.first_name, u.last_name FROM damage_reports dr LEFT JOIN users u ON dr.recorded_by=u.id WHERE dr.inspection_id=? ORDER BY dr.created_at", [$inspId]);
$signatures = DB::fetchAll("SELECT * FROM signatures WHERE inspection_id=?", [$inspId]);
$agentSig   = null; $custSig = null;
foreach ($signatures as $s) { if ($s['role']==='agent') $agentSig=$s; if ($s['role']==='customer') $custSig=$s; }

$activityLog = DB::fetchAll("SELECT ia.*, u.first_name, u.last_name FROM inspection_activity ia JOIN users u ON ia.user_id=u.id WHERE ia.inspection_id=? ORDER BY ia.created_at ASC", [$inspId]);

$photoTypes = ['front'=>'🚗 Front','rear'=>'🔙 Rear','left'=>'◀️ Left Side','right'=>'▶️ Right Side','interior'=>'🪑 Interior','dashboard'=>'🎛️ Dashboard'];
$allPhotoCaptured = count(array_intersect_key($photosByType, $photoTypes)) === count($photoTypes);

require_once __DIR__ . '/../includes/admin-header.php';
?>
<style>
.insp-steps{display:flex;margin-bottom:24px;background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;}
.insp-step{flex:1;padding:14px 8px;text-align:center;cursor:pointer;transition:background .15s;border-right:1px solid var(--border);position:relative;}
.insp-step:last-child{border-right:none;}
.insp-step.done{background:rgba(16,185,129,.06);}
.insp-step.active{background:rgba(59,110,248,.07);}
.step-num{width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;margin:0 auto 6px;background:rgba(0,0,0,.07);color:var(--text-secondary);}
.insp-step.done .step-num{background:var(--success);color:#fff;}
.insp-step.active .step-num{background:var(--accent);color:#fff;}
.step-label{font-size:10.5px;font-weight:500;color:var(--text-secondary);}
.insp-step.active .step-label{color:var(--accent);}
.insp-step.done .step-label{color:var(--success);}
.panel{display:none;}.panel.active{display:block;}
.step-nav{display:flex;gap:10px;margin-top:20px;}

/* Photo grid */
.photo-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.photo-slot{aspect-ratio:4/3;border-radius:var(--radius-sm);border:2px dashed var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;position:relative;overflow:hidden;background:rgba(0,0,0,.015);}
.photo-slot:hover:not(.locked){border-color:var(--accent);background:rgba(59,110,248,.03);}
.photo-slot.captured{border-style:solid;border-color:var(--success);background:rgba(16,185,129,.04);}
.photo-slot.active-slot{border-color:var(--accent);border-style:solid;animation:pulse-slot 1.5s infinite;}
@keyframes pulse-slot{0%,100%{box-shadow:0 0 0 0 rgba(59,110,248,.3);}50%{box-shadow:0 0 0 5px rgba(59,110,248,.08);}}
.photo-check{position:absolute;top:7px;right:7px;width:22px;height:22px;border-radius:50%;background:var(--success);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;z-index:2;}
.photo-ts{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.65);color:#fff;font-size:9px;padding:4px 7px;line-height:1.4;}

/* Damage zones */
.damage-zone{cursor:pointer;fill:rgba(59,110,248,.08);stroke:rgba(59,110,248,.25);stroke-width:1.5;transition:fill .15s;}
.damage-zone:not(.locked-zone):hover{fill:rgba(59,110,248,.2);stroke:rgba(59,110,248,.6);}
.damage-zone.has-damage{fill:rgba(239,68,68,.16);stroke:rgba(239,68,68,.55);}

/* Signature */
.sig-wrap{background:rgba(248,249,252,.9);border:1.5px dashed var(--border);border-radius:var(--radius-sm);height:160px;position:relative;overflow:hidden;cursor:crosshair;}
.sig-wrap canvas{position:absolute;inset:0;width:100%;height:100%;}
.sig-placeholder{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--text-tertiary);pointer-events:none;}
.sig-done{display:flex;align-items:center;justify-content:center;flex-direction:column;padding:16px;background:rgba(16,185,129,.05);border:1.5px solid rgba(16,185,129,.3);border-radius:var(--radius-sm);}

/* Timeline */
.activity-timeline{position:relative;padding-left:22px;}
.activity-timeline::before{content:'';position:absolute;left:7px;top:6px;bottom:6px;width:1.5px;background:var(--border);}
.al-item{position:relative;padding-bottom:16px;}
.al-item:last-child{padding-bottom:0;}
.al-dot{position:absolute;left:-16px;top:3px;width:12px;height:12px;border-radius:50%;background:var(--accent);border:2px solid #fff;box-shadow:0 0 0 1px var(--border);}
.al-time{font-size:10.5px;color:var(--text-tertiary);}
.al-action{font-size:12.5px;font-weight:500;margin:1px 0;}
.al-detail{font-size:11.5px;color:var(--text-secondary);}
.al-geo{font-size:10px;color:var(--text-tertiary);font-family:var(--mono);}

/* Camera preview modal enhancements */
#cameraPreviewModal video {
  transform: scaleX(-1); /* Mirror the camera view */
}
</style>

<!-- Header card -->
<div class="card" style="margin-bottom:18px;padding:18px 24px;">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div>
      <div style="font-size:18px;font-weight:600"><?= ucfirst($inspection['type']) ?> Inspection
        <?php if ($isLocked): ?><span style="margin-left:8px;padding:3px 10px;background:rgba(16,185,129,.1);color:var(--success);border-radius:20px;font-size:11px;font-weight:600">🔒 LOCKED</span><?php endif; ?>
      </div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:2px">
        <?= e($inspection['make'].' '.$inspection['model']) ?> •
        <span style="font-family:var(--mono)"><?= e($inspection['registration_number']) ?></span> •
        <?= e($inspection['first_name'].' '.$inspection['last_name']) ?> •
        Ref: <strong><?= e($inspection['booking_ref']) ?></strong>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="<?= APP_URL ?>/admin/inspection-activity.php?id=<?= $inspId ?>" class="btn btn-ghost" style="font-size:12px">📋 Activity Log</a>
      <?php if ($isLocked): ?>
        <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $inspId ?>" target="_blank" class="btn btn-ghost" style="font-size:12px">📄 Report</a>
        <a href="<?= APP_URL ?>/api/agreement/generate.php?booking_id=<?= $inspection['booking_id'] ?>" target="_blank" class="btn btn-ghost" style="font-size:12px">📋 Agreement</a>
      <?php endif; ?>
      <?= status_badge($inspection['status']) ?>
    </div>
  </div>
</div>

<!-- Step nav -->
<div class="insp-steps" id="stepNav">
  <?php foreach (['📸 Photos','🗺️ Damage','📋 Condition','✍️ Signatures','✅ Complete'] as $i=>$s): ?>
  <div class="insp-step <?= $i===0?'active':'' ?>" id="stepTab<?= $i+1 ?>" onclick="goStep(<?= $i+1 ?>)">
    <div class="step-num"><?= $i+1 ?></div>
    <div class="step-label"><?= $s ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ═══ PANEL 1: PHOTOS ═══ -->
<div class="panel active" id="panel1">
  <div class="card" style="padding:24px">
    <div style="margin-bottom:18px">
      <div class="section-title">📸 Photo Capture</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">
        Click any slot to capture — your camera will open first. All 6 views must be captured before continuing.
        <?php if ($isLocked): ?><span style="color:var(--danger);font-weight:600"> This inspection is locked — photos cannot be changed.</span><?php endif; ?>
      </div>
    </div>

    <!-- Geolocation bar -->
    <div id="geoBar" style="display:none;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:16px;font-size:12px;color:#059669">
      📍 Location: <span id="geoText">Detecting…</span>
    </div>
    <div id="geoErrorBar" style="display:none;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:16px;font-size:12px;color:#d97706">
      ⚠️ Location unavailable — photos will still be captured without geolocation.
    </div>

    <div class="photo-grid">
      <?php foreach ($photoTypes as $type => $label): ?>
      <?php $cap = $photosByType[$type] ?? null; ?>
      <div class="photo-slot <?= $cap?'captured':'' ?> <?= $isLocked?'locked':'' ?>"
           id="slot_<?= $type ?>"
           onclick="<?= $isLocked?'viewPhoto(\''.$type.'\')':'openCapture(\''.$type.'\')'?>">
        <?php if ($cap): ?>
          <img src="<?= e($cap['photo_url']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit" alt="<?= $type ?>">
          <div class="photo-check">✓</div>
          <div class="photo-ts">
            <?= e(substr($label,2)) ?> · <?= format_datetime($cap['taken_at'],'H:i d/M/Y') ?>
            <?php if ($cap['latitude']): ?><br>📍 <?= round($cap['latitude'],5) ?>, <?= round($cap['longitude'],5) ?><?php endif; ?>
            <?php if ($cap['address_label']): ?><br><?= e($cap['address_label']) ?><?php endif; ?>
          </div>
        <?php else: ?>
          <div style="font-size:30px;margin-bottom:8px"><?= explode(' ',$label)[0] ?></div>
          <div style="font-size:11.5px;font-weight:500;color:var(--text-secondary)"><?= trim(substr($label, mb_strlen(explode(' ',$label)[0]))) ?></div>
          <div style="font-size:10px;color:var(--text-tertiary);margin-top:4px">Tap to capture</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Progress indicator -->
    <div style="margin-top:16px">
      <?php $captured = count(array_intersect_key($photosByType, $photoTypes)); ?>
      <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:12px;color:var(--text-secondary)">
        <span><?= $captured ?>/<?= count($photoTypes) ?> photos captured</span>
        <?php if ($captured === count($photoTypes)): ?><span style="color:var(--success);font-weight:500">✅ All done!</span><?php endif; ?>
      </div>
      <div class="progress-bar"><div class="progress-fill" style="width:<?= ($captured/count($photoTypes))*100 ?>%"></div></div>
    </div>

    <div style="margin-top:14px;padding:10px 14px;background:rgba(59,110,248,.05);border-radius:var(--radius-sm);font-size:11.5px;color:var(--text-secondary)">
      📍 Geolocation + timestamp attached automatically. 📷 Camera opens first; tap "Choose File" to upload instead.
    </div>
  </div>

  <!-- Hidden forms -->
  <form id="photoForm" method="POST" action="<?= APP_URL ?>/api/inspections/upload-photo.php" enctype="multipart/form-data" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="inspection_id" value="<?= $inspId ?>">
    <input type="hidden" name="photo_type" id="currentPhotoType">
    <input type="hidden" name="latitude" id="photoLat">
    <input type="hidden" name="longitude" id="photoLng">
    <input type="hidden" name="address_label" id="photoAddress">
  </form>
  <!-- Camera input (capture=environment forces rear camera) -->
  <input type="file" id="cameraInput"  accept="image/*" capture="environment" style="display:none" onchange="handlePhotoFile(this,'camera')">
  <!-- Gallery/upload fallback -->
  <input type="file" id="galleryInput" accept="image/*"                       style="display:none" onchange="handlePhotoFile(this,'upload')">

  <div class="step-nav">
    <div class="spacer"></div>
    <button class="btn btn-primary" onclick="tryGoStep2()">Next: Damage Map →</button>
  </div>
</div>

<!-- ═══ PANEL 2: DAMAGE MAP ═══ -->
<div class="panel" id="panel2">
  <div class="grid-cols-60-40">
    <div class="card" style="padding:24px">
      <div class="section-title" style="margin-bottom:4px">🗺️ Damage Mapping</div>
      <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:18px">Click any zone to record damage<?= $isLocked?' (locked — view only)':'' ?></div>

      <div style="background:rgba(248,249,252,.9);border-radius:var(--radius);padding:20px">
        <svg viewBox="0 0 400 260" xmlns="http://www.w3.org/2000/svg" style="width:100%;max-width:420px;display:block;margin:0 auto">
          <!-- Car body -->
          <rect x="35" y="80" width="330" height="110" rx="14" fill="#e8edf5" stroke="#c5cde0" stroke-width="1.5"/>
          <!-- Roof / cabin -->
          <path d="M105 80 L118 42 L282 42 L295 80Z" fill="#d5dced" stroke="#c5cde0" stroke-width="1.5"/>
          <!-- Windows -->
          <rect x="122" y="45" width="62" height="33" rx="5" fill="#bfcae0" stroke="#aab8d4" stroke-width="1"/>
          <rect x="192" y="45" width="62" height="33" rx="5" fill="#bfcae0" stroke="#aab8d4" stroke-width="1"/>
          <!-- Wheels -->
          <circle cx="95"  cy="192" r="26" fill="#8899b4" stroke="#6b7a96" stroke-width="2"/>
          <circle cx="95"  cy="192" r="14" fill="#c5cde0"/>
          <circle cx="305" cy="192" r="26" fill="#8899b4" stroke="#6b7a96" stroke-width="2"/>
          <circle cx="305" cy="192" r="14" fill="#c5cde0"/>
          <!-- Headlights -->
          <rect x="26" y="97"  width="20" height="13" rx="3" fill="#fde68a" stroke="#f59e0b" stroke-width="1"/>
          <rect x="26" y="127" width="20" height="13" rx="3" fill="#fed7aa" stroke="#f97316" stroke-width="1"/>
          <!-- Tail lights -->
          <rect x="354" y="97"  width="20" height="13" rx="3" fill="#fecaca" stroke="#ef4444" stroke-width="1"/>
          <rect x="354" y="127" width="20" height="13" rx="3" fill="#fecaca" stroke="#ef4444" stroke-width="1"/>
          <!-- Clickable zones -->
          <?php
          $zones = [
            'hood'             => ['x'=>240,'y'=>82,'w'=>95,'h'=>60,'label'=>'Hood'],
            'trunk'            => ['x'=>65, 'y'=>82,'w'=>95,'h'=>60,'label'=>'Trunk'],
            'front_bumper'     => ['x'=>28, 'y'=>95,'w'=>38,'h'=>45,'label'=>'Frt Bumper'],
            'rear_bumper'      => ['x'=>334,'y'=>95,'w'=>38,'h'=>45,'label'=>'Rr Bumper'],
            'roof'             => ['x'=>110,'y'=>43,'w'=>180,'h'=>36,'label'=>'Roof'],
            'left_front_door'  => ['x'=>153,'y'=>82,'w'=>65,'h'=>82,'label'=>'L.Fr Door'],
            'left_rear_door'   => ['x'=>200,'y'=>82,'w'=>65,'h'=>82,'label'=>'L.Rr Door'],
            'right_front_door' => ['x'=>40, 'y'=>82,'w'=>65,'h'=>82,'label'=>'R.Fr Door'],
            'right_rear_door'  => ['x'=>90, 'y'=>82,'w'=>60,'h'=>82,'label'=>'R.Rr Door'],
            'windshield'       => ['x'=>122,'y'=>45,'w'=>62,'h'=>33,'label'=>'Windshield'],
            'rear_window'      => ['x'=>192,'y'=>45,'w'=>62,'h'=>33,'label'=>'Rr Window'],
            'front_left_wheel' => ['cx'=>95, 'cy'=>192,'r'=>25,'label'=>'FL Wheel'],
            'front_right_wheel'=> ['cx'=>305,'cy'=>192,'r'=>25,'label'=>'FR Wheel'],
            'undercarriage'    => ['x'=>130,'y'=>185,'w'=>140,'h'=>28,'label'=>'Under'],
            'left_mirror'      => ['x'=>155,'y'=>72, 'w'=>20,'h'=>14,'label'=>'L.Mirror'],
            'right_mirror'     => ['x'=>225,'y'=>72, 'w'=>20,'h'=>14,'label'=>'R.Mirror'],
          ];
          $damagedZones = array_column($damages,'zone');
          foreach ($zones as $zoneKey => $z):
            $hasDmg = in_array($zoneKey, $damagedZones);
            $cls = 'damage-zone'.($hasDmg?' has-damage':'').($isLocked?' locked-zone':'');
            $onclick = $isLocked?'':'onclick="openDamageModal(\''.$zoneKey.'\',\''.addslashes($z['label']).'\')"';
            if (isset($z['cx'])):
          ?>
          <circle class="<?= $cls ?>" cx="<?= $z['cx'] ?>" cy="<?= $z['cy'] ?>" r="<?= $z['r'] ?>" <?= $onclick ?>/>
          <?php else: ?>
          <rect class="<?= $cls ?>" x="<?= $z['x'] ?>" y="<?= $z['y'] ?>" width="<?= $z['w'] ?>" height="<?= $z['h'] ?>" rx="4" <?= $onclick ?>/>
          <?php endif;
          // Label
          $lx = isset($z['cx']) ? $z['cx'] : ($z['x'] + $z['w']/2);
          $ly = isset($z['cy']) ? $z['cy']+4 : ($z['y'] + $z['h']/2 + 3);
          ?>
          <text x="<?= $lx ?>" y="<?= $ly ?>" text-anchor="middle" font-size="7.5" fill="<?= $hasDmg?'#dc2626':'#555' ?>" font-weight="<?= $hasDmg?'600':'400' ?>" pointer-events="none"><?= $z['label'] ?></text>
          <?php endforeach; ?>
        </svg>
      </div>
      <div style="display:flex;gap:16px;margin-top:12px;font-size:11.5px">
        <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;background:rgba(59,110,248,.15);border:1.5px solid rgba(59,110,248,.4);border-radius:2px;display:inline-block"></span>No damage</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;background:rgba(239,68,68,.2);border:1.5px solid rgba(239,68,68,.6);border-radius:2px;display:inline-block"></span>Damage recorded</span>
      </div>
    </div>

    <!-- Damage list -->
    <div class="card" style="padding:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <div class="section-title">Recorded Damages (<?= count($damages) ?>)</div>
      </div>
      <?php if (empty($damages)): ?>
      <div style="text-align:center;padding:28px;color:var(--text-secondary);font-size:12.5px">✅ No damages recorded<br>Click zones on the diagram to add</div>
      <?php else: foreach ($damages as $dmg): ?>
      <div style="padding:11px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.14);border-radius:var(--radius-sm);margin-bottom:9px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div>
            <div style="font-size:12.5px;font-weight:500"><?= e(ucfirst(str_replace('_',' ',$dmg['zone']))) ?></div>
            <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px"><?= e(ucfirst($dmg['damage_type'])) ?> · <?= e(ucfirst($dmg['severity'])) ?></div>
            <?php if ($dmg['description']): ?><div style="font-size:11px;color:var(--text-tertiary);margin-top:2px"><?= e($dmg['description']) ?></div><?php endif; ?>
            <div style="font-size:10px;color:var(--text-tertiary);margin-top:3px">
              By <?= e(($dmg['first_name']??'').' '.($dmg['last_name']??'')) ?> · <?= format_datetime($dmg['created_at']??$dmg['recorded_at']??'') ?>
            </div>
          </div>
          <?php if (!$isLocked): ?>
          <form method="POST" action="<?= APP_URL ?>/api/inspections/delete-damage.php" onsubmit="return confirm('Remove?')">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="damage_id" value="<?= $dmg['id'] ?>">
            <input type="hidden" name="inspection_id" value="<?= $inspId ?>">
            <button type="submit" class="action-btn danger" style="padding:3px 8px;font-size:10px">✕</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <div class="step-nav">
    <button class="btn btn-ghost" onclick="goStep(1)">← Back</button>
    <div class="spacer"></div>
    <button class="btn btn-primary" onclick="goStep(3)">Next: Condition →</button>
  </div>
</div>

<!-- ═══ PANEL 3: CONDITION ═══ -->
<div class="panel" id="panel3">
  <div class="card" style="padding:24px">
    <div class="section-title" style="margin-bottom:18px">📋 Condition Assessment</div>
    <?php if ($isLocked): ?>
    <div style="padding:16px;background:rgba(16,185,129,.05);border-radius:var(--radius-sm);border:1px solid rgba(16,185,129,.2)">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        <div><div style="font-size:10.5px;color:var(--text-tertiary);text-transform:uppercase">Condition</div><div style="font-weight:500;margin-top:3px"><?= ucfirst($inspection['overall_condition']??'—') ?></div></div>
        <div><div style="font-size:10.5px;color:var(--text-tertiary);text-transform:uppercase">Fuel</div><div style="font-weight:500;margin-top:3px"><?= ucfirst(str_replace('_',' ',$inspection['fuel_level']??'—')) ?></div></div>
        <div><div style="font-size:10.5px;color:var(--text-tertiary);text-transform:uppercase">Mileage</div><div style="font-weight:500;margin-top:3px"><?= $inspection['mileage_at_inspection']?number_format($inspection['mileage_at_inspection']).' km':'—' ?></div></div>
      </div>
      <?php if ($inspection['agent_notes']): ?><div style="margin-top:12px;font-size:12.5px;color:var(--text-secondary)"><strong>Notes:</strong> <?= e($inspection['agent_notes']) ?></div><?php endif; ?>
    </div>
    <?php else: ?>
    <form id="conditionForm" onsubmit="submitConditionForm(event)">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="inspection_id" value="<?= $inspId ?>">
      <input type="hidden" name="geo_lat" id="condGeoLat">
      <input type="hidden" name="geo_lng" id="condGeoLng">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Overall Condition *</label>
          <select class="form-input" name="overall_condition" required>
            <option value="">Select…</option>
            <?php foreach (['excellent','good','fair','poor'] as $c): ?><option value="<?=$c?>" <?=$inspection['overall_condition']===$c?'selected':''?>><?=ucfirst($c)?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Fuel Level</label>
          <select class="form-input" name="fuel_level">
            <?php foreach (['full','three_quarter','half','quarter','empty'] as $f): ?><option value="<?=$f?>" <?=$inspection['fuel_level']===$f?'selected':''?>><?=ucfirst(str_replace('_',' ',$f))?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Mileage at Inspection (km)</label>
        <input class="form-input" type="number" name="mileage_at_inspection" value="<?= $inspection['mileage_at_inspection'] ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Agent Notes</label>
        <textarea class="form-input" name="agent_notes" rows="4" placeholder="Any observations…"><?= e($inspection['agent_notes']??'') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">💾 Save Condition</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="step-nav">
    <button class="btn btn-ghost" onclick="goStep(2)">← Back</button>
    <div class="spacer"></div>
    <button class="btn btn-primary" onclick="goStep(4)">Next: Signatures →</button>
  </div>
</div>

<!-- ═══ PANEL 4: SIGNATURES ═══ -->
<div class="panel" id="panel4">
  <div class="grid-2">
    <!-- Agent -->
    <div class="card" style="padding:24px">
      <div class="section-title" style="margin-bottom:4px">✍️ Agent Signature</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:14px"><?= e(Auth::name()) ?></div>
      <?php if ($agentSig): ?>
      <div class="sig-done">
        <img src="<?= e($agentSig['signature_url']) ?>" style="max-height:90px;max-width:100%;border:1px solid var(--border);border-radius:6px;background:#fff;padding:4px" alt="Agent sig">
        <div style="font-size:11px;color:var(--success);margin-top:8px;font-weight:500">✅ Signed <?= format_datetime($agentSig['signed_at']) ?></div>
        <div style="font-size:10px;color:var(--text-tertiary);margin-top:2px">IP: <?= e($agentSig['ip_address']??'') ?></div>
      </div>
      <?php elseif (!$isLocked): ?>
      <div class="sig-wrap" id="agentSigPad">
        <canvas id="agentCanvas"></canvas>
        <div class="sig-placeholder" id="agentPlaceholder">↙ Sign here</div>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button class="action-btn" onclick="clearSig('agent')">🗑 Clear</button>
        <button class="action-btn primary" onclick="saveSig('agent',<?= $inspId ?>)">💾 Save Signature</button>
      </div>
      <?php else: ?>
      <div style="color:var(--text-tertiary);font-size:13px;padding:20px;text-align:center">Not signed</div>
      <?php endif; ?>
    </div>

    <!-- Customer -->
    <div class="card" style="padding:24px">
      <div class="section-title" style="margin-bottom:4px">✍️ Customer Signature</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:14px"><?= e($inspection['first_name'].' '.$inspection['last_name']) ?></div>
      <?php if ($custSig): ?>
      <div class="sig-done">
        <img src="<?= e($custSig['signature_url']) ?>" style="max-height:90px;max-width:100%;border:1px solid var(--border);border-radius:6px;background:#fff;padding:4px" alt="Cust sig">
        <div style="font-size:11px;color:var(--success);margin-top:8px;font-weight:500">✅ Signed <?= format_datetime($custSig['signed_at']) ?></div>
        <div style="font-size:10px;color:var(--text-tertiary);margin-top:2px">IP: <?= e($custSig['ip_address']??'') ?></div>
      </div>
      <?php elseif (!$isLocked): ?>
      <div class="sig-wrap" id="custSigPad">
        <canvas id="custCanvas"></canvas>
        <div class="sig-placeholder" id="custPlaceholder">↙ Customer signs here</div>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button class="action-btn" onclick="clearSig('cust')">🗑 Clear</button>
        <button class="action-btn primary" onclick="saveSig('cust',<?= $inspId ?>)">💾 Save Signature</button>
      </div>
      <?php else: ?>
      <div style="color:var(--text-tertiary);font-size:13px;padding:20px;text-align:center">Not signed</div>
      <?php endif; ?>
    </div>
  </div>

  <div style="margin-top:14px;padding:12px 16px;background:rgba(59,110,248,.05);border-radius:var(--radius-sm);font-size:12px;color:var(--text-secondary)">
    🔒 By signing, both parties confirm the vehicle condition as documented. Once the inspection is completed, all data is locked and cannot be altered.
  </div>
  <div class="step-nav">
    <button class="btn btn-ghost" onclick="goStep(3)">← Back</button>
    <div class="spacer"></div>
    <button class="btn btn-primary" onclick="goStep(5)">Next: Complete →</button>
  </div>
</div>

<!-- ═══ PANEL 5: COMPLETE ═══ -->
<div class="panel" id="panel5">
  <?php if ($isLocked): ?>
  <div class="card" style="padding:48px;text-align:center">
    <div style="font-size:60px;margin-bottom:16px">🔒</div>
    <div style="font-size:22px;font-weight:700;margin-bottom:8px">Inspection Complete & Locked</div>
    <div style="font-size:14px;color:var(--text-secondary);margin-bottom:28px">All data is immutable. No changes can be made to this inspection.</div>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $inspId ?>" target="_blank" class="btn btn-primary">📄 View PDF Report</a>
      <a href="<?= APP_URL ?>/api/agreement/generate.php?booking_id=<?= $inspection['booking_id'] ?>" target="_blank" class="btn btn-ghost">📋 View Agreement</a>
      <a href="<?= APP_URL ?>/admin/inspections.php" class="btn btn-ghost">← All Inspections</a>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:56px;margin-bottom:14px">📋</div>
    <div style="font-size:20px;font-weight:700;margin-bottom:8px">Ready to Complete</div>
    <div style="font-size:14px;color:var(--text-secondary);margin-bottom:24px">
      ⚠️ Once completed, all inspection data will be <strong>permanently locked</strong> and cannot be modified.
    </div>
    <!-- Checklist -->
    <div style="max-width:380px;margin:0 auto 28px;text-align:left;background:var(--surface);border-radius:var(--radius-sm);padding:16px">
      <?php
      $checks = [
        ['label'=>'All 6 photos captured', 'ok'=>$allPhotoCaptured],
        ['label'=>'Condition assessed', 'ok'=>!empty($inspection['overall_condition'])],
        ['label'=>'Agent signed', 'ok'=>$agentSig !== null],
        ['label'=>'Customer signed', 'ok'=>$custSig !== null],
      ];
      foreach ($checks as $c): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px">
        <span style="font-size:16px"><?= $c['ok']?'✅':'❌' ?></span>
        <span style="<?= $c['ok']?'':'color:var(--danger)' ?>"><?= $c['label'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <form method="POST" action="<?= APP_URL ?>/api/inspections/complete.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="inspection_id" value="<?= $inspId ?>">
      <input type="hidden" name="geolat" id="completeLat">
      <input type="hidden" name="geolng" id="completeLng">
      <button type="submit" class="btn btn-primary" style="font-size:15px;padding:13px 36px" <?= $custSig===null?'disabled title="Waiting for the customer to sign before this inspection can be completed"':'' ?>>🏁 Complete & Lock Inspection</button>
    </form>
    <?php if ($custSig === null): ?>
    <div style="margin-top:14px;font-size:12.5px;color:var(--text-tertiary)">⏳ The customer hasn't signed yet — this inspection can't be completed until they acknowledge it.</div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Activity Timeline -->
  <?php if (!empty($activityLog)): ?>
  <div class="card" style="padding:24px;margin-top:20px">
    <div class="section-title" style="margin-bottom:16px">📅 Inspection Timeline</div>
    <div class="activity-timeline">
      <?php foreach ($activityLog as $al): ?>
      <div class="al-item">
        <div class="al-dot" style="background:<?= str_contains($al['action'],'complete')?'var(--success)':(str_contains($al['action'],'damage')?'var(--danger)':'var(--accent)') ?>"></div>
        <div class="al-time"><?= format_datetime($al['created_at'],'d M Y, H:i:s') ?></div>
        <div class="al-action"><?= e($al['action']) ?> — <span style="color:var(--text-secondary)"><?= e($al['first_name'].' '.$al['last_name']) ?></span></div>
        <?php if ($al['detail']): ?><div class="al-detail"><?= e($al['detail']) ?></div><?php endif; ?>
        <?php if ($al['latitude']): ?><div class="al-geo">📍 <?= $al['latitude'] ?>, <?= $al['longitude'] ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="step-nav">
    <?php if (!$isLocked): ?><button class="btn btn-ghost" onclick="goStep(4)">← Back</button><?php endif; ?>
  </div>
</div>

<!-- Damage modal -->
<div class="modal-overlay" id="damageModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Record Damage — <span id="damageZoneName"></span></span>
      <button class="modal-close" onclick="closeModal('damageModal')">✕</button>
    </div>
    <form id="damageForm" onsubmit="submitDamageForm(event)">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="inspection_id" value="<?= $inspId ?>">
      <input type="hidden" name="zone" id="damageZoneInput">
      <input type="hidden" name="geo_lat" id="dmgLat">
      <input type="hidden" name="geo_lng" id="dmgLng">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Damage Type *</label>
            <select class="form-input" name="damage_type" required>
              <option value="scratch">Scratch</option><option value="dent">Dent</option>
              <option value="crack">Crack</option><option value="chip">Chip</option>
              <option value="tear">Tear/Cut</option><option value="broken">Broken Part</option>
              <option value="missing">Missing Part</option><option value="burn">Burn Mark</option>
              <option value="rust">Rust</option><option value="stain">Stain</option>
              <option value="flood">Flood Damage</option><option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Severity *</label>
            <select class="form-input" name="severity" required>
              <option value="minor">Minor</option><option value="moderate">Moderate</option><option value="severe">Severe</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-input" name="description" rows="2" placeholder="Describe the damage…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Photo Evidence</label>
          <input class="form-input" type="file" name="damage_photo" accept="image/*">
        </div>
        <div class="form-group">
          <label class="form-label">Estimated Repair Cost (₵) *</label>
          <input class="form-input" type="number" name="repair_cost" step="0.01" min="0.01" placeholder="e.g. 150.00" required>
          <div class="form-hint">Needed to compute a charge for this damage — it won't show up for the customer to accept or dispute without one.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('damageModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">⚠️ Record Damage</button>
      </div>
    </form>
  </div>
</div>

<!-- Photo capture choice modal -->
<div class="modal-overlay" id="captureChoiceModal">
  <div class="modal" style="max-width:360px">
    <div class="modal-header">
      <span class="modal-title">Capture Photo — <span id="captureSlotName"></span></span>
      <button class="modal-close" onclick="closeModal('captureChoiceModal')">✕</button>
    </div>
    <div class="modal-body">
      <button onclick="useCamera()" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:12px;font-size:14px;padding:14px">
        📷 Open Camera
      </button>
      <div style="text-align:center;font-size:12px;color:var(--text-secondary);margin-bottom:12px">— or —</div>
      <button onclick="useGallery()" class="btn btn-ghost" style="width:100%;justify-content:center;font-size:14px;padding:14px">
        🖼️ Upload from Device
      </button>
    </div>
  </div>
</div>

<script>
// ── Geolocation ────────────────────────────────────────────────────────────
let geoLat = null, geoLng = null, geoAddress = '';
function initGeo() {
  if (!navigator.geolocation) { document.getElementById('geoErrorBar').style.display='block'; return; }
  navigator.geolocation.getCurrentPosition(pos => {
    geoLat = pos.coords.latitude;
    geoLng = pos.coords.longitude;
    document.getElementById('geoBar').style.display = 'block';
    document.getElementById('geoText').textContent = geoLat.toFixed(5) + ', ' + geoLng.toFixed(5);
    // Reverse geocode (best effort)
    fetch(`https://nominatim.openstreetmap.org/reverse?lat=${geoLat}&lon=${geoLng}&format=json`)
      .then(r=>r.json()).then(d=>{
        geoAddress = d.display_name || '';
        document.getElementById('geoText').textContent = geoAddress || (geoLat.toFixed(5)+', '+geoLng.toFixed(5));
      }).catch(()=>{});
    document.getElementById('completeLat').value = geoLat;
    document.getElementById('completeLng').value = geoLng;
  }, () => { document.getElementById('geoErrorBar').style.display='block'; });
}
initGeo();

// ── Step nav ────────────────────────────────────────────────────────────────
let currentStep = 1;
function goStep(n) {
  document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
  document.getElementById('panel'+n).classList.add('active');
  document.querySelectorAll('.insp-step').forEach((el,i)=>{
    el.classList.remove('active','done');
    if (i+1<n) el.classList.add('done');
    if (i+1===n) el.classList.add('active');
  });
  currentStep = n;
  window.scrollTo({top:0,behavior:'smooth'});
}
function tryGoStep2() {
  const captured = <?= count(array_intersect_key($photosByType, $photoTypes)) ?>;
  const required = <?= count($photoTypes) ?>;
  <?php if ($isLocked): ?>
  goStep(2);
  <?php else: ?>
  if (captured < required) {
    showToast('Please capture all ' + required + ' photos before continuing.', 'error');
    return;
  }
  goStep(2);
  <?php endif; ?>
}

// ── Photo capture ────────────────────────────────────────────────────────────
let currentType = '';
let cameraStream = null;
const photoLabels = {
  'front': 'Front',
  'rear': 'Rear',
  'left': 'Left Side',
  'right': 'Right Side',
  'interior': 'Interior',
  'dashboard': 'Dashboard'
};
const photoKeys = ['front', 'rear', 'left', 'right', 'interior', 'dashboard'];

function openCapture(type) {
  currentType = type;
  const idx = photoKeys.indexOf(type);
  const photoLabel = photoLabels[type] || type;
  document.getElementById('captureSlotName').textContent = photoLabel;
  openModal('captureChoiceModal');
}

function useCamera() {
  closeModal('captureChoiceModal');
  showToast('📷 Accessing camera...', 'default');
  
  // Try to access device camera
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    showToast('Camera not supported on this device. Using file upload...', 'error');
    useGallery();
    return;
  }
  
  navigator.mediaDevices.getUserMedia({ 
    video: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1440 } },
    audio: false 
  })
  .then(stream => {
    cameraStream = stream;
    openCameraPreview();
  })
  .catch(err => {
    console.error('Camera error:', err.name, err.message);
    let errorMsg = 'Camera access denied. ';
    if (err.name === 'NotAllowedError') {
      errorMsg += 'Please enable camera permissions in your device settings.';
    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
      errorMsg += 'No camera found on this device.';
    } else if (err.name === 'NotReadableError' || err.name === 'SecurityError') {
      errorMsg += 'Camera is already in use or blocked.';
    }
    showToast(errorMsg, 'error');
    useGallery();
  });
}

function openCameraPreview() {
  // Create modal overlay for camera preview
  const modal = document.createElement('div');
  modal.id = 'cameraPreviewModal';
  modal.className = 'modal-overlay open';
  modal.style.zIndex = '300';
  const photoLabel = photoLabels[photoKeys.indexOf(currentType)] || currentType;
  modal.innerHTML = `
    <div class="modal" style="max-width:600px;width:90%;max-height:90vh;display:flex;flex-direction:column">
      <div class="modal-header">
        <span class="modal-title">📷 Capture Photo — <span>${photoLabel}</span></span>
        <button class="modal-close" onclick="stopCamera()">✕</button>
      </div>
      <div class="modal-body" style="flex:1;overflow:hidden;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#000">
        <video id="cameraVideo" autoplay playsinline style="width:100%;height:100%;object-fit:cover;border-radius:8px"></video>
        <div id="cameraLoading" style="position:absolute;color:#fff;font-size:14px;">Starting camera...</div>
      </div>
      <div class="modal-footer">
        <button onclick="stopCamera()" class="btn btn-ghost">Cancel</button>
        <button onclick="captureFromCamera()" class="btn btn-primary">📸 Capture</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  
  // Attach stream to video
  const video = document.getElementById('cameraVideo');
  video.srcObject = cameraStream;
  video.onloadedmetadata = () => {
    document.getElementById('cameraLoading').style.display = 'none';
    video.play();
  };
}

function captureFromCamera() {
  const video = document.getElementById('cameraVideo');
  const canvas = document.createElement('canvas');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);
  
  canvas.toBlob(blob => {
    const input = document.createElement('input');
    input.type = 'file';
    const file = new File([blob], 'camera_photo.jpg', { type: 'image/jpeg' });
    const list = new DataTransfer();
    list.items.add(file);
    input.files = list.files;
    handlePhotoFile(input, 'camera');
    stopCamera();
  }, 'image/jpeg', 0.95);
}

function stopCamera() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(track => track.stop());
    cameraStream = null;
  }
  const modal = document.getElementById('cameraPreviewModal');
  if (modal) modal.remove();
}

function useGallery() {
  closeModal('captureChoiceModal');
  showToast('Choose a photo from your device', 'default');
  document.getElementById('galleryInput').click();
}

function handlePhotoFile(input, method) {
  if (!input.files[0]) {
    // File input cancelled
    return;
  }
  
  const file = input.files[0];
  showToast('📤 Uploading photo...', 'default');
  
  const fd = new FormData(document.getElementById('photoForm'));
  fd.set('photo', file);
  fd.set('photo_type', currentType);
  fd.set('latitude', geoLat || '');
  fd.set('longitude', geoLng || '');
  fd.set('address_label', geoAddress || '');
  fd.set('capture_method', method);
  
  // Required to fix form reference
  fd.set('csrf_token', '<?= csrf_token() ?>');
  fd.set('inspection_id', '<?= $inspId ?>');
  input.value = '';

  // Preview immediately in slot
  const slot = document.getElementById('slot_' + currentType);
  const reader = new FileReader();
  reader.onload = e => {
    slot.innerHTML = `<img src="${e.target.result}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit"><div class="photo-check">✓</div>`;
    slot.classList.add('captured');
  };
  reader.readAsDataURL(file);

  fetch('<?= APP_URL ?>/api/inspections/upload-photo.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showToast('✅ Photo saved!', 'success');
        setTimeout(() => location.reload(), 1200);
      } else {
        showToast(d.error || '❌ Upload failed', 'error');
        slot.classList.remove('captured');
        slot.innerHTML = slot.innerHTML.substring(0, slot.innerHTML.lastIndexOf('<div class="photo-check">'));
      }
    })
    .catch(e => {
      console.error('Upload error:', e);
      showToast('❌ Network error', 'error');
      slot.classList.remove('captured');
    });
}

function viewPhoto(type) {
  // This will be called if inspection is locked (photo exists)
  const photoElements = {
    'front': document.getElementById('slot_front'),
    'rear': document.getElementById('slot_rear'),
    'left': document.getElementById('slot_left'),
    'right': document.getElementById('slot_right'),
    'interior': document.getElementById('slot_interior'),
    'dashboard': document.getElementById('slot_dashboard')
  };
  const img = photoElements[type]?.querySelector('img');
  if (img && img.src) {
    window.open(img.src, '_blank');
  }
}

// ── Damage modal ─────────────────────────────────────────────────────────────
function openDamageModal(zone, label) {
  document.getElementById('damageZoneInput').value = zone;
  document.getElementById('damageZoneName').textContent = label;
  document.getElementById('dmgLat').value = geoLat||'';
  document.getElementById('dmgLng').value = geoLng||'';
  openModal('damageModal');
}

// ── Signature pads ────────────────────────────────────────────────────────────
function initCanvas(id) {
  const canvas = document.getElementById(id);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const pad = canvas.parentElement;
  function resize() { canvas.width=pad.offsetWidth; canvas.height=pad.offsetHeight; }
  resize();
  new ResizeObserver(resize).observe(pad);
  let drawing=false, lx=0, ly=0;
  function draw(x,y){ctx.beginPath();ctx.moveTo(lx,ly);ctx.lineTo(x,y);ctx.strokeStyle='#1a1f36';ctx.lineWidth=2.5;ctx.lineCap='round';ctx.lineJoin='round';ctx.stroke();lx=x;ly=y;}
  function pt(e){const r=canvas.getBoundingClientRect();return e.touches?[e.touches[0].clientX-r.left,e.touches[0].clientY-r.top]:[e.clientX-r.left,e.clientY-r.top];}
  canvas.addEventListener('mousedown',e=>{drawing=true;[lx,ly]=pt(e);const ph=document.getElementById(id.replace('Canvas','Placeholder'));if(ph)ph.style.display='none';});
  canvas.addEventListener('mousemove',e=>{if(!drawing)return;const[x,y]=pt(e);draw(x,y);});
  canvas.addEventListener('mouseup',()=>drawing=false);
  canvas.addEventListener('touchstart',e=>{e.preventDefault();drawing=true;[lx,ly]=pt(e);const ph=document.getElementById(id.replace('Canvas','Placeholder'));if(ph)ph.style.display='none';},{passive:false});
  canvas.addEventListener('touchmove',e=>{e.preventDefault();if(!drawing)return;const[x,y]=pt(e);draw(x,y);},{passive:false});
  canvas.addEventListener('touchend',()=>drawing=false);
}
initCanvas('agentCanvas');
initCanvas('custCanvas');

function clearSig(who){
  const c=document.getElementById(who==='agent'?'agentCanvas':'custCanvas');
  if(c){c.getContext('2d').clearRect(0,0,c.width,c.height);}
  const ph=document.getElementById(who==='agent'?'agentPlaceholder':'custPlaceholder');
  if(ph)ph.style.display='';
}

function saveSig(who, inspId) {
  const canvas = document.getElementById(who==='agent'?'agentCanvas':'custCanvas');
  // Check canvas is not blank
  const blank = !canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data.some(v=>v!==0);
  if (blank) { showToast('Please draw your signature first','error'); return; }
  canvas.toBlob(blob=>{
    const fd=new FormData();
    fd.append('inspection_id',inspId);
    fd.append('role',who==='agent'?'agent':'customer');
    fd.append('signature',blob,'sig.png');
    fd.append('geo_lat', geoLat||'');
    fd.append('geo_lng',geoLng||'');
    fetch('<?= APP_URL ?>/api/inspections/save-signature.php',{method:'POST',body:fd})
      .then(r=>{
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.text().then(text => {
          try { return JSON.parse(text); }
          catch(e) { throw new Error('Invalid response: ' + text.substring(0, 100)); }
        });
      })
      .then(d=>{
        if(d.success){
          showToast('✅ '+d.message,'success');
          setTimeout(()=>location.reload(),1200);
        }
        else showToast('❌ '+(d.error||d.message||'Failed'),'error');
      })
      .catch(e=>{
        console.error('Signature error:', e);
        showToast('❌ Error: ' + e.message, 'error');
      });
  },'image/png');
}

// ── AJAX Form Submissions ────────────────────────────────────────────────────
function submitDamageForm(e) {
  e.preventDefault();
  const form = document.getElementById('damageForm');
  const fd = new FormData(form);
  fd.set('geo_lat', geoLat || '');
  fd.set('geo_lng', geoLng || '');
  
  showToast('⏳ Recording damage...', 'default');
  fetch('<?= APP_URL ?>/api/inspections/add-damage.php', {method:'POST', body:fd})
    .then(r=>{
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.text().then(text => {
        try { return JSON.parse(text); }
        catch(e) { throw new Error('Invalid response: ' + text.substring(0, 100)); }
      });
    })
    .then(d=>{
      if(d.success){
        showToast('✅ ' + d.message, 'success');
        form.reset();
        closeModal('damageModal');
        setTimeout(() => location.reload(), 500);
      } else {
        showToast('❌ ' + (d.error || d.message || 'Failed'), 'error');
      }
    })
    .catch(e=>{
      console.error('Damage form error:', e);
      showToast('❌ Error: ' + e.message, 'error');
    });
}

function submitConditionForm(e) {
  e.preventDefault();
  const form = document.getElementById('conditionForm');
  const fd = new FormData(form);
  fd.set('geo_lat', geoLat || '');
  fd.set('geo_lng', geoLng || '');
  
  showToast('⏳ Saving condition...', 'default');
  fetch('<?= APP_URL ?>/api/inspections/save-condition.php', {method:'POST', body:fd})
    .then(r=>{
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.text().then(text => {
        try { return JSON.parse(text); }
        catch(e) { throw new Error('Invalid response: ' + text.substring(0, 100)); }
      });
    })
    .then(d=>{
      if(d.success){
        showToast('✅ ' + d.message, 'success');
        setTimeout(() => location.reload(), 500);
      } else {
        showToast('❌ ' + (d.error || d.message || 'Failed'), 'error');
      }
    })
    .catch(e=>{
      console.error('Condition form error:', e);
      showToast('❌ Error: ' + e.message, 'error');
    });
}
</script>

<?php require_once __DIR__ . "/../includes/admin-footer.php"; ?>
