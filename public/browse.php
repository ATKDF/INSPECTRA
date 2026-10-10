<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$category = $_GET['category'] ?? '';
$q = $_GET['q'] ?? '';
$where = "WHERE v.status='available'";
$params = [];
if ($category) { $where .= " AND v.category=?"; $params[] = $category; }
if ($q) { $where .= " AND (v.make LIKE ? OR v.model LIKE ?)"; $s="%$q%"; $params=array_merge($params,[$s,$s]); }

$vehicles = DB::fetchAll("SELECT v.*, (SELECT photo_url FROM vehicle_photos WHERE vehicle_id=v.id AND is_primary=1 LIMIT 1) as primary_photo FROM vehicles v $where ORDER BY v.daily_rate ASC", $params);

$ratingsMap = [];
$vehicleIds = array_column($vehicles, 'id');
if ($vehicleIds) {
    $placeholders = implode(',', array_fill(0, count($vehicleIds), '?'));
    $ratingRows = DB::fetchAll("
        SELECT vehicle_id, COUNT(*) cnt, AVG(rating) avg_rating
        FROM reviews WHERE vehicle_id IN ($placeholders) AND is_published=1
        GROUP BY vehicle_id
    ", $vehicleIds);
    foreach ($ratingRows as $row) {
        $ratingsMap[$row['vehicle_id']] = ['avg' => round((float)$row['avg_rating'], 1), 'count' => (int)$row['cnt']];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Browse Vehicles — Inspectra</title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/favicon.svg">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/browse.css">
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
  <div class="nav-cta"></div>
  <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-outline">Sign In</a>
  <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary">Get Started</a>
</nav>

<div class="page-wrap">
  <div class="page-header">
    <div class="page-title">Browse Available Vehicles</div>
    <div class="page-sub"><?= count($vehicles) ?> vehicle<?= count($vehicles)!=1?'s':'' ?> ready to rent</div>
  </div>

  <form method="GET" class="filters">
    <input class="search-input" name="q" value="<?= e($q) ?>" placeholder="🔍 Search make or model...">
    <select class="cat-select" name="category" onchange="this.form.submit()">
      <option value="">All Categories</option>
      <?php foreach (['economy'=>'Economy','suv'=>'SUV','luxury'=>'Luxury','van'=>'Van','truck'=>'Truck','motorcycle'=>'Motorcycle'] as $k=>$l): ?>
      <option value="<?=$k?>" <?=$category===$k?'selected':''?>><?=$l?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if ($q||$category): ?><a href="?" class="btn btn-outline">Clear</a><?php endif; ?>
  </form>

  <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="?" class="chip <?= !$category?'active':'' ?>">All</a>
    <?php foreach (['economy'=>'Economy 🚗','suv'=>'SUV 🚙','luxury'=>'Luxury 🏎️','van'=>'Van 🚐'] as $k=>$l): ?>
    <a href="?category=<?=$k?>" class="chip <?=$category===$k?'active':''?>"><?=$l?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($vehicles)): ?>
  <div class="empty-state">
    <div class="empty-icon">🔍</div>
    <div class="empty-title">No vehicles found</div>
    <p>Try different search terms or <a href="?" style="color:var(--accent)">view all vehicles</a></p>
  </div>
  <?php else: ?>
  <div class="vehicles-grid">
    <?php foreach ($vehicles as $v): ?>
    <a href="<?= APP_URL ?>/public/vehicle.php?id=<?= e($v['id']) ?>" class="vcard">
      <div class="vcard-img">
        <?php if ($v['primary_photo']): ?>
        <img src="<?= e($v['primary_photo']) ?>" alt="<?= e($v['make'].' '.$v['model']) ?>">
        <?php else: ?>
        <?= ['economy'=>'🚗','suv'=>'🚙','luxury'=>'🏎️','van'=>'🚐','truck'=>'🚚','motorcycle'=>'🏍️'][$v['category']] ?? '🚗' ?>
        <?php endif; ?>
        <div class="price-badge">₵<?= number_format($v['daily_rate'],0) ?>/day</div>
      </div>
      <div class="vcard-body">
        <div class="vcard-title"><?= e($v['year'].' '.$v['make'].' '.$v['model']) ?></div>
        <div class="vcard-reg"><?= e($v['registration_number']) ?></div>
        <?php if (!empty($ratingsMap[$v['id']])): ?>
        <div style="display:flex;align-items:center;gap:6px;margin:4px 0 8px">
          <?= render_stars($ratingsMap[$v['id']]['avg'], 13) ?>
          <span style="font-size:12px;font-weight:700"><?= number_format($ratingsMap[$v['id']]['avg'], 1) ?></span>
          <span style="font-size:11px;color:var(--text-secondary)">(<?= $ratingsMap[$v['id']]['count'] ?>)</span>
        </div>
        <?php endif; ?>
        <div class="vcard-specs">
          <span>⛽ <?= ucfirst($v['fuel_type']) ?></span>
          <span>⚙️ <?= ucfirst($v['transmission']) ?></span>
          <span>💺 <?= $v['seats'] ?> seats</span>
        </div>
        <?php if ($v['description']): ?>
        <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px;line-height:1.5"><?= e(substr($v['description'],0,80)).(strlen($v['description'])>80?'...':'') ?></div>
        <?php endif; ?>
        <div class="vcard-footer">
          <div>
            <div class="vcard-price">₵<?= number_format($v['daily_rate'],0) ?></div>
            <div style="font-size:11px;color:var(--text-secondary)">per day<?= $v['deposit_amount']>0?' + ₵'.number_format($v['deposit_amount'],0).' deposit':'' ?></div>
          </div>
          <div class="book-btn">Book Now →</div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div style="text-align:center;margin-top:60px;padding:40px;background:#fff;border-radius:var(--radius);border:1px solid var(--border)">
    <div style="font-size:22px;font-weight:700;margin-bottom:8px">Ready to book?</div>
    <div style="color:var(--text-secondary);margin-bottom:20px">Create a free account to book any vehicle in minutes.</div>
    <a href="<?= APP_URL ?>/auth/register.php" class="btn btn-primary" style="font-size:14px;padding:12px 28px">Create Free Account</a>
  </div>
</div>
</body>
</html>
