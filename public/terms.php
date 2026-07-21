<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$companyEmail = 'info@inspectra.com.gh';

$sections = [
    ['id' => 'intro', 'icon' => '📜', 'title' => 'Introduction'],
    ['id' => 'bookings', 'icon' => '💳', 'title' => 'Bookings & Payments'],
    ['id' => 'cancellation', 'icon' => '↩️', 'title' => 'Cancellation & Refunds'],
    ['id' => 'vehicle-use', 'icon' => '🚗', 'title' => 'Vehicle Use & Responsibility'],
    ['id' => 'inspections', 'icon' => '🔍', 'title' => 'Inspections'],
    ['id' => 'liability', 'icon' => '🛡️', 'title' => 'Liability & Damage'],
    ['id' => 'privacy', 'icon' => '🔒', 'title' => 'Privacy'],
    ['id' => 'modifications', 'icon' => '✏️', 'title' => 'Modifications'],
    ['id' => 'contact', 'icon' => '✉️', 'title' => 'Contact'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms of Service — Inspectra</title>
<meta name="description" content="Inspectra's Terms of Service — bookings, payments, cancellations, vehicle use, and inspections.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/index.css">
<style>
.legal-wrap{max-width:1100px;margin:0 auto;padding:150px 5% 80px;display:grid;grid-template-columns:220px 1fr;gap:48px;align-items:start;}
.legal-header{grid-column:1/-1;text-align:center;max-width:640px;margin:0 auto 8px;}
.legal-meta{font-size:13px;color:var(--text-secondary);margin-top:10px;}
.legal-toc{position:sticky;top:96px;display:flex;flex-direction:column;gap:2px;}
.legal-toc a{padding:9px 12px;border-radius:var(--radius-sm);font-size:13px;color:var(--text-secondary);text-decoration:none;transition:all .15s;display:flex;align-items:center;gap:8px;}
.legal-toc a:hover{background:var(--surface);color:var(--text);}
.legal-toc a.active{background:rgba(59,110,248,0.08);color:var(--accent);font-weight:600;}
.legal-content{max-width:720px;}
.legal-section{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);padding:28px 32px;margin-bottom:18px;scroll-margin-top:96px;}
.legal-section h2{display:flex;align-items:center;gap:12px;font-size:19px;font-weight:700;margin-bottom:14px;letter-spacing:-0.2px;}
.legal-icon{width:36px;height:36px;border-radius:10px;background:rgba(59,110,248,0.08);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.legal-section p,.legal-section li{color:var(--text-secondary);line-height:1.8;font-size:14.5px;margin-bottom:12px;}
.legal-section ul{padding-left:22px;}
.legal-section li{margin-bottom:10px;}
.legal-section a{color:var(--accent);text-decoration:none;font-weight:500;}
.legal-section a:hover{text-decoration:underline;}
@media(max-width:900px){.legal-wrap{grid-template-columns:1fr;padding-top:120px;}.legal-toc{position:static;flex-direction:row;flex-wrap:wrap;gap:6px;margin-bottom:8px;}.legal-toc a{padding:6px 12px;background:var(--surface);}}
</style>
</head>
<body>

<nav>
  <a href="<?= APP_URL ?>/index.php" class="nav-logo">
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

<div class="legal-wrap">
  <div class="legal-header">
    <div class="section-badge" style="margin-left:auto;margin-right:auto">📜 Legal</div>
    <div class="section-title" style="font-size:36px">Terms of Service</div>
    <div class="legal-meta">Effective date: <?= date('F d, Y') ?> · Applies to every booking made through Inspectra</div>
  </div>

  <nav class="legal-toc" id="legalToc">
    <?php foreach ($sections as $s): ?>
    <a href="#<?= $s['id'] ?>"><?= $s['icon'] ?> <?= e($s['title']) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="legal-content">
    <div class="legal-section" id="intro">
      <h2><span class="legal-icon">📜</span> Introduction</h2>
      <p>These Terms of Service govern your access to and use of Inspectra. By using our platform, you agree to be bound by these terms. If you do not agree, please do not use Inspectra.</p>
    </div>

    <div class="legal-section" id="bookings">
      <h2><span class="legal-icon">💳</span> Bookings & Payments</h2>
      <ul>
        <li>Bookings are confirmed only after successful payment of the required deposit.</li>
        <li>Deposit amounts are displayed during checkout and are required to reserve the vehicle.</li>
        <li>Remaining rental balances can be paid at any time — in full or in installments — up until the full amount is settled.</li>
        <li>Payments are processed securely by our payment partners (Paystack and Stripe). Inspectra does not store raw card details.</li>
      </ul>
    </div>

    <div class="legal-section" id="cancellation">
      <h2><span class="legal-icon">↩️</span> Cancellation & Refunds</h2>
      <ul>
        <li>Cancellations should be submitted through the platform as soon as possible.</li>
        <li>Deposit refunds may be subject to the cancellation policy displayed at booking.</li>
        <li>Refunds are processed within 3–5 business days once approved.</li>
        <li>No-shows may forfeit the deposit and any other applicable fees.</li>
      </ul>
    </div>

    <div class="legal-section" id="vehicle-use">
      <h2><span class="legal-icon">🚗</span> Vehicle Use & Responsibility</h2>
      <ul>
        <li>You must present a valid driving licence that covers the rented vehicle type.</li>
        <li>Vehicles must not be used for illegal activity, subletting, or racing.</li>
        <li>You are responsible for fines, tolls, and penalties incurred during the rental period.</li>
        <li>Smoking is not allowed in vehicles unless explicitly permitted.</li>
      </ul>
    </div>

    <div class="legal-section" id="inspections">
      <h2><span class="legal-icon">🔍</span> Inspections</h2>
      <p>Every rental includes a digital inspection at pickup and return. Both renter and provider must review and acknowledge the inspection record before the vehicle changes hands.</p>
      <ul>
        <li>Inspection photos, notes, and timestamps are used to document the vehicle condition.</li>
        <li>Any pre-existing damage is recorded before the rental begins.</li>
        <li>Any post-trip charge is computed automatically from the pickup-vs-return comparison. If you disagree with it, you can file a dispute with evidence directly from your booking.</li>
        <li>Disputes are resolved based on the inspection evidence available in the platform.</li>
      </ul>
    </div>

    <div class="legal-section" id="liability">
      <h2><span class="legal-icon">🛡️</span> Liability & Damage</h2>
      <ul>
        <li>You are responsible for damage not documented in the initial inspection report.</li>
        <li>Inspection data is the primary evidence for damage assessment.</li>
        <li>You must report accidents or damage immediately to the platform provider.</li>
      </ul>
    </div>

    <div class="legal-section" id="privacy">
      <h2><span class="legal-icon">🔒</span> Privacy</h2>
      <p>Your use of the platform is subject to our <a href="<?= APP_URL ?>/public/privacy.php">Privacy Policy</a>, which explains how we collect and use personal information.</p>
    </div>

    <div class="legal-section" id="modifications">
      <h2><span class="legal-icon">✏️</span> Modifications</h2>
      <p>We may update these terms at any time. Changes take effect upon posting, and continued use of Inspectra after changes constitutes acceptance.</p>
    </div>

    <div class="legal-section" id="contact">
      <h2><span class="legal-icon">✉️</span> Contact</h2>
      <p>Questions about these terms may be sent to <a href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?></a>. For help, you can also visit our <a href="<?= APP_URL ?>/public/faq.php">FAQ page</a>.</p>
    </div>
  </div>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-grid">
    <div class="footer-brand">
      <a href="<?= APP_URL ?>" class="logo">
        <div class="logo-icon">🔍</div>
        <div class="logo-text">Inspectra</div>
      </a>
      <div class="footer-desc">The premium vehicle rental &amp; inspection platform. Built for trust, designed for clarity.</div>
    </div>
    <div class="footer-col">
      <h4>Platform</h4>
      <a href="<?= APP_URL ?>/public/browse.php">Browse Vehicles</a>
      <a href="<?= APP_URL ?>/auth/register.php">Create Account</a>
      <a href="<?= APP_URL ?>/auth/login.php">Sign In</a>
    </div>
    <div class="footer-col">
      <h4>Company</h4>
      <a href="<?= APP_URL ?>/public/about.php">About Us</a>
      <a href="<?= APP_URL ?>/public/contact.php">Contact</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <a href="<?= APP_URL ?>/public/terms.php">Terms of Service</a>
      <a href="<?= APP_URL ?>/public/privacy.php">Privacy Policy</a>
      <a href="<?= APP_URL ?>/public/faq.php">Frequently Asked Questions</a>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> Inspectra. All rights reserved.</span>
    <span>KDF IT Solutions, Ghana</span>
  </div>
</footer>

<script>
// Highlight the active TOC entry as the reader scrolls
const sections = document.querySelectorAll('.legal-section');
const tocLinks = document.querySelectorAll('#legalToc a');
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      tocLinks.forEach(a => a.classList.remove('active'));
      const link = document.querySelector(`#legalToc a[href="#${entry.target.id}"]`);
      if (link) link.classList.add('active');
    }
  });
}, { rootMargin: '-100px 0px -70% 0px' });
sections.forEach(s => observer.observe(s));
</script>

</body>
</html>
