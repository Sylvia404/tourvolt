<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$accountId = $user['account_id'];
$pdo = db();

// ---------- SEARCH / FILTER ----------
$search = trim($_GET['search'] ?? '');
$sql = "SELECT id, name, phone, email, notes FROM clients WHERE account_id = ?";
$params = [$accountId];
if ($search !== '') {
    $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= " ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// ---------- AGGREGATE STATS FOR EACH CLIENT ----------
$clientStats = [];
$clientIds = array_column($clients, 'id');
if (!empty($clientIds)) {
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
    $stmt = $pdo->prepare("
        SELECT 
            client_id,
            COUNT(*) AS total_trips,
            SUM(CASE WHEN start_date >= CURDATE() AND status != 'cancelled' THEN 1 ELSE 0 END) AS upcoming_trips,
            COALESCE(SUM(p.amount), 0) AS total_revenue,
            MAX(start_date) AS last_trip_date
        FROM trips t
        LEFT JOIN payments p ON p.trip_id = t.id
        WHERE t.client_id IN ($placeholders)
        GROUP BY t.client_id
    ");
    $stmt->execute($clientIds);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stats as $s) {
        $clientStats[$s['client_id']] = $s;
    }
}

// ---------- OVERALL METRICS ----------
$totalClients = count($clients);
$activeClients = 0;
$totalRevenueAll = 0;
foreach ($clients as $c) {
    $stat = $clientStats[$c['id']] ?? null;
    if ($stat && $stat['upcoming_trips'] > 0) $activeClients++;
    $totalRevenueAll += $stat['total_revenue'] ?? 0;
}
$avgRevenue = $totalClients > 0 ? round($totalRevenueAll / $totalClients, 0) : 0;

// ---------- TOP CLIENTS BY REVENUE (for chart) ----------
$topClients = array_filter($clients, function($c) use ($clientStats) {
    return ($clientStats[$c['id']]['total_revenue'] ?? 0) > 0;
});
usort($topClients, function($a, $b) use ($clientStats) {
    return ($clientStats[$b['id']]['total_revenue'] ?? 0) <=> ($clientStats[$a['id']]['total_revenue'] ?? 0);
});
$topClients = array_slice($topClients, 0, 5);
$chartLabels = array_map(fn($c) => $c['name'], $topClients);
$chartData = array_map(fn($c) => $clientStats[$c['id']]['total_revenue'] ?? 0, $topClients);

// ---------- MONTHLY REVENUE (last 12 months) ----------
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(p.payment_date, '%Y-%m') AS month,
        COALESCE(SUM(p.amount), 0) AS revenue
    FROM payments p
    JOIN trips t ON t.id = p.trip_id
    WHERE t.account_id = ?
      AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$stmt->execute([$accountId]);
$monthlyRows = $stmt->fetchAll();

// Build arrays with all 12 months (fill missing with 0)
$months = [];
$monthlyRevenueData = [];
$current = strtotime('-11 months');
$now = time();
while ($current <= $now) {
    $monthKey = date('Y-m', $current);
    $months[] = date('M Y', $current);
    $found = array_filter($monthlyRows, fn($r) => $r['month'] === $monthKey);
    $monthlyRevenueData[] = count($found) > 0 ? (float) array_values($found)[0]['revenue'] : 0;
    $current = strtotime('+1 month', $current);
}

// ---------- ADD CLIENT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_client') {
    check_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($name !== '') {
        $stmt = $pdo->prepare("INSERT INTO clients (account_id, name, phone, email, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$accountId, $name, $phone, $email, $notes]);
        header('Location: clients.php');
        exit;
    }
}

$pageTitle = 'Clients — TourVolt';
require __DIR__ . '/partials/header.php';
?>

<div class="space-y-8">

    <!-- Header & Add Button -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-serif text-2xl md:text-3xl">Clients</h1>
        <button onclick="document.getElementById('addClientForm').classList.toggle('hidden')" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 shadow-md hover:shadow-lg transition-shadow">
            <i class="ti ti-plus"></i> Add Client
        </button>
    </div>

    <!-- Add Client Form (hidden) -->
    <div id="addClientForm" class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg p-5 hidden">
        <h3 class="font-serif text-base mb-4 flex items-center gap-2">
            <i class="ti ti-user-plus text-muted"></i> New Client
        </h3>
        <form method="post" class="space-y-3">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_client">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <input class="input-field" name="name" placeholder="Full name *" required>
                <input class="input-field" name="phone" placeholder="Phone">
                <input class="input-field" name="email" placeholder="Email">
                <input class="input-field" name="notes" placeholder="Notes (optional)">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary"><i class="ti ti-check"></i> Save</button>
                <button type="button" onclick="document.getElementById('addClientForm').classList.add('hidden')" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-blue-400 to-blue-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-users" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Clients</div>
                    <div class="text-xl font-mono font-bold text-blue-600 dark:text-blue-400"><?= $totalClients ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">Active: <?= $activeClients ?> with upcoming trips</div>
        </div>

        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-emerald-400 to-emerald-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-currency-dollar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Revenue</div>
                    <div class="text-xl font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($totalRevenueAll, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS from all clients</div>
        </div>

        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-purple-400 to-purple-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-chart-bar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Avg Revenue / Client</div>
                    <div class="text-xl font-mono font-bold text-purple-600 dark:text-purple-400"><?= number_format($avgRevenue, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS average</div>
        </div>

        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg hover:shadow-xl transition-all duration-300">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-amber-400 to-amber-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-calendar-stats" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Active Now</div>
                    <div class="text-xl font-mono font-bold text-amber-600 dark:text-amber-400"><?= $activeClients ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">clients with upcoming trips</div>
        </div>
    </div>

    <!-- Monthly Revenue Chart -->
    <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg p-5">
        <h3 class="font-serif text-base mb-4 flex items-center gap-2">
            <i class="ti ti-chart-line text-muted"></i> Monthly Revenue (last 12 months)
        </h3>
        <div style="position: relative; height: 180px;">
            <canvas id="monthlyRevenueChart"></canvas>
        </div>
    </div>

    <!-- Chart: Top 5 Clients by Revenue -->
    <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg p-5">
        <h3 class="font-serif text-base mb-4 flex items-center gap-2">
            <i class="ti ti-chart-bar text-muted"></i> Top 5 Clients by Revenue
        </h3>
        <div style="position: relative; height: 180px;">
            <canvas id="topClientsChart"></canvas>
        </div>
    </div>

    <!-- Search -->
    <div class="flex flex-wrap items-center gap-2">
        <form method="get" class="flex flex-1 gap-2">
            <input class="input-field flex-1" type="text" name="search" placeholder="Search by name, phone, email…" value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-secondary"><i class="ti ti-search"></i> Search</button>
            <?php if ($search !== ''): ?>
                <a href="clients.php" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Client List -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-serif text-base flex items-center gap-2">
                <i class="ti ti-list text-muted"></i> All Clients
            </h2>
            <span class="text-xs text-muted"><?= count($clients) ?> clients</span>
        </div>
        <div class="card !p-0 divide-y divide-dayline dark:divide-ledgerline border border-dayline dark:border-ledgerline shadow-lg">
            <?php if (empty($clients)): ?>
                <div class="p-8 text-center text-muted">
                    <?= $search !== '' ? 'No clients match your search.' : 'No clients yet. Add your first client above.' ?>
                </div>
            <?php else: foreach ($clients as $c):
                $stat = $clientStats[$c['id']] ?? null;
                $totalTrips = $stat['total_trips'] ?? 0;
                $upcoming = $stat['upcoming_trips'] ?? 0;
                $revenue = $stat['total_revenue'] ?? 0;
                $lastTrip = $stat['last_trip_date'] ? date('M j, Y', strtotime($stat['last_trip_date'])) : '—';
            ?>
                <div class="flex flex-wrap items-center justify-between p-4 hover:bg-daylight dark:hover:bg-ledgerline transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <!-- ✅ FIX: Client name is now a clickable link -->
                            <a href="client_profile.php?id=<?= $c['id'] ?>" class="text-sm font-medium hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                                <?= htmlspecialchars($c['name']) ?>
                            </a>
                            <?php if ($upcoming > 0): ?>
                                <span class="badge badge-accent text-xs">Active</span>
                            <?php endif; ?>
                            <span class="text-xs text-muted">(<?= $totalTrips ?> trips)</span>
                        </div>
                        <div class="text-xs text-muted mt-1 flex flex-wrap gap-2">
                            <?php if ($c['phone']): ?>
                                <span><i class="ti ti-phone" style="font-size:13px;"></i> <?= htmlspecialchars($c['phone']) ?></span>
                            <?php endif; ?>
                            <?php if ($c['email']): ?>
                                <span><i class="ti ti-mail" style="font-size:13px;"></i> <?= htmlspecialchars($c['email']) ?></span>
                            <?php endif; ?>
                            <span><i class="ti ti-calendar" style="font-size:13px;"></i> Last: <?= $lastTrip ?></span>
                            <span><i class="ti ti-currency-dollar" style="font-size:13px;"></i> <?= number_format($revenue, 0) ?></span>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-2 sm:mt-0">
                        <a href="trip_detail.php?new=1&client=<?= $c['id'] ?>" class="btn-secondary !px-3 !py-1 text-xs" title="New trip for <?= htmlspecialchars($c['name']) ?>">
                            <i class="ti ti-plus"></i> Trip
                        </a>
                        <a href="trips.php?client_id=<?= $c['id'] ?>" class="btn-secondary !px-3 !py-1 text-xs" title="View all trips for <?= htmlspecialchars($c['name']) ?>">
                            <i class="ti ti-eye"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

</div>

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ---------- Monthly Revenue Chart (Line) ----------
    const monthlyCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
    const months = <?= json_encode($months) ?>;
    const monthlyData = <?= json_encode($monthlyRevenueData) ?>;
    if (months.length === 0 || monthlyData.every(v => v === 0)) {
        document.getElementById('monthlyRevenueChart').parentElement.innerHTML = '<div class="text-center text-muted py-6">No payment data yet.</div>';
    } else {
        const gradient = monthlyCtx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Revenue (TZS)',
                    data: monthlyData,
                    borderColor: '#6366f1',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#6366f1',
                        font: { weight: 'bold', size: 9 },
                        formatter: v => v.toLocaleString()
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                        ticks: { font: { size: 9 }, callback: v => v.toLocaleString() }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9 } }
                    }
                },
                animation: { duration: 1200, easing: 'easeOutQuart' }
            },
            plugins: [ChartDataLabels]
        });
    }

    // ---------- Top Clients Bar Chart ----------
    const ctx = document.getElementById('topClientsChart').getContext('2d');
    const labels = <?= json_encode($chartLabels) ?>;
    const data = <?= json_encode($chartData) ?>;
    if (labels.length === 0) {
        document.getElementById('topClientsChart').parentElement.innerHTML = '<div class="text-center text-muted py-6">No revenue data yet.</div>';
    } else {
        const gradient = ctx.createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.8)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.1)');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (TZS)',
                    data: data,
                    backgroundColor: gradient,
                    borderColor: '#6366f1',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#6366f1',
                        font: { weight: 'bold', size: 10 },
                        formatter: v => v.toLocaleString()
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                        ticks: { font: { size: 9 }, callback: v => v.toLocaleString() }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9 } }
                    }
                },
                animation: { duration: 1200, easing: 'easeOutQuart' }
            },
            plugins: [ChartDataLabels]
        });
    }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>