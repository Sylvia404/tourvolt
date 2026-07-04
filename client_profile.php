<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$accountId = $user['account_id'];
$pdo = db();

$clientId = (int)($_GET['id'] ?? 0);
if (!$clientId) {
    header('Location: clients.php');
    exit;
}

// Fetch client details
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND account_id = ?");
$stmt->execute([$clientId, $accountId]);
$client = $stmt->fetch();
if (!$client) {
    header('Location: clients.php');
    exit;
}

// Fetch all trips for this client with revenue, cost, profit
$stmt = $pdo->prepare("
    SELECT t.*,
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE trip_id = t.id) AS revenue,
        (SELECT COALESCE(SUM(amount),0) FROM cost_lines WHERE trip_id = t.id) AS cost
    FROM trips t
    WHERE t.client_id = ? AND t.account_id = ?
    ORDER BY t.start_date DESC, t.created_at DESC
");
$stmt->execute([$clientId, $accountId]);
$trips = $stmt->fetchAll();

// Compute stats
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
$outstanding = 0; // we can compute as total cost - total revenue if positive, but not all costs are due; we'll keep as 0 for now or compute from payments? We'll set as 0 unless we have a clear definition.
// For simplicity, we'll set outstanding as total cost - total revenue (if positive), but that's not accurate. We'll skip.

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

$pageTitle = htmlspecialchars($client['name']) . ' — Profile';
require __DIR__ . '/partials/header.php';
?>

<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-serif text-2xl md:text-3xl"><?= htmlspecialchars($client['name']) ?></h1>
            <div class="text-sm text-muted mt-1">
                <?php if ($client['phone']): ?>
                    <span><i class="ti ti-phone" style="font-size:13px;"></i> <?= htmlspecialchars($client['phone']) ?></span>
                <?php endif; ?>
                <?php if ($client['email']): ?>
                    <span class="ml-3"><i class="ti ti-mail" style="font-size:13px;"></i> <?= htmlspecialchars($client['email']) ?></span>
                <?php endif; ?>
                <?php if ($client['notes']): ?>
                    <span class="ml-3 text-muted italic"><?= htmlspecialchars($client['notes']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <a href="trip_detail.php?new=1&client=<?= $clientId ?>" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 shadow-md hover:shadow-lg transition-shadow">
            <i class="ti ti-plus"></i> New Trip
        </a>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm border border-white/20 dark:border-gray-700/30 shadow-lg">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-blue-400 to-blue-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-calendar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total Trips</div>
                    <div class="text-xl font-mono font-bold text-blue-600 dark:text-blue-400"><?= $totalTrips ?></div>
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
                </div>
            </div>
        </div>
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
                <div class="p-2.5 bg-gradient-to-br from-purple-400 to-purple-500 rounded-xl text-white shadow-md">
                    <i class="ti ti-chart-bar" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Net Profit</div>
                    <div class="text-xl font-mono font-bold text-purple-600 dark:text-purple-400"><?= number_format($profit, 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Trip List -->
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-serif text-base flex items-center gap-2">
                <i class="ti ti-list text-muted"></i> All Trips
            </h2>
            <span class="text-xs text-muted"><?= count($trips) ?> trips</span>
        </div>
        <div class="card !p-0 divide-y divide-dayline dark:divide-ledgerline border border-dayline dark:border-ledgerline shadow-lg">
            <?php if (empty($trips)): ?>
                <div class="p-8 text-center text-muted">
                    No trips yet for this client.
                    <a href="trip_detail.php?new=1&client=<?= $clientId ?>" class="text-amber-600 dark:text-amber-400 font-medium hover:underline">Create one now</a>.
                </div>
            <?php else: foreach ($trips as $t):
                $days = daysUntil($t['start_date']);
                $profitTrip = $t['revenue'] - $t['cost'];
                $isUrgent = ($days >= 0 && $days <= 7 && $t['status'] != 'completed' && $t['status'] != 'cancelled');
            ?>
                <div class="flex flex-wrap items-center justify-between p-4 hover:bg-daylight dark:hover:bg-ledgerline transition-colors <?= $isUrgent ? 'bg-amber-50 dark:bg-amber-900/20' : '' ?>">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="trip_detail.php?id=<?= $t['id'] ?>" class="text-sm font-medium hover:text-amber-600 dark:hover:text-amber-400 transition-colors">
                                <?= htmlspecialchars($t['destination']) ?>
                            </a>
                            <?= statusBadge($t['status']) ?>
                            <?php if ($days >= 0 && $t['status'] != 'completed' && $t['status'] != 'cancelled'): ?>
                                <span class="text-xs font-mono <?= $days <= 3 ? 'text-rose-500 font-bold' : 'text-amber-500' ?>">
                                    <?= $days === 0 ? 'Today' : ($days === 1 ? 'Tomorrow' : "in $days days") ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-muted mt-1 flex flex-wrap items-center gap-1.5">
                            <i class="ti ti-calendar" style="font-size:13px;"></i>
                            <?= date('M j, Y', strtotime($t['start_date'])) ?>&ndash;<?= date('j', strtotime($t['end_date'])) ?> &middot;
                            <?= (int) $t['pax'] ?> pax
                            <span class="ml-2 font-mono"> <?= number_format($t['revenue'], 0) ?></span>
                            <span class="font-mono <?= $profitTrip > 0 ? 'text-emerald-500' : 'text-rose-500' ?>">
                                (<?= $profitTrip > 0 ? '+' : '' ?><?= number_format($profitTrip, 0) ?>)
                            </span>
                        </div>
                    </div>
                    <a href="trip_detail.php?id=<?= $t['id'] ?>" class="text-muted hover:text-amber-600 transition-colors">
                        <i class="ti ti-chevron-right" style="font-size:1.2rem;"></i>
                    </a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Back to clients -->
    <div class="mt-6">
        <a href="clients.php" class="btn-secondary inline-flex items-center gap-2">
            <i class="ti ti-arrow-left"></i> Back to Clients
        </a>
    </div>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>