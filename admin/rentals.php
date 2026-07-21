<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle  = 'Active Rentals';
$activePage = 'rentals';

$rentals = DB::fetchAll("
    SELECT b.*,
           v.make, v.model, v.registration_number, v.category,
           u.first_name, u.last_name, u.email, u.phone,
           DATEDIFF(b.return_date, CURDATE()) AS days_remaining,
           DATEDIFF(CURDATE(), b.pickup_date) AS days_elapsed,
           (SELECT COUNT(*) FROM inspections WHERE booking_id = b.id AND type = 'pickup' AND status = 'completed') AS pickup_done,
           (SELECT COUNT(*) FROM inspections WHERE booking_id = b.id AND type = 'return' AND status = 'completed') AS return_done
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    JOIN users u ON b.customer_id = u.id
    WHERE b.status IN ('active','paid')
      AND (SELECT COUNT(*) FROM inspections WHERE booking_id = b.id AND type = 'pickup' AND status = 'completed') = 1
      AND CURDATE() >= b.pickup_date
      AND CURDATE() <= b.return_date
    ORDER BY b.return_date ASC
");

require_once __DIR__ . '/../includes/admin-header.php';
?>

<!-- summary chips -->
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div class="stat-card" style="flex:1;min-width:160px;padding:18px 20px">
        <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--success),#34d399)"></div>
        <div class="stat-value" style="font-size:24px"><?= count($rentals) ?></div>
        <div class="stat-label">Active Rentals</div>
    </div>
    <div class="stat-card" style="flex:1;min-width:160px;padding:18px 20px">
        <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--danger),#f87171)"></div>
        <div class="stat-value" style="font-size:24px"><?= count(array_filter($rentals, fn($r) => $r['days_remaining'] < 0)) ?></div>
        <div class="stat-label">Overdue Returns</div>
    </div>
    <div class="stat-card" style="flex:1;min-width:160px;padding:18px 20px">
        <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--warn),#fbbf24)"></div>
        <div class="stat-value" style="font-size:24px"><?= count(array_filter($rentals, fn($r) => $r['days_remaining'] >= 0 && $r['days_remaining'] <= 1)) ?></div>
        <div class="stat-label">Due Today / Tomorrow</div>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Ref</th><th>Customer</th><th>Vehicle</th>
                    <th>Pickup</th><th>Return Due</th><th>Progress</th>
                    <th>Inspection</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rentals)): ?>
                <tr><td colspan="9" style="text-align:center;padding:50px;color:var(--text-secondary)">No active rentals</td></tr>
            <?php else: foreach ($rentals as $r): ?>
                <?php
                $overdue   = $r['days_remaining'] < 0;
                $dueSoon   = $r['days_remaining'] >= 0 && $r['days_remaining'] <= 1;
                $rowStyle  = $overdue ? 'background:rgba(239,68,68,0.04)' : ($dueSoon ? 'background:rgba(245,158,11,0.04)' : '');
                ?>
                <tr style="<?= $rowStyle ?>">
                    <td><span style="font-family:var(--mono);font-size:12px;font-weight:600"><?= e($r['booking_ref']) ?></span></td>
                    <td>
                        <div style="font-weight:500"><?= e($r['first_name'].' '.$r['last_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-tertiary)"><?= e($r['phone'] ?? $r['email']) ?></div>
                    </td>
                    <td>
                        <?= e($r['make'].' '.$r['model']) ?><br>
                        <span style="font-size:11px;font-family:var(--mono);color:var(--text-tertiary)"><?= e($r['registration_number']) ?></span>
                    </td>
                    <td style="font-size:12px"><?= format_date($r['pickup_date']) ?></td>
                    <td style="font-size:12px;font-weight:500;color:<?= $overdue ? 'var(--danger)' : ($dueSoon ? 'var(--warn)' : 'inherit') ?>">
                        <?= format_date($r['return_date']) ?>
                    </td>
                    <td style="text-align:center;font-weight:600;color:<?= $overdue ? 'var(--danger)' : ($dueSoon ? 'var(--warn)' : 'var(--success)') ?>">
                        <?php 
                        $total_days = max(1, $r['days_elapsed'] + $r['days_remaining']);
                        $progress = min(100, max(0, ($r['days_elapsed'] / $total_days) * 100));
                        $overdue_days = max(0, -$r['days_remaining']);
                        ?>
                        <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
                            <div style="width:100%;max-width:100px;height:6px;background:rgba(0,0,0,.1);border-radius:3px;overflow:hidden">
                                <div style="width:<?= $progress ?>%;height:100%;background:<?= $overdue ? 'var(--danger)' : ($dueSoon ? 'var(--warn)' : 'var(--success)') ?>;transition:width 0.3s"></div>
                            </div>
                            <div style="font-size:10px">
                                <?= $r['days_elapsed'] ?>d used / <?php echo $overdue_days > 0 ? $overdue_days.'d overdue' : ($r['days_remaining'].'d left'); ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($r['pickup_done']): ?>
                            <span style="color:var(--success);font-size:12px">✅ Pickup done</span>
                        <?php else: ?>
                            <span style="color:var(--warn);font-size:12px">⏳ Pickup pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <a href="<?= APP_URL ?>/admin/inspection-flow.php?booking_id=<?= $r['id'] ?>"
                               class="action-btn primary" title="Inspection">🔍</a>
                            <a href="<?= APP_URL ?>/admin/bookings.php?id=<?= $r['id'] ?>"
                               class="action-btn" title="View booking">📋</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
