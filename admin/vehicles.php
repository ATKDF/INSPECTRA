<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Vehicles'; $activePage = 'vehicles';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action']??'';
    
    if ($action==='add_vehicle') {
        try {
            if (!csrf_verify()) throw new Exception('CSRF verification failed');
            
            $vehicleData = [
                'uuid' => Auth::generateUUID(),
                'make' => sanitize($_POST['make']),
                'model' => sanitize($_POST['model']),
                'year' => (int)$_POST['year'],
                'registration_number' => sanitize($_POST['registration_number']),
                'category' => $_POST['category'],
                'color' => sanitize($_POST['color'] ?? ''),
                'fuel_type' => $_POST['fuel_type'] ?? 'petrol',
                'transmission' => $_POST['transmission'] ?? 'automatic',
                'seats' => (int)($_POST['seats'] ?? 5),
                'daily_rate' => (float)$_POST['daily_rate'],
                'deposit_amount' => (float)($_POST['deposit_amount'] ?? 0),
                'description' => sanitize($_POST['description'] ?? ''),
                'status' => 'available',
                'created_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $vid = DB::insert('vehicles', $vehicleData);
            if (!$vid) throw new Exception('Failed to insert vehicle');
            
            // Upload photos
            $primarySet = false;
            for ($i = 1; $i <= 4; $i++) {
                $key = 'photo_' . $i;
                if (!empty($_FILES[$key]['name'])) {
                    $url = upload_file($_FILES[$key], 'vehicles');
                    if ($url) {
                        $isPrimary = !$primarySet ? 1 : 0;
                        DB::insert('vehicle_photos', [
                            'vehicle_id' => $vid,
                            'photo_url' => $url,
                            'is_primary' => $isPrimary,
                            'sort_order' => $i - 1
                        ]);
                        $primarySet = true;
                    } else {
                        error_log("Photo upload failed: " . $key);
                    }
                }
            }
            
            log_audit(Auth::id(), 'create', 'vehicle', $vid);
            redirect(APP_URL.'/admin/vehicles.php?success=' . urlencode('Vehicle added successfully!'));
        } catch (Exception $e) {
            error_log('Vehicle add error: ' . $e->getMessage());
            $_SESSION['error'] = 'Failed to add vehicle: ' . $e->getMessage();
            redirect(APP_URL.'/admin/vehicles.php?error=' . urlencode($e->getMessage()));
        }
    }

    if ($action==='edit_vehicle') {
        $vid = (int)$_POST['vehicle_id'];
        DB::update('vehicles',['make'=>sanitize($_POST['make']),'model'=>sanitize($_POST['model']),'year'=>(int)$_POST['year'],'registration_number'=>sanitize($_POST['registration_number']),'category'=>$_POST['category'],'color'=>sanitize($_POST['color']??''),'fuel_type'=>$_POST['fuel_type']??'petrol','transmission'=>$_POST['transmission']??'automatic','seats'=>(int)($_POST['seats']??5),'daily_rate'=>(float)$_POST['daily_rate'],'deposit_amount'=>(float)($_POST['deposit_amount']??0),'description'=>sanitize($_POST['description']??'')],'id=?',[$vid]);
        // New photos
        $existingCount = (int) DB::fetchOne("SELECT COUNT(*) as c FROM vehicle_photos WHERE vehicle_id=?", [$vid])['c'];
        for ($i = 1; $i <= 4; $i++) {
            $key = 'new_photo_' . $i;
            if (!empty($_FILES[$key]['name'])) {
                $url = upload_file($_FILES[$key], 'vehicles');
                if ($url && $existingCount < 4) {
                    DB::insert('vehicle_photos', [
                        'vehicle_id' => $vid,
                        'photo_url' => $url,
                        'is_primary' => $existingCount === 0 ? 1 : 0,
                        'sort_order' => $existingCount
                    ]);
                    $existingCount++;
                }
            }
        }
        // Delete photos if requested
        if (!empty($_POST['delete_photos'])) {
            foreach ((array)$_POST['delete_photos'] as $pid) DB::query("DELETE FROM vehicle_photos WHERE id=? AND vehicle_id=?",[(int)$pid,$vid]);
        }
        log_audit(Auth::id(), 'update', 'vehicle', $vid, [], ['make'=>sanitize($_POST['make']),'model'=>sanitize($_POST['model']),'daily_rate'=>(float)$_POST['daily_rate']]);
        redirect(APP_URL.'/admin/vehicles.php?success='.urlencode('Vehicle updated!'));
    }

    if ($action==='update_status') {
        $vid=(int)$_POST['vehicle_id']; $status=$_POST['status'];
        if (in_array($status,['available','rented','maintenance','retired'])) {
            $oldVehicle = DB::fetchOne("SELECT status FROM vehicles WHERE id=?", [$vid]);
            DB::update('vehicles',['status'=>$status],'id=?',[$vid]);
            log_audit(Auth::id(), 'update', 'vehicle', $vid, ['status'=>$oldVehicle['status']??null], ['status'=>$status]);
        }
        redirect(APP_URL.'/admin/vehicles.php?success='.urlencode('Status updated.'));
    }
}

$statusFilter=$_GET['status']??''; $search=$_GET['q']??'';
$where="WHERE v.status!='retired'"; $params=[];
if ($statusFilter){$where.=" AND v.status=?";$params[]=$statusFilter;}
if ($search){$where.=" AND (v.make LIKE ? OR v.model LIKE ? OR v.registration_number LIKE ?)";$s="%$search%";$params=array_merge($params,[$s,$s,$s]);}
$vehicles=DB::fetchAll("SELECT v.*, (SELECT photo_url FROM vehicle_photos WHERE vehicle_id=v.id AND is_primary=1 LIMIT 1) as primary_photo, (SELECT COUNT(*) FROM bookings WHERE vehicle_id=v.id) as rental_count, (SELECT AVG(rating) FROM reviews WHERE vehicle_id=v.id AND is_published=1) as avg_rating, (SELECT COUNT(*) FROM reviews WHERE vehicle_id=v.id AND is_published=1) as review_count FROM vehicles v $where ORDER BY v.created_at DESC",$params);

$error = $_GET['error'] ?? $_SESSION['error'] ?? '';
unset($_SESSION['error']);

require_once __DIR__.'/../includes/admin-header.php';
?>
<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<style>
.modal-scroll-body{overflow-y:auto;max-height:calc(80vh - 140px);}
.alert-error {
  background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:12px 16px;margin-bottom:20px;
}
</style>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="?" class="chip <?=!$statusFilter?'active':''?>">All</a>
    <a href="?status=available" class="chip <?=$statusFilter==='available'?'active':''?>">Available</a>
    <a href="?status=rented" class="chip <?=$statusFilter==='rented'?'active':''?>">Rented</a>
    <a href="?status=maintenance" class="chip <?=$statusFilter==='maintenance'?'active':''?>">Maintenance</a>
  </div>
  <button class="btn btn-primary" onclick="openModal('addVehicleModal')">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M12 5v14M5 12h14"/></svg> Add Vehicle
  </button>
</div>

<form method="GET" style="margin-bottom:18px">
  <div class="search-bar" style="max-width:360px">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
    <input name="q" value="<?=e($search)?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Search vehicles…">
    <?php if($statusFilter):?><input type="hidden" name="status" value="<?=e($statusFilter)?>"><?php endif;?>
  </div>
</form>

<?php if(empty($vehicles)):?>
<div class="card"><div class="empty-state"><div class="empty-state-icon">🚗</div><div class="empty-state-title">No vehicles</div><div class="empty-state-desc">Add your first vehicle to get started</div></div></div>
<?php else:?>
<div class="vehicle-grid">
  <?php foreach($vehicles as $v):?>
  <div class="vehicle-card">
    <div class="vehicle-card-img" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe)">
      <?php if($v['primary_photo']):?><img src="<?=e($v['primary_photo'])?>" style="width:100%;height:100%;object-fit:cover" alt=""><?php else:?><?=vehicle_emoji($v['category'])?><?php endif;?>
      <div class="vehicle-card-status"><?=status_badge($v['status'])?></div>
    </div>
    <div class="vehicle-card-body">
      <div class="vehicle-card-title"><?=e($v['year'].' '.$v['make'].' '.$v['model'])?></div>
      <div class="vehicle-card-reg"><?=e($v['registration_number'])?></div>
      <div class="vehicle-card-meta">
        <div class="vehicle-meta-item"><span class="vehicle-meta-val">₵<?=number_format($v['daily_rate'],0)?></span><span class="vehicle-meta-label">per day</span></div>
        <div class="vehicle-meta-item"><span class="vehicle-meta-val"><?=$v['rental_count']?></span><span class="vehicle-meta-label">rentals</span></div>
        <div class="vehicle-meta-item"><span class="vehicle-meta-val"><?=$v['avg_rating']?'★ '.number_format($v['avg_rating'],1):'—'?></span><span class="vehicle-meta-label"><?=$v['review_count']?' '.$v['review_count'].' review'.($v['review_count']!=1?'s':''):'rating'?></span></div>
        <div class="vehicle-meta-item"><span class="vehicle-meta-val"><?=ucfirst($v['category'])?></span><span class="vehicle-meta-label">type</span></div>
      </div>
      <div class="vehicle-card-actions">
        <button class="action-btn" onclick="openEditModal(<?=htmlspecialchars(json_encode($v),ENT_QUOTES)?>)">✏️ Edit</button>
        <form method="POST" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="vehicle_id" value="<?=$v['id']?>">
          <select name="status" class="action-btn" onchange="this.form.submit()" style="cursor:pointer">
            <?php foreach(['available','rented','maintenance'] as $st):?>
            <option value="<?=$st?>" <?=$v['status']===$st?'selected':''?>><?=ucfirst($st)?></option>
            <?php endforeach;?>
          </select>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<!-- ── ADD VEHICLE MODAL ── -->
<div class="modal-overlay" id="addVehicleModal">
  <div class="modal modal-lg" style="max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-header" style="flex-shrink:0">
      <span class="modal-title">Add New Vehicle</span>
      <button class="modal-close" onclick="closeModal('addVehicleModal')">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;flex:1;overflow:hidden">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="add_vehicle">
      <div class="modal-body modal-scroll-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Make *</label><input class="form-input" name="make" placeholder="Toyota" required></div>
          <div class="form-group"><label class="form-label">Model *</label><input class="form-input" name="model" placeholder="Corolla" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Year *</label><input class="form-input" type="number" name="year" placeholder="<?=date('Y')?>" min="2000" max="<?=date('Y')+1?>" required></div>
          <div class="form-group"><label class="form-label">Registration *</label><input class="form-input" name="registration_number" placeholder="GR-1234-22" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Category *</label><select class="form-input" name="category"><option value="economy">Economy</option><option value="suv">SUV</option><option value="luxury">Luxury</option><option value="van">Van</option><option value="truck">Truck</option><option value="motorcycle">Motorcycle</option></select></div>
          <div class="form-group"><label class="form-label">Color</label><input class="form-input" name="color" placeholder="Silver"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Daily Rate (₵) *</label><input class="form-input" type="number" name="daily_rate" placeholder="250.00" step="0.01" required></div>
          <div class="form-group"><label class="form-label">Seats</label><input class="form-input" type="number" name="seats" placeholder="5" min="1" max="20" value="5"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Fuel Type</label><select class="form-input" name="fuel_type"><option value="petrol">Petrol</option><option value="diesel">Diesel</option><option value="electric">Electric</option><option value="hybrid">Hybrid</option></select></div>
          <div class="form-group"><label class="form-label">Transmission</label><select class="form-input" name="transmission"><option value="automatic">Automatic</option><option value="manual">Manual</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" name="description" rows="2" placeholder="Vehicle description…"></textarea></div>
        <div style="font-size:12.5px;font-weight:600;margin-bottom:10px;color:var(--text-secondary)">Vehicle Photos (up to 4)</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
          <?php for($i=1;$i<=4;$i++):?>
          <div>
            <label style="display:block;text-align:center;padding:12px 8px;border:1.5px dashed var(--border);border-radius:var(--radius-sm);cursor:pointer;font-size:11.5px;color:var(--text-secondary);transition:all .15s" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
              📷 Photo <?=$i?><?=$i===1?' (Primary)':''?>
              <input type="file" name="photo_<?=$i?>" accept="image/*" style="display:none" onchange="previewImg(this,'addPrev<?=$i?>')">
            </label>
            <img id="addPrev<?=$i?>" style="display:none;width:100%;margin-top:6px;border-radius:var(--radius-sm);max-height:80px;object-fit:cover" alt="">
          </div>
          <?php endfor;?>
        </div>
      </div>
      <div class="modal-footer" style="flex-shrink:0">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addVehicleModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Vehicle</button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT VEHICLE MODAL ── -->
<div class="modal-overlay" id="editVehicleModal">
  <div class="modal modal-lg" style="max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-header" style="flex-shrink:0">
      <span class="modal-title">Edit Vehicle</span>
      <button class="modal-close" onclick="closeModal('editVehicleModal')">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data" id="editVehicleForm" style="display:flex;flex-direction:column;flex:1;overflow:hidden">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="edit_vehicle">
      <input type="hidden" name="vehicle_id" id="editVehicleId">
      <div class="modal-body modal-scroll-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Make *</label><input class="form-input" name="make" id="editMake" required></div>
          <div class="form-group"><label class="form-label">Model *</label><input class="form-input" name="model" id="editModel" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Year *</label><input class="form-input" type="number" name="year" id="editYear" required></div>
          <div class="form-group"><label class="form-label">Registration *</label><input class="form-input" name="registration_number" id="editReg" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Category</label><select class="form-input" name="category" id="editCategory"><option value="economy">Economy</option><option value="suv">SUV</option><option value="luxury">Luxury</option><option value="van">Van</option><option value="truck">Truck</option><option value="motorcycle">Motorcycle</option></select></div>
          <div class="form-group"><label class="form-label">Color</label><input class="form-input" name="color" id="editColor"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Daily Rate (₵)</label><input class="form-input" type="number" name="daily_rate" id="editRate" step="0.01"></div>
          <div class="form-group"><label class="form-label">Seats</label><input class="form-input" type="number" name="seats" id="editSeats" min="1" max="20"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Fuel Type</label><select class="form-input" name="fuel_type" id="editFuel"><option value="petrol">Petrol</option><option value="diesel">Diesel</option><option value="electric">Electric</option><option value="hybrid">Hybrid</option></select></div>
          <div class="form-group"><label class="form-label">Transmission</label><select class="form-input" name="transmission" id="editTrans"><option value="automatic">Automatic</option><option value="manual">Manual</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea class="form-input" name="description" id="editDesc" rows="2"></textarea></div>
        <!-- Existing photos -->
        <div id="editExistingPhotos" style="margin-bottom:14px"></div>
        <!-- Add more photos -->
        <div style="font-size:12.5px;font-weight:600;margin-bottom:8px;color:var(--text-secondary)">Add New Photos</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
          <?php for($i=1;$i<=4;$i++):?>
          <label style="display:block;text-align:center;padding:10px 6px;border:1.5px dashed var(--border);border-radius:var(--radius-sm);cursor:pointer;font-size:11px;color:var(--text-secondary)">
            + Photo <?=$i?>
            <input type="file" name="new_photo_<?=$i?>" accept="image/*" style="display:none" onchange="previewImg(this,'editPrev<?=$i?>')">
            <img id="editPrev<?=$i?>" style="display:none;width:100%;margin-top:6px;border-radius:6px;max-height:70px;object-fit:cover" alt="">
          </label>
          <?php endfor;?>
        </div>
      </div>
      <div class="modal-footer" style="flex-shrink:0">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editVehicleModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function previewImg(input, previewId) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => { const img = document.getElementById(previewId); img.src=e.target.result; img.style.display='block'; };
  reader.readAsDataURL(input.files[0]);
}

function openEditModal(v) {
  document.getElementById('editVehicleId').value = v.id;
  document.getElementById('editMake').value = v.make;
  document.getElementById('editModel').value = v.model;
  document.getElementById('editYear').value = v.year;
  document.getElementById('editReg').value = v.registration_number;
  document.getElementById('editCategory').value = v.category;
  document.getElementById('editColor').value = v.color||'';
  document.getElementById('editRate').value = v.daily_rate;
  document.getElementById('editSeats').value = v.seats||5;
  document.getElementById('editFuel').value = v.fuel_type||'petrol';
  document.getElementById('editTrans').value = v.transmission||'automatic';
  document.getElementById('editDesc').value = v.description||'';

  // Load existing photos via AJAX
  fetch(`<?=APP_URL?>/api/vehicles/get-photos.php?vehicle_id=${v.id}`)
    .then(r=>r.json()).then(photos=>{
      let html = '';
      if (photos.length) {
        html = '<div style="font-size:12.5px;font-weight:600;margin-bottom:8px;color:var(--text-secondary)">Current Photos (' + photos.length + '/4)</div><div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px">';
        photos.forEach(p => {
          html += `<div style="position:relative"><img src="${p.url}" style="width:100%;height:70px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
          <label style="position:absolute;top:3px;right:3px;background:rgba(239,68,68,.85);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:10px;cursor:pointer">
            ✕<input type="checkbox" name="delete_photos[]" value="${p.id}" style="display:none" checked>
          </label></div>`;
        });
        html += '</div>';
      }
      document.getElementById('editExistingPhotos').innerHTML = html;
    }).catch(()=>{});

  openModal('editVehicleModal');
}
</script>

<?php require_once __DIR__.'/../includes/admin-footer.php'; ?>
