<?php
require_once __DIR__ . '/includes/bootstrap.php';
// Redirect logged-in users
if (Auth::check()) {
    redirect(Auth::role() === 'admin' ? APP_URL.'/admin/dashboard.php' : APP_URL.'/customer/dashboard.php');
}
$featuredVehicles = DB::fetchAll("SELECT v.*, (SELECT photo_url FROM vehicle_photos WHERE vehicle_id=v.id AND is_primary=1 LIMIT 1) as primary_photo FROM vehicles v WHERE v.status='available' ORDER BY v.created_at DESC LIMIT 6");

$testimonials = DB::fetchAll("
    SELECT r.rating, r.comment, u.first_name, u.last_name, v.make, v.model
    FROM reviews r
    JOIN users u ON r.customer_id = u.id
    JOIN vehicles v ON r.vehicle_id = v.id
    WHERE r.is_published = 1 AND r.comment IS NOT NULL AND r.comment != ''
    ORDER BY r.rating DESC, r.created_at DESC
    LIMIT 3
");

$heroStats = [
    'customers'  => (int) DB::fetchOne("SELECT COUNT(*) c FROM users WHERE role='customer'")['c'],
    'vehicles'   => (int) DB::fetchOne("SELECT COUNT(*) c FROM vehicles WHERE status='available'")['c'],
    'avg_rating' => (float) (DB::fetchOne("SELECT AVG(rating) a FROM reviews WHERE is_published=1")['a'] ?? 0),
];
$disputeStats = DB::fetchOne("SELECT COUNT(*) total, SUM(status IN ('resolved','closed')) resolved FROM disputes");
$heroStats['resolution_rate'] = $disputeStats && $disputeStats['total'] > 0
    ? round(($disputeStats['resolved'] / $disputeStats['total']) * 100)
    : 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inspectra — Smart Vehicle Rental & Inspection</title>
<meta name="description" content="The premium vehicle rental platform with digital inspection, damage verification and automated reporting. Rent with confidence.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/index.css">
</head>
<body>

<!-- NAV -->
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

<!-- HERO -->
<section class="hero">
  <div>
    <div class="hero-badge">🇬🇭 Premium Vehicle Rental</div>
    <h1>Rent Vehicles.<br>Inspect with <span class="gradient">Confidence.</span></h1>
    <p>The rental platform with digital inspection, evidence-based damage verification, and automated reporting. No disputes, just clarity.</p>
    <div class="hero-cta">
      <a href="<?= APP_URL ?>/public/browse.php" class="btn btn-primary" style="font-size:15px;padding:13px 28px">Browse Vehicles</a>
      <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-outliner" style="font-size:15px;padding:13px 28px">Create Free Account</a>
    </div>
    <div class="hero-stats">
      <div style="text-align:center">
        <div class="hero-stat-val"><?= $heroStats['customers'] ?>+</div>
        <div class="hero-stat-label">Happy Customers</div>
      </div>
      <div style="text-align:center">
        <div class="hero-stat-val"><?= $heroStats['resolution_rate'] ?>%</div>
        <div class="hero-stat-label">Dispute Resolution</div>
      </div>
      <div style="text-align:center">
        <div class="hero-stat-val"><?= $heroStats['vehicles'] ?>+</div>
        <div class="hero-stat-label">Vehicles Available</div>
      </div>
      <div style="text-align:center">
        <div class="hero-stat-val"><?= $heroStats['avg_rating'] > 0 ? number_format($heroStats['avg_rating'], 1) . '★' : 'New' ?></div>
        <div class="hero-stat-label">Customer Rating</div>
      </div>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section>
  <div style="max-width:580px">
    <div class="section-badge">✨ Why Inspectra</div>
    <div class="section-title">Every rental, <br>fully protected.</div>
    <div class="section-sub">We combine beautiful design with inspection technology to protect both renters and rental companies.</div>
  </div>
  <div class="features-grid">
    <?php
    $features = [
      ['🔍','Digital Inspection','Step-by-step guided photo capture from all angles. Timestamped, geotagged, and legally defensible.','rgba(59,110,248,0.08)'],
      ['🗺️','Visual Damage Mapping','Interactive vehicle diagram to mark and document damage precisely. Visual heatmaps for clarity.','rgba(239,68,68,0.08)'],
      ['📸','Before vs After','Drag-to-compare slider showing pickup vs return condition side-by-side. Differences highlighted automatically.','rgba(0,201,167,0.08)'],
      ['✍️','Digital Signatures','Touch signatures on mobile for both customer and agent. Legally binding with timestamp binding.','rgba(245,158,11,0.08)'],
      ['📄','Automated Reports','PDF Vehicle Condition Reports generated instantly. Share via link, download, or email.','rgba(99,102,241,0.08)'],
      ['💳','Secure Payments','Pay via Paystack or Stripe. Deposits, refunds, and payment history all tracked transparently.','rgba(249,115,22,0.08)'],
    ];
    foreach ($features as [$icon,$title,$desc,$bg]):
    ?>
    <div class="feature-card">
      <div class="feature-icon" style="background:<?= $bg ?>"><?= $icon ?></div>
      <div class="feature-title"><?= $title ?></div>
      <div class="feature-desc"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- VEHICLES -->
<?php if (!empty($featuredVehicles)): ?>
<section>
  <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:0">
    <div>
      <div class="section-badge">🚗 Fleet</div>
      <div class="section-title">Available Vehicles</div>
    </div>
    <a href="<?= APP_URL ?>/public/browse.php" class="btn btn-outline">View all →</a>
  </div>
  <div class="pub-vehicles">
    <?php foreach ($featuredVehicles as $v): ?>
    <a href="<?= APP_URL ?>/public/vehicle.php?id=<?= e($v['id']) ?>" class="pub-vcard" style="text-decoration:none;color:inherit">
      <div class="pub-vcard-img">
        <?php if ($v['primary_photo']): ?>
        <img src="<?= e($v['primary_photo']) ?>" alt="<?= e($v['make'].' '.$v['model']) ?>">
        <?php else: ?>
        <?= ['economy'=>'🚗','suv'=>'🚙','luxury'=>'🏎️','van'=>'🚐','truck'=>'🚚','motorcycle'=>'🏍️'][$v['category']] ?? '🚗' ?>
        <?php endif; ?>
        <div class="pub-vcard-badge">₵<?= number_format($v['daily_rate'],0) ?>/day</div>
      </div>
      <div class="pub-vcard-body">
        <div class="pub-vcard-title"><?= e($v['year'].' '.$v['make'].' '.$v['model']) ?></div>
        <div class="pub-vcard-meta">
          <span>⛽ <?= ucfirst($v['fuel_type']) ?></span>
          <span>⚙️ <?= ucfirst($v['transmission']) ?></span>
          <span>💺 <?= $v['seats'] ?></span>
        </div>
        <div class="pub-vcard-footer">
          <span style="font-size:18px;font-weight:700;color:var(--accent)">₵<?= number_format($v['daily_rate'],0) ?></span>
          <span style="background:var(--accent);color:#fff;padding:7px 16px;border-radius:8px;font-size:12.5px;font-weight:500">Book Now</span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- HOW IT WORKS -->
<section class="how-bg">
  <div style="text-align:center;margin-bottom:8px">
    <div class="section-badge">⚡ How It Works</div>
    <div class="section-title">From booking to return,<br>in 4 simple steps.</div>
  </div>
  <div class="steps">
    <?php
    $steps = [
      ['📋','Book Online','Browse vehicles, pick your dates, and book in minutes.'],
      ['💳','Make Payment','Pay securely via Paystack or Stripe. Get instant confirmation.'],
      ['🔍','Pickup Inspection','Both parties inspect & sign off digitally before you drive.'],
      ['✅','Return & Report','Return inspection auto-compares. Report generated instantly.'],
    ];
    foreach ($steps as $i => [$icon,$title,$desc]):
    ?>
    <div class="step">
      <div class="step-circle"><?= $icon ?><div class="step-num-badge"><?= $i+1 ?></div></div>
      <div class="step-title"><?= $title ?></div>
      <div class="step-desc"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- TESTIMONIALS -->
<?php if (!empty($testimonials)): ?>
<section class="how-bg">
  <div style="text-align:center">
    <div class="section-badge">💬 Testimonials</div>
    <div class="section-title" style="margin:0 auto 8px">Loved by customers</div>
  </div>
  <div class="testimonials">
    <?php foreach ($testimonials as $t):
      $initials = mb_strtoupper(mb_substr($t['first_name'], 0, 1) . mb_substr($t['last_name'], 0, 1));
      $name = $t['first_name'] . ' ' . mb_substr($t['last_name'], 0, 1) . '.';
    ?>
    <div class="tcard">
      <div class="tcard-stars"><?= str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']) ?></div>
      <div class="tcard-text">"<?= e($t['comment']) ?>"</div>
      <div class="tcard-author">
        <div class="tcard-avatar"><?= e($initials) ?></div>
        <div>
          <div class="tcard-name"><?= e($name) ?></div>
          <div class="tcard-role">Rented a <?= e($t['make'] . ' ' . $t['model']) ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- CTA -->
<div class="cta-banner">
  <h2>Ready to rent smarter?</h2>
  <p>Join thousands of customers who trust Inspectra for transparent, dispute-free rentals.</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;position:relative">
    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-white" style="font-size:15px;padding:13px 32px">Get Started Free</a>
    <a href="<?= APP_URL ?>/public/browse.php" class="btn" style="background:rgba(255,255,255,0.15);color:#fff;font-size:15px;padding:13px 32px;border:1px solid rgba(255,255,255,0.3)">Browse Vehicles</a>
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
      <a href="./public/terms.php">Terms of Service</a>
      <a href="./public/privacy.php">Privacy Policy</a>
      <a href="./public/faq.php">Frequently Asked Questions</a>
    </div>
  </div>
  <div class="footer-bottom">
    <span>© <?= date('Y') ?> Inspectra. All rights reserved.</span>
    <span>KDF IT Solutions, Ghana</span>
  </div>
</footer>

</body>
</html>
