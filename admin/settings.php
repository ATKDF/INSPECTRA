<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Settings'; $activePage = 'settings';

if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_verify()) {
$keys=['company_name','company_email','company_phone','currency','currency_symbol','tax_rate','service_charge_rate','deposit_percentage','exchange_rate','stripe_enabled','paystack_enabled','require_deposit','pickup_locations','allow_partial_payment'];
    foreach ($keys as $key) {
        $val = isset($_POST[$key]) ? sanitize($_POST[$key]) : '0';
        DB::query("INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?", [$key,$val,$val]);
    }
    redirect(APP_URL.'/admin/settings.php?success='.urlencode('Settings saved!'));
}

$s=[]; foreach(DB::fetchAll("SELECT `key`,`value` FROM settings") as $r) $s[$r['key']]=$r['value'];
require_once __DIR__.'/../includes/admin-header.php';
?>
<div style="max-width:720px">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">

    <div class="card" style="padding:28px;margin-bottom:20px">
      <div class="section-title" style="margin-bottom:20px">🏢 Company Information</div>
      <div class="form-group"><label class="form-label">Company Name</label><input class="form-input" name="company_name" value="<?=e($s['company_name']??'Inspectra Rentals')?>"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Contact Email</label><input class="form-input" type="email" name="company_email" value="<?=e($s['company_email']??'')?>"></div>
        <div class="form-group"><label class="form-label">Contact Phone</label><input class="form-input" name="company_phone" value="<?=e($s['company_phone']??'')?>"></div>
      </div>
      <div class="form-group"><label class="form-label">Pickup Locations (comma-separated)</label><input class="form-input" name="pickup_locations" value="<?=e($s['pickup_locations']??'Osu,Tema')?>" placeholder="Osu,Tema,Airport"><div class="form-hint">These will appear as pickup area options in the booking form</div></div>
    </div>

    <div class="card" style="padding:28px;margin-bottom:20px">
      <div class="section-title" style="margin-bottom:20px">💰 Pricing & Charges</div>
      <div style="background:rgba(59,110,248,.05);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:18px;font-size:12.5px;color:var(--text-secondary)">
        ℹ️ Tax and Service Charge are calculated on the subtotal and added to the total. All charges are shown transparently to customers during booking.
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Currency Code</label><input class="form-input" name="currency" value="<?=e($s['currency']??'GHS')?>"></div>
        <div class="form-group"><label class="form-label">Currency Symbol</label><input class="form-input" name="currency_symbol" value="<?=e($s['currency_symbol']??'₵')?>"></div>
        <div class="form-group"><label class="form-label">GHS to USD Exchange Rate<br><small>GHS per 1 USD (e.g. 15.00)</small></label><input class="form-input" type="number" name="exchange_rate" step="0.01" min="1" value="<?=e($s['exchange_rate']??'15.00')?>"><div class="form-hint">Used for Stripe payments. Auto-updates from API but manual override available.</div></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Tax Rate (%)</label>
          <input class="form-input" type="number" name="tax_rate" step="0.01" min="0" max="100" value="<?=e($s['tax_rate']??'0')?>">
          <div class="form-hint">Set to 0 to disable tax</div>
        </div>
        <div class="form-group">
          <label class="form-label">Service Charge (%)</label>
          <input class="form-input" type="number" name="service_charge_rate" step="0.01" min="0" max="100" value="<?=e($s['service_charge_rate']??'0')?>">
          <div class="form-hint">Platform/admin service fee</div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Initial Deposit (%)</label>
          <input class="form-input" type="number" name="deposit_percentage" step="1" min="1" max="100" value="<?=e($s['deposit_percentage']??'50')?>">
          <div class="form-hint">Minimum % of total required as initial payment (default: 50%)</div>
        </div>
        <div class="form-group" style="display:flex;align-items:center;padding-top:20px">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;font-weight:500">
            <input type="checkbox" name="allow_partial_payment" value="1" <?=($s['allow_partial_payment']??'1')==='1'?'checked':''?>>
            Allow customers to pay between deposit and full amount
          </label>
        </div>
      </div>
    </div>

    <!-- Live preview -->
    <div class="card" style="padding:22px;margin-bottom:20px">
      <div class="section-title" style="margin-bottom:14px">🧮 Pricing Preview</div>
      <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px">For a ₵300/day vehicle booked for 3 days:</div>
      <div id="previewBox" style="background:var(--surface);border-radius:var(--radius-sm);padding:14px"></div>
    </div>

    <div class="card" style="padding:28px;margin-bottom:20px">
      <div class="section-title" style="margin-bottom:20px">💳 Payment Gateways</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div style="padding:16px;border:1px solid var(--border);border-radius:var(--radius-sm)">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:12px"><input type="checkbox" name="paystack_enabled" value="1" <?=($s['paystack_enabled']??'1')==='1'?'checked':''?>><strong style="font-size:13px">Paystack</strong></label>
          <div style="font-size:12px;color:var(--text-secondary)">Set keys in <code>config.php</code></div>
        </div>
        <div style="padding:16px;border:1px solid var(--border);border-radius:var(--radius-sm)">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:12px"><input type="checkbox" name="stripe_enabled" value="1" <?=($s['stripe_enabled']??'0')==='1'?'checked':''?>><strong style="font-size:13px">Stripe</strong></label>
          <div style="font-size:12px;color:var(--text-secondary)">Set keys in <code>config.php</code></div>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="font-size:14px;padding:12px 28px">💾 Save Settings</button>
  </form>
</div>

<script>
function updatePreview() {
  const tax = parseFloat(document.querySelector('[name=tax_rate]').value)||0;
  const svc = parseFloat(document.querySelector('[name=service_charge_rate]').value)||0;
  const dep = parseFloat(document.querySelector('[name=deposit_percentage]').value)||50;
  const sym = document.querySelector('[name=currency_symbol]').value||'₵';
  const sub=900,t=sub*(tax/100),s=sub*(svc/100),tot=sub+t+s,d=tot*(dep/100);
  document.getElementById('previewBox').innerHTML=`
    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;padding-bottom:6px;border-bottom:1px solid var(--border)"><span style="color:var(--text-secondary)">Subtotal (${sym}300 × 3 days)</span><span>${sym}${sub.toFixed(2)}</span></div>
    ${tax>0?`<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span style="color:var(--text-secondary)">Tax (${tax}%)</span><span>${sym}${t.toFixed(2)}</span></div>`:''}
    ${svc>0?`<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span style="color:var(--text-secondary)">Service Charge (${svc}%)</span><span>${sym}${s.toFixed(2)}</span></div>`:''}
    <div style="display:flex;justify-content:space-between;font-size:14px;font-weight:700;border-top:1px solid var(--border);padding-top:8px;margin-top:4px"><span>Total</span><span style="color:var(--accent)">${sym}${tot.toFixed(2)}</span></div>
    <div style="margin-top:8px;font-size:12.5px;color:var(--text-secondary)">Minimum deposit required: <strong style="color:var(--accent)">${sym}${d.toFixed(2)}</strong> (${dep}% of total)</div>
  `;
}
document.querySelectorAll('[name=tax_rate],[name=service_charge_rate],[name=deposit_percentage],[name=currency_symbol]').forEach(el=>el.addEventListener('input',updatePreview));
updatePreview();
</script>

<?php require_once __DIR__.'/../includes/admin-footer.php'; ?>
