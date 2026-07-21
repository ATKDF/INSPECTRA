<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::redirectIfLoggedIn();

$token = sanitize($_GET['token'] ?? '');
$error = '';
$done  = false;

// Validate token exists and is not expired
$reset = $token ? DB::fetchOne(
    "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()",
    [$token]
) : null;

if (!$reset && !$done) {
    $error = 'This reset link is invalid or has expired.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && $reset) {
    $pw  = $_POST['password'] ?? '';
    $pw2 = $_POST['confirm_password'] ?? '';

    if (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pw !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        DB::update('users', ['password_hash' => Auth::hashPassword($pw)], 'email = ?', [$reset['email']]);
        DB::query("DELETE FROM password_resets WHERE email = ?", [$reset['email']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — Inspectra</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/reset-password.css">
</head>
<body>
<div class="bg-mesh"></div>
<div class="wrap">
  <div class="logo">
    <a href="<?= APP_URL ?>/index.php" class="logo-link">INSPECTRA</a>
  </div>
  <div class="card">
    <?php if ($done): ?>
      <div style="text-align:center">
        <div style="font-size:48px;margin-bottom:16px">🎉</div>
        <div class="title">Password updated!</div>
        <div class="sub">Your password has been changed. You can now sign in.</div>
        <a href="<?= APP_URL ?>/auth/login.php" class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:8px">Sign In</a>
      </div>
    <?php else: ?>
      <div class="title">Set new password</div>
      <div class="sub">Choose a strong password for your account.</div>
      <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($reset): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input class="form-input" type="password" name="password" placeholder="Min. 8 characters" required minlength="8">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input class="form-input" type="password" name="confirm_password" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn">Update Password</button>
      </form>
      <?php endif; ?>
      <div class="footer"><a href="<?= APP_URL ?>/auth/login.php" class="link">← Back to Sign In</a></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
