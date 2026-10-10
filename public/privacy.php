<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$companyEmail = 'info@inspectra.com.gh';

$sections = [
    ['id' => 'intro', 'icon' => '🔒', 'title' => 'Introduction'],
    ['id' => 'collect', 'icon' => '📋', 'title' => 'Information We Collect'],
    ['id' => 'use', 'icon' => '⚙️', 'title' => 'How We Use It'],
    ['id' => 'sharing', 'icon' => '🤝', 'title' => 'Sharing Your Data'],
    ['id' => 'security', 'icon' => '🛡️', 'title' => 'Data Security'],
    ['id' => 'retention', 'icon' => '🗄️', 'title' => 'Retention'],
    ['id' => 'rights', 'icon' => '⚖️', 'title' => 'Your Rights'],
    ['id' => 'contact', 'icon' => '✉️', 'title' => 'Contact'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy — Inspectra</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<meta name="description" content="How Inspectra collects, uses, and protects your personal information.">
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
.legal-icon{width:36px;height:36px;border-radius:10px;background:rgba(0,201,167,0.08);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.legal-section p,.legal-section li{color:var(--text-secondary);line-height:1.8;font-size:14.5px;margin-bottom:12px;}
.legal-section ul{padding-left:22px;}
.legal-section li{margin-bottom:10px;}
.legal-section li strong{color:var(--text);}
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
    <div class="section-badge" style="margin-left:auto;margin-right:auto">🔒 Legal</div>
    <div class="section-title" style="font-size:36px">Privacy Policy</div>
    <div class="legal-meta">Effective date: <?= date('F d, Y') ?> · How we collect, use, and protect your data</div>
  </div>

  <nav class="legal-toc" id="legalToc">
    <?php foreach ($sections as $s): ?>
    <a href="#<?= $s['id'] ?>"><?= $s['icon'] ?> <?= e($s['title']) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="legal-content">
    <div class="legal-section" id="intro">
      <h2><span class="legal-icon">🔒</span> Introduction</h2>
      <p>At Inspectra, protecting your private information is a priority. This policy explains how we collect, use, store, and protect your data while using our vehicle rental and inspection platform.</p>
    </div>

    <div class="legal-section" id="collect">
      <h2><span class="legal-icon">📋</span> Information We Collect</h2>
      <ul>
        <li><strong>Account information:</strong> name, email address, phone number, and profile details, including optional identification fields (date of birth, address, ID document, emergency contact).</li>
        <li><strong>Booking information:</strong> selected vehicles, rental dates, pickup and return locations, and booking references.</li>
        <li><strong>Inspection information:</strong> photos, damage notes, timestamps, and GPS metadata.</li>
        <li><strong>Technical data:</strong> IP address, device type, browser details, and session activity for security and performance.</li>
      </ul>
    </div>

    <div class="legal-section" id="use">
      <h2><span class="legal-icon">⚙️</span> How We Use Your Information</h2>
      <ul>
        <li>To manage bookings, inspections, and customer support.</li>
        <li>To communicate important updates and transaction confirmations.</li>
        <li>To prevent fraud, maintain security, and protect the platform.</li>
        <li>To comply with legal requirements and assist in dispute resolution.</li>
      </ul>
    </div>

    <div class="legal-section" id="sharing">
      <h2><span class="legal-icon">🤝</span> Sharing Your Data</h2>
      <p>We do not sell your personal data. We share data only with trusted service providers and partners required for normal platform operations.</p>
      <ul>
        <li>Payment processors (Paystack and Stripe) for secure payment handling.</li>
        <li>Inspection and rental partners for booking coordination.</li>
        <li>Law enforcement or regulators when legally required.</li>
      </ul>
    </div>

    <div class="legal-section" id="security">
      <h2><span class="legal-icon">🛡️</span> Data Security</h2>
      <p>We protect your information with HTTPS encryption, secure password hashing, CSRF protection, and validation of all form input. Payment credentials are handled by our payment providers and are not stored on our servers.</p>
    </div>

    <div class="legal-section" id="retention">
      <h2><span class="legal-icon">🗄️</span> Retention</h2>
      <p>We retain data only as long as necessary to provide our services, fulfill legal obligations, and resolve disputes. Booking and inspection records are kept in accordance with our operational needs.</p>
    </div>

    <div class="legal-section" id="rights">
      <h2><span class="legal-icon">⚖️</span> Your Rights</h2>
      <ul>
        <li>Request access to your personal data.</li>
        <li>Request correction of inaccurate information.</li>
        <li>Request deletion of your account, subject to business and legal requirements.</li>
        <li>Object to marketing or profiling based on your data.</li>
      </ul>
    </div>

    <div class="legal-section" id="contact">
      <h2><span class="legal-icon">✉️</span> Contact</h2>
      <p>If you have questions or privacy concerns, email us at <a href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?></a>.</p>
      <p>Learn more in our <a href="<?= APP_URL ?>/public/terms.php">Terms of Service</a> or <a href="<?= APP_URL ?>/public/faq.php">FAQ</a>.</p>
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
