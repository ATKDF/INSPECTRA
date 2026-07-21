<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireCustomer();

$inspId = (int)($_GET['id'] ?? 0);
if (!$inspId) redirect(APP_URL . '/customer/inspections.php');

// Verify customer owns this inspection
$inspection = DB::fetchOne("
  SELECT i.*, b.booking_ref, b.customer_id, v.make, v.model, v.registration_number
  FROM inspections i 
  JOIN bookings b ON i.booking_id=b.id 
  JOIN vehicles v ON b.vehicle_id=v.id 
  WHERE i.id=? AND b.customer_id=?
", [$inspId, Auth::id()]);

if (!$inspection) redirect(APP_URL . '/customer/inspections.php');

$pageTitle = 'Inspection Activity — ' . $inspection['booking_ref'];
$activePage = 'inspections';

$activityLog = DB::fetchAll("
  SELECT ia.*, u.first_name, u.last_name, u.role
  FROM inspection_activity ia 
  JOIN users u ON ia.user_id=u.id 
  WHERE ia.inspection_id=? 
  ORDER BY ia.created_at ASC
", [$inspId]);

require_once __DIR__ . '/../includes/customer-header.php';
?>

<style>
.activity-timeline{position:relative;padding-left:28px;}
.activity-timeline::before{content:'';position:absolute;left:11px;top:12px;bottom:12px;width:2px;background:var(--border);}
.al-item{position:relative;padding-bottom:20px;}
.al-item:last-child{padding-bottom:0;}
.al-dot{position:absolute;left:-25px;top:6px;width:16px;height:16px;border-radius:50%;background:var(--accent);border:3px solid #fff;box-shadow:0 0 0 2px var(--border);}
.al-dot.agent{background:var(--primary);}
.al-dot.complete{background:var(--success);}
.al-time{font-size:11px;color:var(--text-tertiary);font-weight:500;text-transform:uppercase;letter-spacing:0.3px;}
.al-action{font-size:13.5px;font-weight:600;margin:6px 0 3px;color:var(--text-primary);}
.al-actor{font-size:12px;color:var(--text-secondary);margin:2px 0;}
.al-detail{font-size:12px;color:var(--text-secondary);margin:4px 0;padding:8px 12px;background:rgba(59,110,248,.04);border-left:3px solid rgba(59,110,248,.2);border-radius:4px;}
.al-geo{font-size:11px;color:var(--success);font-family:var(--mono);margin-top:4px;display:flex;align-items:center;gap:6px;}
.al-tags{display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;}
.al-tag{font-size:10px;padding:3px 10px;background:rgba(59,110,248,.1);border:1px solid rgba(59,110,248,.2);border-radius:14px;color:var(--accent);font-weight:500;}
.al-tag.agent{background:rgba(59,110,248,.1);border-color:rgba(59,110,248,.25);color:var(--primary);}
.al-tag.customer{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.25);color:var(--success);}
.al-tag.damage{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.25);color:var(--danger);}

.card-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:24px}
</style>

<!-- Header -->
<div class="card" style="padding:24px;margin-bottom:24px">
  <div class="card-header">
    <div>
      <div style="font-size:18px;font-weight:600">Inspection Activity Log</div>
      <div style="font-size:13px;color:var(--text-secondary);margin-top:4px">
        <?= e($inspection['make'].' '.$inspection['model']) ?> • 
        <span style="font-family:var(--mono)"><?= e($inspection['registration_number']) ?></span> • 
        Ref: <strong><?= e($inspection['booking_ref']) ?></strong>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <a href="<?= APP_URL ?>/customer/inspection-flow.php?id=<?= $inspId ?>" class="btn btn-primary" style="font-size:12px">← Back to Inspection</a>
    </div>
  </div>
</div>

<?php if (empty($activityLog)): ?>
<div class="card" style="padding:48px;text-align:center">
  <div style="font-size:48px;margin-bottom:16px">📝</div>
  <div style="font-size:16px;font-weight:600;color:var(--text-secondary);">No activity yet</div>
  <div style="font-size:13px;color:var(--text-tertiary);margin-top:6px">Activity will appear here as you complete inspection stages</div>
</div>
<?php else: ?>

<!-- Status bar -->
<div class="card" style="padding:14px 20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;background:rgba(59,110,248,.02);border:1px solid rgba(59,110,248,.1)">
  <div style="font-size:12px;color:var(--text-secondary)">
    <strong><?= count($activityLog) ?></strong> activities recorded
    <?php 
    $agentActivities = array_sum(array_map(fn($a) => ($a['role'] === 'admin' ? 1 : 0), $activityLog));
    $customerActivities = count($activityLog) - $agentActivities;
    ?>
    • <strong><?= $agentActivities ?></strong> by agent • <strong><?= $customerActivities ?></strong> by you
  </div>
  <a href="<?= APP_URL ?>/api/reports/generate.php?inspection_id=<?= $inspId ?>" target="_blank" class="btn btn-ghost" style="font-size:11px;padding:6px 12px">📄 Full Report</a>
</div>

<!-- Timeline -->
<div class="card" style="padding:24px">
  <div class="activity-timeline">
    <?php foreach ($activityLog as $idx => $al): 
      $isAgent = $al['role'] === 'admin';
      $isComplete = str_contains($al['action'], 'complete');
      $isDamage = str_contains($al['action'], 'damage');
      $isPhoto = str_contains($al['action'], 'photo');
    ?>
    <div class="al-item">
      <div class="al-dot <?= $isAgent?'agent':'' ?> <?= $isComplete?'complete':'' ?>"></div>
      
      <div class="al-time"><?= format_datetime($al['created_at'], 'l, j F Y \a\t H:i:s') ?></div>
      <div class="al-action"><?= e($al['action']) ?></div>
      <div class="al-actor">
        👤 <?= e($al['first_name'].' '.$al['last_name']) ?>
        <span style="display:inline-block;margin-left:8px;padding:2px 8px;background:<?= $isAgent?'rgba(59,110,248,.1)':'rgba(34,197,94,.1)' ?>;border-radius:12px;font-size:10px;color:<?= $isAgent?'var(--primary)':'var(--success)' ?>;font-weight:500">
          <?= $isAgent?'🔧 Agent':'👤 You' ?>
        </span>
      </div>
      
      <?php if ($al['detail']): ?>
      <div class="al-detail"><?= e($al['detail']) ?></div>
      <?php endif; ?>
      
      <?php if ($al['latitude'] && $al['longitude']): ?>
      <div class="al-geo">
        <span>📍</span> 
        <span><?= round($al['latitude'], 5) ?>, <?= round($al['longitude'], 5) ?></span>
      </div>
      <?php endif; ?>
      
      <!-- Tags -->
      <div class="al-tags">
        <span class="al-tag <?= $isAgent?'agent':'customer' ?>">
          <?= $isAgent?'🔧':'👤' ?> <?= $isAgent?'Agent':'You' ?>
        </span>
        <?php if ($isDamage): ?>
        <span class="al-tag damage">⚠️ Damage</span>
        <?php elseif ($isPhoto): ?>
        <span class="al-tag">📸 Photo</span>
        <?php elseif (str_contains($al['action'], 'signature')): ?>
        <span class="al-tag">✍️ Signature</span>
        <?php elseif (str_contains($al['action'], 'condition')): ?>
        <span class="al-tag">📋 Condition</span>
        <?php elseif ($isComplete): ?>
        <span class="al-tag" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.25);color:var(--success)">✅ Complete</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card" style="padding:16px;background:rgba(59,110,248,.04);border:1px solid rgba(59,110,248,.15);margin-top:20px">
  <div style="font-size:12px;color:var(--text-secondary)">
    <strong>📝 Timeline Note:</strong> This log shows all activities performed on this inspection by both you and the agent. All actions are timestamped and geotagged for transparency. Once an inspection is completed and locked, no further changes can be made.
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>

