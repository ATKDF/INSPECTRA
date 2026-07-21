<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Browse Vehicles';
$activePage = 'browse';

// AJAX availability check
if (isset($_GET['check_availability'])) {
    $vid=$_GET['vehicle_id']??0; $pickup=$_GET['pickup_date']??''; $ret=$_GET['return_date']??'';
    if (!$vid||!$pickup||!$ret) json_response(['available'=>false,'message'=>'Invalid parameters']);
    $avail = check_vehicle_availability((int)$vid,$pickup,$ret);
    if (!$avail) {
        $alts = DB::fetchAll("SELECT v.id,v.make,v.model,v.year,v.daily_rate,v.category FROM vehicles v WHERE v.status='available' AND v.id!=? AND NOT EXISTS (SELECT 1 FROM bookings b WHERE b.vehicle_id=v.id AND b.status NOT IN ('cancelled') AND b.pickup_date<=? AND b.return_date>=?) LIMIT 4",[$vid,$ret,$pickup]);
        json_response(['available'=>false,'message'=>'These dates are already booked for this vehicle.','alternatives'=>$alts]);
    }
    json_response(['available'=>true]);
}

// Booking form submit
if ($_SERVER['REQUEST_METHOD']==='POST'&&csrf_verify()) {
    $vehicleId  = (int)$_POST['vehicle_id'];
    $pickupDate = $_POST['pickup_date'];
    $returnDate = $_POST['return_date'];
    $vehicle    = DB::fetchOne("SELECT * FROM vehicles WHERE id=? AND status='available'",[$vehicleId]);
    if (!$vehicle) redirect(APP_URL.'/customer/browse.php?error='.urlencode('Vehicle not available'));
    if (!check_vehicle_availability($vehicleId,$pickupDate,$returnDate)) redirect(APP_URL.'/customer/browse.php?error='.urlencode('Sorry, these dates are no longer available for this vehicle. Please choose different dates.'));
    $days = (int)ceil((strtotime($returnDate)-strtotime($pickupDate))/86400);
    if ($days<1) redirect(APP_URL.'/customer/browse.php?error='.urlencode('Invalid dates'));
    $calc = calculate_booking_amounts($vehicle['daily_rate'],$days);
    $idDocUrl='';
    if (!empty($_FILES['id_document']['name'])) $idDocUrl=upload_file($_FILES['id_document'],'documents',['image/jpeg','image/png','image/webp','application/pdf'])?:'';
    $bookingId = DB::insert('bookings',['uuid'=>Auth::generateUUID(),'booking_ref'=>Auth::generateBookingRef(),'vehicle_id'=>$vehicleId,'customer_id'=>Auth::id(),'pickup_date'=>$pickupDate,'return_date'=>$returnDate,'pickup_time'=>sanitize($_POST['pickup_time']??''),'return_time'=>sanitize($_POST['return_time']??''),'pickup_location'=>sanitize($_POST['pickup_location']??''),'pickup_city'=>sanitize($_POST['pickup_city']??''),'pickup_street'=>sanitize($_POST['pickup_street']??''),'pickup_gps'=>sanitize($_POST['pickup_gps']??''),'id_document_type'=>sanitize($_POST['id_document_type']??''),'id_document_number'=>sanitize($_POST['id_document_number']??''),'id_document_url'=>$idDocUrl,'total_days'=>$days,'daily_rate'=>$vehicle['daily_rate'],'subtotal'=>$calc['subtotal'],'tax_amount'=>$calc['taxAmount'],'service_charge'=>$calc['serviceCharge'],'total_amount'=>$calc['totalAmount'],'initial_deposit'=>$calc['initialDeposit'],'deposit_amount'=>$calc['initialDeposit'],'status'=>'booked','payment_status'=>'unpaid','special_requests'=>sanitize($_POST['special_requests']??''),'created_at'=>date('Y-m-d H:i:s')]);
    $ref=DB::fetchOne("SELECT booking_ref FROM bookings WHERE id=?",[$bookingId])['booking_ref'];
    create_notification(Auth::id(),'booking_created','Booking Created',"Your booking {$ref} is confirmed. Please complete payment.",['booking_id'=>$bookingId]);
    foreach(DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
        $adminMessage = "🔔 New Booking Created!\n\n👤 Customer: " . Auth::name() . "\n📌 Booking Ref: {$ref}\n🚗 Vehicle: {$vehicle['make']} {$vehicle['model']}\n📅 Duration: {$days} day(s)\n💰 Total Amount: ₵" . number_format($calc['totalAmount'], 2) . "\n\n📍 Pickup: {$pickupDate} at " . sanitize($_POST['pickup_location']??'') . "\n🔄 Return: {$returnDate}\n\n⏳ Awaiting Payment";
        create_notification($adm['id'],'new_booking','New Booking',$adminMessage,['booking_id'=>$bookingId]);
    }
    redirect(APP_URL.'/customer/bookings.php?id='.$bookingId.'&success='.urlencode('Booking created! Complete payment to confirm.'));
}

$category=$_GET['category']??''; $q=$_GET['q']??''; $minRate=$_GET['min']??''; $maxRate=$_GET['max']??'';
$where="WHERE v.status='available'"; $params=[];
if($category){$where.=" AND v.category=?";$params[]=$category;}
if($q){$where.=" AND (v.make LIKE ? OR v.model LIKE ?)";$s="%$q%";$params=array_merge($params,[$s,$s]);}
if($minRate){$where.=" AND v.daily_rate>=?";$params[]=$minRate;}
if($maxRate){$where.=" AND v.daily_rate<=?";$params[]=$maxRate;}
$vehicles=DB::fetchAll("SELECT v.*, (SELECT photo_url FROM vehicle_photos WHERE vehicle_id=v.id AND is_primary=1 LIMIT 1) as primary_photo FROM vehicles v $where ORDER BY v.daily_rate ASC",$params);
$taxRate=(float)get_setting('tax_rate','0');
$serviceChargeRate=(float)get_setting('service_charge_rate','0');
$depositPct=(float)get_setting('deposit_percentage','50');
$pickupLocations=array_map('trim',explode(',',get_setting('pickup_locations','Osu,Tema')));
require_once __DIR__.'/../includes/customer-header.php';
?>
<style>
.vcard{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;transition:all .2s;cursor:pointer}
.vcard:hover{box-shadow:var(--shadow-md);transform:translateY(-2px)}
.vcard-img{height:178px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);display:flex;align-items:center;justify-content:center;font-size:64px;position:relative;overflow:hidden}
.vcard-img img{width:100%;height:100%;object-fit:cover}
.price-badge{position:absolute;bottom:10px;right:10px;background:rgba(15,17,23,.82);color:#fff;padding:4px 12px;border-radius:20px;font-size:12.5px;font-weight:600;backdrop-filter:blur(8px)}
.avail-tag{position:absolute;top:10px;left:10px;padding:3px 10px;border-radius:20px;font-size:10.5px;font-weight:600}
.cost-row{display:flex;justify-content:space-between;font-size:13px;margin-bottom:7px}
.cost-total{display:flex;justify-content:space-between;font-weight:700;font-size:15px;border-top:1px solid var(--border);padding-top:10px;margin-top:4px}
.fsec{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-secondary);margin:18px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border)}
</style>

<!-- Filters -->
<form method="GET" style="background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:18px 22px;margin-bottom:22px">
  <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:12px;align-items:end">
    <div>
      <label class="form-label">Search</label>
      <div class="search-bar"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg><input name="q" value="<?=e($q)?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Make, model…"></div>
    </div>
    <div><label class="form-label">Category</label><select class="form-input" name="category"><option value="">All</option><?php foreach(['economy'=>'Economy','suv'=>'SUV','luxury'=>'Luxury','van'=>'Van','truck'=>'Truck','motorcycle'=>'Motorcycle'] as $k=>$l): ?><option value="<?=$k?>" <?=$category===$k?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
    <div><label class="form-label">Min (₵)</label><input class="form-input" type="number" name="min" value="<?=e($minRate)?>" placeholder="0"></div>
    <div><label class="form-label">Max (₵)</label><input class="form-input" type="number" name="max" value="<?=e($maxRate)?>" placeholder="Any"></div>
    <div style="display:flex;gap:8px;align-items:flex-end"><button type="submit" class="btn btn-primary">Search</button><?php if($q||$category||$minRate||$maxRate):?><a href="?" class="btn btn-ghost">Clear</a><?php endif;?></div>
  </div>
</form>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
  <div style="font-size:13px;color:var(--text-secondary)"><?=count($vehicles)?> vehicle<?=count($vehicles)!=1?'s':''?> available</div>
  <div style="display:flex;gap:7px;flex-wrap:wrap">
    <a href="?" class="chip <?=!$category?'active':''?>">All</a>
    <?php foreach(['economy'=>'Economy 🚗','suv'=>'SUV 🚙','luxury'=>'Luxury 🏎️'] as $k=>$l):?><a href="?category=<?=$k?>" class="chip <?=$category===$k?'active':''?>"><?=$l?></a><?php endforeach;?>
  </div>
</div>

<?php if(empty($vehicles)):?>
<div class="card"><div class="empty-state"><div class="empty-state-icon">🔍</div><div class="empty-state-title">No vehicles found</div><div class="empty-state-desc">Try different filters</div></div></div>
<?php else:?>
<div class="vehicle-grid">
  <?php foreach($vehicles as $v):?>
  <div class="vcard" onclick="openBooking(<?=htmlspecialchars(json_encode(['id'=>$v['id'],'name'=>$v['year'].' '.$v['make'].' '.$v['model'],'rate'=>(float)$v['daily_rate'],'category'=>$v['category']]),ENT_QUOTES)?>)">
    <div class="vcard-img">
      <?php if($v['primary_photo']):?><img src="<?=e($v['primary_photo'])?>" alt="<?=e($v['make'].' '.$v['model'])?>">
      <?php else:?><?=vehicle_emoji($v['category'])?><?php endif;?>
      <div class="price-badge">₵<?=number_format($v['daily_rate'],0)?>/day</div>
      <div class="avail-tag" style="background:rgba(16,185,129,.88);color:#fff">✓ Available</div>
    </div>
    <div style="padding:16px">
      <div style="font-size:15px;font-weight:600;margin-bottom:3px"><?=e($v['year'].' '.$v['make'].' '.$v['model'])?></div>
      <div style="font-size:11.5px;font-family:var(--mono);color:var(--text-secondary);margin-bottom:12px"><?=e($v['registration_number'])?></div>
      <div style="display:flex;gap:14px;font-size:12px;color:var(--text-secondary);margin-bottom:14px">
        <span>⛽ <?=ucfirst($v['fuel_type'])?></span><span>⚙️ <?=ucfirst($v['transmission'])?></span><span>💺 <?=$v['seats']?></span>
      </div>
      <?php if($v['description']):?><div style="font-size:12px;color:var(--text-secondary);margin-bottom:12px;line-height:1.5"><?=e(substr($v['description'],0,80)).(strlen($v['description'])>80?'...':'')?></div><?php endif;?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid var(--border)">
        <div><div style="font-size:18px;font-weight:700;color:var(--accent)">₵<?=number_format($v['daily_rate'],0)?></div><div style="font-size:10.5px;color:var(--text-secondary)">per day</div></div>
        <span style="background:var(--accent);color:#fff;padding:9px 20px;border-radius:var(--radius-sm);font-size:13px;font-weight:500">Book Now</span>
      </div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<!-- ── Booking Modal ── -->
<div class="modal-overlay" id="bookingModal">
  <div class="modal modal-lg" style="max-height:92vh;display:flex;flex-direction:column">
    <div class="modal-header" style="flex-shrink:0">
      <span class="modal-title">Book — <span id="bkVehicleName"></span></span>
      <button class="modal-close" onclick="closeModal('bookingModal')">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data" style="overflow-y:auto;flex:1;display:flex;flex-direction:column">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="vehicle_id" id="bkVehicleId">
      <div class="modal-body" style="flex:1;overflow-y:auto">

        <div id="availAlert" style="display:none;padding:11px 14px;border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px"></div>

        <div class="fsec">📅 Rental Period</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Pickup Date *</label><input class="form-input" type="date" name="pickup_date" id="bkPickup" min="<?=date('Y-m-d')?>" required onchange="calcAndCheck()"></div>
          <div class="form-group"><label class="form-label">Pickup Time *</label><input class="form-input" type="time" name="pickup_time" required value="09:00"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Return Date *</label><input class="form-input" type="date" name="return_date" id="bkReturn" min="<?=date('Y-m-d',strtotime('+1 day'))?>" required onchange="calcAndCheck()"></div>
          <div class="form-group"><label class="form-label">Return Time *</label><input class="form-input" type="time" name="return_time" required value="09:00"></div>
        </div>

        <!-- Cost box -->
        <div id="bkCostBox" style="display:none;background:var(--surface);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:16px">
          <div class="cost-row"><span style="color:var(--text-secondary)">Daily Rate</span><span id="bkRateDisp">—</span></div>
          <div class="cost-row"><span style="color:var(--text-secondary)">Days</span><span id="bkDaysDisp">—</span></div>
          <div class="cost-row"><span style="color:var(--text-secondary)">Subtotal</span><span id="bkSubtotal">—</span></div>
          <?php if($taxRate>0):?><div class="cost-row"><span style="color:var(--text-secondary)">Tax (<?=$taxRate?>%)</span><span id="bkTax">—</span></div><?php endif;?>
          <?php if($serviceChargeRate>0):?><div class="cost-row"><span style="color:var(--text-secondary)">Service Charge (<?=$serviceChargeRate?>%)</span><span id="bkSvc">—</span></div><?php endif;?>
          <div class="cost-total"><span>Total Amount</span><span id="bkTotal" style="color:var(--accent)">—</span></div>
          <div style="margin-top:10px;padding:10px 12px;background:rgba(59,110,248,.07);border-radius:var(--radius-sm)">
            <div style="font-size:12.5px;font-weight:600;color:var(--accent);margin-bottom:2px">💳 Payment at Checkout</div>
            <div style="font-size:12px;color:var(--text-secondary)">Minimum deposit: <strong id="bkDepositMin">—</strong> (<?=$depositPct?>% of total). You can pay up to the full amount.</div>
          </div>
        </div>

        <div class="fsec">📍 Pickup Location</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Pickup Area *</label>
            <select class="form-input" name="pickup_location" required><option value="">Select…</option><?php foreach($pickupLocations as $loc):?><option value="<?=e($loc)?>"><?=e($loc)?></option><?php endforeach;?></select>
          </div>
          <div class="form-group"><label class="form-label">City / Town *</label><input class="form-input" name="pickup_city" placeholder="e.g. Accra" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Street / Landmark</label><input class="form-input" name="pickup_street" placeholder="e.g. Oxford Street, Osu"></div>
          <div class="form-group"><label class="form-label">GPS Address</label><input class="form-input" name="pickup_gps" placeholder="e.g. GV-182-5478"></div>
        </div>

        <div class="fsec">🪪 ID Verification</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">ID Document Type *</label>
            <select class="form-input" name="id_document_type" required><option value="">Select…</option>
              <?php foreach(['Ghana Card','International Passport','Voter ID Card',"Driver's License",'SSNIT Card'] as $t):?><option value="<?=e($t)?>"><?=e($t)?></option><?php endforeach;?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">ID Number *</label><input class="form-input" name="id_document_number" placeholder="GHA-XXXXXXXXX-X" required></div>
        </div>
        <div class="form-group"><label class="form-label">Upload ID (image or PDF)</label><input class="form-input" type="file" name="id_document" accept="image/*,application/pdf"><div class="form-hint">Optional but recommended</div></div>

        <div class="fsec">📝 Additional</div>
        <div class="form-group"><label class="form-label">Special Requests</label><textarea class="form-input" name="special_requests" rows="2" placeholder="Any special requirements…"></textarea></div>
      </div>
      <div class="modal-footer" style="flex-shrink:0">
        <button type="button" class="btn btn-ghost" onclick="closeModal('bookingModal')">Cancel</button>
        <button type="submit" id="bkSubmitBtn" class="btn btn-primary" disabled>Confirm Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- Unavailable alternatives modal -->
<div class="modal-overlay" id="unavailModal">
  <div class="modal">
    <div class="modal-header"><span class="modal-title">⚠️ Dates Not Available</span><button class="modal-close" onclick="closeModal('unavailModal')">✕</button></div>
    <div class="modal-body">
      <p style="font-size:13.5px;margin-bottom:16px">These dates are booked for this vehicle. Choose different dates or pick another:</p>
      <button onclick="closeModal('unavailModal')" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:12px">← Change Dates</button>
      <div id="altVehicles"></div>
    </div>
  </div>
</div>

<script>
let bkV={};
function openBooking(v){bkV=v;document.getElementById('bkVehicleId').value=v.id;document.getElementById('bkVehicleName').textContent=v.name;document.getElementById('bkRateDisp').textContent='₵'+parseFloat(v.rate).toFixed(2);document.getElementById('bkCostBox').style.display='none';document.getElementById('bkSubmitBtn').disabled=true;document.getElementById('availAlert').style.display='none';openModal('bookingModal');}
const TAX=<?=$taxRate?>,SVC=<?=$serviceChargeRate?>,DEP=<?=$depositPct?>;
function calcAndCheck(){
  const p=document.getElementById('bkPickup').value,r=document.getElementById('bkReturn').value;
  if(!p||!r||!bkV.id)return;
  const days=Math.ceil((new Date(r)-new Date(p))/(864e5));
  if(days<1)return;
  const sub=days*parseFloat(bkV.rate),tax=sub*(TAX/100),svc=sub*(SVC/100),tot=sub+tax+svc,dep=tot*(DEP/100);
  document.getElementById('bkDaysDisp').textContent=days+'d';
  document.getElementById('bkSubtotal').textContent='₵'+sub.toFixed(2);
  if(document.getElementById('bkTax'))document.getElementById('bkTax').textContent='₵'+tax.toFixed(2);
  if(document.getElementById('bkSvc'))document.getElementById('bkSvc').textContent='₵'+svc.toFixed(2);
  document.getElementById('bkTotal').textContent='₵'+tot.toFixed(2);
  document.getElementById('bkDepositMin').textContent='₵'+dep.toFixed(2);
  document.getElementById('bkCostBox').style.display='block';
  const ab=document.getElementById('availAlert');
  ab.style.cssText='display:block;padding:11px 14px;border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px;background:rgba(59,110,248,.06);border:1px solid rgba(59,110,248,.2);color:var(--accent)';
  ab.textContent='🔍 Checking availability…';
  document.getElementById('bkSubmitBtn').disabled=true;
  fetch(`?check_availability=1&vehicle_id=${bkV.id}&pickup_date=${p}&return_date=${r}`).then(r=>r.json()).then(d=>{
    if(d.available){ab.style.cssText='display:block;padding:11px 14px;border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);color:#059669';ab.textContent='✅ Available for your selected dates!';document.getElementById('bkSubmitBtn').disabled=false;}
    else{ab.style.cssText='display:block;padding:11px 14px;border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#dc2626';ab.textContent='❌ '+d.message;document.getElementById('bkSubmitBtn').disabled=true;
      if(d.alternatives&&d.alternatives.length){let h='<div style="font-size:13px;font-weight:600;margin-bottom:10px">Available alternatives:</div>';d.alternatives.forEach(a=>{h+=`<div onclick="openBooking({id:${a.id},name:'${a.year||''} ${a.make} ${a.model}',rate:${a.daily_rate},category:'${a.category}'})" style="cursor:pointer;padding:12px;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:8px" onmouseover="this.style.background='rgba(59,110,248,.05)'" onmouseout="this.style.background=''"><div style="font-weight:500;font-size:13.5px">${a.year||''} ${a.make} ${a.model}</div><div style="font-size:12px;color:var(--text-secondary)">₵${parseFloat(a.daily_rate).toFixed(0)}/day</div></div>`;});document.getElementById('altVehicles').innerHTML=h;openModal('unavailModal');}}
  }).catch(()=>{ab.textContent='⚠️ Could not verify availability.';document.getElementById('bkSubmitBtn').disabled=false;});
}
</script>
<?php require_once __DIR__.'/../includes/customer-footer.php'; ?>
