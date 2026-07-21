<?php
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::redirectIfLoggedIn();

$error = '';
$success = $_GET['registered'] ?? false;
$next = rawurldecode($_REQUEST['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Security token mismatch. Please try again.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (!$email || !$password) {
            $error = 'Email and password are required.';
        } else {
            $user = DB::fetchOne("SELECT * FROM users WHERE email = ? AND is_active = 1", [$email]);
            if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
                $error = 'Invalid email or password.';
            } else {
                Auth::login($user);
                log_audit($user['id'], 'login', 'user', $user['id']);
                if ($next && preg_match('/^(\/|' . preg_quote(APP_URL, '/') . ')/i', $next)) {
                    redirect($next);
                }
                $dest = $user['role'] === 'admin'
                    ? APP_URL . '/admin/dashboard.php'
                    : APP_URL . '/customer/dashboard.php';
                redirect($dest);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — Inspectra</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/login.css">
</head>
<body>
<div class="bg-mesh"></div>
<div class="auth-wrap">
  <div class="auth-logo">
    <a href="<?= APP_URL ?>/index.php" class="logo-link">INSPECTRA</a>
  </div>

  <div class="auth-card">
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to your account to continue</p>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success">✅ Account created! Please sign in.</div>
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

    <div class="divider"><span class="divider-text">or sign in with email</span></div>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <div class="form-group">
        <label class="form-label">Email address</label>
        <input class="form-input" type="email" name="email" placeholder="you@email.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
      </div>

      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="input-group">
          <input class="form-input" type="password" name="password" id="pwdInput"
                 placeholder="••••••••" required autocomplete="current-password" style="padding-right: 44px;">
          <button type="button" class="input-toggle" onclick="togglePwd()">👁️</button>
        </div>
      </div>

      <div class="form-row">
        <label class="form-check">
          <input type="checkbox" name="remember"> Remember me
        </label>
        <a href="<?= APP_URL ?>/auth/forgot-password.php" class="link">Forgot password?</a>
      </div>

      <button type="submit" class="btn">Sign In</button>
    </form>

    <div class="auth-footer">
      Don't have an account? <a href="<?= APP_URL ?>/auth/register.php?next=<?= e(urlencode($next)) ?>" class="link">Create one</a>
    </div>
  </div>
</div>

<script>
function togglePwd() {
  const inp = document.getElementById('pwdInput');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
