<?php
require_once __DIR__ . '/app/bootstrap.php';
$number = (string) ($_GET['number'] ?? $_SESSION['last_order_number'] ?? '');
$order = null;
if ($number && db_available()) {
    $statement = db()->prepare('SELECT * FROM orders WHERE order_number = ?');
    $statement->execute([$number]);
    $order = $statement->fetch() ?: null;
}
$pageTitle = 'Order confirmed';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-2xl px-5 py-20 text-center">
    <div class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-green-100 text-green-700"><span
            class="material-symbols-outlined text-4xl">check_circle</span></div>
    <p class="mt-7 text-xs font-bold uppercase tracking-[.16em] text-green-700">Order confirmed</p>
    <h1 class="mt-3 text-4xl font-bold tracking-[-.05em]">You’re in the frame.</h1>
    <p class="mx-auto mt-4 max-w-md text-sm leading-7 text-zinc-600">Thank you for your order. We’ll send a confirmation
        and delivery updates to your email.</p><?php if ($order): ?><div
            class="mx-auto mt-8 grid max-w-md grid-cols-2 rounded-xl border border-zinc-200 bg-white text-left">
            <div class="border-r border-zinc-200 p-5"><span class="label">Order number</span><strong
                    class="text-sm"><?= h($order['order_number']) ?></strong></div>
            <div class="p-5"><span class="label">Order total</span><strong
                    class="text-sm"><?= money($order['total']) ?></strong></div>
        </div><?php endif; ?><div class="mt-9 flex justify-center gap-3"><a class="button button-primary"
            href="<?= h(url('shop.php')) ?>">Continue shopping</a><?php if (current_user()): ?><a
                class="button button-secondary" href="<?= h(url('orders.php')) ?>">View my orders</a><?php endif; ?></div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>