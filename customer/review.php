<?php
define('INSPECTRA_CUSTOMER', true);
require_once __DIR__ . '/../includes/bootstrap.php';
Auth::requireCustomer();

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) redirect(APP_URL . '/customer/trip-history.php');

$booking = DB::fetchOne("
    SELECT b.*, v.make, v.model, v.registration_number, v.year
    FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id
    WHERE b.id=? AND b.customer_id=?
", [$bookingId, Auth::id()]);

if (!$booking) redirect(APP_URL . '/customer/trip-history.php');
if ($booking['status'] !== 'completed') {
    redirect(APP_URL . '/customer/trip-history.php?error=' . urlencode('You can review a trip once it is completed.'));
}

$existing = DB::fetchOne("SELECT * FROM reviews WHERE booking_id=?", [$bookingId]);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    if (!csrf_verify()) {
        redirect(APP_URL . '/customer/trip-history.php?error=' . urlencode('Security check failed, please try again.'));
    }
    $rating = (int)($_POST['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) $errors[] = 'Please choose an overall rating.';

    $subRatings = [];
    foreach (['cleanliness', 'service', 'value'] as $field) {
        $v = $_POST[$field] ?? '';
        $subRatings[$field] = ($v !== '' && (int)$v >= 1 && (int)$v <= 5) ? (int)$v : null;
    }
    $comment = trim($_POST['comment'] ?? '');

    if (empty($errors)) {
        $reviewId = DB::insert('reviews', [
            'uuid'        => Auth::generateUUID(),
            'booking_id'  => $bookingId,
            'customer_id' => Auth::id(),
            'vehicle_id'  => $booking['vehicle_id'],
            'rating'      => $rating,
            'cleanliness' => $subRatings['cleanliness'],
            'service'     => $subRatings['service'],
            'value'       => $subRatings['value'],
            'comment'     => $comment !== '' ? sanitize($comment) : null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        log_audit(Auth::id(), 'create', 'review', $reviewId, [], ['booking_ref' => $booking['booking_ref'], 'rating' => $rating]);

        foreach (DB::fetchAll("SELECT id FROM users WHERE role='admin' AND is_active=1") as $adm) {
            create_notification($adm['id'], 'review_submitted', 'New Review',
                Auth::name() . " left a {$rating}-star review for {$booking['make']} {$booking['model']} (Booking {$booking['booking_ref']}).",
                ['booking_id' => $bookingId]);
        }

        redirect(APP_URL . '/customer/trip-history.php?success=' . urlencode('Thanks for your review!'));
    }
}

$pageTitle = 'Leave a Review — ' . $booking['booking_ref'];
$activePage = 'trips';

require_once __DIR__ . '/../includes/customer-header.php';
?>
<style>
.star-picker{display:inline-flex;flex-direction:row-reverse;gap:4px}
.star-picker input{position:absolute;opacity:0;width:0;height:0}
.star-picker label{cursor:pointer;color:var(--border);transition:color .15s;user-select:none}
.star-picker input:checked ~ label{color:#f59e0b}
.star-picker label:hover,.star-picker label:hover ~ label{color:#f59e0b}
.star-picker-lg label{font-size:40px}
.star-picker-sm label{font-size:24px}
.rating-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--border)}
.rating-row:last-child{border-bottom:none}
.rating-row-label{font-size:13px;font-weight:500;color:var(--text-secondary)}
</style>

<div class="card" style="max-width:560px;margin:0 auto;padding:24px">
  <div style="text-align:center;margin-bottom:20px">
    <div style="font-size:13px;color:var(--text-secondary)">How was your trip with</div>
    <div style="font-size:18px;font-weight:600;margin-top:2px"><?= e($booking['year'] . ' ' . $booking['make'] . ' ' . $booking['model']) ?></div>
    <div style="font-size:12px;color:var(--text-tertiary);font-family:var(--mono);margin-top:2px"><?= e($booking['booking_ref']) ?></div>
  </div>

  <?php if (!empty($errors)): ?>
  <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);color:var(--danger);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:16px;font-size:13px">
    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($existing): ?>
  <!-- Read-only: already reviewed -->
  <div style="text-align:center;padding:12px 0 20px">
    <?= render_stars((float)$existing['rating'], 34) ?>
    <div style="font-size:12px;color:var(--text-tertiary);margin-top:6px">You rated this trip <?= (int)$existing['rating'] ?>/5 on <?= format_date($existing['created_at']) ?></div>
  </div>

  <?php if ($existing['cleanliness'] || $existing['service'] || $existing['value']): ?>
  <div style="background:var(--surface2);border-radius:var(--radius-sm);padding:4px 16px;margin-bottom:16px">
    <?php foreach (['cleanliness' => 'Cleanliness', 'service' => 'Service', 'value' => 'Value for Money'] as $field => $label): ?>
    <?php if ($existing[$field]): ?>
    <div class="rating-row"><span class="rating-row-label"><?= $label ?></span><?= render_stars((float)$existing[$field], 16) ?></div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($existing['comment']): ?>
  <div style="background:var(--surface2);border-radius:var(--radius-sm);padding:14px 16px;font-size:13.5px;color:var(--text-secondary);margin-bottom:16px">
    "<?= nl2br(e($existing['comment'])) ?>"
  </div>
  <?php endif; ?>

  <?php if ($existing['admin_response']): ?>
  <div style="background:rgba(59,110,248,.05);border:1px solid rgba(59,110,248,.15);border-radius:var(--radius-sm);padding:14px 16px;font-size:13px">
    <strong>Response from Inspectra:</strong><br>
    <?= nl2br(e($existing['admin_response'])) ?>
  </div>
  <?php endif; ?>

  <div style="text-align:center;margin-top:20px">
    <a href="<?= APP_URL ?>/customer/trip-history.php" class="btn btn-ghost">← Back to Trip History</a>
  </div>

  <?php else: ?>
  <!-- Form -->
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div style="text-align:center;margin-bottom:24px">
      <div style="font-size:13px;font-weight:600;margin-bottom:10px">Overall Rating *</div>
      <div class="star-picker star-picker-lg" role="radiogroup" aria-label="Overall rating">
        <?php for ($i = 5; $i >= 1; $i--): ?>
        <input type="radio" id="rating-<?= $i ?>" name="rating" value="<?= $i ?>" required>
        <label for="rating-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
        <?php endfor; ?>
      </div>
    </div>

    <div style="background:var(--surface2);border-radius:var(--radius-sm);padding:4px 16px;margin-bottom:20px">
      <?php foreach (['cleanliness' => 'Cleanliness', 'service' => 'Service', 'value' => 'Value for Money'] as $field => $label): ?>
      <div class="rating-row">
        <span class="rating-row-label"><?= $label ?> <span style="color:var(--text-tertiary);font-weight:400">(optional)</span></span>
        <div class="star-picker star-picker-sm" role="radiogroup" aria-label="<?= $label ?> rating">
          <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" id="<?= $field ?>-<?= $i ?>" name="<?= $field ?>" value="<?= $i ?>">
          <label for="<?= $field ?>-<?= $i ?>" title="<?= $i ?>">★</label>
          <?php endfor; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="form-group">
      <label class="form-label">Your Comments <span style="color:var(--text-tertiary);font-weight:400">(optional)</span></label>
      <textarea class="form-input" name="comment" rows="4" placeholder="Tell other renters about your experience..."></textarea>
    </div>

    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">Submit Review</button>
    <a href="<?= APP_URL ?>/customer/trip-history.php" class="btn btn-ghost" style="width:100%;justify-content:center;margin-top:8px;text-decoration:none">Cancel</a>
  </form>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/customer-footer.php'; ?>
