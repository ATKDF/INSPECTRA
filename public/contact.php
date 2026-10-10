<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $name    = sanitize($_POST['name'] ?? '');
    $email   = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    if ($name && $email && $message) {
        $messageId = DB::insert('contact_messages', [
            'uuid'    => Auth::generateUUID(),
            'name'    => $name,
            'email'   => $email,
            'subject' => $subject !== '' ? $subject : null,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $preview = mb_strlen($message) > 120 ? mb_substr($message, 0, 120) . '…' : $message;
        foreach (DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
            create_notification($adm['id'], 'contact_message', 'New Contact Message',
                "{$name} ({$email}) sent a message" . ($subject ? " — \"{$subject}\"" : '') . ":\n\n{$preview}",
                ['contact_message_id' => $messageId]);
        }

        $success = true;
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact — Inspectra</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/contact.css">
</head>
<body>
<nav>
  <a href="<?= APP_URL ?>" class="nav-logo">
    <div class="nav-logo-text">INSPECTRA</div>
  </a>
  <div class="nav-links">
    <a href="<?= APP_URL ?>/index.php">Home</a>
    <a href="<?= APP_URL ?>/public/browse.php">Browse Vehicles</a>
    <a href="<?= APP_URL ?>/public/about.php">About</a>
    <a href="<?= APP_URL ?>/public/contact.php">Contact</a>
  </div>
  <div class="nav-cta">
    <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline">Sign In</a>
    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary">Get Started</a>
  </div>
</nav>

<div class="page-wrap">
  <div class="contact-info">
    <h1>Get in touch</h1>
    <p>Have questions about Inspectra? Want to partner with us or need support? We'd love to hear from you.</p>
    <div class="info-item">
      <div class="info-icon">📧</div>
      <div><div class="info-label">Email</div><div class="info-val">info@inspectra.com</div></div>
    </div>
    <div class="info-item">
      <div class="info-icon">📞</div>
      <div><div class="info-label">Phone</div><div class="info-val">+233 54 294 9653</div></div>
    </div>
    <div class="info-item">
      <div class="info-icon">📍</div>
      <div><div class="info-label">Location</div><div class="info-val">Accra, Ghana</div></div>
    </div>
    <div class="info-item">
      <div class="info-icon">⏰</div>
      <div><div class="info-label">Hours</div><div class="info-val">Mon–Sat, 8am–6pm</div></div>
    </div>
  </div>

  <div class="contact-form">
    <div style="font-size:20px;font-weight:600;margin-bottom:6px">Send us a message</div>
    <div style="font-size:13px;color:var(--text-secondary);margin-bottom:24px">We typically respond within 24 hours.</div>

    <?php if ($success): ?>
    <div class="alert alert-success">✅ Your message has been sent! We'll get back to you soon.</div>
    <?php elseif ($error): ?>
    <div class="alert alert-error">⚠️ <?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Your Name *</label>
          <input class="form-input" name="name" value="<?= e($_POST['name']??'') ?>" placeholder="Kofi Mensah" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address *</label>
          <input class="form-input" type="email" name="email" value="<?= e($_POST['email']??'') ?>" placeholder="you@email.com" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Subject</label>
        <input class="form-input" name="subject" value="<?= e($_POST['subject']??'') ?>" placeholder="How can we help?">
      </div>
      <div class="form-group">
        <label class="form-label">Message *</label>
        <textarea class="form-input" name="message" rows="5" required placeholder="Tell us what's on your mind..."><?= e($_POST['message']??'') ?></textarea>
      </div>
      <button type="submit" class="submit-btn">Send Message</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<footer>© <?= date('Y') ?> Inspectra. KDF IT Solutions, Ghana</footer>
</body>
</html>
