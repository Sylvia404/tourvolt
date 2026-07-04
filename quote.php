<?php
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
$accountId = $user['account_id'];
$pdo = db();
$tripId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
  SELECT t.*, cl.name AS client_name FROM trips t JOIN clients cl ON cl.id = t.client_id
  WHERE t.id = ? AND t.account_id = ?
");
$stmt->execute([$tripId, $accountId]);
$trip = $stmt->fetch();
if (!$trip) {
    header('Location: trips.php');
    exit;
}

$itinerary = $pdo->prepare("SELECT * FROM itinerary_items WHERE trip_id = ? ORDER BY day_number");
$itinerary->execute([$tripId]);
$itinerary = $itinerary->fetchAll();

$costs = $pdo->prepare("SELECT * FROM cost_lines WHERE trip_id = ? ORDER BY category, id");
$costs->execute([$tripId]);
$costs = $costs->fetchAll();
$totalCost = array_sum(array_column($costs, 'amount'));

// Group costs by category
$costsByCategory = [];
foreach ($costs as $c) {
    $costsByCategory[$c['category']][] = $c;
}

$account = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
$account->execute([$accountId]);
$account = $account->fetch();

$pageTitle = 'Quote — ' . htmlspecialchars($trip['destination']);
require __DIR__ . '/partials/header.php';
?>

<div class="space-y-8">

    <!-- Action Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
        <div>
            <h1 class="font-serif text-2xl md:text-3xl">Quote</h1>
            <p class="text-sm text-muted mt-1">For trip: <?= htmlspecialchars($trip['destination']) ?></p>
        </div>
        <div class="flex gap-3">
            <a href="trip_detail.php?id=<?= $tripId ?>" class="btn-secondary inline-flex items-center gap-2">
                <i class="ti ti-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn-primary inline-flex items-center gap-2">
                <i class="ti ti-printer"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Quote Card -->
    <div class="max-w-4xl mx-auto">
        <div class="card bg-white dark:bg-gray-900 shadow-xl border border-dayline dark:border-ledgerline print:shadow-none print:border print:border-gray-300 p-6 md:p-10 print:p-6">

            <!-- Header: Company + Date -->
            <div class="flex items-start justify-between mb-8 pb-6 border-b-2 border-dayline dark:border-ledgerline print:border-gray-300">
                <div>
                    <div class="font-serif text-2xl md:text-3xl font-semibold text-rust dark:text-brass">
                        <?= htmlspecialchars($account['business_name'] ?? 'TourVolt') ?>
                    </div>
                    <div class="text-sm text-muted mt-1">Travel &amp; Safari</div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-medium text-muted uppercase tracking-wider">Quote #</div>
                    <div class="text-lg font-mono font-bold text-rust dark:text-brass"><?= sprintf('Q%04d', $tripId) ?></div>
                    <div class="text-xs text-muted mt-1"><?= date('F j, Y') ?></div>
                </div>
            </div>

            <!-- Client & Trip Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <div class="text-xs font-medium text-muted uppercase tracking-wider mb-1">Prepared for</div>
                    <div class="font-serif text-xl font-medium"><?= htmlspecialchars($trip['client_name']) ?></div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-medium text-muted uppercase tracking-wider mb-1">Trip Details</div>
                    <div class="text-sm font-medium"><?= htmlspecialchars($trip['destination']) ?></div>
                    <div class="text-sm text-muted">
                        <?= date('M j', strtotime($trip['start_date'])) ?>&ndash;<?= date('M j, Y', strtotime($trip['end_date'])) ?>
                        &middot; <?= (int) $trip['pax'] ?> travelers
                    </div>
                </div>
            </div>

            <!-- Itinerary -->
            <?php if (!empty($itinerary)): ?>
            <div class="mb-8">
                <h2 class="font-serif text-lg font-medium mb-4">Itinerary</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($itinerary as $item): ?>
                        <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="text-xs font-mono font-bold text-muted w-8 pt-0.5">Day <?= (int) $item['day_number'] ?></div>
                            <div>
                                <div class="text-sm font-medium"><?= htmlspecialchars($item['activity']) ?></div>
                                <?php if ($item['location']): ?>
                                    <div class="text-xs text-muted mt-0.5">
                                        <i class="ti ti-map-pin" style="font-size:12px;"></i> <?= htmlspecialchars($item['location']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cost Breakdown -->
            <div class="mb-8">
                <h2 class="font-serif text-lg font-medium mb-4">Cost Summary</h2>
                <?php if (empty($costs)): ?>
                    <p class="text-sm text-muted italic">No costs added yet.</p>
                <?php else: ?>
                    <div class="overflow-hidden rounded-lg border border-dayline dark:border-ledgerline">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-dayline dark:border-ledgerline">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-muted">Category</th>
                                    <th class="px-4 py-2 text-left font-medium text-muted">Description</th>
                                    <th class="px-4 py-2 text-right font-medium text-muted">Amount (TZS)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-dayline dark:divide-ledgerline">
                                <?php foreach ($costs as $c): ?>
                                <tr>
                                    <td class="px-4 py-2 text-xs text-muted"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $c['category']))) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($c['description']) ?></td>
                                    <td class="px-4 py-2 text-right font-mono"><?= number_format($c['amount'], 0) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-800 border-t border-dayline dark:border-ledgerline font-semibold">
                                <tr>
                                    <td colspan="2" class="px-4 py-2 text-right">Total</td>
                                    <td class="px-4 py-2 text-right font-mono text-rust dark:text-brass"><?= number_format($totalCost, 0) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer / Terms -->
            <div class="border-t border-dayline dark:border-ledgerline pt-6 text-xs text-muted text-center">
                <p>This is a preliminary quote. Final pricing may vary based on actual bookings and exchange rates.</p>
                <p class="mt-1">Thank you for choosing <?= htmlspecialchars($account['business_name'] ?? 'TourVolt') ?>.</p>
                <p class="mt-2 text-[10px] text-muted">Generated on <?= date('Y-m-d H:i') ?></p>
            </div>

        </div>
    </div>

</div>

<style>
    /* Print styles */
    @media print {
        body { background: #fff !important; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
        .bg-gray-50 { background: #f9fafb !important; }
        .dark\\:bg-gray-800 { background: #f9fafb !important; }
        .print\\:shadow-none { box-shadow: none !important; }
        .print\\:border { border: 1px solid #ddd !important; }
        .print\\:border-gray-300 { border-color: #d1d5db !important; }
        .print\\:p-6 { padding: 2rem !important; }
        .btn-primary, .btn-secondary, .print\\:hidden { display: none !important; }
        .text-rust { color: #b45309 !important; }
        .dark\\:text-brass { color: #b45309 !important; }
    }
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>