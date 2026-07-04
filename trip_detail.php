<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$accountId = $user['account_id'];
$pdo = db();

$isNew = isset($_GET['new']);
$tripId = isset($_GET['id']) ? (int) $_GET['id'] : null;

// --- Handle form submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_trip') {
        $stmt = $pdo->prepare("INSERT INTO trips (account_id, client_id, destination, start_date, end_date, pax, status) VALUES (?,?,?,?,?,?,'inquiry')");
        $stmt->execute([$accountId, $_POST['client_id'], $_POST['destination'], $_POST['start_date'], $_POST['end_date'], $_POST['pax']]);
        header('Location: trip_detail.php?id=' . $pdo->lastInsertId());
        exit;
    }

    if ($action === 'quick_client') {
        $stmt = $pdo->prepare("INSERT INTO clients (account_id, name, phone, email) VALUES (?,?,?,?)");
        $stmt->execute([$accountId, $_POST['name'], $_POST['phone'], $_POST['email']]);
        header('Location: trip_detail.php?new=1&client=' . $pdo->lastInsertId());
        exit;
    }

    if ($action === 'update_status' && $tripId) {
        $stmt = $pdo->prepare("UPDATE trips SET status = ? WHERE id = ? AND account_id = ?");
        $stmt->execute([$_POST['status'], $tripId, $accountId]);
        header('Location: trip_detail.php?id=' . $tripId);
        exit;
    }

    if ($action === 'add_itinerary' && $tripId) {
        $stmt = $pdo->prepare("INSERT INTO itinerary_items (trip_id, day_number, activity, location, notes) VALUES (?,?,?,?,?)");
        $stmt->execute([$tripId, $_POST['day_number'], $_POST['activity'], $_POST['location'], $_POST['notes']]);
        header('Location: trip_detail.php?id=' . $tripId . '#itinerary');
        exit;
    }

    if ($action === 'add_cost' && $tripId) {
        $stmt = $pdo->prepare("INSERT INTO cost_lines (trip_id, category, description, amount) VALUES (?,?,?,?)");
        $stmt->execute([$tripId, $_POST['category'], $_POST['description'], $_POST['amount']]);
        header('Location: trip_detail.php?id=' . $tripId . '#costs');
        exit;
    }

    if ($action === 'add_payment' && $tripId) {
        $stmt = $pdo->prepare("INSERT INTO payments (trip_id, amount, payment_date, method, type) VALUES (?,?,?,?,?)");
        $stmt->execute([$tripId, $_POST['amount'], $_POST['payment_date'], $_POST['method'], $_POST['type']]);
        header('Location: trip_detail.php?id=' . $tripId . '#payments');
        exit;
    }

    if ($action === 'assign_resource' && $tripId) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO trip_resources (trip_id, resource_id) VALUES (?,?)");
        $stmt->execute([$tripId, $_POST['resource_id']]);
        header('Location: trip_detail.php?id=' . $tripId . '#resources');
        exit;
    }
}

// --- New trip form ---
if ($isNew) {
    $clients = $pdo->prepare("SELECT id, name FROM clients WHERE account_id = ? ORDER BY name");
    $clients->execute([$accountId]);
    $clients = $clients->fetchAll();
    $preselect = $_GET['client'] ?? null;

    $pageTitle = 'New trip — TourVolt';
    require __DIR__ . '/partials/header.php';
    ?>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="font-serif text-2xl md:text-3xl">New Trip</h1>
            <a href="trips.php" class="btn-secondary inline-flex items-center gap-2">
                <i class="ti ti-arrow-left"></i> Back to trips
            </a>
        </div>

        <div class="card max-w-lg">
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="create_trip">

                <div>
                    <label class="label font-medium text-sm">Client</label>
                    <?php if (empty($clients)): ?>
                        <div class="text-sm text-muted mb-2">No clients yet — add one below first.</div>
                    <?php else: ?>
                        <select name="client_id" class="input-field" required>
                            <option value="">Select a client…</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $preselect == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="label font-medium text-sm">Destination</label>
                    <input class="input-field" name="destination" placeholder="Serengeti + Zanzibar" required>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label font-medium text-sm">Start date</label>
                        <input class="input-field" type="date" name="start_date" required>
                    </div>
                    <div>
                        <label class="label font-medium text-sm">End date</label>
                        <input class="input-field" type="date" name="end_date" required>
                    </div>
                </div>

                <div>
                    <label class="label font-medium text-sm">Number of travelers</label>
                    <input class="input-field" type="number" name="pax" min="1" value="1" required>
                </div>

                <button type="submit" class="btn-primary w-full justify-center">
                    <i class="ti ti-check"></i> Create trip
                </button>
            </form>
        </div>

        <div class="card max-w-lg">
            <h3 class="font-serif text-base mb-3">Quick‑add a client</h3>
            <form method="post" class="space-y-3">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="quick_client">
                <input class="input-field" name="name" placeholder="Client name" required>
                <input class="input-field" name="phone" placeholder="Phone">
                <input class="input-field" name="email" placeholder="Email">
                <button type="submit" class="btn-secondary w-full justify-center">
                    <i class="ti ti-user-plus"></i> Add client
                </button>
            </form>
        </div>
    </div>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

// --- View existing trip ---
if (!$tripId) { header('Location: trips.php'); exit; }

$stmt = $pdo->prepare("
  SELECT t.*, cl.name AS client_name, cl.phone AS client_phone, cl.email AS client_email
  FROM trips t JOIN clients cl ON cl.id = t.client_id
  WHERE t.id = ? AND t.account_id = ?
");
$stmt->execute([$tripId, $accountId]);
$trip = $stmt->fetch();
if (!$trip) { header('Location: trips.php'); exit; }

$itinerary = $pdo->prepare("SELECT * FROM itinerary_items WHERE trip_id = ? ORDER BY day_number");
$itinerary->execute([$tripId]);
$itinerary = $itinerary->fetchAll();

$costs = $pdo->prepare("SELECT * FROM cost_lines WHERE trip_id = ? ORDER BY id");
$costs->execute([$tripId]);
$costs = $costs->fetchAll();
$totalCost = array_sum(array_column($costs, 'amount'));

$payments = $pdo->prepare("SELECT * FROM payments WHERE trip_id = ? ORDER BY payment_date");
$payments->execute([$tripId]);
$payments = $payments->fetchAll();
$totalPaid = array_sum(array_column($payments, 'amount'));

$profit = $totalPaid - $totalCost;
$balanceDue = max(0, $totalCost - $totalPaid);

$assigned = $pdo->prepare("SELECT r.* FROM resources r JOIN trip_resources tr ON tr.resource_id = r.id WHERE tr.trip_id = ?");
$assigned->execute([$tripId]);
$assigned = $assigned->fetchAll();

$availableResources = $pdo->prepare("
  SELECT * FROM resources WHERE account_id = ? AND id NOT IN (
    SELECT resource_id FROM trip_resources WHERE trip_id = ?
  ) ORDER BY name
");
$availableResources->execute([$accountId, $tripId]);
$availableResources = $availableResources->fetchAll();

$pageTitle = htmlspecialchars($trip['destination']) . ' — TourVolt';
require __DIR__ . '/partials/header.php';
?>

<div class="space-y-6">

    <!-- Trip header with status -->
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="font-serif text-2xl md:text-3xl"><?= htmlspecialchars($trip['destination']) ?></h1>
                <span class="badge badge-accent"><?= ucfirst(str_replace('_', ' ', $trip['status'])) ?></span>
            </div>
            <div class="text-sm text-muted mt-1 flex flex-wrap items-center gap-1.5">
                <i class="ti ti-user"></i> <?= htmlspecialchars($trip['client_name']) ?> &middot;
                <i class="ti ti-calendar"></i> <?= date('M j', strtotime($trip['start_date'])) ?>&ndash;<?= date('M j, Y', strtotime($trip['end_date'])) ?> &middot;
                <i class="ti ti-users"></i> <?= (int) $trip['pax'] ?> pax
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="quote.php?id=<?= $tripId ?>" class="btn-primary inline-flex items-center gap-2">
                <i class="ti ti-file-text"></i> Quote
            </a>
            <form method="post" class="inline-flex">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <select name="status" onchange="this.form.submit()" class="input-field !w-auto">
                    <?php foreach (['inquiry'=>'Inquiry','confirmed'=>'Confirmed','in_progress'=>'In progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$l): ?>
                        <option value="<?= $k ?>" <?= $trip['status']===$k?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <!-- Financial metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card bg-gradient-to-br from-rose-500/5 to-rose-500/10 dark:from-rose-400/10 dark:to-rose-400/20 border-l-4 border-rose-500">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-rose-500/20 rounded-lg">
                    <i class="ti ti-coins text-rose-500" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total cost</div>
                    <div class="text-xl font-mono font-bold"><?= number_format($totalCost, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS</div>
        </div>

        <div class="card bg-gradient-to-br from-emerald-500/5 to-emerald-500/10 dark:from-emerald-400/10 dark:to-emerald-400/20 border-l-4 border-emerald-500">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-500/20 rounded-lg">
                    <i class="ti ti-wallet text-emerald-500" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Total paid</div>
                    <div class="text-xl font-mono font-bold text-emerald-500"><?= number_format($totalPaid, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS</div>
        </div>

        <div class="card bg-gradient-to-br from-amber-500/5 to-amber-500/10 dark:from-amber-400/10 dark:to-amber-400/20 border-l-4 border-amber-500">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-500/20 rounded-lg">
                    <i class="ti ti-alert-triangle text-amber-500" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Balance due</div>
                    <div class="text-xl font-mono font-bold <?= $balanceDue > 0 ? 'text-amber-500' : 'text-muted' ?>">
                        <?= number_format($balanceDue, 0) ?>
                    </div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS</div>
        </div>

        <div class="card bg-gradient-to-br from-accent/5 to-accent/10 dark:from-accent/10 dark:to-accent/20 border-l-4 border-accent">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-accent/20 rounded-lg">
                    <i class="ti ti-chart-bar text-accent" style="font-size:1.5rem;"></i>
                </div>
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Profit</div>
                    <div class="text-xl font-mono font-bold text-accent"><?= number_format($profit, 0) ?></div>
                </div>
            </div>
            <div class="text-xs text-muted mt-2">TZS</div>
        </div>
    </div>

    <!-- Itinerary -->
    <div id="itinerary" class="card">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-serif text-base flex items-center gap-2">
                <i class="ti ti-list"></i> Itinerary
            </h2>
        </div>
        <?php if (empty($itinerary)): ?>
            <div class="text-sm text-muted mb-4">No days added yet.</div>
        <?php else: ?>
            <div class="divide-y divide-dayline dark:divide-ledgerline mb-4">
                <?php foreach ($itinerary as $item): ?>
                    <div class="py-3 flex gap-4">
                        <div class="text-xs text-muted w-14 pt-0.5 font-mono">Day <?= (int) $item['day_number'] ?></div>
                        <div>
                            <div class="text-sm font-medium"><?= htmlspecialchars($item['activity']) ?></div>
                            <?php if ($item['location']): ?>
                                <div class="text-xs text-muted mt-0.5">📍 <?= htmlspecialchars($item['location']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_itinerary">
            <input class="input-field md:col-span-1" type="number" name="day_number" placeholder="Day #" min="1" required>
            <input class="input-field md:col-span-2" name="activity" placeholder="Activity" required>
            <input class="input-field md:col-span-1" name="location" placeholder="Location">
            <button type="submit" class="btn-secondary justify-center"><i class="ti ti-plus"></i> Add</button>
        </form>
    </div>

    <!-- Costs -->
    <div id="costs" class="card">
        <h2 class="font-serif text-base mb-4 flex items-center gap-2">
            <i class="ti ti-coin"></i> Costs
        </h2>
        <?php if (!empty($costs)): ?>
            <div class="divide-y divide-dayline dark:divide-ledgerline mb-4">
                <?php foreach ($costs as $c): ?>
                    <div class="py-2.5 flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm">
                            <span class="badge badge-muted mr-2"><?= htmlspecialchars(str_replace('_',' ',$c['category'])) ?></span>
                            <?= htmlspecialchars($c['description']) ?>
                        </div>
                        <div class="text-sm font-mono font-medium"><?= number_format($c['amount'], 0) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_cost">
            <select name="category" class="input-field md:col-span-1">
                <?php foreach (['hotel'=>'Hotel','guide'=>'Guide','vehicle'=>'Vehicle','park_fee'=>'Park fee','fuel'=>'Fuel','other'=>'Other'] as $k=>$l): ?>
                    <option value="<?= $k ?>"><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <input class="input-field md:col-span-2" name="description" placeholder="Description">
            <input class="input-field md:col-span-1" type="number" step="0.01" name="amount" placeholder="Amount (TZS)" required>
            <button type="submit" class="btn-secondary justify-center"><i class="ti ti-plus"></i> Add</button>
        </form>
    </div>

    <!-- Payments -->
    <div id="payments" class="card">
        <h2 class="font-serif text-base mb-4 flex items-center gap-2">
            <i class="ti ti-wallet"></i> Payments
        </h2>
        <?php if (!empty($payments)): ?>
            <div class="divide-y divide-dayline dark:divide-ledgerline mb-4">
                <?php foreach ($payments as $p): ?>
                    <div class="py-2.5 flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm">
                            <span class="badge badge-muted mr-2"><?= htmlspecialchars(ucfirst($p['type'])) ?></span>
                            <?= date('M j, Y', strtotime($p['payment_date'])) ?> &middot; <?= htmlspecialchars(strtoupper($p['method'])) ?>
                        </div>
                        <div class="text-sm font-mono font-bold text-rust dark:text-brass"><?= number_format($p['amount'], 0) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="add_payment">
            <input class="input-field md:col-span-1" type="number" step="0.01" name="amount" placeholder="Amount (TZS)" required>
            <input class="input-field md:col-span-1" type="date" name="payment_date" required>
            <select name="method" class="input-field md:col-span-1">
                <option value="cash">Cash</option>
                <option value="mpesa">M-Pesa</option>
                <option value="bank">Bank</option>
                <option value="other">Other</option>
            </select>
            <select name="type" class="input-field md:col-span-1">
                <option value="deposit">Deposit</option>
                <option value="balance">Balance</option>
                <option value="refund">Refund</option>
            </select>
            <button type="submit" class="btn-secondary justify-center"><i class="ti ti-plus"></i> Add</button>
        </form>
    </div>

    <!-- Resources -->
    <div id="resources" class="card">
        <h2 class="font-serif text-base mb-4 flex items-center gap-2">
            <i class="ti ti-users"></i> Guide &amp; vehicle
        </h2>
        <?php if (!empty($assigned)): ?>
            <div class="flex flex-wrap gap-2 mb-4">
                <?php foreach ($assigned as $r): ?>
                    <span class="badge badge-muted inline-flex items-center gap-1.5">
                        <i class="ti ti-user" style="font-size:12px;"></i>
                        <?= htmlspecialchars($r['name']) ?> &middot; <?= htmlspecialchars(ucfirst($r['type'])) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($availableResources)): ?>
            <form method="post" class="flex flex-wrap gap-2">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="assign_resource">
                <select name="resource_id" class="input-field flex-1 min-w-[150px]">
                    <?php foreach ($availableResources as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars(ucfirst($r['type'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-secondary"><i class="ti ti-plus"></i> Assign</button>
            </form>
        <?php else: ?>
            <div class="text-sm text-muted">No guides, drivers, or vehicles set up yet.</div>
        <?php endif; ?>
    </div>

    <!-- Bottom actions -->
    <div class="flex flex-wrap justify-end gap-3">
        <a href="trips.php" class="btn-secondary inline-flex items-center gap-2">
            <i class="ti ti-arrow-left"></i> Back to trips
        </a>
        <a href="quote.php?id=<?= $tripId ?>" class="btn-primary inline-flex items-center gap-2">
            <i class="ti ti-file-text"></i> Preview quote
        </a>
    </div>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>