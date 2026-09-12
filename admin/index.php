<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$metrics = ['revenue' => 0, 'orders' => 0, 'customers' => 0, 'average' => 0];
$recentOrders = [];
$topProducts = [];
$lowStock = [];
$revenueDays = [];
$revenueStart = (new DateTimeImmutable('today'))->sub(new DateInterval('P13D'));
for ($offset = 0; $offset < 14; $offset++) {
    $day = $revenueStart->add(new DateInterval('P' . $offset . 'D'));
    $revenueDays[$day->format('Y-m-d')] = ['day' => $day->format('Y-m-d'), 'revenue' => 0.0, 'orders' => 0, 'units' => 0];
}
if (db_available()) {
    $metrics = db()->query("SELECT COALESCE(SUM(total), 0) revenue, COUNT(*) orders, COALESCE(AVG(total), 0) average FROM orders WHERE payment_status = 'paid' AND order_status NOT IN ('cancelled', 'returned')")->fetch() + $metrics;
    $metrics['customers'] = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $recentOrders = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 5')->fetchAll();
    $topProducts = db()->query("SELECT oi.product_name, SUM(oi.quantity) sold, SUM(oi.line_total) revenue FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE o.payment_status = 'paid' AND o.order_status NOT IN ('cancelled', 'returned') GROUP BY oi.product_id, oi.product_name ORDER BY sold DESC LIMIT 4")->fetchAll();
    $lowStock = db()->query('SELECT id,name,stock_quantity FROM products WHERE stock_quantity <= 5 ORDER BY stock_quantity ASC LIMIT 4')->fetchAll();
    $dailyStatement = db()->prepare("SELECT DATE(o.created_at) AS day, COALESCE(SUM(o.total), 0) AS revenue, COUNT(*) AS orders, COALESCE(SUM(item_totals.units), 0) AS units
        FROM orders o LEFT JOIN (SELECT order_id, SUM(quantity) AS units FROM order_items GROUP BY order_id) item_totals ON item_totals.order_id = o.id
        WHERE o.payment_status = 'paid' AND o.order_status NOT IN ('cancelled', 'returned') AND o.created_at >= ? GROUP BY DATE(o.created_at)");
    $dailyStatement->execute([$revenueStart->format('Y-m-d 00:00:00')]);
    foreach ($dailyStatement->fetchAll() as $row) {
        if (isset($revenueDays[$row['day']])) {
            $revenueDays[$row['day']]['revenue'] = (float) $row['revenue'];
            $revenueDays[$row['day']]['orders'] = (int) $row['orders'];
            $revenueDays[$row['day']]['units'] = (int) $row['units'];
        }
    }
}
$maxRevenue = max(1, ...array_values(array_map(static fn(array $day): float => (float) $day['revenue'], $revenueDays)));
$quickTools = [
    ['Categories & brands', 'category', 'Organise catalogue', 'admin/catalogue.php'],
    ['Products & variants', 'tune', 'Frames and variations', 'admin/products.php'],
    ['Inventory', 'inventory', 'Stock and audit trail', 'admin/inventory.php'],
    ['Banners', 'view_carousel', 'Homepage campaigns', 'admin/banners.php'],
    ['Coupons', 'local_offer', 'Promotional offers', 'admin/coupons.php'],
    ['Reviews', 'star', 'Moderate feedback', 'admin/reviews.php'],
    ['Return requests', 'assignment_return', 'Returns & refunds', 'admin/returns.php'],
    ['Reports', 'monitoring', 'Sales intelligence', 'admin/reports.php'],
];
$adminPage = 'overview';
$pageTitle = 'Dashboard';
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <p class="label">Dashboard overview</p>
        <h1 class="text-3xl font-bold tracking-[-.05em]">Good day, <?= h(current_user()['first_name']) ?>.</h1>
        <p class="mt-2 flex items-center gap-2 text-sm text-zinc-500"><span
                class="material-symbols-outlined text-base">calendar_today</span><?= date('F j, Y') ?></p>
    </div>
    <div class="flex gap-2"><a class="button button-secondary" href="<?= h(url('admin/settings.php')) ?>"><span
                class="material-symbols-outlined text-base">settings</span>Settings</a><a class="button button-primary"
            href="<?= h(url('admin/product-form.php')) ?>"><span
                class="material-symbols-outlined text-base">add</span>Add product</a></div>
</div>

<section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border border-zinc-300 bg-white p-6"><span class="label">Revenue</span><strong
            class="mt-4 block text-2xl tracking-[-.04em]"><?= money($metrics['revenue']) ?></strong><span
            class="mt-3 inline-block rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">Paid
            sales only</span></div>
    <div class="rounded-2xl border border-zinc-300 bg-white p-6"><span class="label">Orders</span><strong
            class="mt-4 block text-2xl tracking-[-.04em]"><?= (int) $metrics['orders'] ?></strong><span
            class="mt-3 inline-block rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">Paid &
            completed</span></div>
    <div class="rounded-2xl border border-zinc-300 bg-white p-6"><span class="label">Active customers</span><strong
            class="mt-4 block text-2xl tracking-[-.04em]"><?= (int) $metrics['customers'] ?></strong><span
            class="mt-3 inline-block rounded-full bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-600">Registered
            users</span></div>
    <div class="rounded-2xl border border-zinc-300 bg-white p-6"><span class="label">Avg. order value</span><strong
            class="mt-4 block text-2xl tracking-[-.04em]"><?= money($metrics['average']) ?></strong><span
            class="mt-3 inline-block rounded-full bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-600">Excludes
            cancelled</span></div>
</section>

<section class="mt-6 rounded-2xl border border-zinc-300 bg-white p-5 sm:p-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold">Quick management</h2>
            <p class="mt-1 text-sm text-zinc-500">Every Lensify admin feature, one tap away.</p>
        </div><span class="material-symbols-outlined text-2xl text-zinc-400">dashboard_customize</span>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($quickTools as [$label, $icon, $description, $href]): ?><a
                class="group flex items-center justify-between rounded-xl border border-zinc-200 p-4 transition hover:-translate-y-0.5 hover:border-black hover:shadow-soft"
                href="<?= h(url($href)) ?>"><span class="flex items-center gap-3"><span
                        class="grid h-9 w-9 place-items-center rounded-lg bg-zinc-100 transition group-hover:bg-black group-hover:text-white"><span
                            class="material-symbols-outlined text-xl"><?= h($icon) ?></span></span><span><strong
                            class="block text-sm"><?= h($label) ?></strong><small
                            class="mt-1 block text-[11px] text-zinc-500"><?= h($description) ?></small></span></span><span
                    class="material-symbols-outlined text-base text-zinc-400">arrow_forward</span></a><?php endforeach; ?>
    </div>
</section>

<section class="mt-6 grid gap-6 xl:grid-cols-[1.6fr_.8fr]">
    <div class="rounded-2xl border border-zinc-300 bg-white p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold">Revenue analysis</h2>
                <p class="mt-1 text-sm text-zinc-500">Paid sales across the last 14 days. Select a day for details.</p>
            </div><a class="rounded-lg bg-zinc-100 px-3 py-2 text-xs font-semibold"
                href="<?= h(url('admin/reports.php')) ?>">Open reports</a>
        </div>
        <div class="mt-10 flex h-56 items-end justify-between gap-2" aria-label="Revenue analysis chart">
            <?php foreach ($revenueDays as $day): ?><a class="group relative flex h-full flex-1 flex-col justify-end"
                    href="<?= h(url('admin/reports.php?day=' . rawurlencode($day['day']))) ?>"
                    aria-label="<?= h(date('d M', strtotime($day['day']))) ?>: <?= h(money($day['revenue'])) ?> across <?= (int) $day['units'] ?> frames"><span
                        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-black px-2 py-1 text-[10px] text-white group-hover:block"><?= date('d M', strtotime($day['day'])) ?>
                        · <?= money($day['revenue']) ?> · <?= (int) $day['units'] ?> sold</span><span
                        class="rounded-t-md bg-zinc-200 transition group-hover:bg-black"
                        style="height: <?= max(4, round($day['revenue'] / $maxRevenue * 100)) ?>%"></span></a><?php endforeach; ?>
        </div>
        <div class="mt-4 flex justify-between text-[10px] font-bold uppercase tracking-[.1em] text-zinc-400">
            <span><?= date('d M', strtotime(array_key_first($revenueDays))) ?></span><span><?= date('d M', strtotime(array_key_last($revenueDays))) ?></span>
        </div>
    </div>
    <div class="rounded-2xl border border-zinc-300 bg-white p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-bold">Inventory alert</h2><a class="text-xs font-bold underline"
                href="<?= h(url('admin/inventory.php')) ?>">Manage stock</a>
        </div><?php if ($lowStock): ?><div class="mt-5 space-y-4"><?php foreach ($lowStock as $product): ?><div
                        class="flex items-center justify-between gap-3">
                        <div><strong class="block text-sm"><?= h($product['name']) ?></strong><span
                                class="text-xs text-zinc-500">Low stock item</span></div><span
                            class="badge bg-amber-100 text-amber-800"><?= (int) $product['stock_quantity'] ?> left</span>
                    </div><?php endforeach; ?></div><?php else: ?><div class="mt-10 text-center"><span
                    class="material-symbols-outlined text-4xl text-green-700">verified</span>
                <p class="mt-3 text-sm text-zinc-500">All frames are comfortably stocked.</p>
            </div><?php endif; ?>
    </div>
</section>

<section class="mt-6 overflow-hidden rounded-2xl border border-zinc-300 bg-white">
    <div class="flex items-center justify-between border-b border-zinc-200 p-6">
        <div>
            <h2 class="text-lg font-bold">Recent orders</h2>
            <p class="mt-1 text-sm text-zinc-500">Latest purchases from your store.</p>
        </div><a class="text-xs font-bold underline" href="<?= h(url('admin/orders.php')) ?>">View all</a>
    </div><?php if ($recentOrders): ?><div class="overflow-x-auto">
            <table class="min-w-[680px] w-full text-left text-sm">
                <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                    <tr>
                        <th class="px-6 py-4">Order</th>
                        <th class="px-6 py-4">Customer</th>
                        <th class="px-6 py-4">Total</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200"><?php foreach ($recentOrders as $order): ?><tr>
                            <td class="px-6 py-4 font-semibold"><?= h($order['order_number']) ?></td>
                            <td class="px-6 py-4"><?= h($order['customer_name']) ?></td>
                            <td class="px-6 py-4 font-semibold"><?= money($order['total']) ?></td>
                            <td class="px-6 py-4"><span
                                    class="badge bg-blue-100 text-blue-700"><?= h(ucfirst($order['order_status'])) ?></span>
                            </td>
                            <td class="px-6 py-4"><a class="font-semibold underline"
                                    href="<?= h(url('admin/order.php?id=' . $order['id'])) ?>">Details</a></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div><?php else: ?><div class="px-6 py-14 text-center"><span
                class="material-symbols-outlined text-4xl text-zinc-400">receipt_long</span>
            <p class="mt-3 text-sm text-zinc-500">No orders yet. The dashboard updates automatically when customers check
                out.</p>
        </div><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>