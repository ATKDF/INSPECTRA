<?php
define('INSPECTRA_ADMIN', true);
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Reports & Analytics';
$activePage = 'reports';

// Damage Analytics
$damageStats = DB::fetchOne("
    SELECT
        COUNT(*) as total_damages,
        SUM(repair_cost) as total_cost,
        COUNT(CASE WHEN severity = 'severe' THEN 1 END) as severe_count,
        COUNT(CASE WHEN severity = 'moderate' THEN 1 END) as moderate_count,
        COUNT(CASE WHEN severity = 'minor' THEN 1 END) as minor_count
    FROM damage_reports
");

// Most damaged vehicles
$mostDamagedVehicles = DB::fetchAll("
    SELECT
        v.id, v.make, v.model, v.registration_number,
        COUNT(dr.id) as damage_count,
        SUM(dr.repair_cost) as total_cost
    FROM vehicles v
    LEFT JOIN bookings b ON v.id = b.vehicle_id
    LEFT JOIN inspections i ON b.id = i.booking_id
    LEFT JOIN damage_reports dr ON i.id = dr.inspection_id
    WHERE dr.id IS NOT NULL
    GROUP BY v.id, v.make, v.model, v.registration_number
    ORDER BY damage_count DESC
    LIMIT 10
");

// Most common damage types
$damageTypes = DB::fetchAll("
    SELECT
        damage_type,
        COUNT(*) as count,
        SUM(repair_cost) as total_cost
    FROM damage_reports
    GROUP BY damage_type
    ORDER BY count DESC
    LIMIT 10
");

// Damage frequency by zone
$damageZones = DB::fetchAll("
    SELECT
        zone,
        COUNT(*) as count
    FROM damage_reports
    GROUP BY zone
    ORDER BY count DESC
");

// Staff Performance Analytics
$staffStats = DB::fetchAll("
    SELECT
        u.id, u.first_name, u.last_name,
        COUNT(i.id) as inspection_count,
        AVG(TIMESTAMPDIFF(MINUTE, i.created_at, i.updated_at)) as avg_time_minutes
    FROM users u
    LEFT JOIN inspections i ON u.id = i.conducted_by
    WHERE u.role = 'admin' OR u.role = 'inspector' OR u.role = 'agent'
    GROUP BY u.id, u.first_name, u.last_name
    ORDER BY inspection_count DESC
");

// Vehicle Condition Analytics
$vehicleConditions = DB::fetchAll("
    SELECT
        v.id, v.make, v.model, v.registration_number,
        COUNT(i.id) as inspection_count,
        AVG(CASE
            WHEN i.overall_condition = 'excellent' THEN 4
            WHEN i.overall_condition = 'good' THEN 3
            WHEN i.overall_condition = 'fair' THEN 2
            WHEN i.overall_condition = 'poor' THEN 1
            ELSE 2
        END) as avg_condition_score
    FROM vehicles v
    LEFT JOIN bookings b ON v.id = b.vehicle_id
    LEFT JOIN inspections i ON b.id = i.booking_id
    WHERE i.overall_condition IS NOT NULL
    GROUP BY v.id, v.make, v.model, v.registration_number
    HAVING inspection_count > 0
    ORDER BY avg_condition_score DESC
    LIMIT 20
");

// Overall condition distribution
$conditionDistribution = DB::fetchAll("
    SELECT
        overall_condition as condition_rating,
        COUNT(*) as count
    FROM inspections
    WHERE overall_condition IS NOT NULL
    GROUP BY overall_condition
    ORDER BY count DESC
");

// Monthly damage trends (last 12 months)
$monthlyDamageTrends = DB::fetchAll("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as damage_count,
        SUM(repair_cost) as total_cost
    FROM damage_reports
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
");

require_once __DIR__ . '/../includes/admin-header.php';
?>
<style>
.report-section{margin-bottom:28px}
.report-chart-wrap{position:relative;height:260px}
.report-chart-wrap.tall{height:320px}
.pill{padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:500;display:inline-block}
</style>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#ef4444,#f87171)"></div>
    <div class="stat-value"><?= number_format($damageStats['total_damages'] ?? 0) ?></div>
    <div class="stat-label">Total Damages · <?= (int)($damageStats['severe_count'] ?? 0) ?> severe</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#f59e0b,#fbbf24)"></div>
    <div class="stat-value">₵<?= number_format($damageStats['total_cost'] ?? 0, 0) ?></div>
    <div class="stat-label">Estimated Repair Cost</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,var(--accent),#6366f1)"></div>
    <div class="stat-value"><?= count($staffStats) ?></div>
    <div class="stat-label">Active Staff Tracked</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-accent" style="background:linear-gradient(90deg,#10b981,#34d399)"></div>
    <div class="stat-value"><?= count($vehicleConditions) ?></div>
    <div class="stat-label">Vehicles With Condition Scores</div>
  </div>
</div>

<div class="report-section" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="card" style="padding:22px">
    <div class="section-title" style="margin-bottom:16px">🩹 Damage Severity Distribution</div>
    <?php if (($damageStats['total_damages'] ?? 0) > 0): ?>
    <div class="report-chart-wrap"><canvas id="damageSeverityChart"></canvas></div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">🩹</div>
      <div class="empty-state-title">No damage reports yet</div>
      <div class="empty-state-desc">Severity breakdown will appear once inspections log damage.</div>
    </div>
    <?php endif; ?>
  </div>
  <div class="card" style="padding:22px">
    <div class="section-title" style="margin-bottom:16px">🚗 Vehicle Condition Distribution</div>
    <?php if (!empty($conditionDistribution)): ?>
    <div class="report-chart-wrap"><canvas id="conditionChart"></canvas></div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">🚗</div>
      <div class="empty-state-title">No condition data yet</div>
      <div class="empty-state-desc">Complete inspections to see the condition breakdown.</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="report-section" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <div class="card">
    <div style="padding:18px 22px;border-bottom:1px solid var(--border)"><span class="section-title">🚨 Most Damaged Vehicles</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Vehicle</th><th>Damages</th><th>Total Cost</th></tr></thead>
        <tbody>
          <?php if (empty($mostDamagedVehicles)): ?>
          <tr><td colspan="3">
            <div class="empty-state">
              <div class="empty-state-icon">✅</div>
              <div class="empty-state-title">No damage on record</div>
            </div>
          </td></tr>
          <?php else: foreach ($mostDamagedVehicles as $vehicle): ?>
          <tr>
            <td style="font-size:13px">
              <?= e($vehicle['make'] . ' ' . $vehicle['model']) ?>
              <div style="font-size:11px;color:var(--text-tertiary);font-family:var(--mono)"><?= e($vehicle['registration_number']) ?></div>
            </td>
            <td><span class="pill" style="background:rgba(239,68,68,0.1);color:var(--danger)"><?= $vehicle['damage_count'] ?></span></td>
            <td style="font-weight:600">₵<?= number_format($vehicle['total_cost'], 0) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div style="padding:18px 22px;border-bottom:1px solid var(--border)"><span class="section-title">🔧 Most Common Damage Types</span></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Damage Type</th><th>Frequency</th><th>Total Cost</th></tr></thead>
        <tbody>
          <?php if (empty($damageTypes)): ?>
          <tr><td colspan="3">
            <div class="empty-state">
              <div class="empty-state-icon">🔧</div>
              <div class="empty-state-title">No damage types recorded</div>
            </div>
          </td></tr>
          <?php else: foreach ($damageTypes as $type): ?>
          <tr>
            <td style="font-size:13px"><?= ucfirst(e($type['damage_type'])) ?></td>
            <td><span class="pill" style="background:rgba(245,158,11,0.1);color:#d97706"><?= $type['count'] ?></span></td>
            <td style="font-weight:600">₵<?= number_format($type['total_cost'], 0) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="report-section card">
  <div style="padding:18px 22px;border-bottom:1px solid var(--border)"><span class="section-title">👷 Staff Performance</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Staff Member</th><th>Inspections Completed</th><th>Average Time</th><th>Performance Rating</th></tr></thead>
      <tbody>
        <?php if (empty($staffStats)): ?>
        <tr><td colspan="4">
          <div class="empty-state">
            <div class="empty-state-icon">👷</div>
            <div class="empty-state-title">No staff activity yet</div>
          </div>
        </td></tr>
        <?php else: foreach ($staffStats as $staff): ?>
        <tr>
          <td style="font-size:13px;font-weight:500"><?= e($staff['first_name'] . ' ' . $staff['last_name']) ?></td>
          <td><span class="pill" style="background:rgba(59,110,248,0.1);color:var(--accent)"><?= $staff['inspection_count'] ?></span></td>
          <td style="font-size:13px;color:var(--text-secondary)"><?= $staff['avg_time_minutes'] ? round($staff['avg_time_minutes'], 1) . ' min' : '—' ?></td>
          <td style="font-size:13px">
            <?php
            $count = $staff['inspection_count'];
            if ($count >= 50) echo '⭐⭐⭐⭐⭐ Excellent';
            elseif ($count >= 30) echo '⭐⭐⭐⭐ Very Good';
            elseif ($count >= 15) echo '⭐⭐⭐ Good';
            elseif ($count >= 5) echo '⭐⭐ Fair';
            elseif ($count > 0) echo '⭐ Needs Improvement';
            else echo '<span style="color:var(--text-tertiary)">—</span>';
            ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="report-section card">
  <div style="padding:18px 22px;border-bottom:1px solid var(--border)"><span class="section-title">📋 Vehicle Condition Scores</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Vehicle</th><th>Inspections</th><th>Average Score</th><th>Condition Rating</th></tr></thead>
      <tbody>
        <?php if (empty($vehicleConditions)): ?>
        <tr><td colspan="4">
          <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">No condition scores yet</div>
          </div>
        </td></tr>
        <?php else: foreach ($vehicleConditions as $vehicle):
          $score = round($vehicle['avg_condition_score'], 1);
          if ($score >= 3.5) { $ratingLabel = 'Excellent'; $ratingStyle = 'background:rgba(16,185,129,0.1);color:var(--success)'; }
          elseif ($score >= 2.5) { $ratingLabel = 'Good'; $ratingStyle = 'background:rgba(59,110,248,0.1);color:var(--accent)'; }
          elseif ($score >= 1.5) { $ratingLabel = 'Fair'; $ratingStyle = 'background:rgba(245,158,11,0.1);color:#d97706'; }
          else { $ratingLabel = 'Poor'; $ratingStyle = 'background:rgba(239,68,68,0.1);color:var(--danger)'; }
        ?>
        <tr>
          <td style="font-size:13px">
            <?= e($vehicle['make'] . ' ' . $vehicle['model']) ?>
            <div style="font-size:11px;color:var(--text-tertiary);font-family:var(--mono)"><?= e($vehicle['registration_number']) ?></div>
          </td>
          <td style="font-size:13px"><?= $vehicle['inspection_count'] ?></td>
          <td style="font-size:13px;font-weight:600"><?= $score ?>/4.0</td>
          <td><span class="pill" style="<?= $ratingStyle ?>"><?= $ratingLabel ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="report-section card" style="padding:22px">
  <div class="section-title" style="margin-bottom:16px">📈 Monthly Damage Trends <span style="font-weight:400;color:var(--text-tertiary);font-size:12.5px">(last 12 months)</span></div>
  <?php if (!empty($monthlyDamageTrends)): ?>
  <div class="report-chart-wrap tall"><canvas id="monthlyTrendsChart"></canvas></div>
  <?php else: ?>
  <div class="empty-state">
    <div class="empty-state-icon">📈</div>
    <div class="empty-state-title">No trend data yet</div>
    <div class="empty-state-desc">Once damage reports accumulate over time, monthly trends will appear here.</div>
  </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
Chart.defaults.font.family = getComputedStyle(document.body).getPropertyValue('--font') || 'DM Sans, sans-serif';
Chart.defaults.color = '#6b7280';

<?php if (($damageStats['total_damages'] ?? 0) > 0): ?>
new Chart(document.getElementById('damageSeverityChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Severe', 'Moderate', 'Minor'],
        datasets: [{
            data: [
                <?= (int)($damageStats['severe_count'] ?? 0) ?>,
                <?= (int)($damageStats['moderate_count'] ?? 0) ?>,
                <?= (int)($damageStats['minor_count'] ?? 0) ?>
            ],
            backgroundColor: ['#ef4444', '#f59e0b', '#10b981'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 16 } } }
    }
});
<?php endif; ?>

<?php if (!empty($conditionDistribution)): ?>
new Chart(document.getElementById('conditionChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($conditionDistribution as $cond): ?>'<?= ucfirst($cond['condition_rating']) ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Inspections',
            data: [<?php foreach ($conditionDistribution as $cond): ?><?= (int)$cond['count'] ?>,<?php endforeach; ?>],
            backgroundColor: '#3b6ef8',
            borderRadius: 6,
            maxBarThickness: 48
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } }
    }
});
<?php endif; ?>

<?php if (!empty($monthlyDamageTrends)): ?>
new Chart(document.getElementById('monthlyTrendsChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: [<?php foreach (array_reverse($monthlyDamageTrends) as $trend): ?>'<?= date('M Y', strtotime($trend['month'] . '-01')) ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Damage Count',
            data: [<?php foreach (array_reverse($monthlyDamageTrends) as $trend): ?><?= (int)$trend['damage_count'] ?>,<?php endforeach; ?>],
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239,68,68,0.08)',
            tension: 0.3,
            fill: true,
            yAxisID: 'y'
        }, {
            label: 'Total Cost (₵)',
            data: [<?php foreach (array_reverse($monthlyDamageTrends) as $trend): ?><?= (float)$trend['total_cost'] ?>,<?php endforeach; ?>],
            borderColor: '#f59e0b',
            backgroundColor: 'rgba(245,158,11,0.08)',
            tension: 0.3,
            fill: true,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 16 } } },
        scales: {
            y: { type: 'linear', display: true, position: 'left', beginAtZero: true, title: { display: true, text: 'Damage Count' }, grid: { color: 'rgba(0,0,0,0.05)' } },
            y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, title: { display: true, text: 'Total Cost (₵)' }, grid: { drawOnChartArea: false } }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
