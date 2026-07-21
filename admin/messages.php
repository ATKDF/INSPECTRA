<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Contact Messages';
$activePage = 'messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $msgId = (int)($_POST['message_id'] ?? 0);
    $msg = DB::fetchOne("SELECT * FROM contact_messages WHERE id=?", [$msgId]);

    if ($msg && $action === 'update_status') {
        $status = $_POST['status'] ?? 'new';
        if (!in_array($status, ['new', 'read', 'replied'], true)) $status = $msg['status'];
        $notes = trim($_POST['admin_notes'] ?? '');
        DB::update('contact_messages', [
            'status' => $status,
            'admin_notes' => $notes !== '' ? sanitize($notes) : null,
        ], 'id=?', [$msgId]);
        redirect(APP_URL . '/admin/messages.php?success=' . urlencode('Message updated.'));
    }
}

$status = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = "WHERE 1";
$params = [];
if ($status) { $where .= " AND status=?"; $params[] = $status; }
if ($q) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ?)";
    $s = "%$q%"; array_push($params, $s, $s, $s);
}

$total = DB::fetchOne("SELECT COUNT(*) c FROM contact_messages $where", $params)['c'];
$stats = DB::fetchOne("SELECT SUM(status='new') new_cnt, SUM(status='replied') replied_cnt FROM contact_messages");

$dataParams = array_merge($params, [$perPage, ($page - 1) * $perPage]);
$messages = DB::fetchAll("
    SELECT * FROM contact_messages
    $where
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
", $dataParams);

$pagination = paginate($total, $perPage, $page);

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-value"><?= number_format($total) ?></div>
    <div class="stat-label">Total Messages</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div>
    <div class="stat-value"><?= (int)($stats['new_cnt'] ?? 0) ?></div>
    <div class="stat-label">New / Unread</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#10b981,#34d399)"></div>
    <div class="stat-value"><?= (int)($stats['replied_cnt'] ?? 0) ?></div>
    <div class="stat-label">Replied</div>
  </div>
</div>

<form method="GET" style="margin-bottom:16px">
  <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="?<?= http_build_query(array_merge($_GET, ['status' => '', 'page' => 1])) ?>" class="chip <?= $status === '' ? 'active' : '' ?>">All</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'new', 'page' => 1])) ?>" class="chip <?= $status === 'new' ? 'active' : '' ?>">New</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'read', 'page' => 1])) ?>" class="chip <?= $status === 'read' ? 'active' : '' ?>">Read</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['status' => 'replied', 'page' => 1])) ?>" class="chip <?= $status === 'replied' ? 'active' : '' ?>">Replied</a>
    </div>
    <div class="search-bar" style="max-width:300px">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input name="q" value="<?= e($q) ?>" style="background:none;border:none;outline:none;font-family:var(--font);font-size:13px;color:var(--text);width:100%" placeholder="Name, email, or subject...">
      <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>From</th><th>Subject</th><th>Message</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($messages)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="empty-state-icon">✉️</div>
            <div class="empty-state-title">No messages yet</div>
            <div class="empty-state-desc">Messages submitted via the public Contact page will appear here.</div>
          </div>
        </td></tr>
        <?php else: foreach ($messages as $m): ?>
        <tr>
          <td>
            <div style="font-size:13px;font-weight:600"><?= e($m['name']) ?></div>
            <div style="font-size:11.5px;color:var(--text-secondary)"><?= e($m['email']) ?></div>
          </td>
          <td style="font-size:13px;max-width:160px"><?= $m['subject'] ? e($m['subject']) : '<span style="color:var(--text-tertiary)">—</span>' ?></td>
          <td style="font-size:12.5px;color:var(--text-secondary);max-width:240px">
            <?= e(mb_substr($m['message'], 0, 80)) . (mb_strlen($m['message']) > 80 ? '…' : '') ?>
          </td>
          <td>
            <?php $badgeColor = ['new' => 'background:rgba(245,158,11,0.1);color:#f59e0b', 'read' => 'background:rgba(107,114,128,0.1);color:#6b7280', 'replied' => 'background:rgba(16,185,129,0.1);color:var(--success)'][$m['status']]; ?>
            <span style="padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;<?= $badgeColor ?>"><?= ucfirst($m['status']) ?></span>
          </td>
          <td style="font-size:12px;color:var(--text-secondary)"><?= format_date($m['created_at']) ?></td>
          <td>
            <button type="button" class="action-btn primary" style="font-size:11px" onclick='openMessage(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>👁️ View</button>
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

<!-- Message Detail Modal -->
<div class="modal-overlay" id="messageModal">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Message Details</span>
      <button class="modal-close" onclick="closeModal('messageModal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="message_id" id="msgId">
      <div class="modal-body modal-scroll-body">
        <div class="form-group">
          <label class="form-label">From</label>
          <div id="msgFrom" style="font-size:14px;font-weight:600"></div>
          <div id="msgEmail" style="font-size:12.5px;color:var(--text-secondary)"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Subject</label>
          <div id="msgSubject" style="font-size:13.5px"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Message</label>
          <div id="msgBody" style="font-size:13.5px;color:var(--text-secondary);line-height:1.6;background:var(--surface2);border-radius:var(--radius-sm);padding:14px 16px;white-space:pre-wrap"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Received</label>
          <div id="msgDate" style="font-size:12.5px;color:var(--text-secondary)"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-input" name="status" id="msgStatus">
            <option value="new">New</option>
            <option value="read">Read</option>
            <option value="replied">Replied</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Internal Notes <span style="color:var(--text-tertiary);font-weight:400">(not visible to sender)</span></label>
          <textarea class="form-input" name="admin_notes" id="msgNotes" rows="3" placeholder="e.g. Replied via email on..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <a id="msgMailto" href="#" class="btn btn-ghost" style="text-decoration:none">📧 Reply via Email</a>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openMessage(m) {
  document.getElementById('msgId').value = m.id;
  document.getElementById('msgFrom').textContent = m.name;
  document.getElementById('msgEmail').textContent = m.email;
  document.getElementById('msgSubject').textContent = m.subject || '(no subject)';
  document.getElementById('msgBody').textContent = m.message;
  document.getElementById('msgDate').textContent = m.created_at;
  document.getElementById('msgStatus').value = m.status;
  document.getElementById('msgNotes').value = m.admin_notes || '';
  document.getElementById('msgMailto').href = 'mailto:' + m.email + '?subject=' + encodeURIComponent('Re: ' + (m.subject || 'Your message to Inspectra'));
  openModal('messageModal');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
