<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle  = 'My Profile';
$activePage = 'profile';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $updates = [
            'first_name' => sanitize($_POST['first_name']),
            'last_name'  => sanitize($_POST['last_name']),
            'phone'      => sanitize($_POST['phone'] ?? ''),
            'date_of_birth' => $_POST['date_of_birth'] !== '' ? sanitize($_POST['date_of_birth']) : null,
            'address'    => sanitize($_POST['address'] ?? '') ?: null,
            'city'       => sanitize($_POST['city'] ?? '') ?: null,
            'id_document_type'   => sanitize($_POST['id_document_type'] ?? '') ?: null,
            'id_document_number' => sanitize($_POST['id_document_number'] ?? '') ?: null,
            'emergency_contact_name'  => sanitize($_POST['emergency_contact_name'] ?? '') ?: null,
            'emergency_contact_phone' => sanitize($_POST['emergency_contact_phone'] ?? '') ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (!empty($_FILES['avatar']['name'])) {
            $url = upload_file($_FILES['avatar'],'avatars',['image/jpeg','image/png','image/webp','image/gif']);
            if ($url) { $updates['avatar'] = $url; $updates['avatar_path'] = $url; }
        }
        DB::update('users', $updates, 'id=?', [Auth::id()]);
        log_audit(Auth::id(), 'update', 'user', Auth::id(), [], ['profile_updated' => true]);
        $_SESSION['user_name'] = $updates['first_name'] . ' ' . $updates['last_name'];
        redirect(APP_URL.'/admin/profile.php?success='.urlencode('Profile updated!'));
    }

    if ($action === 'change_password') {
        $user = DB::fetchOne("SELECT * FROM users WHERE id=?", [Auth::id()]);
        if (!Auth::verifyPassword($_POST['current_password'], $user['password_hash'])) redirect(APP_URL.'/admin/profile.php?error='.urlencode('Current password incorrect.'));
        if ($_POST['new_password'] !== $_POST['confirm_password']) redirect(APP_URL.'/admin/profile.php?error='.urlencode('Passwords do not match.'));
        if (strlen($_POST['new_password']) < 8) redirect(APP_URL.'/admin/profile.php?error='.urlencode('Password must be 8+ characters.'));
        DB::update('users',['password_hash'=>Auth::hashPassword($_POST['new_password'])],'id=?',[Auth::id()]);
        redirect(APP_URL.'/admin/profile.php?success='.urlencode('Password changed!'));
    }
}

$user = DB::fetchOne("SELECT * FROM users WHERE id=?", [Auth::id()]);
require_once __DIR__ . '/../includes/admin-header.php';
?>
<style>
.avatar-upload-wrap{position:relative;width:80px;height:80px;cursor:pointer;}
.avatar-upload-wrap .overlay{position:absolute;inset:0;background:rgba(0,0,0,.4);border-radius:50%;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;color:#fff;font-size:12px;font-weight:500;}
.avatar-upload-wrap:hover .overlay{opacity:1;}
.avatar-lg{width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#3b6ef8,#6366f1);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;object-fit:cover;}
</style>

<div style="max-width:640px">
  <div class="card" style="padding:28px;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px">
      <form method="POST" enctype="multipart/form-data" id="avatarForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="first_name" value="<?= e($user['first_name']) ?>">
        <input type="hidden" name="last_name"  value="<?= e($user['last_name']) ?>">
        <input type="hidden" name="phone"       value="<?= e($user['phone']??'') ?>">
        <input type="hidden" name="date_of_birth" value="<?= e($user['date_of_birth']??'') ?>">
        <input type="hidden" name="address" value="<?= e($user['address']??'') ?>">
        <input type="hidden" name="city" value="<?= e($user['city']??'') ?>">
        <input type="hidden" name="id_document_type" value="<?= e($user['id_document_type']??'') ?>">
        <input type="hidden" name="id_document_number" value="<?= e($user['id_document_number']??'') ?>">
        <input type="hidden" name="emergency_contact_name" value="<?= e($user['emergency_contact_name']??'') ?>">
        <input type="hidden" name="emergency_contact_phone" value="<?= e($user['emergency_contact_phone']??'') ?>">
        <div class="avatar-upload-wrap" onclick="document.getElementById('avatarInput').click()" title="Click to change photo">
          <?php if (!empty($user['avatar'])): ?>
          <img src="<?= e($user['avatar']) ?>" class="avatar-lg" id="avatarPreview" alt="Avatar">
          <?php else: ?>
          <div class="avatar-lg" id="avatarPreview"><?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)) ?></div>
          <?php endif; ?>
          <div class="overlay">📷 Change</div>
        </div>
        <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none" onchange="previewAvatar(this)">
      </form>
      <div>
        <div style="font-size:20px;font-weight:600"><?= e($user['first_name'].' '.$user['last_name']) ?></div>
        <div style="font-size:13px;color:var(--text-secondary)"><?= e($user['email']) ?></div>
        <div style="font-size:11px;color:var(--text-tertiary);margin-top:2px">Admin · Since <?= format_date($user['created_at'],'M Y') ?></div>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="update_profile">
      <div class="form-row">
        <div class="form-group"><label class="form-label">First Name</label><input class="form-input" name="first_name" value="<?= e($user['first_name']) ?>" required></div>
        <div class="form-group"><label class="form-label">Last Name</label><input class="form-input" name="last_name" value="<?= e($user['last_name']) ?>" required></div>
      </div>
      <div class="form-group"><label class="form-label">Email (read-only)</label><input class="form-input" value="<?= e($user['email']) ?>" disabled style="opacity:.6"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone" value="<?= e($user['phone']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Date of Birth</label><input class="form-input" type="date" name="date_of_birth" value="<?= e($user['date_of_birth']??'') ?>"></div>
      </div>

      <div class="section-title" style="margin:24px 0 14px;font-size:14px">📍 Address</div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Street Address</label><input class="form-input" name="address" value="<?= e($user['address']??'') ?>" placeholder="e.g. 12 Independence Ave"></div>
        <div class="form-group"><label class="form-label">City</label><input class="form-input" name="city" value="<?= e($user['city']??'') ?>" placeholder="e.g. Accra"></div>
      </div>

      <div class="section-title" style="margin:24px 0 14px;font-size:14px">🪪 Identification</div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">ID Document Type</label>
          <select class="form-input" name="id_document_type">
            <option value="">Select type</option>
            <?php foreach (['Ghana Card', "Driver's License", 'Passport', 'Voter ID'] as $docType): ?>
            <option value="<?= e($docType) ?>" <?= ($user['id_document_type'] ?? '') === $docType ? 'selected' : '' ?>><?= e($docType) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">ID Document Number</label><input class="form-input" name="id_document_number" value="<?= e($user['id_document_number']??'') ?>" placeholder="e.g. GHA-000000000-0"></div>
      </div>

      <div class="section-title" style="margin:24px 0 14px;font-size:14px">🆘 Emergency Contact</div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Contact Name</label><input class="form-input" name="emergency_contact_name" value="<?= e($user['emergency_contact_name']??'') ?>" placeholder="Full name"></div>
        <div class="form-group"><label class="form-label">Contact Phone</label><input class="form-input" name="emergency_contact_phone" value="<?= e($user['emergency_contact_phone']??'') ?>" placeholder="+233 24 000 0000"></div>
      </div>

      <div class="form-group">
        <label class="form-label">Profile Photo</label>
        <input class="form-input" type="file" name="avatar" accept="image/*">
        <div class="form-hint">JPG, PNG or WebP · Max 10MB</div>
      </div>
      <button type="submit" class="btn btn-primary">💾 Save Changes</button>
    </form>
  </div>

  <div class="card" style="padding:28px">
    <div class="section-title" style="margin-bottom:20px">🔒 Change Password</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group"><label class="form-label">Current Password</label><input class="form-input" type="password" name="current_password" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">New Password</label><input class="form-input" type="password" name="new_password" required minlength="8"></div>
        <div class="form-group"><label class="form-label">Confirm</label><input class="form-input" type="password" name="confirm_password" required></div>
      </div>
      <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
  </div>
</div>

<script>
function previewAvatar(input) {
  if (!input.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    const prev = document.getElementById('avatarPreview');
    if (prev.tagName === 'IMG') { prev.src = e.target.result; }
    else {
      const img = document.createElement('img');
      img.src = e.target.result; img.className = 'avatar-lg'; img.id = 'avatarPreview'; img.alt = 'Avatar';
      prev.replaceWith(img);
    }
    document.getElementById('avatarForm').submit();
  };
  reader.readAsDataURL(input.files[0]);
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
