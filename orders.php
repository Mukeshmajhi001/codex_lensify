<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_return') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? ''));
    $details = trim((string) ($_POST['details'] ?? ''));
    $statement = db()->prepare('SELECT id, order_status FROM orders WHERE id = ? AND user_id = ?');
    $statement->execute([$orderId, current_user()['id']]);
    $order = $statement->fetch();
    if (!$order || $order['order_status'] !== 'delivered') {
        flash('error', 'Only delivered orders can be requested for return.');
    } elseif (!$reason) {
        flash('error', 'Select a return reason.');
    } else {
        $duplicate = db()->prepare('SELECT id FROM return_requests WHERE order_id = ? AND user_id = ? LIMIT 1');
        $duplicate->execute([$orderId, current_user()['id']]);
        if ($duplicate->fetch()) {
            flash('error', 'A return request already exists for this order.');
        } else {
            db()->prepare('INSERT INTO return_requests (order_id, user_id, reason, details) VALUES (?, ?, ?, ?)')->execute([$orderId, current_user()['id'], $reason, $details ?: null]);
            flash('success', 'Your return request has been submitted for review.');
        }
    }
    redirect('orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $statement = db()->prepare('SELECT id, order_number, order_status FROM orders WHERE id = ? AND user_id = ?');
    $statement->execute([$orderId, current_user()['id']]);
    $order = $statement->fetch();

    if (!$order) {
        flash('error', 'Order not found.');
    } elseif (!customer_can_cancel_order((string) $order['order_status'])) {
        flash('error', 'This order has already been shipped and can no longer be cancelled online.');
    } else {
        $cancelStatement = db()->prepare('UPDATE orders SET order_status = "cancelled", cancellation_reason = ?, cancelled_by = ?, cancelled_at = NOW() WHERE id = ? AND order_status IN ("processing", "confirmed")');
        $cancelStatement->execute([
            'Cancelled by customer before shipment.',
            current_user()['id'],
            $orderId,
        ]);
        if ($cancelStatement->rowCount() > 0) {
            notify_admins('Order cancelled', "{$order['order_number']} was cancelled by the customer before shipment.", 'admin/order.php?id=' . $orderId);
            flash('success', 'Your order has been cancelled.');
        } else {
            flash('error', 'This order can no longer be cancelled online.');
        }
    }
    redirect('orders.php');
}

$orders = [];
$orderItemsByOrder = [];
$reviewStatusByProduct = [];
if (db_available()) {
    $statement = db()->prepare("SELECT o.*, COUNT(oi.id) AS item_count, (SELECT status FROM return_requests rr WHERE rr.order_id = o.id AND rr.user_id = o.user_id ORDER BY rr.id DESC LIMIT 1) AS return_status FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id WHERE o.user_id = ? GROUP BY o.id ORDER BY o.created_at DESC");
    $statement->execute([current_user()['id']]);
    $orders = $statement->fetchAll();
    if ($orders) {
        $orderIds = array_map(static fn(array $order): int => (int) $order['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemsStatement = db()->prepare("SELECT oi.*, p.slug AS product_slug,
            COALESCE(NULLIF(oi.product_image_url, ''), (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.sort_order, pi.id LIMIT 1)) AS image_url
            FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id IN ({$placeholders}) ORDER BY oi.order_id, oi.id");
        $itemsStatement->execute($orderIds);
        foreach ($itemsStatement->fetchAll() as $item) {
            $orderItemsByOrder[(int) $item['order_id']][] = $item;
        }

        $productIds = [];
        foreach ($orderItemsByOrder as $items) {
            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                if ($productId > 0) {
                    $productIds[$productId] = $productId;
                }
            }
        }
        if ($productIds) {
            $reviewPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
            $reviewsStatement = db()->prepare("SELECT product_id, status FROM reviews WHERE user_id = ? AND product_id IN ({$reviewPlaceholders})");
            $reviewsStatement->execute([(int) current_user()['id'], ...array_values($productIds)]);
            foreach ($reviewsStatement->fetchAll() as $review) {
                $reviewStatusByProduct[(int) $review['product_id']] = $review['status'];
            }
        }
    }
}
$pageTitle = 'My orders';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1180px] px-5 py-10 lg:px-10">
    <div class="mb-9 flex items-end justify-between">
        <div>
            <p class="label">My account</p>
            <h1 class="text-4xl font-bold tracking-[-.05em]">My orders</h1>
        </div><a class="text-xs font-bold underline underline-offset-4" href="<?= h(url('account.php')) ?>">Back to
            profile</a>
    </div>
    <?php if (!$orders): ?>
        <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-20 text-center"><span
                class="material-symbols-outlined text-5xl text-zinc-400">receipt_long</span>
            <h2 class="mt-4 text-xl font-bold">No orders yet</h2>
            <p class="mt-2 text-sm text-zinc-500">When you find your next frame, it will appear here.</p><a
                class="button button-primary mt-6" href="<?= h(url('shop.php')) ?>">Shop frames</a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): ?>
                <article class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-6">
                    <div class="grid gap-4 sm:grid-cols-[1.2fr_.8fr_.8fr_.6fr] sm:items-center">
                        <div><strong class="block text-sm"><?= h($order['order_number']) ?></strong><span
                                class="mt-1 block text-xs text-zinc-500"><?= (int) $order['item_count'] ?>
                                item<?= $order['item_count'] == 1 ? '' : 's' ?></span></div>
                        <span class="text-sm text-zinc-600"><?= date('d M Y', strtotime($order['created_at'])) ?></span>
                        <span><span
                                class="badge <?= $order['order_status'] === 'delivered' ? 'bg-green-100 text-green-700' : ($order['order_status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') ?>"><?= h(ucfirst($order['order_status'])) ?></span></span>
                        <strong class="text-sm"><?= money($order['total']) ?></strong>
                    </div>
                    <div class="mt-5 divide-y divide-zinc-100 border-y border-zinc-200">
                        <?php foreach ($orderItemsByOrder[(int) $order['id']] ?? [] as $item): ?>
                            <?php
                            $reviewStatus = $reviewStatusByProduct[(int) $item['product_id']] ?? null;
                            $canReview = $order['order_status'] === 'delivered'
                                && $order['payment_status'] === 'paid'
                                && !empty($item['product_slug']);
                            ?>
                            <div class="py-3">
                                <div class="flex gap-3 sm:items-center">
                                    <img class="h-16 w-16 flex-none rounded-lg bg-zinc-100 object-cover"
                                        src="<?= h(display_image_url($item['image_url'] ?? null)) ?>"
                                        alt="<?= h($item['product_name']) ?>">
                                    <div class="min-w-0 flex-1">
                                        <?php if ($item['product_slug']): ?><a
                                                class="block truncate text-sm font-bold hover:underline"
                                                href="<?= h(url('product.php?slug=' . rawurlencode($item['product_slug']))) ?>"><?= h($item['product_name']) ?></a><?php else: ?><strong
                                                class="block truncate text-sm"><?= h($item['product_name']) ?></strong><?php endif; ?>
                                        <p class="mt-1 text-xs text-zinc-500"><?= h($item['variant_name'] ?: $item['sku']) ?> ·
                                            <?= h($item['lens_type'] ?: 'Standard') ?> · Qty <?= (int) $item['quantity'] ?></p>
                                    </div>
                                    <strong class="whitespace-nowrap text-sm"><?= money($item['line_total']) ?></strong>
                                </div>
                                <?php if ($reviewStatus): ?>
                                    <p class="ml-[76px] mt-3 text-xs text-zinc-500">Review submitted · <strong
                                            class="text-zinc-700"><?= h(ucfirst($reviewStatus)) ?></strong></p>
                                <?php elseif ($canReview): ?>
                                    <details class="ml-[76px] mt-3">
                                        <summary class="cursor-pointer text-xs font-bold underline underline-offset-4">Write a review
                                        </summary>
                                        <form class="mt-4 max-w-xl space-y-4 rounded-xl bg-mist p-4"
                                            action="<?= h(url('product.php?slug=' . rawurlencode($item['product_slug']))) ?>"
                                            method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="submit_review">
                                            <input type="hidden" name="return_to" value="orders.php">
                                            <fieldset>
                                                <legend class="label">Your rating</legend>
                                                <div class="review-rating">
                                                    <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                                                        <?php $ratingId = 'order-item-' . (int) $item['id'] . '-rating-' . $rating; ?>
                                                        <input id="<?= $ratingId ?>" name="rating" <?= $rating === 5 ? 'required' : '' ?>
                                                            type="radio" value="<?= $rating ?>">
                                                        <label for="<?= $ratingId ?>"><span class="sr-only"><?= $rating ?>
                                                                star<?= $rating === 1 ? '' : 's' ?></span></label>
                                                    <?php endfor; ?>
                                                </div>
                                            </fieldset>
                                            <div><label class="label">Review title</label><input class="input" maxlength="180"
                                                    name="title" placeholder="Optional headline"></div>
                                            <div><label class="label">Your review</label><textarea class="input min-h-28"
                                                    maxlength="2000" name="body" required
                                                    placeholder="Tell us about your frame and fit..."></textarea></div>
                                            <button class="button button-primary" type="submit">Submit for approval</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($order['order_status'] === 'cancelled'): ?>
                        <p class="mt-4 text-xs text-zinc-500">Cancellation reason: <strong
                                class="text-zinc-800"><?= h($order['cancellation_reason'] ?: 'Cancelled') ?></strong></p>
                    <?php elseif (customer_can_cancel_order((string) $order['order_status'])): ?>
                        <form class="mt-4" method="post"
                            onsubmit="return confirm('Cancel this order? This cannot be undone online.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel_order">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <button class="button border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"
                                type="submit">Cancel order</button>
                        </form>
                    <?php elseif (in_array($order['order_status'], ['shipped', 'delivered'], true)): ?>
                        <p class="mt-4 text-xs text-zinc-500">Online cancellation is unavailable after an order has shipped. Please
                            contact support if you need help.</p>
                    <?php endif; ?>
                    <?php if ($order['return_status']): ?>
                        <p class="mt-4 text-xs text-zinc-500">Return request: <strong
                                class="text-zinc-800"><?= h(ucfirst($order['return_status'])) ?></strong></p>
                    <?php elseif ($order['order_status'] === 'delivered'): ?>
                        <details class="mt-4">
                            <summary class="cursor-pointer text-xs font-bold underline underline-offset-4">Request a return
                            </summary>
                            <form class="mt-4 grid gap-3 sm:grid-cols-[180px_1fr_auto]" method="post"><?= csrf_field() ?><input
                                    type="hidden" name="action" value="request_return"><input type="hidden" name="order_id"
                                    value="<?= (int) $order['id'] ?>"><select class="input" name="reason" required>
                                    <option value="">Reason</option>
                                    <option>Wrong fit</option>
                                    <option>Damaged item</option>
                                    <option>Different from expected</option>
                                    <option>Other</option>
                                </select><input class="input" name="details" maxlength="1000" placeholder="Optional details"><button
                                    class="button button-secondary" type="submit">Submit request</button></form>
                        </details>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>