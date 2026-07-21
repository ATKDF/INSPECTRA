<?php
/**
 * Email Queue Monitoring Dashboard
 * Shows status of background email sending system
 * Accessible at: /admin/email-queue-monitor.php
 */

define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Email Queue Monitor';
$activePage = 'email-queue';

// Get queue statistics
$stats = [
    'pending'  => (int)(DB::fetchOne("SELECT COUNT(*) c FROM email_queue WHERE status='pending'")['c'] ?? 0),
    'processing' => (int)(DB::fetchOne("SELECT COUNT(*) c FROM email_queue WHERE status='processing'")['c'] ?? 0),
    'sent'     => (int)(DB::fetchOne("SELECT COUNT(*) c FROM email_queue WHERE status='sent'")['c'] ?? 0),
    'failed'   => (int)(DB::fetchOne("SELECT COUNT(*) c FROM email_queue WHERE status='failed'")['c'] ?? 0),
];

// Get failure breakdown
$failureBreakdown = DB::fetchAll("
    SELECT type, COUNT(*) as count 
    FROM email_queue 
    WHERE status='failed' 
    GROUP BY type 
    ORDER BY count DESC
");

// Get recent emails
$recentEmails = DB::fetchAll("
    SELECT eq.id, eq.user_id, eq.type, eq.title, eq.status, eq.attempt_count, eq.created_at, eq.processed_at, u.email
    FROM email_queue eq
    LEFT JOIN users u ON eq.user_id = u.id
    ORDER BY eq.created_at DESC
    LIMIT 50
");

// Handle manual actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'retry_failed') {
        $affected = DB::query(
            "UPDATE email_queue SET status='pending', attempt_count=0 WHERE status='failed' AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $message = "✓ Queued failed emails for retry";
    } elseif ($action === 'clear_sent') {
        $affected = DB::query(
            "DELETE FROM email_queue WHERE status='sent' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $message = "✓ Cleared sent emails older than 30 days";
    } elseif ($action === 'retry_single') {
        $emailId = (int)($_POST['email_id'] ?? 0);
        if ($emailId) {
            DB::update('email_queue', ['status'=>'pending', 'attempt_count'=>0], 'id=?', [$emailId]);
            $message = "✓ Email #$emailId queued for retry";
        }
    } elseif ($action === 'process_now') {
        // Trigger background worker
        $ch = curl_init(APP_URL . '/api/workers/process-email-queue.php');
        curl_setopt_array($ch, [CURLOPT_TIMEOUT => 2, CURLOPT_RETURNTRANSFER => true]);
        $result = curl_exec($ch);
        curl_close($ch);
        $message = "✓ Background processor triggered";
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<style>
.stat-card { display: inline-block; padding: 20px; border-radius: 8px; margin: 10px 10px 10px 0; min-width: 180px; text-align: center; background: linear-gradient(135deg, var(--accent) 0%, rgba(102, 126, 234, 0.1) 100%); border: 1px solid var(--border); }
.stat-card.success { background: linear-gradient(135deg, var(--success) 0%, rgba(34, 197, 94, 0.1) 100%); }
.stat-card.warning { background: linear-gradient(135deg, var(--warning) 0%, rgba(234, 179, 8, 0.1) 100%); }
.stat-card.danger { background: linear-gradient(135deg, var(--danger) 0%, rgba(239, 68, 68, 0.1) 100%); }
.stat-card.info { background: linear-gradient(135deg, #3b82f6 0%, rgba(59, 130, 246, 0.1) 100%); }
.stat-number { font-size: 28px; font-weight: 700; margin: 10px 0; color: #1f2937; }
.stat-label { font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 500; }
.action-buttons { margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap; }
.action-buttons .btn { padding: 10px 16px; font-size: 13px; }
.email-list { max-height: 600px; overflow-y: auto; }
.email-row { display: grid; grid-template-columns: 60px 120px 200px 80px 80px 150px; gap: 12px; padding: 12px; border-bottom: 1px solid var(--border); align-items: center; font-size: 12px; }
.email-row:hover { background: rgba(0,0,0,0.02); }
.status-badge { padding: 4px 8px; border-radius: 4px; font-weight: 500; font-size: 11px; text-align: center; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-sent { background: #dcfce7; color: #166534; }
.status-failed { background: #fee2e2; color: #991b1b; }
.status-processing { background: #dbeafe; color: #0c4a6e; }
.chart-container { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; }
</style>

<div class="card" style="margin-bottom: 24px; padding: 20px;">
  <h2 style="margin-top: 0;">📧 Email Queue Monitor</h2>
  
  <?php if (isset($message)): ?>
  <div style="background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 16px;">
    <?= e($message) ?>
  </div>
  <?php endif; ?>
  
  <div style="display: flex; flex-wrap: wrap; gap: 10px;">
    <div class="stat-card warning">
      <div class="stat-label">⏳ Pending</div>
      <div class="stat-number"><?= $stats['pending'] ?></div>
    </div>
    <div class="stat-card info">
      <div class="stat-label">⚙️ Processing</div>
      <div class="stat-number"><?= $stats['processing'] ?></div>
    </div>
    <div class="stat-card success">
      <div class="stat-label">✓ Sent</div>
      <div class="stat-number"><?= $stats['sent'] ?></div>
    </div>
    <div class="stat-card danger">
      <div class="stat-label">✗ Failed</div>
      <div class="stat-number"><?= $stats['failed'] ?></div>
    </div>
  </div>
  
  <div class="action-buttons">
    <form method="POST" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="process_now">
      <button type="submit" class="btn btn-primary" style="font-size: 13px;">▶ Process Now</button>
    </form>
    
    <?php if ($stats['failed'] > 0): ?>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="retry_failed">
      <button type="submit" class="btn btn-warning" onclick="return confirm('Retry all failed emails?')">⟳ Retry Failed</button>
    </form>
    <?php endif; ?>
    
    <form method="POST" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="clear_sent">
      <button type="submit" class="btn btn-ghost" onclick="return confirm('Delete sent emails older than 30 days?')">🗑 Clear Sent</button>
    </form>
  </div>
</div>

<?php if (!empty($failureBreakdown)): ?>
<div class="card" style="margin-bottom: 24px; padding: 20px;">
  <h3 style="margin-top: 0;">Failed Email Types</h3>
  <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
    <thead>
      <tr style="border-bottom: 2px solid var(--border);">
        <th style="text-align: left; padding: 10px;">Type</th>
        <th style="text-align: right; padding: 10px;">Count</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($failureBreakdown as $fb): ?>
      <tr style="border-bottom: 1px solid var(--border);">
        <td style="padding: 10px;"><?= e($fb['type']) ?></td>
        <td style="text-align: right; padding: 10px;"><strong><?= $fb['count'] ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 24px; padding: 20px;">
  <h3 style="margin-top: 0;">📋 Recent Emails (Last 50)</h3>
  
  <?php if (empty($recentEmails)): ?>
  <div style="padding: 20px; text-align: center; color: var(--text-secondary);">No emails in queue yet</div>
  <?php else: ?>
  <div class="email-list">
    <div class="email-row" style="background: var(--bg-secondary); position: sticky; top: 0; z-index: 10; font-weight: 600;">
      <div>ID</div>
      <div>Type</div>
      <div>Recipient</div>
      <div>Status</div>
      <div>Attempts</div>
      <div>Created</div>
    </div>
    
    <?php foreach ($recentEmails as $email): ?>
    <div class="email-row">
      <div>#<?= $email['id'] ?></div>
      <div><?= e(substr($email['type'], 0, 15)) ?></div>
      <div style="word-break: break-all;"><?= e($email['email'] ?? 'unknown') ?></div>
      <div>
        <span class="status-badge status-<?= $email['status'] ?>">
          <?php switch($email['status']) {
            case 'sent': echo '✓ Sent'; break;
            case 'failed': echo '✗ Failed'; break;
            case 'pending': echo '⏳ Pending'; break;
            case 'processing': echo '⚙ Processing'; break;
            default: echo ucfirst($email['status']);
          } ?>
        </span>
      </div>
      <div><?= $email['attempt_count'] ?></div>
      <div><?= format_datetime($email['created_at'], 'M d H:i') ?></div>
      
      <?php if ($email['status'] === 'failed'): ?>
      <div style="grid-column: 1/-1; padding: 8px 0;">
        <form method="POST" style="display: inline;">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="retry_single">
          <input type="hidden" name="email_id" value="<?= $email['id'] ?>">
          <button type="submit" class="btn btn-sm btn-ghost" style="padding: 4px 8px; font-size: 11px;">⟳ Retry</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div class="card" style="padding: 20px;">
  <h3 style="margin-top: 0;">ℹ️ Information</h3>
  
  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; font-size: 13px; line-height: 1.8;">
    <div>
      <strong>System Status:</strong><br>
      ✓ Database Queue: Active<br>
      ✓ Background Worker: Enabled<br>
      ✓ Auto-retry: Up to 3 attempts<br>
    </div>
    <div>
      <strong>Processing:</strong><br>
      • Cron job: Every minute (if configured)<br>
      • HTTP worker: On-demand (always available)<br>
      • Manual: <code>php process_queue_manual.php</code><br>
    </div>
    <div colspan="2">
      <strong>Logs:</strong><br>
      Check error logs for [Email Queue] entries. Output shows processing status and any failures.
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
