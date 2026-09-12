<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$id = (int) ($_GET['id'] ?? 0);
$statement = db()->prepare('SELECT * FROM orders WHERE id = ?');
$statement->execute([$id]);
$order = $statement->fetch();
if (!$order) {
    exit('Invoice not found.');
}
$itemsQuery = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsQuery->execute([$id]);
$items = $itemsQuery->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Invoice <?= h($order['order_number']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print {
                display: none !important
            }

            body {
                background: #fff !important
            }
        }
    </style>
</head>

<body class="bg-zinc-100 p-5 font-sans text-zinc-900">
    <main class="mx-auto max-w-3xl bg-white p-7 shadow-sm sm:p-12">
        <div class="no-print mb-8"><button class="rounded-lg bg-black px-4 py-2 text-sm font-bold text-white"
                onclick="window.print()">Print invoice</button></div>
        <div class="flex justify-between gap-6 border-b border-zinc-200 pb-8">
            <div>
                <h1 class="text-3xl font-extrabold tracking-[-.07em]">Lensify</h1>
                <p class="mt-3 text-sm text-zinc-500">Premium eyewear<br>hello@lensify.test</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold uppercase tracking-[.12em] text-zinc-500">Invoice</p>
                <h2 class="mt-2 text-xl font-bold"><?= h($order['order_number']) ?></h2>
                <p class="mt-2 text-sm text-zinc-500"><?= date('d M Y', strtotime($order['created_at'])) ?></p>
            </div>
        </div>
        <div class="grid gap-8 py-8 sm:grid-cols-2">
            <div>
                <p class="text-xs font-bold uppercase tracking-[.12em] text-zinc-500">Bill to</p>
                <p class="mt-3 text-sm font-bold"><?= h($order['customer_name']) ?></p>
                <p class="mt-1 text-sm text-zinc-500">
                    <?= h($order['customer_email']) ?><br><?= h($order['customer_phone']) ?></p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-[.12em] text-zinc-500">Deliver to</p>
                <p class="mt-3 whitespace-pre-line text-sm text-zinc-600"><?= h($order['delivery_address']) ?></p>
            </div>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="border-y border-zinc-200 text-xs uppercase tracking-[.1em] text-zinc-500">
                <tr>
                    <th class="py-4">Item</th>
                    <th class="py-4 text-center">Qty</th>
                    <th class="py-4 text-right">Total</th>
                </tr>
            </thead>
            <tbody><?php foreach ($items as $item): ?><tr class="border-b border-zinc-100">
                        <td class="py-4"><strong class="block"><?= h($item['product_name']) ?></strong><span
                                class="text-xs text-zinc-500"><?= h($item['variant_name'] ?: '') ?><?= $item['variant_name'] ? ' · ' : '' ?><?= h($item['lens_type'] ?: 'Standard') ?></span>
                        </td>
                        <td class="py-4 text-center"><?= $item['quantity'] ?></td>
                        <td class="py-4 text-right font-semibold"><?= money($item['line_total']) ?></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
        <div class="ml-auto mt-8 max-w-xs space-y-3 text-sm">
            <div class="flex justify-between"><span
                    class="text-zinc-500">Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
            <?php if ((float) $order['discount_amount'] > 0): ?><div class="flex justify-between text-green-700">
                    <span>Discount</span><span>−<?= money($order['discount_amount']) ?></span>
                </div><?php endif; ?><div class="flex justify-between"><span
                    class="text-zinc-500">Shipping</span><span><?= money($order['shipping_amount']) ?></span></div>
            <div class="flex justify-between border-t border-zinc-200 pt-3 text-lg font-bold">
                <span>Total</span><span><?= money($order['total']) ?></span>
            </div>
        </div>
    </main>
</body>

</html>