<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireCustomer();

$inspId = (int)($_GET['id'] ?? 0);
if (!$inspId) redirect(APP_URL . '/customer/inspections.php');

// Verify customer owns this inspection (through booking)
$inspection = DB::fetchOne("
  SELECT i.*, b.booking_ref, b.vehicle_id, b.customer_id, b.pickup_date, b.return_date,
         b.total_amount, v.make, v.model, v.registration_number, v.year,
         u.first_name, u.last_name, u.email, u.phone
  FROM inspections i
  JOIN bookings b ON i.booking_id=b.id
  JOIN vehicles v ON b.vehicle_id=v.id
  JOIN users u ON b.customer_id=u.id
  WHERE i.id=? AND b.customer_id=?
", [$inspId, Auth::id()]);

if (!$inspection) redirect(APP_URL . '/customer/inspections.php');

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

require_once __DIR__ . '/../includes/customer-header.php';
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
.photo-slot{aspect-ratio:4/3;border-radius:var(--radius-sm);border:2px dashed var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;overflow:hidden;background:rgba(0,0,0,.015);}
.photo-slot.captured{border-style:solid;border-color:var(--success);background:rgba(16,185,129,.04);}
.photo-check{position:absolute;top:7px;right:7px;width:22px;height:22px;border-radius:50%;background:var(--success);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;z-index:2;}
.photo-ts{position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.65);color:#fff;font-size:9px;padding:4px 7px;line-height:1.4;}

/* Damage zones */
.damage-zone{fill:rgba(59,110,248,.08);stroke:rgba(59,110,248,.25);stroke-width:1.5;}
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

/* Info box */
.info-box{padding:14px;background:rgba(59,110,248,.05);border:1px solid rgba(59,110,248,.2);border-radius:var(--radius-sm);font-size:12.5px;color:var(--text-secondary);}
.info-box.warning{background:rgba(245,158,11,.05);border-color:rgba(245,158,11,.2);color:#92400e;}
.info-box.danger{background:rgba(239,68,68,.05);border-color:rgba(239,68,68,.2);color:#991b1b;}
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
        Ref: <strong><?= e($inspection['booking_ref']) ?></strong>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="<?= APP_URL ?>/customer/inspection-activity.php?id=<?= $inspId ?>" class="btn btn-ghost" style="font-size:12px">📋 Activity Log</a>
      <?php if ($isLocked): ?>
        <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $inspId ?>" target="_blank" class="btn btn-ghost" style="font-size:12px">📄 Report</a>
      <?php endif; ?>
      <?= status_badge($inspection['status']) ?>
    </div>
  </div>
</div>

<!-- Status info -->
<?php if ($isLocked): ?>
<div class="info-box" style="margin-bottom:18px">
  🔒 This inspection has been completed and locked. You can view all data below.
</div>
<?php elseif ($custSig): ?>
<div class="info-box" style="margin-bottom:18px">
  ✅ You've signed off on this inspection. The admin will finalize the report once their side is complete.
</div>
<?php elseif ($agentSig): ?>
<div class="info-box warning" style="margin-bottom:18px">
  ✍️ The agent has completed their part — please review the details below and add your signature to acknowledge the inspection.
</div>
<?php else: ?>
<div class="info-box" style="margin-bottom:18px">
  📝 This inspection is being carried out by our team. You can follow its progress here in real time — you'll be able to sign once the agent's part is complete.
</div>
<?php endif; ?>

<!-- Step nav -->
<div class="insp-steps" id="stepNav">
  <?php foreach (['📸 Photos','🗺️ Damage','📋 Condition','✍️ Signatures','ℹ️ Status'] as $i=>$s): ?>
  <div class="insp-step <?= $i===0?'active':'' ?>" id="stepTab<?= $i+1 ?>" onclick="goStep(<?= $i+1 ?>)">
    <div class="step-num"><?= $i+1 ?></div>
    <div class="step-label"><?= $s ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ═══ PANEL 1: PHOTOS (read-only) ═══ -->
<div class="panel active" id="panel1">
  <div class="card" style="padding:24px">
    <div style="margin-bottom:18px">
      <div class="section-title">📸 Photo Capture</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">
        Photos are captured by the inspecting agent. This view updates as each one is added.
      </div>
    </div>

    <div class="photo-grid">
      <?php foreach ($photoTypes as $type => $label): ?>
      <?php $cap = $photosByType[$type] ?? null; ?>
      <div class="photo-slot <?= $cap?'captured':'' ?>" id="slot_<?= $type ?>" onclick="<?= $cap ? "viewPhoto('".$type."')" : '' ?>" style="<?= $cap?'cursor:pointer':'' ?>">
        <?php if ($cap): ?>
          <img src="<?= e($cap['photo_url']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit" alt="<?= $type ?>">
          <div class="photo-check">✓</div>
          <div class="photo-ts">
            <?= e(substr($label,2)) ?> · <?= format_datetime($cap['taken_at'],'H:i d/M/Y') ?>
            <?php if ($cap['latitude']): ?><br>📍 <?= round($cap['latitude'],5) ?>, <?= round($cap['longitude'],5) ?><?php endif; ?>
            <?php if ($cap['address_label']): ?><br><?= e($cap['address_label']) ?><?php endif; ?></div>
        <?php else: ?>
          <div style="font-size:30px;margin-bottom:8px"><?= explode(' ',$label)[0] ?></div>
          <div style="font-size:11.5px;font-weight:500;color:var(--text-secondary)"><?= trim(substr($label, mb_strlen(explode(' ',$label)[0]))) ?></div>
          <div style="font-size:10px;color:var(--text-tertiary);margin-top:4px">Not yet captured</div>
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
  </div>

  <div class="step-nav">
    <div class="spacer"></div>
    <button class="btn btn-primary" onclick="goStep(2)">Next: Damage Map →</button>
  </div>
</div>

<!-- ═══ PANEL 2: DAMAGE MAP (read-only) ═══ -->
<div class="panel" id="panel2">
  <div class="grid-cols-60-40">
    <div class="card" style="padding:24px">
      <div class="section-title" style="margin-bottom:4px">🗺️ Damage Mapping</div>
      <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:18px">Zones recorded by the inspecting agent</div>

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
          <!-- Zones -->
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
            $cls = 'damage-zone'.($hasDmg?' has-damage':'');
            if (isset($z['cx'])):
          ?>
          <circle class="<?= $cls ?>" cx="<?= $z['cx'] ?>" cy="<?= $z['cy'] ?>" r="<?= $z['r'] ?>"/>
          <?php else: ?>
          <rect class="<?= $cls ?>" x="<?= $z['x'] ?>" y="<?= $z['y'] ?>" width="<?= $z['w'] ?>" height="<?= $z['h'] ?>" rx="4"/>
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
      <div style="text-align:center;padding:28px;color:var(--text-secondary);font-size:12.5px">✅ No damages recorded</div>
      <?php else: foreach ($damages as $dmg): ?>
      <div style="padding:11px;background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.14);border-radius:var(--radius-sm);margin-bottom:9px">
        <div style="font-size:12.5px;font-weight:500"><?= e(ucfirst(str_replace('_',' ',$dmg['zone']))) ?></div>
        <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px"><?= e(ucfirst($dmg['damage_type'])) ?> · <?= e(ucfirst($dmg['severity'])) ?></div>
        <?php if ($dmg['description']): ?><div style="font-size:11px;color:var(--text-tertiary);margin-top:2px"><?= e($dmg['description']) ?></div><?php endif; ?>
        <div style="font-size:10px;color:var(--text-tertiary);margin-top:3px">
          By <?= e(($dmg['first_name']??'').' '.($dmg['last_name']??'')) ?> · <?= format_datetime($dmg['created_at']??$dmg['recorded_at']??'') ?>
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

<!-- ═══ PANEL 3: CONDITION (read-only) ═══ -->
<div class="panel" id="panel3">
  <div class="card" style="padding:24px">
    <div class="section-title" style="margin-bottom:18px">📋 Condition Assessment</div>
    <div style="padding:16px;background:rgba(16,185,129,.05);border-radius:var(--radius-sm);border:1px solid rgba(16,185,129,.2)">
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        <div><div style="font-size:10.5px;color:var(--text-tertiary);text-transform:uppercase">Condition</div><div style="font-weight:500;margin-top:3px"><?= ucfirst($inspection['overall_condition']??'—') ?></div></div>
        <div><div style="font-size:10.5px;color:var(--text-tertiary);text-transform:uppercase">Fuel</div><div style="font-weight:500;margin-top:3px"><?= ucfirst(str_replace('_',' ',$inspection['fuel_level']??'—')) ?></div></div>
        <div><div style="font-size:10.5px;color:var(--text-tertiary);text-transform:uppercase">Mileage</div><div style="font-weight:500;margin-top:3px"><?= $inspection['mileage_at_inspection']?number_format($inspection['mileage_at_inspection']).' km':'—' ?></div></div>
      </div>
      <?php if (!empty($inspection['agent_notes'])): ?><div style="margin-top:12px;font-size:12.5px;color:var(--text-secondary)"><strong>Agent Notes:</strong> <?= e($inspection['agent_notes']) ?></div><?php endif; ?>
      <?php if (empty($inspection['overall_condition'])): ?><div style="margin-top:12px;font-size:12.5px;color:var(--text-tertiary)">The agent hasn't recorded a condition assessment yet.</div><?php endif; ?>
    </div>
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
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:14px">Inspection Agent</div>
      <?php if ($agentSig): ?>
      <div class="sig-done">
        <img src="<?= e($agentSig['signature_url']) ?>" style="max-height:90px;max-width:100%;border:1px solid var(--border);border-radius:6px;background:#fff;padding:4px" alt="Agent sig">
        <div style="font-size:11px;color:var(--success);margin-top:8px;font-weight:500">✅ Signed <?= format_datetime($agentSig['signed_at']) ?></div>
      </div>
      <?php else: ?>
      <div style="color:var(--text-tertiary);font-size:13px;padding:20px;text-align:center">Awaiting agent signature</div>
      <?php endif; ?>
    </div>

    <!-- Customer -->
    <div class="card" style="padding:24px">
      <div class="section-title" style="margin-bottom:4px">✍️ Your Signature</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:14px"><?= e(Auth::name()) ?></div>
      <?php if ($custSig): ?>
      <div class="sig-done">
        <img src="<?= e($custSig['signature_url']) ?>" style="max-height:90px;max-width:100%;border:1px solid var(--border);border-radius:6px;background:#fff;padding:4px" alt="Cust sig">
        <div style="font-size:11px;color:var(--success);margin-top:8px;font-weight:500">✅ Signed <?= format_datetime($custSig['signed_at']) ?></div>
      </div>
      <?php elseif ($isLocked): ?>
      <div style="color:var(--text-tertiary);font-size:13px;padding:20px;text-align:center">Not signed</div>
      <?php elseif ($agentSig): ?>
      <div class="sig-wrap" id="custSigPad">
        <canvas id="custCanvas"></canvas>
        <div class="sig-placeholder" id="custPlaceholder">↙ Sign here</div>
      </div>
      <div style="display:flex;gap:8px;margin-top:10px">
        <button class="action-btn" onclick="clearSig('cust')">🗑 Clear</button>
        <button class="action-btn primary" onclick="saveSig('cust',<?= $inspId ?>)">💾 Save Signature</button>
      </div>
      <?php else: ?>
      <div style="color:var(--text-tertiary);font-size:13px;padding:20px;text-align:center">⏳ Waiting for the agent to complete their signature before you can sign</div>
      <?php endif; ?>
    </div>
  </div>

  <div style="margin-top:14px;padding:12px 16px;background:rgba(59,110,248,.05);border-radius:var(--radius-sm);font-size:12px;color:var(--text-secondary)">
    🔒 By signing, you confirm the vehicle condition as documented above. You can only sign for yourself — the agent's signature is theirs alone.
  </div>
  <div class="step-nav">
    <button class="btn btn-ghost" onclick="goStep(3)">← Back</button>
    <div class="spacer"></div>
    <button class="btn btn-primary" onclick="goStep(5)">Next: Status →</button>
  </div>
</div>

<!-- ═══ PANEL 5: STATUS ═══ -->
<div class="panel" id="panel5">
  <?php if ($isLocked): ?>
  <div class="card" style="padding:48px;text-align:center">
    <div style="font-size:60px;margin-bottom:16px">🔒</div>
    <div style="font-size:22px;font-weight:700;margin-bottom:8px">Inspection Complete & Locked</div>
    <div style="font-size:14px;color:var(--text-secondary);margin-bottom:28px">All data is final. No changes can be made to this inspection.</div>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $inspId ?>" target="_blank" class="btn btn-primary">📄 View PDF Report</a>
      <a href="<?= APP_URL ?>/customer/inspections.php" class="btn btn-ghost">← My Inspections</a>
    </div>
  </div>
  <?php elseif ($custSig): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:56px;margin-bottom:14px">✅</div>
    <div style="font-size:20px;font-weight:700;margin-bottom:8px">You've Signed Off</div>
    <div style="font-size:14px;color:var(--text-secondary)">The admin will finalize the inspection report once their side is complete. You'll be notified when it's ready.</div>
  </div>
  <?php elseif ($agentSig): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:56px;margin-bottom:14px">✍️</div>
    <div style="font-size:20px;font-weight:700;margin-bottom:8px">Your Signature Is Needed</div>
    <div style="font-size:14px;color:var(--text-secondary);margin-bottom:24px">The agent has completed their part of the inspection. Please review the details and add your signature to acknowledge it.</div>
    <button class="btn btn-primary" onclick="goStep(4)">✍️ Go to Signatures</button>
  </div>
  <?php else: ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:56px;margin-bottom:14px">⏳</div>
    <div style="font-size:20px;font-weight:700;margin-bottom:8px">Inspection In Progress</div>
    <div style="font-size:14px;color:var(--text-secondary)">The agent is still carrying out this inspection. You'll be notified as soon as it's ready for your signature.</div>
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
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="step-nav">
    <button class="btn btn-ghost" onclick="goStep(4)">← Back</button>
  </div>
</div>

<script>
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

function viewPhoto(type) {
  const slot = document.getElementById('slot_' + type);
  const img = slot?.querySelector('img');
  if (img && img.src) window.open(img.src, '_blank');
}

// ── Signature pad (customer only) ────────────────────────────────────────────
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
  canvas.addEventListener('mousedown',e=>{drawing=true;[lx,ly]=pt(e);const ph=document.getElementById('custPlaceholder');if(ph)ph.style.display='none';});
  canvas.addEventListener('mousemove',e=>{if(!drawing)return;const[x,y]=pt(e);draw(x,y);});
  canvas.addEventListener('mouseup',()=>drawing=false);
  canvas.addEventListener('touchstart',e=>{e.preventDefault();drawing=true;[lx,ly]=pt(e);const ph=document.getElementById('custPlaceholder');if(ph)ph.style.display='none';},{passive:false});
  canvas.addEventListener('touchmove',e=>{e.preventDefault();if(!drawing)return;const[x,y]=pt(e);draw(x,y);},{passive:false});
  canvas.addEventListener('touchend',()=>drawing=false);
}
initCanvas('custCanvas');

function clearSig(who){
  const c=document.getElementById('custCanvas');
  if(c){c.getContext('2d').clearRect(0,0,c.width,c.height);}
  const ph=document.getElementById('custPlaceholder');
  if(ph)ph.style.display='';
}

function saveSig(who, inspId) {
  const canvas = document.getElementById('custCanvas');
  const blank = !canvas.getContext('2d').getImageData(0,0,canvas.width,canvas.height).data.some(v=>v!==0);
  if (blank) { showToast('Please draw your signature first','error'); return; }
  canvas.toBlob(blob=>{
    const fd=new FormData();
    fd.append('inspection_id',inspId);
    fd.append('role','customer');
    fd.append('signature',blob,'sig.png');
    fetch('<?= APP_URL ?>/api/inspections/save-signature.php',{method:'POST',body:fd})
      .then(r=>{ if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.text().then(text => { try { return JSON.parse(text); } catch(e) { throw new Error('Invalid response: ' + text.substring(0, 100)); } }); })
      .then(d=>{ if(d.success){ showToast('✅ '+d.message,'success'); setTimeout(()=>location.reload(),1200); } else showToast('❌ '+(d.error||d.message||'Failed'),'error'); })
      .catch(e=>{ console.error('Signature error:', e); showToast('❌ Error: ' + e.message, 'error'); });
  },'image/png');
}
</script>

<?php require_once __DIR__ . "/../includes/customer-footer.php"; ?>
