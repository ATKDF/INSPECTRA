<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::redirectIfLoggedIn();

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $email = sanitize($_POST['email'] ?? '');
    $user  = DB::fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);

    // Always show "sent" to prevent email enumeration
    if ($user) {
        $token   = Auth::generateToken(32);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        DB::query("DELETE FROM password_resets WHERE email = ?", [$email]);
        DB::insert('password_resets', ['email' => $email, 'token' => $token, 'expires_at' => $expires, 'created_at' => date('Y-m-d H:i:s')]);

        $resetLink = APP_URL . '/auth/reset-password.php?token=' . $token;

        // In production send via PHPMailer / SMTP. For now log it.
        error_log("PASSWORD RESET LINK for {$email}: {$resetLink}");

        // Uncomment and configure SMTP to actually send email:
        // mail($email, 'Reset your Inspectra password',
        //     "Click the link to reset your password:\n\n{$resetLink}\n\nExpires in 1 hour.",
        //     "From: " . SMTP_FROM);
    }
    $sent = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password — Inspectra</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/forgot-password.css">
</head>
<body>
<div class="bg-mesh"></div>
<div class="wrap">
  <div class="logo">
  <a href="<?= APP_URL ?>/index.php" class="logo-link">INSPECTRA</a>
  </div>
  <div class="card">
    <div class="title">Forgot your password?</div>
    <div class="sub">Enter your email and we'll send you a link to reset it.</div>

    <?php if ($sent): ?>
    <div class="alert-success">✅ If that email is registered, a reset link has been sent. Check your inbox (and spam folder).</div>
    <?php else: ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-group">
        <label class="form-label">Email address</label>
        <input class="form-input" type="email" name="email" placeholder="you@email.com" required>
      </div>
      <button type="submit" class="btn">Send Reset Link</button>
    </form>
    <?php endif; ?>
    <div class="footer"><a href="<?= APP_URL ?>/auth/login.php" class="link">← Back to Sign In</a></div>
  </div>
</div>
</body>
</html>
