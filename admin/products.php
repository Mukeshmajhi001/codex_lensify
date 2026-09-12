<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_product') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare('UPDATE products SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    db()->prepare('INSERT INTO admin_logs (admin_id, event, ip_address) VALUES (?, ?, ?)')->execute([current_user()['id'], 'Toggled product #' . $id, $_SERVER['REMOTE_ADDR'] ?? null]);
    flash('success', 'Product visibility updated.');
    redirect('admin/products.php');
}
$query = trim((string) ($_GET['q'] ?? ''));
$catalogue = [];
if (db_available()) {
    $sql = 'SELECT p.*, c.name category, b.name brand, (SELECT image_url FROM product_images WHERE product_id=p.id ORDER BY sort_order,id LIMIT 1) image_url FROM products p JOIN categories c ON c.id=p.category_id JOIN brands b ON b.id=p.brand_id';
    $params = [];
    if ($query) {
        $sql .= ' WHERE p.name LIKE ? OR p.sku LIKE ?';
        $params = ['%' . $query . '%', '%' . $query . '%'];
    }
    $sql .= ' ORDER BY p.created_at DESC';
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $catalogue = $statement->fetchAll();
}
$adminPage = 'products';
$pageTitle = 'Products';
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="label">Catalogue</p>
        <h1 class="text-3xl font-bold tracking-[-.05em]">Products</h1>
        <p class="mt-2 text-sm text-zinc-500">Manage frames, inventory and store visibility.</p>
    </div><a class="button button-primary" href="<?= h(url('admin/product-form.php')) ?>"><span
            class="material-symbols-outlined text-base">add</span>Add product</a>
</div>
<form class="mt-8 flex max-w-md gap-2" method="get"><input class="input py-2.5" name="q" value="<?= h($query) ?>"
        placeholder="Search name or SKU"><button class="button button-secondary py-2.5" type="submit">Search</button>
</form>
<section class="mt-5 overflow-hidden rounded-2xl border border-zinc-300 bg-white">
    <div class="overflow-x-auto">
        <table class="min-w-[850px] w-full text-left text-sm">
            <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                <tr>
                    <th class="px-6 py-4">Product</th>
                    <th class="px-6 py-4">Category</th>
                    <th class="px-6 py-4">Price</th>
                    <th class="px-6 py-4">Stock</th>
                    <th class="px-6 py-4">Visibility</th>
                    <th class="px-6 py-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200"><?php foreach ($catalogue as $product): ?><tr>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3"><img class="h-11 w-11 rounded-lg object-cover"
                                    src="<?= h(display_image_url($product['image_url'] ?? null, 'https://placehold.co/100x100?text=Lensify')) ?>"
                                    alt="">
                                <div><strong class="block"><?= h($product['name']) ?></strong><span
                                        class="text-xs text-zinc-500"><?= h($product['sku']) ?> ·
                                        <?= h($product['brand']) ?></span></div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-zinc-600"><?= h($product['category']) ?></td>
                        <td class="px-6 py-4 font-semibold"><?= money($product['price']) ?></td>
                        <td class="px-6 py-4"><span
                                class="<?= $product['stock_quantity'] <= 5 ? 'text-amber-700' : 'text-zinc-700' ?> font-semibold"><?= (int) $product['stock_quantity'] ?>
                                units</span></td>
                        <td class="px-6 py-4"><span
                                class="badge <?= $product['is_active'] ? 'bg-green-100 text-green-700' : 'bg-zinc-200 text-zinc-600' ?>"><?= $product['is_active'] ? 'Live' : 'Hidden' ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex gap-3"><a class="font-semibold underline"
                                    href="<?= h(url('admin/product-form.php?id=' . $product['id'])) ?>">Edit</a>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="action"
                                        value="toggle_product"><input type="hidden" name="id"
                                        value="<?= (int) $product['id'] ?>"><button class="font-semibold underline"
                                        type="submit"><?= $product['is_active'] ? 'Hide' : 'Show' ?></button></form>
                            </div>
                        </td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div><?php if (!$catalogue): ?><div class="px-6 py-12 text-center text-sm text-zinc-500">No products found. Add
            your first frame to begin.</div><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>