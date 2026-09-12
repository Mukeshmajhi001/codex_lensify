<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $previousStatement = db()->prepare('SELECT user_id, order_number, order_status, payment_status FROM orders WHERE id = ?');
    $previousStatement->execute([$id]);
    $previous = $previousStatement->fetch();

    if (!$previous) {
        flash('error', 'Order not found.');
        redirect('admin/orders.php');
    }

    if ($action === 'update_order') {
        $orderStatus = (string) ($_POST['order_status'] ?? '');
        $paymentStatus = (string) ($_POST['payment_status'] ?? '');
        $validOrder = ['processing', 'confirmed', 'shipped', 'delivered', 'returned'];
        $validPayment = ['pending', 'paid', 'failed', 'refunded'];

        if ($previous['order_status'] === 'cancelled') {
            flash('error', 'Cancelled orders cannot be changed.');
        } elseif (!in_array($orderStatus, $validOrder, true) || !in_array($paymentStatus, $validPayment, true)) {
            flash('error', 'Invalid status.');
        } else {
            db()->prepare('UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ?')->execute([$orderStatus, $paymentStatus, $id]);
            if ((int) $previous['user_id'] > 0 && ($previous['order_status'] !== $orderStatus || $previous['payment_status'] !== $paymentStatus)) {
                $message = "Order {$previous['order_number']} is now " . ucfirst($orderStatus) . ' and payment is ' . ucfirst($paymentStatus) . '.';
                create_notification((int) $previous['user_id'], 'Order update', $message, 'orders.php');
            }
            log_admin('Updated order #' . $id);
            flash('success', 'Order status updated.');
        }
    } elseif ($action === 'cancel_order') {
        $reason = trim((string) ($_POST['cancellation_reason'] ?? ''));
        if ($previous['order_status'] === 'cancelled') {
            flash('error', 'This order has already been cancelled.');
        } else {
            $reason = $reason !== '' ? mb_substr($reason, 0, 1000) : 'Cancelled by store administrator.';
            db()->prepare('UPDATE orders SET order_status = "cancelled", cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW() WHERE id = ?')->execute([
                $reason,
                current_user()['id'],
                $id,
            ]);
            if ((int) $previous['user_id'] > 0) {
                create_notification((int) $previous['user_id'], 'Order cancelled', "Order {$previous['order_number']} was cancelled. Reason: {$reason}", 'orders.php');
            }
            log_admin('Cancelled order #' . $id . ': ' . $reason);
            flash('success', 'Order cancelled. The customer has been notified.');
        }
    }
    redirect('admin/order.php?id=' . $id);
}

$statement = db()->prepare('SELECT * FROM orders WHERE id = ?');
$statement->execute([$id]);
$order = $statement->fetch();
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/orders.php');
}
$itemsStatement = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStatement->execute([$id]);
$items = $itemsStatement->fetchAll();
$adminPage = 'orders';
$pageTitle = 'Order ' . $order['order_number'];
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="flex items-end justify-between">
    <div><a class="text-xs font-bold underline" href="<?= h(url('admin/orders.php')) ?>">← All orders</a>
        <h1 class="mt-4 text-3xl font-bold tracking-[-.05em]"><?= h($order['order_number']) ?></h1>
        <p class="mt-2 text-sm text-zinc-500">Placed <?= date('d M Y, H:i', strtotime($order['created_at'])) ?></p>
    </div>
    <div class="flex items-center gap-3"><a class="button button-secondary px-3 py-2"
            href="<?= h(url('admin/invoice.php?id=' . $order['id'])) ?>" target="_blank">Invoice</a><span
            class="badge <?= $order['order_status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' ?>"><?= h($order['order_status']) ?></span>
    </div>
</div>
<div class="mt-8 grid gap-6 xl:grid-cols-[1.25fr_.75fr]">
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-zinc-300 bg-white">
            <div class="border-b border-zinc-200 px-6 py-5">
                <h2 class="font-bold">Order items</h2>
            </div>
            <div class="divide-y divide-zinc-200"><?php foreach ($items as $item): ?><div
                        class="flex items-center justify-between gap-4 px-6 py-5">
                        <div><strong class="block text-sm"><?= h($item['product_name']) ?></strong><span
                                class="mt-1 block text-xs text-zinc-500"><?= h($item['variant_sku'] ?: $item['sku']) ?><?= $item['variant_name'] ? ' · ' . h($item['variant_name']) : '' ?>
                                · <?= h($item['lens_type'] ?: 'Standard') ?> · Qty <?= $item['quantity'] ?></span></div>
                        <strong class="text-sm"><?= money($item['line_total']) ?></strong>
                    </div><?php endforeach; ?></div>
            <div class="space-y-3 bg-zinc-50 px-6 py-5 text-sm">
                <div class="flex justify-between text-zinc-600">
                    <span>Subtotal</span><span><?= money($order['subtotal']) ?></span>
                </div>
                <?php if ((float) $order['discount_amount'] > 0): ?><div class="flex justify-between text-green-700">
                        <span>Discount</span><span>−<?= money($order['discount_amount']) ?></span>
                    </div><?php endif; ?><div class="flex justify-between text-zinc-600">
                    <span>Shipping</span><span><?= money($order['shipping_amount']) ?></span>
                </div>
                <div class="flex justify-between border-t border-zinc-200 pt-3 text-base font-bold">
                    <span>Total</span><span><?= money($order['total']) ?></span>
                </div>
            </div>
        </section>
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <h2 class="font-bold">Customer & delivery</h2>
            <div class="mt-5 grid gap-6 sm:grid-cols-2">
                <div><span class="label">Customer</span><strong
                        class="block text-sm"><?= h($order['customer_name']) ?></strong>
                    <p class="mt-1 text-sm text-zinc-600">
                        <?= h($order['customer_email']) ?><br><?= h($order['customer_phone']) ?></p>
                </div>
                <div><span class="label">Delivery address</span>
                    <p class="whitespace-pre-line text-sm leading-6 text-zinc-600"><?= h($order['delivery_address']) ?>
                    </p>
                </div>
            </div>
            <?php if (trim((string) ($order['customer_message'] ?? '')) !== ''): ?><div
                    class="mt-6 rounded-xl bg-amber-50 p-4"><span class="label text-amber-800">Customer message</span>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-amber-950">
                        <?= h($order['customer_message']) ?></p>
                </div><?php endif; ?>
        </section><?php if ($order['order_status'] === 'cancelled'): ?><section
                class="rounded-2xl border border-red-200 bg-red-50 p-6">
                <h2 class="font-bold text-red-900">Cancellation details</h2>
                <p class="mt-3 text-sm leading-6 text-red-800">
                    <?= h($order['cancellation_reason'] ?: 'No reason recorded.') ?></p>
                <?php if ($order['cancelled_at']): ?><p class="mt-3 text-xs text-red-700">Cancelled
                        <?= date('d M Y, H:i', strtotime($order['cancelled_at'])) ?></p><?php endif; ?>
            </section><?php endif; ?>
    </div>
    <aside class="h-fit space-y-6">
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <h2 class="font-bold">Update order</h2><?php if ($order['order_status'] === 'cancelled'): ?><p
                    class="mt-3 text-sm leading-6 text-zinc-500">This order is cancelled, so its fulfilment status is
                    locked.</p><?php else: ?><form class="mt-5 space-y-5" method="post"><?= csrf_field() ?><input
                        type="hidden" name="action" value="update_order"><input type="hidden" name="id" value="<?= $id ?>">
                    <div><label class="label">Order status</label><select class="input"
                            name="order_status"><?php foreach (['processing', 'confirmed', 'shipped', 'delivered', 'returned'] as $status): ?>
                                <option <?= $order['order_status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    <div><label class="label">Payment status</label><select class="input"
                            name="payment_status"><?php foreach (['pending', 'paid', 'failed', 'refunded'] as $status): ?>
                                <option <?= $order['payment_status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                            <?php endforeach; ?>
                        </select></div><button class="button button-primary w-full" type="submit">Save status</button>
                </form><?php endif; ?>
        </section><?php if ($order['order_status'] !== 'cancelled'): ?><section
                class="rounded-2xl border border-red-200 bg-red-50 p-6">
                <h2 class="font-bold text-red-900">Cancel order</h2>
                <p class="mt-2 text-sm leading-6 text-red-800">Administrators can cancel this order at any stage, including
                    after it has shipped.</p>
                <form class="mt-5 space-y-4" method="post"
                    onsubmit="return confirm('Cancel this order? The fulfilment status will be locked.');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="cancel_order"><input type="hidden"
                        name="id" value="<?= $id ?>">
                    <div><label class="label">Cancellation reason</label><textarea class="input min-h-24" maxlength="1000"
                            name="cancellation_reason" placeholder="Explain why this order is being cancelled"></textarea>
                    </div><button class="button w-full border border-red-700 bg-red-700 text-white hover:bg-red-800"
                        type="submit">Cancel order</button>
                </form>
            </section><?php endif; ?>
    </aside>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>