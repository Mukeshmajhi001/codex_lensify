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
    mark_notifications_read_for_target('admin/returns.php?id=' . $id);
    $valid = ['requested', 'approved', 'rejected', 'received', 'refunded'];
    $allowedTransitions = [
        'requested' => ['requested', 'approved', 'rejected'],
        'approved' => ['approved', 'received', 'rejected'],
        'received' => ['received', 'refunded'],
        'rejected' => ['rejected'],
        'refunded' => ['refunded'],
    ];
    $requestStatement = db()->prepare('SELECT rr.id, rr.status, rr.order_id, rr.user_id, o.order_number, o.order_status, o.payment_status FROM return_requests rr INNER JOIN orders o ON o.id = rr.order_id WHERE rr.id = ? LIMIT 1');
    $requestStatement->execute([$id]);
    $request = $requestStatement->fetch();

    if (!$request) {
        flash('error', 'Return request not found.');
    } elseif (!in_array($status, $valid, true) || !in_array($status, $allowedTransitions[$request['status']] ?? [], true)) {
        flash('error', 'That return status change is not allowed.');
    } elseif ($status === 'refunded' && $request['status'] !== 'received') {
        flash('error', 'Mark the returned item as received before refunding it.');
    } elseif ($status === 'approved' && $request['order_status'] !== 'delivered') {
        flash('error', 'Only delivered orders can be approved for return.');
    } else {
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE return_requests SET status=? WHERE id=?')->execute([$status, $id]);
            if ($status === 'refunded') {
                db()->prepare('UPDATE orders SET order_status = "returned", payment_status = "refunded" WHERE id = ?')->execute([(int) $request['order_id']]);
            }
            db()->commit();
            if ((int) $request['user_id'] > 0 && $request['status'] !== $status) {
                $message = "Return request for order {$request['order_number']} is now " . ucfirst($status) . '.';
                create_notification((int) $request['user_id'], 'Return request update', $message, 'orders.php');
            }
            log_admin('Updated return request #' . $id . ' to ' . $status);
            flash('success', 'Return status updated.');
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'Could not update the return request.');
        }
    }
    redirect('admin/returns.php');
}
$openedReturnId = (int) ($_GET['id'] ?? 0);
if ($openedReturnId > 0) {
    mark_notifications_read_for_target('admin/returns.php?id=' . $openedReturnId);
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
                            <a class="font-bold underline underline-offset-2" href="<?= h(url('admin/returns.php?id=' . (int) $return['id'])) ?>">#RET-<?= str_pad((string)$return['id'], 4, '0', STR_PAD_LEFT) ?></a><span
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
                                        <option value="<?= h($status) ?>" <?= $return['status'] === $status ? 'selected' : '' ?>>
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
