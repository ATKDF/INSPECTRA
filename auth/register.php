<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::redirectIfLoggedIn();

$error = '';
$next = rawurldecode($_REQUEST['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token mismatch.';
    } else {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name'] ?? '');
        $email     = sanitize($_POST['email'] ?? '');
        $phone     = sanitize($_POST['phone'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        if (!$firstName || !$lastName || !$email || !$password) {
            $error = 'All required fields must be filled.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (DB::fetchOne("SELECT id FROM users WHERE email = ?", [$email])) {
            $error = 'An account with this email already exists.';
        } else {
            $userId = DB::insert('users', [
                'uuid'          => Auth::generateUUID(),
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'email'         => $email,
                'phone'         => $phone,
                'password_hash' => Auth::hashPassword($password),
                'role'          => 'customer',
                'is_verified'   => 1,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            log_audit($userId, 'register', 'user', $userId, [], ['email' => $email, 'name' => $firstName . ' ' . $lastName]);

            redirect(APP_URL . '/auth/login.php?registered=1' . ($next ? '&next=' . urlencode($next) : ''));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — Inspectra</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/register.css">
</head>
<body>
<div class="bg-mesh"></div>
<div class="auth-wrap">
  <div class="auth-logo">
    <a href="<?= APP_URL ?>/index.php" class="logo-link">INSPECTRA</a>
  </div>
  <div class="auth-card">
    <h1 class="auth-title">Create your account</h1>
    <p class="auth-sub">Join Inspectra and start renting vehicles today</p>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <div class="oauth-btns">
      <a href="<?= APP_URL ?>/api/auth/google.php" class="oauth-btn">
        <svg width="16" height="16" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
        Google
      </a>
      <a href="#" class="oauth-btn" onclick="alert('Apple Sign In coming soon')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
        Apple
      </a>
    </div>
    <div class="divider"><span class="divider-text">or register with email</span></div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">First Name *</label>
          <input class="form-input" name="first_name" placeholder="Kofi" value="<?= e($_POST['first_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name *</label>
          <input class="form-input" name="last_name" placeholder="Mensah" value="<?= e($_POST['last_name'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address *</label>
        <input class="form-input" type="email" name="email" placeholder="you@email.com" value="<?= e($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number</label>
        <input class="form-input" type="tel" name="phone" placeholder="+233 24 000 0000" value="<?= e($_POST['phone'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password *</label>
          <input class="form-input" type="password" name="password" placeholder="Min. 8 characters" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password *</label>
          <input class="form-input" type="password" name="confirm_password" placeholder="Repeat password" required>
        </div>
      </div>
      <button type="submit" class="btn">Create Account</button>
      <p class="terms">By creating an account you agree to our <a href="<?= APP_URL ?>/public/terms.php" class="link">Terms of Service</a> and <a href="<?= APP_URL ?>/public/privacy.php" class="link">Privacy Policy</a></p>
    </form>
    <div class="auth-footer">Already have an account? <a href="<?= APP_URL ?>/auth/login.php?next=<?= e(urlencode($next)) ?>" class="link">Sign in</a></div>
  </div>
</div>
</body>
</html>
