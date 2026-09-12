<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$customers = [];
if (db_available()) {
    $customers = db()->query("SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.created_at,COUNT(o.id) order_count,COALESCE(SUM(o.total),0) total_spend FROM users u LEFT JOIN orders o ON o.user_id=u.id AND o.order_status!='cancelled' WHERE u.role='customer' GROUP BY u.id ORDER BY u.created_at DESC")->fetchAll();
}
$adminPage = 'customers';
$pageTitle = 'Customers';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Store relationships</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Customers</h1>
    <p class="mt-2 text-sm text-zinc-500">A clear view of your growing Lensify community.</p>
</div>
<section class="mt-8 overflow-hidden rounded-2xl border border-zinc-300 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-[820px] w-full text-left text-sm">
            <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                <tr>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Phone</th>
                    <th class="px-6 py-4">Orders</th>
                    <th class="px-6 py-4">Total spend</th>
                    <th class="px-6 py-4">Joined</th>
                    <th class="px-6 py-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200"><?php foreach ($customers as $customer): ?><tr>
                        <td class="px-6 py-4"><strong
                                class="block"><?= h($customer['first_name'] . ' ' . $customer['last_name']) ?></strong><span
                                class="text-xs text-zinc-500"><?= h($customer['email']) ?></span></td>
                        <td class="px-6 py-4 text-zinc-600"><?= h($customer['phone'] ?: '—') ?></td>
                        <td class="px-6 py-4 font-semibold"><?= $customer['order_count'] ?></td>
                        <td class="px-6 py-4 font-semibold"><?= money($customer['total_spend']) ?></td>
                        <td class="px-6 py-4 text-zinc-600"><?= date('d M Y', strtotime($customer['created_at'])) ?></td>
                        <td class="px-6 py-4"><a class="font-semibold underline"
                                href="<?= h(url('admin/customer.php?id=' . $customer['id'])) ?>">Profile</a></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div><?php if (!$customers): ?><p class="px-6 py-14 text-center text-sm text-zinc-500">Customer accounts will
            appear
            here after registration.</p><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>