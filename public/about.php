<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$stats = [
    'customers'   => (int) DB::fetchOne("SELECT COUNT(*) c FROM users WHERE role='customer'")['c'],
    'vehicles'    => (int) DB::fetchOne("SELECT COUNT(*) c FROM vehicles WHERE status='available'")['c'],
    'bookings'    => (int) DB::fetchOne("SELECT COUNT(*) c FROM bookings")['c'],
    'inspections' => (int) DB::fetchOne("SELECT COUNT(*) c FROM inspections WHERE status='completed'")['c'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About — Inspectra</title>
<meta name="description" content="Inspectra is the premium vehicle rental platform with digital inspection, damage verification and automated reporting.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/index.css">
<style>
.section-center{text-align:center;}
.section-center .section-sub{margin-left:auto;margin-right:auto;}

/* OUR STORY */
.story-grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:56px;align-items:center;}
.story-copy p{font-size:15.5px;color:var(--text-secondary);line-height:1.8;margin-bottom:16px;}
.story-visual{background:linear-gradient(135deg,#eef2ff,#e0f7f1);border:1px solid var(--border);border-radius:var(--radius);padding:48px 32px;text-align:center;}
.story-visual-icon{width:88px;height:88px;border-radius:50%;background:linear-gradient(135deg,#3b6ef8,#00c9a7);display:flex;align-items:center;justify-content:center;font-size:38px;margin:0 auto 20px;box-shadow:0 12px 32px rgba(59,110,248,0.25);}
.story-visual h3{font-size:18px;font-weight:700;margin-bottom:8px;}
.story-visual p{font-size:13.5px;color:var(--text-secondary);line-height:1.6;}
.story-stat-row{display:flex;justify-content:center;gap:32px;margin-top:24px;padding-top:24px;border-top:1px solid var(--border);}
.story-stat-val{font-size:22px;font-weight:700;color:var(--accent);}
.story-stat-label{font-size:11.5px;color:var(--text-secondary);margin-top:2px;}

/* STATS BAND */
.stats-band{background:var(--surface);border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:56px 5%;display:grid;grid-template-columns:repeat(4,1fr);gap:24px;text-align:center;}
.stats-band-val{font-size:34px;font-weight:800;letter-spacing:-1px;color:var(--text);}
.stats-band-label{font-size:13px;color:var(--text-secondary);margin-top:6px;}

/* TEAM */
.team-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px;margin-top:56px;}
.team-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:28px 20px;text-align:center;transition:all 0.2s;}
.team-card:hover{box-shadow:var(--shadow-lg);transform:translateY(-3px);background:#fff;}
.team-avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#3b6ef8,#6366f1);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;font-weight:700;margin:0 auto 14px;}
.team-name{font-size:14.5px;font-weight:600;margin-bottom:3px;}
.team-role{font-size:12.5px;color:var(--text-secondary);}

@media(max-width:900px){.story-grid{grid-template-columns:1fr;}.stats-band{grid-template-columns:1fr 1fr;}.team-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:600px){.team-grid{grid-template-columns:1fr;}}
</style>
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
<section class="hero hero-about">
  <div>
    <div class="hero-badge">🇬🇭 Built for Ghana</div>
    <h1>We're building the future of <span class="gradient">vehicle rentals.</span></h1>
    <p>Inspectra removes ambiguity from rentals with clear, timestamped inspections and an easy booking experience for renters and operators. Transparent inspections, fair outcomes — trusted by customers and operators.</p>
    <div class="hero-cta">
      <a href="<?= APP_URL ?>/public/browse.php" class="btn btn-primary" style="font-size:15px;padding:13px 28px">Browse Vehicles</a>
      <a href="<?= APP_URL ?>/public/contact.php" class="btn btn-outliner" style="font-size:15px;padding:13px 28px">Contact Us</a>
    </div>
  </div>
</section>

<!-- OUR STORY -->
<section>
  <div class="story-grid">
    <div class="story-copy">
      <div class="section-badge">📖 Our Story</div>
      <div class="section-title">Born from a simple frustration.</div>
      <p>Inspectra started with a problem every renter and rental operator in Ghana knows too well: the argument over who caused that scratch, that dent, that stain. Handshake agreements and blurry phone photos rarely held up when a deposit was on the line.</p>
      <p>So we built a platform where every rental begins and ends with a clear, timestamped, geotagged digital inspection — evidence both sides can trust, not opinions both sides dispute.</p>
      <p>Today, Inspectra powers bookings, payments, inspections and dispute resolution end-to-end, so rental companies can focus on their fleet and customers can drive off with confidence.</p>
    </div>
    <div class="story-visual">
      <div class="story-visual-icon">🔍</div>
      <h3>Evidence over arguments</h3>
      <p>Every pickup and return is documented in minutes, not disputed for weeks.</p>
      <div class="story-stat-row">
        <div>
          <div class="story-stat-val"><?= $stats['inspections'] ?>+</div>
          <div class="story-stat-label">Inspections Completed</div>
        </div>
        <div>
          <div class="story-stat-val"><?= $stats['bookings'] ?>+</div>
          <div class="story-stat-label">Bookings Handled</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS BAND -->
<div class="stats-band">
  <div>
    <div class="stats-band-val"><?= $stats['customers'] ?>+</div>
    <div class="stats-band-label">Registered Customers</div>
  </div>
  <div>
    <div class="stats-band-val"><?= $stats['vehicles'] ?>+</div>
    <div class="stats-band-label">Vehicles Available</div>
  </div>
  <div>
    <div class="stats-band-val"><?= $stats['bookings'] ?>+</div>
    <div class="stats-band-label">Bookings Handled</div>
  </div>
  <div>
    <div class="stats-band-val"><?= $stats['inspections'] ?>+</div>
    <div class="stats-band-label">Digital Inspections</div>
  </div>
</div>

<!-- MISSION / VALUES -->
<section>
  <div class="section-center" style="max-width:640px;margin:0 auto">
    <div class="section-badge">✨ Our Mission</div>
    <div class="section-title">Every rental, fully protected.</div>
    <div class="section-sub">To eliminate vehicle rental disputes through evidence-based digital inspections — making rentals fair, professional, and trustworthy for everyone involved.</div>
  </div>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(59,110,248,0.08)">🔍</div>
      <div class="feature-title">Evidence First</div>
      <div class="feature-desc">Every inspection produces timestamped, geotagged photos and notes that protect both renters and providers.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(0,201,167,0.08)">🤝</div>
      <div class="feature-title">Built for Trust</div>
      <div class="feature-desc">Shared inspection records create a single source of truth — reducing disputes and saving time.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(245,158,11,0.08)">⚡</div>
      <div class="feature-title">Relentlessly Simple</div>
      <div class="feature-desc">Powerful technology with a frictionless UX so teams can adopt quickly without training.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(99,102,241,0.08)">🇬🇭</div>
      <div class="feature-title">Local Expertise</div>
      <div class="feature-desc">Designed around how rentals actually work in Ghana — from mobile money payments to local pickup logistics.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(239,68,68,0.08)">🛡️</div>
      <div class="feature-title">Fair Resolutions</div>
      <div class="feature-desc">When disagreements happen, decisions are grounded in inspection evidence, not he-said-she-said.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon" style="background:rgba(0,201,167,0.08)">📈</div>
      <div class="feature-title">Always Improving</div>
      <div class="feature-desc">We ship based on real feedback from renters and operators using the platform every day.</div>
    </div>
  </div>
</section>

<!-- OUR PROCESS -->
<section class="how-bg">
  <div style="text-align:center;margin-bottom:8px">
    <div class="section-badge">⚙️ How We Operate</div>
    <div class="section-title">Our approach to every rental.</div>
  </div>
  <div class="steps">
    <?php
    $process = [
      ['✅','Vet the Fleet','Every vehicle is verified for condition, documentation, and roadworthiness before it is listed.'],
      ['📸','Document Everything','Digital inspections capture photos, notes, and GPS data at pickup and return — no exceptions.'],
      ['💬','Support Both Sides','Our team is on hand to help renters and operators resolve questions quickly and fairly.'],
      ['📊','Learn & Improve','Every rental feeds data back into better vetting, clearer reports, and a smoother platform.'],
    ];
    foreach ($process as $i => [$icon,$title,$desc]):
    ?>
    <div class="step">
      <div class="step-circle"><?= $icon ?><div class="step-num-badge"><?= $i+1 ?></div></div>
      <div class="step-title"><?= $title ?></div>
      <div class="step-desc"><?= $desc ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- TEAM -->
<section>
  <div class="section-center" style="max-width:640px;margin:0 auto">
    <div class="section-badge">👥 The Team</div>
    <div class="section-title">A team obsessed with clarity.</div>
    <div class="section-sub">A passionate team of engineers and designers committed to transforming the rental experience across Africa.</div>
  </div>
  <div class="team-grid">
    <div class="team-card">
      <div class="team-avatar">KA</div>
      <div class="team-name">Kweku Asante</div>
      <div class="team-role">Founder &amp; CEO</div>
    </div>
    <div class="team-card">
      <div class="team-avatar">MA</div>
      <div class="team-name">Maame Agyemang</div>
      <div class="team-role">Head of Product</div>
    </div>
    <div class="team-card">
      <div class="team-avatar">BA</div>
      <div class="team-name">Ben Asiedu</div>
      <div class="team-role">Lead Engineer</div>
    </div>
    <div class="team-card">
      <div class="team-avatar">AA</div>
      <div class="team-name">Abena Adjei</div>
      <div class="team-role">Design Lead</div>
    </div>
  </div>
</section>

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

</body>
</html>
