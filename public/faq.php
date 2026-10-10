<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$categories = [
    'booking' => ['icon' => '🚗', 'label' => 'Booking & Account'],
    'payments' => ['icon' => '💳', 'label' => 'Payments & Refunds'],
    'inspections' => ['icon' => '🔍', 'label' => 'Inspections & Damage'],
    'reviews' => ['icon' => '⭐', 'label' => 'Reviews'],
    'support' => ['icon' => '🔒', 'label' => 'Security & Support'],
];

$faqs = [
    ['booking', 'How do I make a booking?', 'Browse the available vehicles, choose your rental dates, and complete the booking form. You will need to register and sign in before confirming a booking.'],
    ['booking', 'Do I need an account to browse vehicles?', 'No. The public fleet — including photos, pricing, and customer reviews — is visible without signing in, but you must create an account to complete a booking and access your reservation details.'],
    ['booking', 'Can I extend my rental period?', 'Yes. Contact our team or request an extension from your booking details page, subject to vehicle availability for the new dates.'],
    ['payments', 'What payment methods are accepted?', 'We accept payments through Paystack (Visa/Mastercard, MTN Mobile Money, Vodafone Cash, AirtelTigo Money) and Stripe.'],
    ['payments', 'Can I pay in installments?', "Yes. You only need to pay the minimum deposit to confirm your booking, then top up the remaining balance whenever you're ready — the option to pay stays available on your booking until it's fully settled."],
    ['payments', 'What happens if I want to cancel?', 'Cancel through your booking page as soon as possible. Refund eligibility follows the cancellation policy shown during checkout and may be subject to a deposit fee.'],
    ['payments', 'How soon will I receive a refund?', 'Approved refunds are processed within 3–5 business days to the original payment method, depending on the payment provider.'],
    ['inspections', 'How is the inspection process handled?', 'A digital inspection is performed at pickup and return. Photos, damage notes, GPS metadata, and timestamps are captured and recorded in the platform. Both parties acknowledge the inspection before the vehicle is released.'],
    ['inspections', 'What should I do if there is damage to the vehicle?', 'Report it immediately through the platform. Inspection records at pickup and return are used to determine liability and compute any charge.'],
    ['inspections', 'What if I disagree with a charge after my trip?', "Any post-trip charge is computed automatically from the pickup-vs-return comparison — you can view exactly how it was calculated. If you still disagree, file a dispute with a description and evidence photos directly from your booking, and our team will review and respond."],
    ['reviews', 'Can I leave a review after my trip?', 'Yes. Once a trip is marked completed, you can rate your experience and leave a comment from your trip history. Published reviews appear on the vehicle\'s page to help other renters.'],
    ['support', 'Is my personal information safe?', 'Yes. We use HTTPS, secure password handling, CSRF protection, and do not store raw card data on our servers. Please see our Privacy Policy for full details.'],
    ['support', 'Where can I get more help?', 'If you still have questions, contact our support team via the Contact page or email us directly from the link below.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FAQ — Inspectra</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<meta name="description" content="Answers to common questions about booking, payments, inspections, and reviews on Inspectra.">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/index.css">
<style>
.faq-wrap{max-width:820px;margin:0 auto;padding:150px 5% 80px;}
.faq-header{text-align:center;margin-bottom:8px;}
.faq-header .section-badge{margin-left:auto;margin-right:auto}
.faq-filters{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin:28px 0 40px;}
.faq-filters .chip{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:30px;border:1px solid var(--border);font-size:13px;font-weight:500;color:var(--text-secondary);text-decoration:none;transition:all .15s;}
.faq-filters .chip:hover{background:var(--surface);color:var(--text);}
.faq-filters .chip.active{background:var(--accent);border-color:var(--accent);color:#fff;}
.faq-category-title{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--text-secondary);margin:36px 0 14px;display:flex;align-items:center;gap:8px;}
.faq-category-title:first-of-type{margin-top:0;}
.faq-item{background:var(--surface2);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:12px;transition:box-shadow .2s;}
.faq-item:hover{box-shadow:0 4px 16px rgba(15,23,42,0.06);}
.faq-q{display:flex;width:100%;gap:16px;align-items:center;justify-content:space-between;padding:18px 22px;cursor:pointer;background:none;border:none;text-align:left;font-family:var(--font);}
.faq-question{font-size:14.5px;font-weight:600;color:var(--text);}
.faq-chevron{transition:transform .2s ease;color:var(--text-secondary);flex-shrink:0;}
.faq-item.open .faq-chevron{transform:rotate(180deg);color:var(--accent);}
.faq-a{max-height:0;overflow:hidden;transition:max-height .25s ease;}
.faq-a-inner{padding:0 22px 20px;color:var(--text-secondary);line-height:1.75;font-size:13.5px;}
.faq-item.open .faq-a{max-height:400px;}
.faq-cta{margin-top:44px;padding:32px 28px;background:linear-gradient(135deg,#1a1f36,#2d3561);border-radius:var(--radius);text-align:center;color:#fff;}
.faq-cta h2{margin-bottom:8px;font-size:20px;font-weight:700;}
.faq-cta p{margin-bottom:18px;color:rgba(255,255,255,0.75);font-size:13.5px;}
.faq-cta .btn{background:#fff;color:#1a1f36;font-weight:600;}
.faq-cta .btn:hover{background:#f0f4ff;}
@media(max-width:600px){.faq-wrap{padding-top:120px;}}
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

<div class="faq-wrap">
  <div class="faq-header">
    <div class="section-badge">💬 Support</div>
    <div class="section-title" style="font-size:36px">Frequently Asked Questions</div>
    <div class="section-sub" style="margin-left:auto;margin-right:auto;text-align:center">Answers to the most common booking, payment, inspection, and account questions.</div>
  </div>

  <div class="faq-filters" id="faqFilters">
    <a href="#" class="chip active" data-filter="all">All</a>
    <?php foreach ($categories as $key => $cat): ?>
    <a href="#" class="chip" data-filter="<?= $key ?>"><?= $cat['icon'] ?> <?= e($cat['label']) ?></a>
    <?php endforeach; ?>
  </div>

  <div id="faqList">
    <?php $lastCategory = null; foreach ($faqs as $index => [$catKey, $question, $answer]): ?>
    <?php if ($catKey !== $lastCategory): $lastCategory = $catKey; ?>
    <div class="faq-category-title" data-category-heading="<?= $catKey ?>"><?= $categories[$catKey]['icon'] ?> <?= e($categories[$catKey]['label']) ?></div>
    <?php endif; ?>
    <article class="faq-item" id="faq<?= $index ?>" data-category="<?= $catKey ?>">
      <button type="button" class="faq-q" onclick="toggleFaq(<?= $index ?>)">
        <span class="faq-question"><?= e($question) ?></span>
        <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-a"><div class="faq-a-inner"><?= nl2br(e($answer)) ?></div></div>
    </article>
    <?php endforeach; ?>
  </div>

  <div class="faq-cta">
    <h2>Still have questions?</h2>
    <p>Reach out to us and our support team will respond quickly.</p>
    <a href="<?= APP_URL ?>/public/contact.php" class="btn">Contact Support</a>
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
function toggleFaq(index) {
  document.getElementById('faq' + index).classList.toggle('open');
}

document.getElementById('faqFilters').addEventListener('click', function(e) {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  e.preventDefault();
  this.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  const filter = chip.dataset.filter;

  document.querySelectorAll('.faq-item').forEach(item => {
    item.style.display = (filter === 'all' || item.dataset.category === filter) ? '' : 'none';
  });
  document.querySelectorAll('[data-category-heading]').forEach(heading => {
    heading.style.display = (filter === 'all' || heading.dataset.categoryHeading === filter) ? '' : 'none';
  });
});
</script>

</body>
</html>
