<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    redirect(APP_URL . '/public/browse.php');
}

$bookingError = '';
$bookingData = [
    'pickup_date' => '',
    'pickup_time' => '09:00',
    'return_date' => '',
    'return_time' => '09:00',
    'pickup_location' => '',
    'pickup_city' => '',
    'pickup_street' => '',
    'pickup_gps' => '',
    'id_document_type' => '',
    'id_document_number' => '',
    'special_requests' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    if (!Auth::check() || Auth::role() !== 'customer') {
        redirect(APP_URL . '/auth/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }

    $vehicleId = $id;
    $pickupDate = $_POST['pickup_date'] ?? '';
    $returnDate = $_POST['return_date'] ?? '';
    $pickupTime = sanitize($_POST['pickup_time'] ?? '');
    $returnTime = sanitize($_POST['return_time'] ?? '');
    $bookingData = [
        'pickup_date' => $pickupDate,
        'pickup_time' => $pickupTime ?: '09:00',
        'return_date' => $returnDate,
        'return_time' => $returnTime ?: '09:00',
        'pickup_location' => sanitize($_POST['pickup_location'] ?? ''),
        'pickup_city' => sanitize($_POST['pickup_city'] ?? ''),
        'pickup_street' => sanitize($_POST['pickup_street'] ?? ''),
        'pickup_gps' => sanitize($_POST['pickup_gps'] ?? ''),
        'id_document_type' => sanitize($_POST['id_document_type'] ?? ''),
        'id_document_number' => sanitize($_POST['id_document_number'] ?? ''),
        'special_requests' => sanitize($_POST['special_requests'] ?? ''),
    ];

    if (!$pickupDate || !$returnDate || !$pickupTime || !$returnTime) {
        $bookingError = 'Please select pickup and return dates and times.';
    } else {
        $vehicle = DB::fetchOne("SELECT * FROM vehicles WHERE id=? AND status='available'", [$vehicleId]);
        if (!$vehicle) {
            redirect(APP_URL . '/public/browse.php?error=' . urlencode('Vehicle not available'));
        }

        $pickupTs = strtotime($pickupDate);
        $returnTs = strtotime($returnDate);
        if ($pickupTs === false || $returnTs === false) {
            $bookingError = 'Please enter valid pickup and return dates.';
        } else {
            $days = (int) ceil(($returnTs - $pickupTs) / 86400);
            if ($days < 1) {
                $bookingError = 'Return date must be after pickup date.';
            } elseif (!check_vehicle_availability($vehicleId, $pickupDate, $returnDate)) {
                $bookingError = 'Sorry, these dates are no longer available for this vehicle. Please choose different dates.';
            } else {
                $calc = calculate_booking_amounts($vehicle['daily_rate'], $days);
                $idDocUrl = '';
                if (!empty($_FILES['id_document']['name'])) {
                    $idDocUrl = upload_file($_FILES['id_document'], 'documents', ['image/jpeg','image/png','image/webp','application/pdf']) ?: '';
                }

                $bookingId = DB::insert('bookings', [
                    'uuid' => Auth::generateUUID(),
                    'booking_ref' => Auth::generateBookingRef(),
                    'vehicle_id' => $vehicleId,
                    'customer_id' => Auth::id(),
                    'pickup_date' => $pickupDate,
                    'return_date' => $returnDate,
                    'pickup_time' => $pickupTime,
                    'return_time' => $returnTime,
                    'pickup_location' => $bookingData['pickup_location'],
                    'pickup_city' => $bookingData['pickup_city'],
                    'pickup_street' => $bookingData['pickup_street'],
                    'pickup_gps' => $bookingData['pickup_gps'],
                    'id_document_type' => $bookingData['id_document_type'],
                    'id_document_number' => $bookingData['id_document_number'],
                    'id_document_url' => $idDocUrl,
                    'total_days' => $days,
                    'daily_rate' => $vehicle['daily_rate'],
                    'subtotal' => $calc['subtotal'],
                    'tax_amount' => $calc['taxAmount'],
                    'service_charge' => $calc['serviceCharge'],
                    'total_amount' => $calc['totalAmount'],
                    'initial_deposit' => $calc['initialDeposit'],
                    'deposit_amount' => $calc['initialDeposit'],
                    'status' => 'booked',
                    'payment_status' => 'unpaid',
                    'special_requests' => $bookingData['special_requests'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $ref = DB::fetchOne('SELECT booking_ref FROM bookings WHERE id = ?', [$bookingId])['booking_ref'];
                log_audit(Auth::id(), 'create', 'booking', $bookingId, [], ['booking_ref' => $ref, 'vehicle' => $vehicle['make'] . ' ' . $vehicle['model'], 'total_amount' => $calc['totalAmount']]);
                create_notification(Auth::id(), 'booking_created', 'Booking Created', "Your booking {$ref} is confirmed. Please complete payment.", ['booking_id' => $bookingId]);
                foreach (DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
                    $adminMessage = "🔔 New Booking Created!\n\n👤 Customer: " . Auth::name() . "\n📌 Booking Ref: {$ref}\n🚗 Vehicle: {$vehicle['make']} {$vehicle['model']}\n📅 Duration: {$days} day(s)\n💰 Total Amount: ₵" . number_format($calc['totalAmount'], 2) . "\n\n📍 Pickup: {$pickupDate} at " . $bookingData['pickup_location'] . "\n🔄 Return: {$returnDate}\n\n⏳ Awaiting Payment";
                    create_notification($adm['id'], 'new_booking', 'New Booking', $adminMessage, ['booking_id' => $bookingId]);
                }

                redirect(APP_URL . '/customer/bookings.php?id=' . $bookingId . '&success=' . urlencode('Booking created! Complete payment to confirm.'));
            }
        }
    }
}

$vehicle = DB::fetchOne(
    "SELECT v.*, (SELECT photo_url FROM vehicle_photos WHERE vehicle_id=v.id AND is_primary=1 LIMIT 1) as primary_photo FROM vehicles v WHERE v.id = ? AND v.status = 'available'",
    [$id]
);

if (!$vehicle) {
    redirect(APP_URL . '/public/browse.php');
}

$photoUrls = DB::fetchAll("SELECT photo_url FROM vehicle_photos WHERE vehicle_id = ? ORDER BY is_primary DESC, id ASC", [$id]);
$photoUrls = array_column($photoUrls, 'photo_url');
$pickupLocations = array_filter(array_map('trim', explode(',', get_setting('pickup_locations', 'Osu,Tema'))));
$loggedInCustomer = Auth::check() && Auth::role() === 'customer';
$ratingSummary = get_vehicle_rating_summary($id);
$reviews = DB::fetchAll("
    SELECT r.*, u.first_name, u.last_name
    FROM reviews r JOIN users u ON r.customer_id = u.id
    WHERE r.vehicle_id = ? AND r.is_published = 1
    ORDER BY r.created_at DESC LIMIT 10
", [$id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?> — Inspectra</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/vehicle.css">
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
  <div class="vehicle-header">
    <div>
      <div class="section-badge">Vehicle Details</div>
      <h1><?= e($vehicle['year'].' '.$vehicle['make'].' '.$vehicle['model']) ?></h1>
      <p class="vehicle-category"><?= ucfirst($vehicle['category'] ?? 'Vehicle') ?> · <?= ucfirst($vehicle['fuel_type'] ?? 'N/A') ?> · <?= ucfirst($vehicle['transmission'] ?? 'N/A') ?></p>
      <?php if ($ratingSummary['count'] > 0): ?>
      <div style="display:flex;align-items:center;gap:8px;margin-top:10px">
        <?= render_stars($ratingSummary['avg'], 17) ?>
        <span style="font-size:14px;font-weight:700;color:var(--text)"><?= number_format($ratingSummary['avg'], 1) ?></span>
        <span style="font-size:13px;color:var(--text-secondary)"><?= $ratingSummary['count'] ?> review<?= $ratingSummary['count'] != 1 ? 's' : '' ?></span>
      </div>
      <?php endif; ?>
    </div>
    <div class="vehicle-rate">
      <div class="rate-label">Daily rate</div>
      <div class="rate-value">₵<?= number_format($vehicle['daily_rate'],0) ?></div>
    </div>
  </div>

  <div class="vehicle-grid">
    <div class="vehicle-gallery">
      <div class="vehicle-main-image">
        <img src="<?= e($photoUrls[0] ?? $vehicle['primary_photo'] ?? '') ?>" alt="<?= e($vehicle['make'].' '.$vehicle['model']) ?>">
      </div>
      <?php if (!empty($photoUrls)): ?>
      <div class="gallery-thumbs">
        <?php foreach ($photoUrls as $url): ?>
          <button type="button" class="thumb-button" onclick="document.querySelector('.vehicle-main-image img').src='<?= e($url) ?>'">
            <img src="<?= e($url) ?>" alt="Vehicle photo">
          </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($loggedInCustomer): ?>
      <div class="vehicle-details-block">
        <div class="vehicle-card-row">
          <div>
            <div class="vehicle-meta-label">Registration</div>
            <div class="vehicle-meta-value"><?= e($vehicle['registration_number'] ?? 'N/A') ?></div>
          </div>
          <div>
            <div class="vehicle-meta-label">Seats</div>
            <div class="vehicle-meta-value"><?= e($vehicle['seats'] ?? 'N/A') ?></div>
          </div>
        </div>
        <div class="vehicle-card-row">
          <div>
            <div class="vehicle-meta-label">Fuel type</div>
            <div class="vehicle-meta-value"><?= ucfirst($vehicle['fuel_type'] ?? 'N/A') ?></div>
          </div>
          <div>
            <div class="vehicle-meta-label">Transmission</div>
            <div class="vehicle-meta-value"><?= ucfirst($vehicle['transmission'] ?? 'N/A') ?></div>
          </div>
        </div>
        <div class="vehicle-card-row">
          <div>
            <div class="vehicle-meta-label">Category</div>
            <div class="vehicle-meta-value"><?= ucfirst($vehicle['category'] ?? 'N/A') ?></div>
          </div>
          <div>
            <div class="vehicle-meta-label">Availability</div>
            <div class="vehicle-meta-value"><?= $vehicle['status'] === 'available' ? 'Available now' : ucfirst($vehicle['status']) ?></div>
          </div>
        </div>
      </div>

      <?php if (!empty($vehicle['description'])): ?>
      <div class="vehicle-description">
        <?= nl2br(e($vehicle['description'])) ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="vehicle-details-card<?= $loggedInCustomer ? '' : ' guest-summary' ?>">
      <?php if ($bookingError): ?>
      <div class="alert alert-error"><?= e($bookingError) ?></div>
      <?php endif; ?>

      <?php if (!$loggedInCustomer): ?>
      <div class="vehicle-details-block guest-details-block">
        <div class="vehicle-card-row">
          <div>
            <div class="vehicle-meta-label">Registration</div>
            <div class="vehicle-meta-value"><?= e($vehicle['registration_number'] ?? 'N/A') ?></div>
          </div>
          <div>
            <div class="vehicle-meta-label">Seats</div>
            <div class="vehicle-meta-value"><?= e($vehicle['seats'] ?? 'N/A') ?></div>
          </div>
        </div>
        <div class="vehicle-card-row">
          <div>
            <div class="vehicle-meta-label">Fuel type</div>
            <div class="vehicle-meta-value"><?= ucfirst($vehicle['fuel_type'] ?? 'N/A') ?></div>
          </div>
          <div>
            <div class="vehicle-meta-label">Transmission</div>
            <div class="vehicle-meta-value"><?= ucfirst($vehicle['transmission'] ?? 'N/A') ?></div>
          </div>
        </div>
        <div class="vehicle-card-row">
          <div>
            <div class="vehicle-meta-label">Category</div>
            <div class="vehicle-meta-value"><?= ucfirst($vehicle['category'] ?? 'N/A') ?></div>
          </div>
          <div>
            <div class="vehicle-meta-label">Availability</div>
            <div class="vehicle-meta-value"><?= $vehicle['status'] === 'available' ? 'Available now' : ucfirst($vehicle['status']) ?></div>
          </div>
        </div>
        <?php if (!empty($vehicle['description'])): ?>
        <div class="vehicle-description">
          <?= nl2br(e($vehicle['description'])) ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="vehicle-actions">
        <a href="<?= APP_URL ?>/auth/login.php?next=<?= e(urlencode($_SERVER['REQUEST_URI'])) ?>" class="btn btn-primary">Sign in to book</a>
        <a href="<?= APP_URL ?>/auth/register.php?next=<?= e(urlencode($_SERVER['REQUEST_URI'])) ?>" class="btn btn-outline">Create Free Account</a>
        <a href="<?= APP_URL ?>/public/browse.php" class="btn btn-outline">Back to browse</a>
      </div>
      <?php else: ?>
      <form method="POST" enctype="multipart/form-data" class="booking-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="vehicle_id" value="<?= $id ?>">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pickup Date *</label>
            <input class="form-input" type="date" name="pickup_date" value="<?= e($bookingData['pickup_date']) ?>" min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Pickup Time *</label>
            <input class="form-input" type="time" name="pickup_time" value="<?= e($bookingData['pickup_time']) ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Return Date *</label>
            <input class="form-input" type="date" name="return_date" value="<?= e($bookingData['return_date']) ?>" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Return Time *</label>
            <input class="form-input" type="time" name="return_time" value="<?= e($bookingData['return_time']) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Pickup Location</label>
          <?php if (!empty($pickupLocations)): ?>
            <select class="form-input" name="pickup_location">
              <option value="">Select pickup location</option>
              <?php foreach ($pickupLocations as $location): ?>
              <option value="<?= e($location) ?>" <?= $bookingData['pickup_location'] === $location ? 'selected' : '' ?>><?= e($location) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input class="form-input" type="text" name="pickup_location" value="<?= e($bookingData['pickup_location']) ?>" placeholder="Pickup location">
          <?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pickup City</label>
            <input class="form-input" type="text" name="pickup_city" value="<?= e($bookingData['pickup_city']) ?>" placeholder="City">
          </div>
          <div class="form-group">
            <label class="form-label">Pickup Street</label>
            <input class="form-input" type="text" name="pickup_street" value="<?= e($bookingData['pickup_street']) ?>" placeholder="Street address">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">ID Document</label>
          <div style="display:grid;gap:12px;">
            <input class="form-input" type="text" name="id_document_type" value="<?= e($bookingData['id_document_type']) ?>" placeholder="e.g. Driver's license">
            <input class="form-input" type="text" name="id_document_number" value="<?= e($bookingData['id_document_number']) ?>" placeholder="Document number">
            <input class="form-input" type="file" name="id_document" accept="image/jpeg,image/png,image/webp,application/pdf">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Notes / Special requests</label>
          <textarea class="form-input" name="special_requests" placeholder="Extra notes for the rental"><?= e($bookingData['special_requests']) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Book this vehicle</button>
        <a href="<?= APP_URL ?>/public/browse.php" class="btn btn-outline btn-block">Back to browse</a>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="reviews-section" style="margin-top:48px">
    <div class="section-badge">Customer Reviews</div>
    <div style="display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
      <h2 style="font-size:26px;font-weight:800;margin:0">What renters are saying</h2>
      <?php if ($ratingSummary['count'] > 0): ?>
      <div style="display:flex;align-items:center;gap:10px">
        <?= render_stars($ratingSummary['avg'], 20) ?>
        <span style="font-size:18px;font-weight:800"><?= number_format($ratingSummary['avg'], 1) ?></span>
        <span style="font-size:14px;color:var(--text-secondary)">based on <?= $ratingSummary['count'] ?> review<?= $ratingSummary['count'] != 1 ? 's' : '' ?></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if (empty($reviews)): ?>
    <div class="vehicle-details-card" style="text-align:center;color:var(--text-secondary);font-size:14px">
      No reviews yet — be the first to book and share your experience.
    </div>
    <?php else: ?>
    <?php if ($ratingSummary['avg_cleanliness'] || $ratingSummary['avg_service'] || $ratingSummary['avg_value']): ?>
    <div class="vehicle-details-card" style="margin-bottom:20px">
      <div class="vehicle-card-row" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:0">
        <?php foreach (['avg_cleanliness' => 'Cleanliness', 'avg_service' => 'Service', 'avg_value' => 'Value for Money'] as $key => $label): ?>
        <?php if ($ratingSummary[$key]): ?>
        <div>
          <div class="vehicle-meta-label"><?= $label ?></div>
          <div style="display:flex;align-items:center;gap:6px;margin-top:4px"><?= render_stars($ratingSummary[$key], 15) ?><span style="font-size:13px;font-weight:700"><?= number_format($ratingSummary[$key], 1) ?></span></div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div style="display:grid;gap:16px">
      <?php foreach ($reviews as $r): ?>
      <div class="vehicle-details-card">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap">
          <div style="display:flex;align-items:center;gap:10px">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--surface);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--accent)">
              <?= e(mb_strtoupper(mb_substr($r['first_name'], 0, 1))) ?>
            </div>
            <div>
              <div style="font-size:14px;font-weight:700"><?= e($r['first_name'] . ' ' . mb_substr($r['last_name'], 0, 1) . '.') ?></div>
              <div style="font-size:12px;color:var(--text-secondary)"><?= date('M Y', strtotime($r['created_at'])) ?></div>
            </div>
          </div>
          <?= render_stars((float)$r['rating'], 16) ?>
        </div>
        <?php if ($r['comment']): ?>
        <p style="font-size:14px;color:var(--text-secondary);line-height:1.65;margin:0"><?= nl2br(e($r['comment'])) ?></p>
        <?php endif; ?>
        <?php if ($r['admin_response']): ?>
        <div style="margin-top:12px;padding:12px 16px;background:var(--surface);border-radius:var(--radius-sm);font-size:13px">
          <strong style="color:var(--accent)">Response from Inspectra:</strong> <?= nl2br(e($r['admin_response'])) ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
