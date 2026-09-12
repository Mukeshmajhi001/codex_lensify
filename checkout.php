<?php
require_once __DIR__ . '/app/bootstrap.php';
handle_store_actions();
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place_order') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }

    $items = cart_details();
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
    $customerMessage = mb_substr(trim((string) ($_POST['customer_message'] ?? '')), 0, 1000);
    $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cod', 'upi', 'card'], true) ? $_POST['payment_method'] : 'cod';

    if (!$items) {
        flash('error', 'Your bag is empty.');
        redirect('cart.php');
    }
    if ($paymentMethod !== 'cod') {
        flash('error', 'UPI, wallets and cards are not available yet. Please choose Cash on delivery.');
    } elseif (!$firstName || !$lastName || !$email || !$phone || !$address || !$city || !$postalCode) {
        flash('error', 'Please complete all delivery details.');
    } elseif (!db_available()) {
        flash('error', 'Database is not ready. Import database/final.sql, then place your order.');
    } else {
        $coupon = active_coupon();
        $subtotal = cart_subtotal();
        $discount = cart_discount();
        $shipping = cart_shipping();
        $total = cart_total();
        $orderNumber = 'LNS-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $deliveryAddress = implode("\n", [trim($firstName . ' ' . $lastName), $address, trim($city . ', ' . $postalCode), 'Nepal', 'Phone: ' . $phone]);
        try {
            db()->beginTransaction();
            $statement = db()->prepare('INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, delivery_address, customer_message, subtotal, shipping_amount, discount_amount, total, payment_method, payment_status, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$orderNumber, current_user()['id'] ?? null, trim($firstName . ' ' . $lastName), $email, $phone, $deliveryAddress, $customerMessage ?: null, $subtotal, $shipping, $discount, $total, $paymentMethod, $paymentMethod === 'cod' ? 'pending' : 'paid', 'confirmed']);
            $orderId = (int) db()->lastInsertId();
            $itemStatement = db()->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, product_image_url, variant_name, sku, variant_sku, lens_type, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                // The cart lives in the session, so its stock snapshot may be
                // stale by checkout time. Lock and re-check both stock pools
                // inside the order transaction before creating any order rows.
                $productStockStatement = db()->prepare('SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE');
                $productStockStatement->execute([(int) $item['id']]);
                $productStock = $productStockStatement->fetchColumn();
                if ($productStock === false || (int) $productStock < (int) $item['quantity']) {
                    throw new DomainException('One or more frames in your bag no longer have enough stock. Please update your bag and try again.');
                }
                if (!empty($item['variant_id'])) {
                    $variantStockStatement = db()->prepare('SELECT stock_quantity FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1 FOR UPDATE');
                    $variantStockStatement->execute([(int) $item['variant_id'], (int) $item['id']]);
                    $variantStock = $variantStockStatement->fetchColumn();
                    if ($variantStock === false || (int) $variantStock < (int) $item['quantity']) {
                        throw new DomainException('One or more frame options in your bag no longer have enough stock. Please update your bag and try again.');
                    }
                }
                $itemStatement->execute([$orderId, $item['id'], $item['variant_id'], $item['name'], $item['image_url'] ?? null, $item['variant_name'], $item['sku'] ?? ('LNS-' . $item['id']), $item['variant_sku'], $item['lens_type'], $item['quantity'], $item['unit_price'], $item['line_total']]);
                db()->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?')->execute([$item['quantity'], $item['id']]);
                if ($item['variant_id']) {
                    db()->prepare('UPDATE product_variants SET stock_quantity = stock_quantity - ? WHERE id = ?')->execute([$item['quantity'], $item['variant_id']]);
                }
            }
            if ($coupon) {
                db()->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?')->execute([$coupon['id']]);
            }
            db()->commit();
            $customerUserId = (int) (current_user()['id'] ?? 0);
            if ($customerUserId > 0) {
                create_notification($customerUserId, 'Order confirmed', "Your order {$orderNumber} has been placed. We will notify you as it moves forward.", 'orders.php');
            }
            notify_admins('New order received', "{$orderNumber} was placed for " . money($total) . '.', 'admin/order.php?id=' . $orderId);
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon_code']);
            $_SESSION['last_order_number'] = $orderNumber;
            redirect('order-confirmed.php?number=' . rawurlencode($orderNumber));
        } catch (DomainException $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', $exception->getMessage());
        } catch (Throwable) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('error', 'We could not place the order. Please try again.');
        }
    }
}

$items = cart_details();
if (!$items) {
    flash('error', 'Your bag is empty.');
    redirect('cart.php');
}
$user = current_user();
$coupon = active_coupon();
$pageTitle = 'Checkout';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1180px] px-5 py-10 lg:px-10">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <p class="label">Secure checkout</p>
            <h1 class="text-4xl font-bold tracking-[-.05em]">Delivery & payment</h1>
        </div><span class="hidden items-center gap-2 text-xs text-zinc-500 sm:flex"><span
                class="material-symbols-outlined text-base text-green-700">lock</span>Encrypted checkout</span>
    </div>
    <div class="grid gap-10 lg:grid-cols-[1fr_340px]">
        <form method="post" class="space-y-8" data-checkout-form>
            <?= csrf_field() ?><input type="hidden" name="action" value="place_order">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7">
                <h2 class="text-xl font-bold">Contact information</h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label class="label">First name</label><input class="input" name="first_name" required
                            value="<?= h($user['first_name'] ?? '') ?>"></div>
                    <div><label class="label">Last name</label><input class="input" name="last_name" required
                            value="<?= h($user['last_name'] ?? '') ?>"></div>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="label">Email address</label><input class="input" name="email" type="email"
                            required value="<?= h($user['email'] ?? '') ?>"></div>
                    <div><label class="label">Phone number</label><input class="input" name="phone" required
                            placeholder="98XXXXXXXX"></div>
                </div>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7">
                <h2 class="text-xl font-bold">Delivery address</h2>
                <div class="mt-5"><label class="label">Address</label><input class="input" name="address" required
                        placeholder="House no., street, area"></div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="label">City</label><input class="input" name="city" required
                            placeholder="Kathmandu"></div>
                    <div><label class="label">Postal code</label><input class="input" name="postal_code" required
                            placeholder="44600"></div>
                </div>
                <p class="mt-4 text-xs text-zinc-500">We currently deliver throughout Nepal.</p>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7">
                <h2 class="text-xl font-bold">Order note <span
                        class="text-sm font-normal text-zinc-500">(optional)</span></h2>
                <p class="mt-2 text-sm text-zinc-500">Add any special instruction for your order.</p>
                <textarea class="input mt-5 min-h-28" name="customer_message" maxlength="1000"
                    placeholder="e.g. Please send the +2 prescription or blue colour."></textarea>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7">
                <h2 class="text-xl font-bold">Payment method</h2>
                <div class="mt-5 grid gap-3"><label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-zinc-300 p-4 has-[:checked]:border-black has-[:checked]:bg-zinc-50"><input
                            class="border-zinc-300 text-black focus:ring-black" type="radio" name="payment_method"
                            value="cod" checked><span class="material-symbols-outlined">payments</span><span><strong
                                class="block text-sm">Cash on delivery</strong><small class="text-zinc-500">Pay when
                                your order arrives.</small></span></label><label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-zinc-300 p-4 has-[:checked]:border-black has-[:checked]:bg-zinc-50"><input
                            class="border-zinc-300 text-black focus:ring-black" type="radio" name="payment_method"
                            value="upi" data-unavailable-payment><span
                            class="material-symbols-outlined">qr_code_2</span><span><strong class="block text-sm">UPI /
                                digital wallet</strong><small class="text-zinc-500">Coming soon — not available for
                                payment yet.</small></span></label><label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-zinc-300 p-4 has-[:checked]:border-black has-[:checked]:bg-zinc-50"><input
                            class="border-zinc-300 text-black focus:ring-black" type="radio" name="payment_method"
                            value="card" data-unavailable-payment><span
                            class="material-symbols-outlined">credit_card</span><span><strong
                                class="block text-sm">Credit or debit card</strong><small class="text-zinc-500">Coming
                                soon — not available for payment yet.</small></span></label></div>
            </section>
            <button class="button button-primary w-full sm:w-auto" type="submit">Place secure order <span
                    class="material-symbols-outlined text-base">arrow_forward</span></button>
        </form>
        <aside class="h-fit rounded-xl bg-mist p-6">
            <h2 class="text-lg font-bold">Order summary</h2>
            <form class="mt-5 flex gap-2" method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="apply_coupon"><input type="hidden"
                    name="return_to" value="checkout.php"><input class="input min-w-0 py-2 text-sm" name="coupon_code"
                    placeholder="Coupon / promo code" aria-label="Coupon code"><button
                    class="button button-secondary shrink-0 px-4" type="submit">Apply</button>
            </form>
            <?php if ($coupon): ?><div
                    class="mt-3 flex items-center justify-between rounded-lg bg-green-100 px-3 py-2 text-xs text-green-800">
                    <span><strong><?= h($coupon['code']) ?></strong> applied</span>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove_coupon"><input
                            type="hidden" name="return_to" value="checkout.php"><button class="font-bold underline"
                            type="submit">Remove</button></form>
                </div><?php endif; ?>
            <div class="mt-5 divide-y divide-zinc-300"><?php foreach ($items as $item): ?><div class="flex gap-3 py-3">
                        <img class="h-14 w-14 rounded-lg object-cover"
                            src="<?= h(display_image_url($item['image_url'] ?? null)) ?>" alt="">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-bold"><?= h($item['name']) ?></p>
                            <p class="mt-1 text-[11px] text-zinc-500">
                                <?= $item['variant_name'] ? h($item['variant_name']) . ' · ' : '' ?><?= h($item['lens_type']) ?>
                                · Qty <?= $item['quantity'] ?></p>
                        </div><span class="text-xs font-bold"><?= money($item['line_total']) ?></span>
                    </div><?php endforeach; ?></div>
            <div class="mt-5 space-y-3 border-t border-zinc-300 pt-5 text-sm">
                <div class="flex justify-between"><span
                        class="text-zinc-600">Subtotal</span><span><?= money(cart_subtotal()) ?></span></div>
                <?php if ($coupon): ?><div class="flex justify-between text-green-700"><span>Coupon
                            <?= h($coupon['code']) ?></span><span>−<?= money(cart_discount()) ?></span></div><?php endif; ?>
                <div class="flex justify-between"><span class="text-zinc-600">Shipping</span><span
                        class="<?= cart_shipping() > 0 ? 'text-zinc-900' : 'text-green-700' ?>"><?= cart_shipping() > 0 ? money(cart_shipping()) : 'Free' ?></span>
                </div>
                <div class="flex justify-between pt-2 text-base font-bold">
                    <span>Total</span><span><?= money(cart_total()) ?></span>
                </div>
            </div><?php if ($coupon): ?><a class="mt-4 block text-center text-xs font-bold underline underline-offset-4"
                    href="<?= h(url('cart.php')) ?>">Change coupon</a><?php endif; ?>
        </aside>
    </div>
</section>
<div class="fixed inset-0 z-[70] hidden items-center justify-center bg-black/45 p-5" data-payment-unavailable-modal
    aria-hidden="true" role="dialog" aria-labelledby="payment-unavailable-title" aria-modal="true">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl"><span
            class="material-symbols-outlined text-4xl text-amber-600">info</span>
        <h2 class="mt-3 text-xl font-bold" id="payment-unavailable-title">Payment option coming soon</h2>
        <p class="mt-3 text-sm leading-6 text-zinc-600">UPI, digital wallets, and credit or debit card payments are not
            available yet. Please use Cash on delivery for now.</p><button class="button button-primary mt-6 w-full"
            data-payment-modal-close type="button">Use cash on delivery</button>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
