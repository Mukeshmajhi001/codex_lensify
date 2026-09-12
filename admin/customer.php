<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$id = (int)($_GET['id'] ?? 0);
$statement = db()->prepare('SELECT id,first_name,last_name,email,phone,profile_image,bio,is_active,created_at FROM users WHERE id=? AND role="customer"');
$statement->execute([$id]);
$customer = $statement->fetch();
if (!$customer) {
    flash('error', 'Customer not found.');
    redirect('admin/customers.php');
}
$ordersQuery = db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$ordersQuery->execute([$id]);
$orders = $ordersQuery->fetchAll();
$loginQuery = db()->prepare('SELECT * FROM user_login_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 25');
$loginQuery->execute([$id]);
$logins = $loginQuery->fetchAll();
$adminPage = 'customers';
$pageTitle = 'Customer profile';
require APP_ROOT . '/includes/admin-header.php';
?>
<div><a class="text-xs font-bold underline" href="<?= h(url('admin/customers.php')) ?>">← All customers</a>
    <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4"><?php if ($avatar = avatar_url($customer)): ?><img
                    class="h-16 w-16 rounded-full object-cover" src="<?= h($avatar) ?>" alt=""><?php else: ?><span
                    class="grid h-16 w-16 place-items-center rounded-full bg-black text-base font-bold text-white"><?= h(initials($customer)) ?></span><?php endif; ?>
            <div>
                <p class="label">Customer profile</p>
                <h1 class="text-3xl font-bold tracking-[-.05em]">
                    <?= h($customer['first_name'] . ' ' . $customer['last_name']) ?></h1>
                <p class="mt-1 text-sm text-zinc-500"><?= h($customer['email']) ?></p>
            </div>
        </div><span
            class="badge <?= $customer['is_active'] ? 'bg-green-100 text-green-700' : 'bg-zinc-200 text-zinc-600' ?>"><?= $customer['is_active'] ? 'Active' : 'Suspended' ?></span>
    </div>
</div>
<div class="mt-8 grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
    <section class="overflow-hidden rounded-2xl border border-zinc-300 bg-white">
        <div class="border-b border-zinc-200 px-6 py-5">
            <h2 class="font-bold">Order history</h2>
        </div>
        <div class="divide-y divide-zinc-200"><?php foreach ($orders as $order): ?><a
                    class="flex items-center justify-between gap-4 px-6 py-5 transition hover:bg-zinc-50"
                    href="<?= h(url('admin/order.php?id=' . $order['id'])) ?>"><span><strong
                            class="block text-sm"><?= h($order['order_number']) ?></strong><small
                            class="mt-1 block text-zinc-500"><?= date('d M Y', strtotime($order['created_at'])) ?></small></span><span><strong
                            class="block text-right text-sm"><?= money($order['total']) ?></strong><small
                            class="mt-1 block text-right text-zinc-500"><?= h($order['order_status']) ?></small></span></a><?php endforeach; ?><?php if (!$orders): ?>
                <p class="px-6 py-12 text-center text-sm text-zinc-500">No orders from this customer yet.</p><?php endif; ?>
        </div>
    </section>
    <div class="space-y-6">
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <h2 class="font-bold">Contact details</h2>
            <dl class="mt-5 space-y-4 text-sm">
                <div>
                    <dt class="label">Phone</dt>
                    <dd><?= h($customer['phone'] ?: 'Not provided') ?></dd>
                </div>
                <div>
                    <dt class="label">About</dt>
                    <dd class="leading-6 text-zinc-600"><?= h($customer['bio'] ?: 'No profile bio') ?></dd>
                </div>
                <div>
                    <dt class="label">Joined</dt>
                    <dd><?= date('d M Y', strtotime($customer['created_at'])) ?></dd>
                </div>
            </dl>
        </section>
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <h2 class="font-bold">Login history</h2>
            <div class="mt-4 space-y-4"><?php foreach ($logins as $login): ?><div
                        class="border-l-2 border-zinc-200 pl-3 text-sm"><strong
                            class="block"><?= date('d M Y, H:i', strtotime($login['created_at'])) ?></strong><span
                            class="text-xs text-zinc-500"><?= h($login['ip_address'] ?: 'Local') ?></span></div>
                    <?php endforeach; ?><?php if (!$logins): ?><p class="text-sm text-zinc-500">No recorded sign-ins.</p>
                <?php endif; ?></div>
        </section>
    </div>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>