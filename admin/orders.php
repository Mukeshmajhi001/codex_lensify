<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$status = (string) ($_GET['status'] ?? '');
$orders = [];
$newOrderCount = unread_notification_count_by_title('New order received', (int) current_user()['id']);
if (db_available()) {
    $sql = 'SELECT o.*, COUNT(oi.id) item_count FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id';
    $params = [];
    if (in_array($status, ['processing', 'confirmed', 'shipped', 'delivered', 'cancelled', 'returned'], true)) {
        $sql .= ' WHERE o.order_status=?';
        $params[] = $status;
    }
    $sql .= ' GROUP BY o.id ORDER BY o.created_at DESC';
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $orders = $statement->fetchAll();
}
$adminPage = 'orders';
$pageTitle = 'Orders';
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="label">Sales</p>
        <h1 class="text-3xl font-bold tracking-[-.05em]">Orders</h1>
        <p class="mt-2 text-sm text-zinc-500">Track payment, fulfilment and delivery status.</p>
    </div>
    <form method="get"><select class="input w-48 py-2.5" name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['processing', 'confirmed', 'shipped', 'delivered', 'cancelled', 'returned'] as $option): ?>
                <option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h(ucfirst($option)) ?>
                </option>
            <?php endforeach; ?>
        </select></form>
</div>
<?php if ($newOrderCount): ?><section
        class="mt-6 flex items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4">
        <div class="flex items-center gap-3"><span class="material-symbols-outlined text-amber-700">notifications</span>
            <div>
                <h2 class="text-sm font-bold"><?= $newOrderCount ?> new order<?= $newOrderCount === 1 ? '' : 's' ?></h2>
                <p class="mt-1 text-xs text-amber-800">Each new checkout is counted once here.</p>
            </div>
        </div><a class="text-xs font-bold underline underline-offset-4" href="<?= h(url('admin/notifications.php')) ?>">View
            notifications</a>
    </section><?php endif; ?>
<section class="mt-8 overflow-hidden rounded-2xl border border-zinc-300 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-[900px] w-full text-left text-sm">
            <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                <tr>
                    <th class="px-6 py-4">Order</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Items</th>
                    <th class="px-6 py-4">Total</th>
                    <th class="px-6 py-4">Payment</th>
                    <th class="px-6 py-4">Status</th>
                    <th class="px-6 py-4">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200"><?php foreach ($orders as $order): ?><tr
                        class="transition hover:bg-zinc-50">
                        <td class="px-6 py-4"><a class="font-semibold underline"
                                href="<?= h(url('admin/order.php?id=' . $order['id'])) ?>"><?= h($order['order_number']) ?></a>
                        </td>
                        <td class="px-6 py-4"><strong
                                class="block font-medium"><?= h($order['customer_name']) ?></strong><span
                                class="text-xs text-zinc-500"><?= h($order['customer_email']) ?></span></td>
                        <td class="px-6 py-4"><?= (int)$order['item_count'] ?></td>
                        <td class="px-6 py-4 font-semibold"><?= money($order['total']) ?></td>
                        <td class="px-6 py-4"><span
                                class="badge <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>"><?= h($order['payment_status']) ?></span>
                        </td>
                        <td class="px-6 py-4"><span
                                class="badge bg-blue-100 text-blue-700"><?= h($order['order_status']) ?></span></td>
                        <td class="px-6 py-4 text-zinc-600"><?= date('d M Y', strtotime($order['created_at'])) ?></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div><?php if (!$orders): ?><div class="px-6 py-14 text-center"><span
                class="material-symbols-outlined text-4xl text-zinc-400">shopping_cart</span>
            <p class="mt-3 text-sm text-zinc-500">No orders match this view yet.</p>
        </div><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>