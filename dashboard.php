<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$accountId = $user['account_id'];
$pdo = db();

// ---------- METRICS ----------
$stmt = $pdo->prepare("
  SELECT COALESCE(SUM(amount),0) AS total
  FROM payments p JOIN trips t ON t.id = p.trip_id
  WHERE t.account_id = ? AND MONTH(p.payment_date) = MONTH(CURDATE()) AND YEAR(p.payment_date) = YEAR(CURDATE())
");
$stmt->execute([$accountId]);
$monthRevenue = (float) $stmt->fetch()['total'];

$stmt = $pdo->prepare("
  SELECT COALESCE(SUM(c.amount),0) AS total
  FROM cost_lines c JOIN trips t ON t.id = c.trip_id
  WHERE t.account_id = ? AND MONTH(t.start_date) = MONTH(CURDATE()) AND YEAR(t.start_date) = YEAR(CURDATE())
");
$stmt->execute([$accountId]);
$monthCosts = (float) $stmt->fetch()['total'];
$monthProfit = $monthRevenue - $monthCosts;

$stmt = $pdo->prepare("
  SELECT COUNT(*) AS c FROM trips
  WHERE account_id = ? AND start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status != 'cancelled'
");
$stmt->execute([$accountId]);
$upcomingCount = (int) $stmt->fetch()['c'];

$stmt = $pdo->prepare("
  SELECT t.id,
    (SELECT COALESCE(SUM(amount),0) FROM cost_lines WHERE trip_id = t.id) AS cost_total,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE trip_id = t.id) AS paid_total
  FROM trips t
  WHERE t.account_id = ? AND t.status IN ('confirmed','in_progress')
");
$stmt->execute([$accountId]);
$outstanding = 0.0;
foreach ($stmt->fetchAll() as $row) {
    $diff = $row['cost_total'] - $row['paid_total'];
    if ($diff > 0) $outstanding += $diff;
}

// ---------- UPCOMING TRIPS ----------
$stmt = $pdo->prepare("
  SELECT t.id, t.destination, t.start_date, t.end_date, t.pax, t.status, cl.name AS client_name,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE trip_id = t.id) AS paid
  FROM trips t JOIN clients cl ON cl.id = t.client_id
  WHERE t.account_id = ? AND t.start_date >= CURDATE() AND t.status != 'cancelled'
  ORDER BY t.start_date ASC LIMIT 8
");
$stmt->execute([$accountId]);
$upcoming = $stmt->fetchAll();

// ---------- CHART DATA ----------
// Revenue vs Costs over last 6 months
$months = [];
$revenues = [];
$costs = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[] = date('M', strtotime("-$i months"));
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0) AS total
        FROM payments p JOIN trips t ON t.id = p.trip_id
        WHERE t.account_id = ? AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?
    ");
    $stmt->execute([$accountId, $month]);
    $revenues[] = (float) $stmt->fetch()['total'];
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.amount),0) AS total
        FROM cost_lines c JOIN trips t ON t.id = c.trip_id
        WHERE t.account_id = ? AND DATE_FORMAT(t.start_date, '%Y-%m') = ?
    ");
    $stmt->execute([$accountId, $month]);
    $costs[] = (float) $stmt->fetch()['total'];
}

// Status distribution
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM trips WHERE account_id = ? GROUP BY status");
$stmt->execute([$accountId]);
$statusCounts = [];
foreach ($stmt->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['count'];
}
$statusLabels = ['inquiry', 'confirmed', 'in_progress', 'completed', 'cancelled'];
$statusColors = [
    'inquiry'    => '#94a3b8',
    'confirmed'  => '#f59e0b',
    'in_progress'=> '#3b82f6',
    'completed'  => '#10b981',
    'cancelled'  => '#ef4444'
];
$chartLabels = [];
$chartData = [];
$chartColors = [];
foreach ($statusLabels as $label) {
    if (isset($statusCounts[$label]) && $statusCounts[$label] > 0) {
        $chartLabels[] = ucfirst(str_replace('_', ' ', $label));
        $chartData[] = $statusCounts[$label];
        $chartColors[] = $statusColors[$label];
    }
}

function statusBadge(string $status): string {
    return match ($status) {
        'inquiry' => '<span class="badge badge-muted">Inquiry</span>',
        'confirmed' => '<span class="badge badge-accent">Confirmed</span>',
        'in_progress' => '<span class="badge badge-accent">In progress</span>',
        'completed' => '<span class="badge badge-muted">Completed</span>',
        default => '<span class="badge badge-muted">' . htmlspecialchars($status) . '</span>',
    };
}

$pageTitle = 'Dashboard — TourVolt';
require __DIR__ . '/partials/header.php';
?>

<div class="space-y-8">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="font-serif text-2xl md:text-3xl">Dashboard</h1>
    <a href="trip_detail.php?new=1" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5">
      <i class="ti ti-plus"></i>
      <span>New Trip</span>
    </a>
  </div>

  <!-- Metric Cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="card bg-gradient-to-br from-rust/5 to-rust/10 dark:from-rust/10 dark:to-rust/20 border-l-4 border-rust dark:border-brass">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-rust/20 dark:bg-brass/20 rounded-lg">
          <i class="ti ti-wallet text-rust dark:text-brass" style="font-size:1.5rem;"></i>
        </div>
        <div>
          <div class="text-xs font-medium text-muted uppercase tracking-wider">Outstanding</div>
          <div class="text-xl font-mono font-bold text-rust dark:text-brass"><?= number_format($outstanding, 0) ?></div>
        </div>
      </div>
      <div class="text-xs text-muted mt-2">TZS – against agreed costs</div>
    </div>

    <div class="card bg-gradient-to-br from-emerald/5 to-emerald/10 dark:from-emerald/10 dark:to-emerald/20 border-l-4 border-emerald">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-emerald/20 rounded-lg">
          <i class="ti ti-currency-dollar text-emerald" style="font-size:1.5rem;"></i>
        </div>
        <div>
          <div class="text-xs font-medium text-muted uppercase tracking-wider">Revenue (month)</div>
          <div class="text-xl font-mono font-bold text-emerald"><?= number_format($monthRevenue, 0) ?></div>
        </div>
      </div>
      <div class="text-xs text-muted mt-2">TZS received this month</div>
    </div>

    <div class="card bg-gradient-to-br from-accent/5 to-accent/10 dark:from-accent/10 dark:to-accent/20 border-l-4 border-accent">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-accent/20 rounded-lg">
          <i class="ti ti-chart-bar text-accent" style="font-size:1.5rem;"></i>
        </div>
        <div>
          <div class="text-xs font-medium text-muted uppercase tracking-wider">Profit (month)</div>
          <div class="text-xl font-mono font-bold text-accent"><?= number_format($monthProfit, 0) ?></div>
        </div>
      </div>
      <div class="text-xs text-muted mt-2">TZS – revenue minus costs</div>
    </div>

    <div class="card bg-gradient-to-br from-blue-500/5 to-blue-500/10 dark:from-blue-400/10 dark:to-blue-400/20 border-l-4 border-blue-500">
      <div class="flex items-center gap-3">
        <div class="p-2 bg-blue-500/20 rounded-lg">
          <i class="ti ti-calendar-stats text-blue-500" style="font-size:1.5rem;"></i>
        </div>
        <div>
          <div class="text-xs font-medium text-muted uppercase tracking-wider">Upcoming trips</div>
          <div class="text-xl font-mono font-bold text-blue-500"><?= $upcomingCount ?></div>
        </div>
      </div>
      <div class="text-xs text-muted mt-2">next 30 days</div>
    </div>
  </div>

  <!-- Charts row -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card p-5">
      <h3 class="font-serif text-base mb-4">Revenue vs Costs (last 6 months)</h3>
      <div style="position: relative; height: 200px;">
        <canvas id="revenueCostChart"></canvas>
      </div>
    </div>
    <div class="card p-5">
      <h3 class="font-serif text-base mb-4">Trip Status</h3>
      <div style="position: relative; height: 200px;">
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Upcoming trips -->
  <div>
    <div class="flex items-center justify-between mb-3">
      <h2 class="font-serif text-base">Upcoming trips</h2>
      <a href="trips.php" class="nav-link inline-flex items-center gap-1">View all <i class="ti ti-arrow-right" style="font-size:1rem;"></i></a>
    </div>
    <div class="card !p-0 divide-y divide-dayline dark:divide-ledgerline">
      <?php if (empty($upcoming)): ?>
        <div class="p-8 text-center text-muted">
          No upcoming trips yet.
          <a href="trip_detail.php?new=1" class="text-rust dark:text-brass font-medium hover:underline">Add your first trip</a>.
        </div>
      <?php else: foreach ($upcoming as $t): ?>
        <a href="trip_detail.php?id=<?= $t['id'] ?>" class="flex flex-wrap items-center justify-between p-4 hover:bg-daylight dark:hover:bg-ledgerline transition-colors">
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium"><?= htmlspecialchars($t['client_name']) ?></div>
            <div class="text-xs text-muted mt-1 flex flex-wrap items-center gap-1.5">
              <i class="ti ti-map-pin" style="font-size:13px;"></i>
              <?= htmlspecialchars($t['destination']) ?> &middot;
              <?= date('M j', strtotime($t['start_date'])) ?>&ndash;<?= date('j', strtotime($t['end_date'])) ?> &middot;
              <?= (int) $t['pax'] ?> pax
            </div>
          </div>
          <div class="flex items-center gap-3">
            <?= statusBadge($t['status']) ?>
            <i class="ti ti-chevron-right text-muted" style="font-size:1.2rem;"></i>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Revenue vs Costs
  const rcCtx = document.getElementById('revenueCostChart').getContext('2d');
  new Chart(rcCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode($months) ?>,
      datasets: [
        {
          label: 'Revenue',
          data: <?= json_encode($revenues) ?>,
          backgroundColor: 'rgba(16, 185, 129, 0.7)',
          borderColor: 'rgb(16, 185, 129)',
          borderWidth: 1,
          borderRadius: 4,
          barPercentage: 0.6,
        },
        {
          label: 'Costs',
          data: <?= json_encode($costs) ?>,
          backgroundColor: 'rgba(239, 68, 68, 0.7)',
          borderColor: 'rgb(239, 68, 68)',
          borderWidth: 1,
          borderRadius: 4,
          barPercentage: 0.6,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          labels: { boxWidth: 12, padding: 12, font: { size: 11 } }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { font: { size: 10 }, callback: v => v.toLocaleString() }
        },
        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
      }
    }
  });

  // Status Doughnut
  const statusCtx = document.getElementById('statusChart').getContext('2d');
  new Chart(statusCtx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [{
        data: <?= json_encode($chartData) ?>,
        backgroundColor: <?= json_encode($chartColors) ?>,
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: { boxWidth: 12, padding: 10, font: { size: 11 } }
        }
      },
      cutout: '65%'
    }
  });
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>