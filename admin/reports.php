<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$paidRevenueWhere = "o.payment_status = 'paid' AND o.order_status NOT IN ('cancelled', 'returned')";
$startDate = (new DateTimeImmutable('today'))->sub(new DateInterval('P13D'));
$days = [];
for ($offset = 0; $offset < 14; $offset++) {
    $day = $startDate->add(new DateInterval('P' . $offset . 'D'));
    $days[$day->format('Y-m-d')] = ['day' => $day->format('Y-m-d'), 'revenue' => 0.0, 'orders' => 0, 'units' => 0];
}

$dailyStatement = db()->prepare("SELECT DATE(o.created_at) AS day, COALESCE(SUM(o.total), 0) AS revenue, COUNT(*) AS orders, COALESCE(SUM(item_totals.units), 0) AS units
    FROM orders o LEFT JOIN (SELECT order_id, SUM(quantity) AS units FROM order_items GROUP BY order_id) item_totals ON item_totals.order_id = o.id
    WHERE {$paidRevenueWhere} AND o.created_at >= ? GROUP BY DATE(o.created_at)");
$dailyStatement->execute([$startDate->format('Y-m-d 00:00:00')]);
foreach ($dailyStatement->fetchAll() as $row) {
    if (isset($days[$row['day']])) {
        $days[$row['day']]['revenue'] = (float) $row['revenue'];
        $days[$row['day']]['orders'] = (int) $row['orders'];
        $days[$row['day']]['units'] = (int) $row['units'];
    }
}

$summaryStatement = db()->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(o.total), 0) AS revenue, COALESCE(AVG(o.total), 0) AS average FROM orders o WHERE {$paidRevenueWhere} AND o.created_at >= ?");
$summaryStatement->execute([$startDate->format('Y-m-d 00:00:00')]);
$summary = $summaryStatement->fetch() ?: ['orders' => 0, 'revenue' => 0, 'average' => 0];

$selectedDay = (string) ($_GET['day'] ?? '');
if (!isset($days[$selectedDay])) {
    $selectedDay = (new DateTimeImmutable('today'))->format('Y-m-d');
}
$selected = $days[$selectedDay];
$selectedOrdersStatement = db()->prepare("SELECT o.id, o.order_number, o.customer_name, o.total, o.created_at, COALESCE(SUM(oi.quantity), 0) AS units
    FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE {$paidRevenueWhere} AND DATE(o.created_at) = ? GROUP BY o.id ORDER BY o.created_at DESC");
$selectedOrdersStatement->execute([$selectedDay]);
$selectedOrders = $selectedOrdersStatement->fetchAll();

$topStatement = db()->prepare("SELECT oi.product_name, SUM(oi.quantity) AS units, SUM(oi.line_total) AS revenue FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE {$paidRevenueWhere} AND o.created_at >= ? GROUP BY oi.product_id, oi.product_name ORDER BY revenue DESC LIMIT 8");
$topStatement->execute([$startDate->format('Y-m-d 00:00:00')]);
$topProducts = $topStatement->fetchAll();
$maxRevenue = max(1, ...array_values(array_map(static fn(array $day): float => (float) $day['revenue'], $days)));
$adminPage = 'reports';
$pageTitle = 'Sales reports';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Growth</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Sales reports</h1>
    <p class="mt-2 text-sm text-zinc-500">Real paid revenue for the latest 14 days. Cancelled and returned orders are
        excluded.</p>
</div>
<section class="mt-8 grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-zinc-300 bg-white p-6"><span class="label">14-day revenue</span><strong
            class="mt-3 block text-3xl tracking-[-.04em]"><?= money($summary['revenue']) ?></strong></div>
    <div class="rounded-2xl border border-zinc-300 bg-white p-6"><span class="label">Paid orders</span><strong
            class="mt-3 block text-3xl tracking-[-.04em]"><?= (int) $summary['orders'] ?></strong></div>
    <div class="rounded-2xl border border-zinc-300 bg-white p-6"><span class="label">Avg. order value</span><strong
            class="mt-3 block text-3xl tracking-[-.04em]"><?= money($summary['average']) ?></strong></div>
</section>
<section class="mt-6 grid gap-6 xl:grid-cols-[1.4fr_.8fr]">
    <div class="rounded-2xl border border-zinc-300 bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-bold">Revenue analysis</h2>
                <p class="mt-1 text-sm text-zinc-500">Tap a day to see revenue, orders and frames sold.</p>
            </div><span
                class="badge bg-zinc-100 text-zinc-600"><?= date('d M', strtotime(array_key_first($days))) ?>–<?= date('d M', strtotime(array_key_last($days))) ?></span>
        </div>
        <div class="mt-10 flex h-64 items-end gap-1.5 sm:gap-2" aria-label="14 day revenue chart">
            <?php foreach ($days as $day): ?><a class="group relative flex h-full min-w-0 flex-1 flex-col justify-end"
                    href="<?= h(url('admin/reports.php?day=' . rawurlencode($day['day']))) ?>"
                    aria-label="<?= h(date('d M', strtotime($day['day']))) ?>: <?= h(money($day['revenue'])) ?> across <?= (int) $day['units'] ?> frames"><span
                        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-black px-2 py-1 text-[10px] text-white group-hover:block"><?= date('d M', strtotime($day['day'])) ?>
                        · <?= money($day['revenue']) ?> · <?= (int) $day['units'] ?> sold</span><span
                        class="rounded-t-md transition <?= $day['day'] === $selectedDay ? 'bg-black' : 'bg-zinc-300 group-hover:bg-zinc-700' ?>"
                        style="height: <?= max(4, round($day['revenue'] / $maxRevenue * 100)) ?>%"></span></a><?php endforeach; ?>
        </div>
        <div class="mt-3 flex justify-between text-[10px] uppercase tracking-[.1em] text-zinc-400">
            <span><?= date('d M', strtotime(array_key_first($days))) ?></span><span><?= date('d M', strtotime(array_key_last($days))) ?></span>
        </div>
    </div>
    <div class="rounded-2xl border border-zinc-300 bg-white p-6">
        <h2 class="font-bold">Top products</h2>
        <p class="mt-1 text-sm text-zinc-500">Paid sales in this same period.</p>
        <div class="mt-5 space-y-5"><?php foreach ($topProducts as $product): ?><div class="flex justify-between gap-3">
                    <div><strong class="block text-sm"><?= h($product['product_name']) ?></strong><span
                            class="text-xs text-zinc-500"><?= (int) $product['units'] ?> sold</span></div><strong
                        class="text-sm"><?= money($product['revenue']) ?></strong>
                </div><?php endforeach; ?><?php if (!$topProducts): ?><p class="text-sm text-zinc-500">Top products will
                    appear after a paid order.</p><?php endif; ?></div>
    </div>
</section>
<section class="mt-6 overflow-hidden rounded-2xl border border-zinc-300 bg-white">
    <div class="border-b border-zinc-200 p-6">
        <p class="label">Selected day</p>
        <h2 class="mt-1 text-xl font-bold"><?= date('l, d M Y', strtotime($selectedDay)) ?></h2>
        <p class="mt-2 text-sm text-zinc-500"><?= (int) $selected['units'] ?>
            frame<?= $selected['units'] === 1 ? '' : 's' ?> sold · <?= (int) $selected['orders'] ?> paid
            order<?= $selected['orders'] === 1 ? '' : 's' ?> · <?= money($selected['revenue']) ?> revenue</p>
    </div><?php if ($selectedOrders): ?><div class="overflow-x-auto">
            <table class="min-w-[680px] w-full text-left text-sm">
                <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                    <tr>
                        <th class="px-6 py-4">Order</th>
                        <th class="px-6 py-4">Customer</th>
                        <th class="px-6 py-4">Time</th>
                        <th class="px-6 py-4">Frames</th>
                        <th class="px-6 py-4">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200"><?php foreach ($selectedOrders as $order): ?><tr>
                            <td class="px-6 py-4"><a class="font-semibold underline"
                                    href="<?= h(url('admin/order.php?id=' . $order['id'])) ?>"><?= h($order['order_number']) ?></a>
                            </td>
                            <td class="px-6 py-4"><?= h($order['customer_name']) ?></td>
                            <td class="px-6 py-4 text-zinc-600"><?= date('H:i', strtotime($order['created_at'])) ?></td>
                            <td class="px-6 py-4"><?= (int) $order['units'] ?></td>
                            <td class="px-6 py-4 font-semibold"><?= money($order['total']) ?></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div><?php else: ?><div class="px-6 py-14 text-center text-sm text-zinc-500">No paid orders on this day.</div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>