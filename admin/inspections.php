<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Inspections';
$activePage = 'inspections';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_inspection') {
        $bookingId = (int)$_POST['booking_id'];
        $type = $_POST['type'];
        $existing = DB::fetchOne("SELECT id FROM inspections WHERE booking_id=? AND type=?", [$bookingId, $type]);
        if (!$existing) {
            $inspId = DB::insert('inspections', [
                'uuid'          => Auth::generateUUID(),
                'booking_id'    => $bookingId,
                'type'          => $type,
                'status'        => 'in_progress',
                'conducted_by'  => Auth::id(),
                'agent_id'      => Auth::id(),
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
            redirect(APP_URL . '/admin/inspection-flow.php?id=' . $inspId);
        } else {
            redirect(APP_URL . '/admin/inspection-flow.php?id=' . $existing['id']);
        }
    }
}

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$where = "WHERE 1";
$params = [];
if ($bookingId) { $where .= " AND i.booking_id = ?"; $params[] = $bookingId; }

$inspections = DB::fetchAll("SELECT i.*, b.booking_ref, b.status as booking_status, v.make, v.model, v.registration_number, u.first_name, u.last_name, (SELECT COUNT(*) FROM damage_reports WHERE inspection_id=i.id) as damage_count FROM inspections i JOIN bookings b ON i.booking_id=b.id JOIN vehicles v ON b.vehicle_id=v.id JOIN users u ON b.customer_id=u.id $where ORDER BY i.created_at DESC", $params);

// For start inspection modal
$activeBookings = DB::fetchAll("SELECT b.*, v.make, v.model, v.registration_number FROM bookings b JOIN vehicles v ON b.vehicle_id=v.id WHERE b.status IN ('paid','active','returned') ORDER BY b.created_at DESC");

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
  <div style="font-size:13px;color:var(--text-secondary)"><?= count($inspections) ?> inspection<?= count($inspections)!=1?'s':'' ?></div>
  <button class="btn btn-primary" onclick="openModal('startInspModal')">
    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16"><path d="M12 5v14M5 12h14"/></svg>
    Start Inspection
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Booking</th><th>Vehicle</th><th>Customer</th><th>Type</th><th>Status</th><th>Damages</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($inspections)): ?>
        <tr><td colspan="8" style="text-align:center;padding:50px;color:var(--text-secondary);">No inspections yet</td></tr>
        <?php else: foreach ($inspections as $insp): ?>
        <tr>
          <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($insp['booking_ref']) ?></span></td>
          <td><?= e($insp['make'].' '.$insp['model']) ?><br><span style="font-size:11px;color:var(--text-tertiary);font-family:var(--mono)"><?= e($insp['registration_number']) ?></span></td>
          <td><?= e($insp['first_name'].' '.$insp['last_name']) ?></td>
          <td>
            <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $insp['type']==='pickup'?'background:rgba(59,110,248,0.1);color:var(--accent)':'background:rgba(249,115,22,0.1);color:#f97316' ?>">
              <?= ucfirst($insp['type']) ?>
            </span>
          </td>
          <td><?= status_badge($insp['status']) ?></td>
          <td>
            <?php if ($insp['damage_count'] > 0): ?>
            <span style="color:var(--danger);font-weight:600">⚠️ <?= $insp['damage_count'] ?></span>
            <?php else: ?>
            <span style="color:var(--success)">✅ None</span>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($insp['created_at']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/admin/inspection-flow.php?id=<?= $insp['id'] ?>" class="action-btn primary">
              <?= $insp['status']==='completed'?'View':'Continue' ?>
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Start Inspection Modal -->
<div class="modal-overlay" id="startInspModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Start Inspection</span>
      <button class="modal-close" onclick="closeModal('startInspModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="start_inspection">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Booking</label>
          <select class="form-input" name="booking_id" required>
            <option value="">Select booking</option>
            <?php foreach ($activeBookings as $bk): ?>
            <option value="<?= $bk['id'] ?>"><?= e($bk['booking_ref']) ?> — <?= e($bk['make'].' '.$bk['model']) ?> (<?= e($bk['registration_number']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Inspection Type</label>
          <select class="form-input" name="type" required>
            <option value="pickup">Pickup Inspection</option>
            <option value="return">Return Inspection</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('startInspModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Start Inspection</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
