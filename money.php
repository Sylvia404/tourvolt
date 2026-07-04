<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$accountId = $user['account_id'];
$pdo = db();

$filter = $_GET['filter'] ?? 'all';

$stmt = $pdo->prepare("
  SELECT t.id, t.destination, t.start_date, t.status, cl.name AS client_name,
    (SELECT COALESCE(SUM(amount),0) FROM cost_lines WHERE trip_id = t.id) AS cost_total,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE trip_id = t.id) AS paid_total
  FROM trips t JOIN clients cl ON cl.id = t.client_id
  WHERE t.account_id = ? AND t.status != 'cancelled'
  ORDER BY t.start_date ASC
");
$stmt->execute([$accountId]);
$rows = $stmt->fetchAll();

$allWithBalance = [];
$filtered = [];
$totalOutstanding = 0;
$totalPaidAll = 0;
$totalCostAll = 0;

foreach ($rows as $r) {
    $balance = $r['cost_total'] - $r['paid_total'];
    $r['balance'] = $balance;
    $r['is_outstanding'] = $balance > 0;
    $allWithBalance[] = $r;
    $totalPaidAll += $r['paid_total'];
    $totalCostAll += $r['cost_total'];
    if ($balance > 0) $totalOutstanding += $balance;

    if ($filter === 'all') {
        $filtered[] = $r;
    } elseif ($filter === 'outstanding' && $r['is_outstanding']) {
        $filtered[] = $r;
    } elseif ($filter === 'paid' && !$r['is_outstanding']) {
        $filtered[] = $r;
    }
}

usort($filtered, function ($a, $b) {
    return $b['balance'] <=> $a['balance'];
});

$netProfit = $totalPaidAll - $totalCostAll;

// ---- CHART DATA ----
// Horizontal bar – top 5 outstanding
$topOutstanding = array_filter($allWithBalance, fn($r) => $r['balance'] > 0);
usort($topOutstanding, fn($a, $b) => $b['balance'] <=> $a['balance']);
$topOutstanding = array_slice($topOutstanding, 0, 5);
$chartLabels = array_map(fn($r) => $r['client_name'] . ' (' . $r['destination'] . ')', $topOutstanding);
$chartData = array_map(fn($r) => $r['balance'], $topOutstanding);

// Doughnut: Outstanding vs Paid
$doughnutLabels = ['Outstanding', 'Paid'];
$doughnutData = [$totalOutstanding, $totalPaidAll];
$doughnutColors = ['#f59e0b', '#10b981'];

$pageTitle = 'Money — TourVolt';
require __DIR__ . '/partials/header.php';
?>

<div class="space-y-8">

    <!-- Header & Filter -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-serif text-2xl md:text-3xl">Money</h1>
        <div class="flex items-center gap-2">
            <span class="text-sm text-muted">Filter:</span>
            <div class="flex gap-1">
                <a href="?filter=all" class="<?= $filter === 'all' ? 'nav-link-active' : 'nav-link' ?> px-3 py-1 text-sm">All</a>
                <a href="?filter=outstanding" class="<?= $filter === 'outstanding' ? 'nav-link-active' : 'nav-link' ?> px-3 py-1 text-sm">Outstanding</a>
                <a href="?filter=paid" class="<?= $filter === 'paid' ? 'nav-link-active' : 'nav-link' ?> px-3 py-1 text-sm">Paid</a>
            </div>
        </div>
    </div>

    <!-- Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-amber-400 to-amber-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-wallet" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Outstanding</div>
                    <div class="text-xl font-mono font-bold text-amber-600 dark:text-amber-400"><?= number_format($totalOutstanding, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS – owed to you</div>
        </div>

        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-emerald-400 to-emerald-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-currency-dollar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Revenue</div>
                    <div class="text-xl font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($totalPaidAll, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS – received from clients</div>
        </div>

        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-rose-400 to-rose-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-coins" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Costs</div>
                    <div class="text-xl font-mono font-bold text-rose-500"><?= number_format($totalCostAll, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS – spent on trips</div>
        </div>

        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-blue-400 to-blue-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-chart-bar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Net Profit</div>
                    <div class="text-xl font-mono font-bold text-blue-600 dark:text-blue-400"><?= number_format($netProfit, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS – revenue minus costs</div>
        </div>
    </div>

    <!-- Charts Row (Premium) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Horizontal Bar: Top Outstanding -->
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg p-5">
            <h3 class="font-serif text-base mb-4 flex items-center gap-2">
                <i class="ti ti-chart-bar text-muted"></i> Top Outstanding
            </h3>
            <div style="position: relative; height: 200px;">
                <canvas id="outstandingChart"></canvas>
            </div>
        </div>

        <!-- Doughnut with Center Label -->
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg p-5">
            <h3 class="font-serif text-base mb-4 flex items-center gap-2">
                <i class="ti ti-pie-chart text-muted"></i> Outstanding vs Paid
            </h3>
            <div style="position: relative; height: 200px;">
                <canvas id="overviewChart"></canvas>
                <!-- Center label will be injected via plugin -->
            </div>
        </div>
    </div>

    <!-- Trip List -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-serif text-base flex items-center gap-2">
                <i class="ti ti-list text-muted"></i> Trip Balances
            </h2>
            <span class="text-xs text-muted"><?= count($filtered) ?> trips shown</span>
        </div>
        <div class="card !p-0 divide-y divide-dayline dark:divide-ledgerline border border-dayline dark:border-ledgerline shadow-lg">
            <?php if (empty($filtered)): ?>
                <div class="p-8 text-center text-muted">
                    <?= $filter === 'all' ? 'No trips yet.' : 'No trips match this filter.' ?>
                </div>
            <?php else: foreach ($filtered as $r):
                $balance = $r['balance'];
                $paidPercent = $r['cost_total'] > 0 ? min(100, round(($r['paid_total'] / $r['cost_total']) * 100)) : 0;
            ?>
                <a href="trip_detail.php?id=<?= $r['id'] ?>#payments" class="block hover:bg-daylight dark:hover:bg-ledgerline transition-colors">
                    <div class="p-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium"><?= htmlspecialchars($r['client_name']) ?></span>
                                <?php if ($r['is_outstanding']): ?>
                                    <span class="badge badge-warning text-xs">Outstanding</span>
                                <?php else: ?>
                                    <span class="badge badge-muted text-xs">Paid</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-muted mt-1 flex flex-wrap items-center gap-1.5">
                                <i class="ti ti-map-pin" style="font-size:13px;"></i>
                                <?= htmlspecialchars($r['destination']) ?> &middot;
                                <?= date('M j, Y', strtotime($r['start_date'])) ?>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1 min-w-[100px]">
                            <div class="font-mono text-sm font-bold <?= $balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted' ?>">
                                <?= number_format($balance, 0) ?> TZS
                            </div>
                            <div class="w-full max-w-[120px] h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 dark:bg-emerald-400 transition-all" style="width: <?= $paidPercent ?>%;"></div>
                            </div>
                            <div class="text-xs text-muted">
                                <?= number_format($r['paid_total'], 0) ?> / <?= number_format($r['cost_total'], 0) ?>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>

</div>

<!-- Chart.js with Premium Plugins -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---------- Horizontal Bar Chart (Top Outstanding) ----------
    const outCtx = document.getElementById('outstandingChart').getContext('2d');
    const labels = <?= json_encode($chartLabels) ?>;
    const data = <?= json_encode($chartData) ?>;
    if (labels.length === 0) {
        document.getElementById('outstandingChart').parentElement.innerHTML = '<div class="text-center text-muted py-6">No outstanding balances.</div>';
    } else {
        // Create gradient for bars
        const gradient = outCtx.createLinearGradient(0, 0, 300, 0);
        gradient.addColorStop(0, '#f59e0b');
        gradient.addColorStop(1, '#fbbf24');

        new Chart(outCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Outstanding (TZS)',
                    data: data,
                    backgroundColor: gradient,
                    borderColor: '#d97706',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.5,
                }]
            },
            options: {
                indexAxis: 'y',   // horizontal bars
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#d97706',
                        font: { weight: 'bold', size: 10 },
                        formatter: v => v.toLocaleString()
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                        ticks: { font: { size: 9 }, callback: v => v.toLocaleString() }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 9 } }
                    }
                },
                animation: { duration: 1200, easing: 'easeOutQuart' }
            },
            plugins: [ChartDataLabels]
        });
    }

    // ---------- Doughnut with Center Label ----------
    const ovCtx = document.getElementById('overviewChart').getContext('2d');
    const doughnutLabels = <?= json_encode($doughnutLabels) ?>;
    const doughnutData = <?= json_encode($doughnutData) ?>;
    const doughnutColors = <?= json_encode($doughnutColors) ?>;

    // Custom center label plugin
    const centerText = {
        id: 'centerText',
        beforeDraw(chart) {
            const { width, height, ctx } = chart;
            ctx.save();
            const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
            const text = total.toLocaleString() + ' TZS';
            ctx.font = 'bold 14px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = chart.options.color || '#1e293b';
            ctx.fillText(text, width / 2, height / 2 - 4);
            ctx.font = '10px Inter, sans-serif';
            ctx.fillStyle = '#94a3b8';
            ctx.fillText('Total', width / 2, height / 2 + 18);
            ctx.restore();
        }
    };

    new Chart(ovCtx, {
        type: 'doughnut',
        data: {
            labels: doughnutLabels,
            datasets: [{
                data: doughnutData,
                backgroundColor: doughnutColors,
                borderWidth: 3,
                borderColor: 'rgba(255,255,255,0.8)',
                hoverOffset: 12,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, padding: 12, font: { size: 11, weight: '500' }, usePointStyle: true, pointStyle: 'circle' }
                }
            },
            cutout: '70%',
            color: '#1e293b',
            animation: { animateRotate: true, duration: 1400, easing: 'easeOutQuart' }
        },
        plugins: [centerText]
    });
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>