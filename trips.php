<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$accountId = $user['account_id'];
$pdo = db();

// ---------- HANDLE INLINE ADD PAYMENT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment_inline') {
    check_csrf();
    $tripId = (int)($_POST['trip_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $method = $_POST['method'] ?? 'cash';
    $type = $_POST['type'] ?? 'deposit';
    if ($tripId > 0 && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO payments (trip_id, amount, payment_date, method, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$tripId, $amount, $paymentDate, $method, $type]);
    }
    header('Location: trips.php?status=' . urlencode($_GET['status'] ?? 'all'));
    exit;
}

// ---------- HANDLE INLINE ADD COST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_cost_inline') {
    check_csrf();
    $tripId = (int)($_POST['trip_id'] ?? 0);
    $category = $_POST['category'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    if ($tripId > 0 && $description !== '' && $amount > 0) {
        $stmt = $pdo->prepare("INSERT INTO cost_lines (trip_id, category, description, amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tripId, $category, $description, $amount]);
    }
    header('Location: trips.php?status=' . urlencode($_GET['status'] ?? 'all'));
    exit;
}

// ---------- HANDLE EXPORT CSV ----------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filter = $_GET['status'] ?? 'all';
    $sql = "
        SELECT t.id, t.destination, t.start_date, t.end_date, t.pax, t.status, cl.name AS client_name,
            (SELECT COALESCE(SUM(amount),0) FROM payments WHERE trip_id = t.id) AS revenue,
            (SELECT COALESCE(SUM(amount),0) FROM cost_lines WHERE trip_id = t.id) AS cost,
            t.created_at
        FROM trips t JOIN clients cl ON cl.id = t.client_id
        WHERE t.account_id = ?
    ";
    $params = [$accountId];
    if ($filter !== 'all') {
        $sql .= " AND t.status = ?";
        $params[] = $filter;
    }
    $sql .= " ORDER BY t.start_date ASC, t.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trips_export_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Client', 'Destination', 'Start Date', 'End Date', 'Pax', 'Status', 'Revenue', 'Cost', 'Profit', 'Created At']);
    foreach ($rows as $r) {
        fputcsv($output, [
            $r['id'], $r['client_name'], $r['destination'], $r['start_date'], $r['end_date'],
            $r['pax'], $r['status'], $r['revenue'], $r['cost'], $r['revenue'] - $r['cost'], $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// ---------- HANDLE STATUS UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    check_csrf();
    $tripId = (int)($_POST['trip_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if ($tripId > 0 && in_array($newStatus, ['inquiry','confirmed','in_progress','completed','cancelled'])) {
        $stmt = $pdo->prepare("UPDATE trips SET status = ? WHERE id = ? AND account_id = ?");
        $stmt->execute([$newStatus, $tripId, $accountId]);
        header('Location: trips.php?status=' . urlencode($_GET['status'] ?? 'all'));
        exit;
    }
}

// ---------- FILTER ----------
$filter = $_GET['status'] ?? 'all';

// ---------- FETCH TRIPS ----------
$sql = "
  SELECT t.id, t.destination, t.start_date, t.end_date, t.pax, t.status, cl.name AS client_name,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE trip_id = t.id) AS revenue,
    (SELECT COALESCE(SUM(amount),0) FROM cost_lines WHERE trip_id = t.id) AS cost,
    t.created_at
  FROM trips t JOIN clients cl ON cl.id = t.client_id
  WHERE t.account_id = ?
";
$params = [$accountId];
if ($filter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY t.start_date ASC, t.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trips = $stmt->fetchAll();

// ---------- METRICS ----------
$totalTrips = count($trips);
$upcoming = 0;
$completed = 0;
$cancelled = 0;
$totalRevenue = 0;
$totalCost = 0;
foreach ($trips as $t) {
    $totalRevenue += $t['revenue'];
    $totalCost += $t['cost'];
    if (in_array($t['status'], ['in_progress', 'confirmed'])) $upcoming++;
    if ($t['status'] === 'completed') $completed++;
    if ($t['status'] === 'cancelled') $cancelled++;
}
$profit = $totalRevenue - $totalCost;
$avgProfit = $totalTrips > 0 ? round($profit / $totalTrips, 0) : 0;

// ---------- MONTHLY BREAKDOWN ----------
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(start_date, '%Y-%m') AS month,
        COUNT(*) AS count
    FROM trips
    WHERE account_id = ? 
      AND start_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
");
$stmt->execute([$accountId]);
$monthlyStats = $stmt->fetchAll();

// ---------- HELPER ----------
function statusBadge(string $status): string {
    return match ($status) {
        'inquiry' => '<span class="badge badge-muted">Inquiry</span>',
        'confirmed' => '<span class="badge badge-accent">Confirmed</span>',
        'in_progress' => '<span class="badge badge-accent">In progress</span>',
        'completed' => '<span class="badge badge-muted">Completed</span>',
        'cancelled' => '<span class="badge badge-warning">Cancelled</span>',
        default => '<span class="badge badge-muted">' . htmlspecialchars($status) . '</span>',
    };
}

function daysUntil($date) {
    $now = new DateTime();
    $start = new DateTime($date);
    $diff = $now->diff($start);
    return $diff->days * ($diff->invert ? -1 : 1);
}

$pageTitle = 'Trips — TourVolt';
require __DIR__ . '/partials/header.php';
?>

<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="font-serif text-2xl md:text-3xl">Trips</h1>
        <div class="flex gap-2">
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn-secondary inline-flex items-center gap-2">
                <i class="ti ti-file-export"></i> Export CSV
            </a>
            <a href="trip_detail.php?new=1" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 shadow-md hover:shadow-lg transition-shadow">
                <i class="ti ti-plus"></i> New Trip
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-blue-400 to-blue-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-calendar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Trips</div>
                    <div class="text-xl font-mono font-bold text-blue-600 dark:text-blue-400"><?= $totalTrips ?></div>
                    <?php
                    // Fixed: use traditional anonymous function
                    $recentCount = count(array_filter($trips, function($t) {
                        return strtotime($t['created_at']) > strtotime('-30 days');
                    }));
                    ?>
                    <div class="text-xs text-muted">+<?= $recentCount ?> this month</div>
                </div>
            </div>
        </div>
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-amber-400 to-amber-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-clock" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Active / Upcoming</div>
                    <div class="text-xl font-mono font-bold text-amber-600 dark:text-amber-400"><?= $upcoming ?></div>
                    <div class="text-xs text-muted">needs attention</div>
                </div>
            </div>
        </div>
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-emerald-400 to-emerald-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-check" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Completed</div>
                    <div class="text-xl font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= $completed ?></div>
                    <div class="text-xs text-muted"><?= $totalTrips > 0 ? round($completed / $totalTrips * 100) : 0 ?>% success</div>
                </div>
            </div>
        </div>
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-rose-400 to-rose-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-x" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Cancelled</div>
                    <div class="text-xl font-mono font-bold text-rose-500"><?= $cancelled ?></div>
                    <div class="text-xs text-muted"><?= $totalTrips > 0 ? round($cancelled / $totalTrips * 100) : 0 ?>% rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-emerald-400 to-emerald-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-currency-dollar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Revenue</div>
                    <div class="text-xl font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($totalRevenue, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-rose-400 to-rose-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-coins" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Costs</div>
                    <div class="text-xl font-mono font-bold text-rose-500"><?= number_format($totalCost, 0) ?></div>
                </div>
            </div>
        </div>
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-purple-400 to-purple-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-chart-bar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Net Profit</div>
                    <div class="text-xl font-mono font-bold text-purple-600 dark:text-purple-400"><?= number_format($profit, 0) ?></div>
                    <div class="text-xs text-muted">avg per trip: <?= number_format($avgProfit, 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Breakdown -->
    <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg p-4">
        <h3 class="font-serif text-base mb-3 flex items-center gap-2">
            <i class="ti ti-calendar-stats text-muted"></i> Monthly Trip Creation (last 6 months)
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
            <?php 
            $monthMap = [];
            foreach ($monthlyStats as $row) {
                $monthMap[$row['month']] = $row['count'];
            }
            $current = strtotime('-5 months');
            $now = time();
            while ($current <= $now) {
                $monthKey = date('Y-m', $current);
                $label = date('M Y', $current);
                $count = $monthMap[$monthKey] ?? 0;
                $barWidth = min(100, max(10, $count * 15));
                echo "<div class='text-center'>";
                echo "<div class='text-xs font-medium text-muted'>$label</div>";
                echo "<div class='text-xl font-mono font-bold'>$count</div>";
                echo "<div class='h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mt-1 overflow-hidden'>";
                echo "<div class='h-full bg-blue-500 dark:bg-blue-400 rounded-full' style='width: {$barWidth}%;'></div>";
                echo "</div></div>";
                $current = strtotime('+1 month', $current);
            }
            ?>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="flex overflow-x-auto gap-1 pb-2 -mx-1 px-1 border-b border-dayline dark:border-ledgerline">
        <?php
        $statuses = [
            'all' => 'All',
            'inquiry' => 'Inquiry',
            'confirmed' => 'Confirmed',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];
        foreach ($statuses as $key => $label):
            $active = $filter === $key ? 'nav-link-active' : 'nav-link';
        ?>
        <a href="?status=<?= $key ?>" class="<?= $active ?> whitespace-nowrap px-3 py-1.5 text-sm">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Trip List with Inline Forms -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-serif text-base flex items-center gap-2">
                <i class="ti ti-list text-muted"></i> Trips
            </h2>
            <span class="text-xs text-muted"><?= count($trips) ?> trips</span>
        </div>
        <div class="card !p-0 divide-y divide-dayline dark:divide-ledgerline border border-dayline dark:border-ledgerline shadow-lg">
            <?php if (empty($trips)): ?>
                <div class="p-8 text-center text-muted">
                    No trips found for this filter.
                    <a href="trip_detail.php?new=1" class="text-amber-600 dark:text-amber-400 font-medium hover:underline">Create one now</a>.
                </div>
            <?php else: foreach ($trips as $t): 
                $days = daysUntil($t['start_date']);
                $profitTrip = $t['revenue'] - $t['cost'];
                $isUrgent = ($days >= 0 && $days <= 7 && $t['status'] != 'completed' && $t['status'] != 'cancelled');
            ?>
                <div class="p-4 hover:bg-daylight dark:hover:bg-ledgerline transition-colors <?= $isUrgent ? 'bg-amber-50 dark:bg-amber-900/20' : '' ?>">
                    <!-- Trip row -->
                    <div class="flex flex-wrap items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="client_profile.php?id=<?= $t['client_id'] ?? 0 ?>" class="text-sm font-medium hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                                    <?= htmlspecialchars($t['client_name']) ?>
                                </a>
                                <?= statusBadge($t['status']) ?>
                                <?php if ($days >= 0 && $t['status'] != 'completed' && $t['status'] != 'cancelled'): ?>
                                    <span class="text-xs font-mono <?= $days <= 3 ? 'text-rose-500 font-bold' : 'text-amber-500' ?>">
                                        <?= $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : "in $days days") ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-muted mt-1 flex flex-wrap items-center gap-1.5">
                                <i class="ti ti-map-pin" style="font-size:13px;"></i>
                                <?= htmlspecialchars($t['destination']) ?> &middot;
                                <?= date('M j, Y', strtotime($t['start_date'])) ?>&ndash;<?= date('j', strtotime($t['end_date'])) ?> &middot;
                                <?= (int) $t['pax'] ?> pax
                                <span class="ml-2 font-mono">💵 <?= number_format($t['revenue'], 0) ?></span>
                                <span class="font-mono <?= $profitTrip > 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
                                    (<?= $profitTrip > 0 ? '+' : '' ?><?= number_format($profitTrip, 0) ?>)
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-2 sm:mt-0">
                            <!-- Status dropdown -->
                            <form method="post" class="inline-flex items-center gap-1" onsubmit="return confirm('Change status?')">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="trip_id" value="<?= $t['id'] ?>">
                                <select name="status" onchange="this.form.submit()" class="text-xs border rounded px-2 py-1 bg-white dark:bg-gray-800 border-dayline dark:border-ledgerline focus:outline-none focus:ring-1 focus:ring-amber-500">
                                    <?php foreach (['inquiry','confirmed','in_progress','completed','cancelled'] as $opt): ?>
                                        <option value="<?= $opt ?>" <?= $t['status'] === $opt ? 'selected' : '' ?>>
                                            <?= ucfirst(str_replace('_', ' ', $opt)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <!-- Toggle inline forms button -->
                            <button onclick="toggleForms(<?= $t['id'] ?>)" class="text-muted hover:text-emerald-500 transition-colors" title="Quick add payment or cost">
                                <i class="ti ti-plus" style="font-size:1.2rem;"></i>
                            </button>
                            <a href="trip_detail.php?id=<?= $t['id'] ?>" class="text-muted hover:text-amber-600 transition-colors">
                                <i class="ti ti-chevron-right" style="font-size:1.2rem;"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Inline forms (hidden by default) -->
                    <div id="inline-forms-<?= $t['id'] ?>" class="hidden mt-3 pt-3 border-t border-dayline dark:border-ledgerline">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Add Payment -->
                            <form method="post" class="flex flex-wrap items-end gap-2 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="add_payment_inline">
                                <input type="hidden" name="trip_id" value="<?= $t['id'] ?>">
                                <span class="text-xs font-medium text-muted w-full">Add Payment</span>
                                <input type="number" step="0.01" name="amount" placeholder="Amount" class="input-field !py-1 !text-xs flex-1 min-w-[80px]" required>
                                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" class="input-field !py-1 !text-xs w-[130px]">
                                <select name="method" class="input-field !py-1 !text-xs w-[80px]">
                                    <option value="cash">Cash</option>
                                    <option value="mpesa">M-Pesa</option>
                                    <option value="bank">Bank</option>
                                </select>
                                <select name="type" class="input-field !py-1 !text-xs w-[80px]">
                                    <option value="deposit">Deposit</option>
                                    <option value="balance">Balance</option>
                                </select>
                                <button type="submit" class="btn-primary !py-1 !px-3 text-xs">Add</button>
                            </form>

                            <!-- Add Cost -->
                            <form method="post" class="flex flex-wrap items-end gap-2 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg">
                                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="add_cost_inline">
                                <input type="hidden" name="trip_id" value="<?= $t['id'] ?>">
                                <span class="text-xs font-medium text-muted w-full">Add Cost</span>
                                <select name="category" class="input-field !py-1 !text-xs w-[80px]">
                                    <option value="hotel">Hotel</option>
                                    <option value="guide">Guide</option>
                                    <option value="vehicle">Vehicle</option>
                                    <option value="park_fee">Park fee</option>
                                    <option value="fuel">Fuel</option>
                                    <option value="other">Other</option>
                                </select>
                                <input type="text" name="description" placeholder="Description" class="input-field !py-1 !text-xs flex-1 min-w-[100px]" required>
                                <input type="number" step="0.01" name="amount" placeholder="Amount" class="input-field !py-1 !text-xs w-[100px]" required>
                                <button type="submit" class="btn-primary !py-1 !px-3 text-xs">Add</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

</div>

<!-- JavaScript to toggle inline forms -->
<script>
function toggleForms(tripId) {
    const el = document.getElementById('inline-forms-' + tripId);
    if (el) {
        el.classList.toggle('hidden');
    }
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>