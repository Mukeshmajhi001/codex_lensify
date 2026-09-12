<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_return') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $valid = ['requested', 'approved', 'rejected', 'received', 'refunded'];
    if (!in_array($status, $valid, true)) {
        flash('error', 'Invalid return status.');
    } else {
        db()->prepare('UPDATE return_requests SET status=? WHERE id=?')->execute([$status, $id]);
        log_admin('Updated return request #' . $id . ' to ' . $status);
        flash('success', 'Return status updated.');
    }
    redirect('admin/returns.php');
}
$returns = db()->query('SELECT rr.*,o.order_number,o.total,u.first_name,u.last_name,u.email FROM return_requests rr JOIN orders o ON o.id=rr.order_id LEFT JOIN users u ON u.id=rr.user_id ORDER BY rr.created_at DESC')->fetchAll();
$adminPage = 'returns';
$pageTitle = 'Return requests';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Store operations</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Return requests</h1>
    <p class="mt-2 text-sm text-zinc-500">Review every return before it enters the refund flow.</p>
</div>
<section class="mt-8 overflow-hidden rounded-2xl border border-zinc-300 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-[880px] w-full text-left text-sm">
            <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                <tr>
                    <th class="px-6 py-4">Request</th>
                    <th class="px-6 py-4">Customer</th>
                    <th class="px-6 py-4">Reason</th>
                    <th class="px-6 py-4">Order</th>
                    <th class="px-6 py-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200"><?php foreach ($returns as $return): ?><tr>
                        <td class="px-6 py-4">
                            <strong>#RET-<?= str_pad((string)$return['id'], 4, '0', STR_PAD_LEFT) ?></strong><span
                                class="mt-1 block text-xs text-zinc-500"><?= date('d M Y', strtotime($return['created_at'])) ?></span>
                        </td>
                        <td class="px-6 py-4"><strong
                                class="block"><?= h(trim(($return['first_name'] ?? 'Guest') . ' ' . ($return['last_name'] ?? ''))) ?></strong><span
                                class="text-xs text-zinc-500"><?= h($return['email'] ?? 'Guest checkout') ?></span></td>
                        <td class="px-6 py-4"><strong class="block text-sm"><?= h($return['reason']) ?></strong><span
                                class="mt-1 block max-w-xs truncate text-xs text-zinc-500"><?= h($return['details'] ?: 'No further details') ?></span>
                        </td>
                        <td class="px-6 py-4"><a class="font-semibold underline"
                                href="<?= h(url('admin/order.php?id=' . $return['order_id'])) ?>"><?= h($return['order_number']) ?></a><span
                                class="ml-2 text-xs text-zinc-500"><?= money($return['total']) ?></span></td>
                        <td class="px-6 py-4">
                            <form method="post" class="flex items-center gap-2"><?= csrf_field() ?><input type="hidden"
                                    name="action" value="update_return"><input type="hidden" name="id"
                                    value="<?= $return['id'] ?>"><select
                                    class="rounded-lg border-zinc-300 py-1.5 text-xs focus:border-black focus:ring-black"
                                    name="status"
                                    onchange="this.form.submit()"><?php foreach (['requested', 'approved', 'rejected', 'received', 'refunded'] as $status): ?>
                                        <option <?= $return['status'] === $status ? 'selected' : '' ?>>
                                            <?= h(ucfirst($status)) ?></option>
                                    <?php endforeach; ?>
                                </select></form>
                        </td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div><?php if (!$returns): ?><div class="px-6 py-16 text-center"><span
                class="material-symbols-outlined text-4xl text-zinc-400">assignment_return</span>
            <p class="mt-3 text-sm text-zinc-500">Return requests from customers will appear here.</p>
        </div><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>