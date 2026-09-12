<?php
require_once __DIR__ . '/app/bootstrap.php';
handle_store_actions();
$items = cart_details();
$coupon = active_coupon();
$pageTitle = 'Your Bag';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1180px] px-5 py-10 lg:px-10 lg:py-14">
    <h1 class="text-4xl font-bold tracking-[-.05em]">Your bag <span
            class="text-lg font-normal text-zinc-500">(<?= cart_count() ?>)</span></h1>
    <?php if (!$items): ?>
        <div class="mt-10 rounded-2xl border border-dashed border-zinc-300 px-6 py-20 text-center"><span
                class="material-symbols-outlined text-5xl text-zinc-400">shopping_bag</span>
            <h2 class="mt-4 text-2xl font-bold">Your bag is waiting.</h2>
            <p class="mt-2 text-sm text-zinc-500">Find a frame that feels like you.</p><a class="button button-primary mt-6"
                href="<?= h(url('shop.php')) ?>">Discover frames</a>
        </div>
    <?php else: ?>
        <div class="mt-9 grid gap-10 lg:grid-cols-[1fr_340px]">
            <div class="divide-y divide-zinc-200 border-y border-zinc-200">
                <?php foreach ($items as $item): ?>
                    <article class="flex gap-4 py-5 sm:gap-6"><a
                            class="h-28 w-28 flex-none overflow-hidden rounded-lg bg-zinc-100 sm:h-32 sm:w-36"
                            href="<?= h(url('product.php?slug=' . rawurlencode($item['slug']))) ?>"><img
                                class="h-full w-full object-cover" src="<?= h(display_image_url($item['image_url'] ?? null)) ?>"
                                alt="<?= h($item['name']) ?>"></a>
                        <div class="min-w-0 flex-1">
                            <div class="flex justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-[.12em] text-zinc-500">
                                        <?= h($item['brand']) ?></p><a class="mt-1 block text-base font-bold"
                                        href="<?= h(url('product.php?slug=' . rawurlencode($item['slug']))) ?>"><?= h($item['name']) ?></a>
                                    <p class="mt-1 text-xs text-zinc-500"><?= h($item['variant_name'] ?: $item['color']) ?> ·
                                        <?= h($item['lens_type']) ?></p>
                                </div><strong class="whitespace-nowrap text-sm"><?= money($item['line_total']) ?></strong>
                            </div>
                            <div class="mt-5 flex items-center justify-between">
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action"
                                        value="update_cart"><label class="sr-only"
                                        for="qty-<?= h($item['cart_key']) ?>">Quantity</label><select
                                        class="rounded-lg border-zinc-300 py-1.5 text-sm focus:border-black focus:ring-black"
                                        id="qty-<?= h($item['cart_key']) ?>" name="quantity[<?= h($item['cart_key']) ?>]"
                                        data-quantity-input><?php for ($i = 1; $i <= 10; $i++): ?><option value="<?= $i ?>"
                                                <?= $item['quantity'] === $i ? 'selected' : '' ?>>Qty: <?= $i ?></option>
                                        <?php endfor; ?></select></form>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action"
                                        value="remove_cart_item"><input type="hidden" name="cart_key"
                                        value="<?= h($item['cart_key']) ?>"><button
                                        class="text-xs font-bold underline underline-offset-4" type="submit">Remove</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <aside class="h-fit rounded-xl bg-mist p-6">
                <h2 class="text-xl font-bold">Order summary</h2>
                <form class="mt-5 flex gap-2" method="post"><?= csrf_field() ?><input type="hidden" name="action"
                        value="apply_coupon"><input type="hidden" name="return_to" value="cart.php"><input
                        class="input min-w-0 py-2 text-sm" name="coupon_code" placeholder="Coupon code"
                        aria-label="Coupon code"><button class="button button-secondary shrink-0 px-4"
                        type="submit">Apply</button></form><?php if ($coupon): ?><div
                        class="mt-3 flex items-center justify-between rounded-lg bg-green-100 px-3 py-2 text-xs text-green-800">
                        <span><strong><?= h($coupon['code']) ?></strong> applied</span>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove_coupon"><input
                                type="hidden" name="return_to" value="cart.php"><button class="font-bold underline"
                                type="submit">Remove</button></form>
                    </div><?php endif; ?><div class="mt-6 space-y-3 border-b border-zinc-300 pb-5 text-sm">
                    <div class="flex justify-between"><span
                            class="text-zinc-600">Subtotal</span><span><?= money(cart_subtotal()) ?></span></div>
                    <?php if ($coupon): ?><div class="flex justify-between text-green-700">
                            <span>Discount</span><span>−<?= money(cart_discount()) ?></span>
                        </div><?php endif; ?><div class="flex justify-between"><span class="text-zinc-600">Shipping</span><span
                            class="<?= cart_shipping() > 0 ? 'text-zinc-900' : 'text-green-700' ?>"><?= cart_shipping() > 0 ? money(cart_shipping()) : 'Free' ?></span>
                    </div>
                </div>
                <div class="mt-5 flex justify-between text-lg font-bold">
                    <span>Total</span><span><?= money(cart_total()) ?></span>
                </div><a class="button button-primary mt-6 w-full" href="<?= h(url('checkout.php')) ?>">Secure checkout
                    <span class="material-symbols-outlined text-base">lock</span></a>
                <p class="mt-4 text-center text-[11px] leading-5 text-zinc-500">Taxes included. Shipping is calculated at
                    19.5% after discounts unless your free-shipping threshold applies.</p>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>