<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Reviews';
$activePage = 'reviews';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $review = DB::fetchOne("SELECT r.*, b.booking_ref FROM reviews r JOIN bookings b ON r.booking_id=b.id WHERE r.id=?", [$reviewId]);

    if ($review && $action === 'toggle_publish') {
        $newPublished = $review['is_published'] ? 0 : 1;
        DB::update('reviews', ['is_published' => $newPublished], 'id=?', [$reviewId]);
        log_audit(Auth::id(), 'update', 'review', $reviewId, ['is_published' => $review['is_published']], ['is_published' => $newPublished]);
        redirect(APP_URL . '/admin/reviews.php?success=' . urlencode('Review visibility updated.'));
    }

    if ($review && $action === 'respond') {
        $response = trim($_POST['response'] ?? '');
        DB::update('reviews', ['admin_response' => $response !== '' ? sanitize($response) : null], 'id=?', [$reviewId]);
        log_audit(Auth::id(), 'update', 'review', $reviewId, [], ['admin_response' => $response]);
        if ($response !== '') {
            create_notification($review['customer_id'], 'review_response', 'Response to Your Review',
                "Inspectra responded to your review for booking {$review['booking_ref']}:\n\n{$response}",
                ['booking_id' => $review['booking_id']]);
        }
        redirect(APP_URL . '/admin/reviews.php?success=' . urlencode('Response saved.'));
    }
}

$rating = $_GET['rating'] ?? '';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = "WHERE 1";
$params = [];
if ($rating) { $where .= " AND r.rating=?"; $params[] = (int)$rating; }
if ($q) {
    $where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR v.make LIKE ? OR v.model LIKE ? OR b.booking_ref LIKE ?)";
    $s = "%$q%"; array_push($params, $s, $s, $s, $s, $s);
}

$joins = "FROM reviews r
    JOIN bookings b ON r.booking_id=b.id
    JOIN users u ON r.customer_id=u.id
    JOIN vehicles v ON r.vehicle_id=v.id";

$total = DB::fetchOne("SELECT COUNT(*) c $joins $where", $params)['c'];
$stats = DB::fetchOne("SELECT AVG(r.rating) avg_rating, SUM(r.is_published=0) hidden_cnt $joins $where", $params);

$dataParams = array_merge($params, [$perPage, ($page - 1) * $perPage]);
$reviews = DB::fetchAll("
    SELECT r.*, u.first_name, u.last_name, v.make, v.model, b.booking_ref
    $joins
    $where
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
", $dataParams);

$pagination = paginate($total, $perPage, $page);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-value"><?= number_format($total) ?></div>
    <div class="stat-label">Total Reviews</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div>
    <div class="stat-value"><?= $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : '—' ?> <span style="font-size:16px">★</span></div>
    <div class="stat-label">Average Rating</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#6b7280,#9ca3af)"></div>
    <div class="stat-value"><?= (int)$stats['hidden_cnt'] ?></div>
    <div class="stat-label">Hidden</div>
  </div>
</div>

<form method="GET" style="margin-bottom:16px">
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?<?= http_build_query(array_merge($_GET, ['rating' => '', 'page' => 1])) ?>" class="chip <?= $rating === '' ? 'active' : '' ?>">All</a>
      <?php for ($i = 5; $i >= 1; $i--): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['rating' => $i, 'page' => 1])) ?>" class="chip <?= (string)$rating === (string)$i ? 'active' : '' ?>"><?= $i ?> ★</a>
      <?php endfor; ?>
    </div>
    <div class="search-bar" style="max-width:300px">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input name="q" value="<?= e($q) ?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Customer, vehicle, or booking ref...">
      <?php if ($rating): ?><input type="hidden" name="rating" value="<?= e($rating) ?>"><?php endif; ?>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Customer</th><th>Vehicle</th><th>Booking</th><th>Rating</th><th>Comment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($reviews)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <div class="empty-state-icon">⭐</div>
            <div class="empty-state-title">No reviews yet</div>
            <div class="empty-state-desc">Reviews left by customers after a completed trip will appear here.</div>
          </div>
        </td></tr>
        <?php else: foreach ($reviews as $r): ?>
        <tr>
          <td style="font-size:13px"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
          <td style="font-size:13px"><?= e($r['make'] . ' ' . $r['model']) ?></td>
          <td><span style="font-family:var(--mono);font-size:12px"><?= e($r['booking_ref']) ?></span></td>
          <td><?= render_stars((float)$r['rating'], 14) ?></td>
          <td style="font-size:12.5px;color:var(--text-secondary);max-width:220px">
            <?= $r['comment'] ? e(mb_substr($r['comment'], 0, 80)) . (mb_strlen($r['comment']) > 80 ? '…' : '') : '—' ?>
            <?php if ($r['admin_response']): ?><div style="margin-top:4px;font-size:11px;color:var(--accent)">↳ Responded</div><?php endif; ?>
          </td>
          <td><span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $r['is_published'] ? 'background:rgba(16,185,129,0.1);color:var(--success)' : 'background:rgba(107,114,128,0.1);color:#6b7280' ?>"><?= $r['is_published'] ? 'Published' : 'Hidden' ?></span></td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($r['created_at']) ?></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
                <button type="submit" class="action-btn" style="font-size:11px"><?= $r['is_published'] ? '🙈 Hide' : '👁️ Publish' ?></button>
              </form>
              <button type="button" class="action-btn primary" style="font-size:11px" onclick='openRespond(<?= $r['id'] ?>, <?= json_encode($r['admin_response'] ?? '') ?>)'>💬 Respond</button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pagination['pages'] > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap">
  <?php if ($pagination['has_prev']): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="action-btn">← Prev</a><?php endif; ?>
  <?php for ($i = max(1, $page - 2); $i <= min($pagination['pages'], $page + 2); $i++): ?>
  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="action-btn <?= $i === $page ? 'primary' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
  <?php if ($pagination['has_next']): ?><a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="action-btn">Next →</a><?php endif; ?>
</div>
<?php endif; ?>

<!-- Respond Modal -->
<div class="modal-overlay" id="respondModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Respond to Review</span>
      <button class="modal-close" onclick="closeModal('respondModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="respond">
      <input type="hidden" name="review_id" id="respondReviewId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Your Response</label>
          <textarea class="form-input" name="response" id="respondText" rows="4" placeholder="Write a professional response the customer will see..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('respondModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Response</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRespond(id, existing) {
  document.getElementById('respondReviewId').value = id;
  document.getElementById('respondText').value = existing || '';
  openModal('respondModal');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
