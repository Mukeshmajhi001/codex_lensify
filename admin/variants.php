<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$selectedProduct = (int) ($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'save_variant') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
        $color = trim((string) ($_POST['color'] ?? ''));
        $adjustment = (float) ($_POST['price_adjustment'] ?? 0);
        $stock = max(0, (int) ($_POST['stock_quantity'] ?? 0));
        if (!$selectedProduct || !$name || !$sku) {
            flash('error', 'Select a product and complete variant name and SKU.');
        } else {
            try {
                if ($id) {
                    db()->prepare('UPDATE product_variants SET name=?, sku=?, color=?, price_adjustment=?, stock_quantity=?, is_active=? WHERE id=?')->execute([$name, $sku, $color ?: null, $adjustment, $stock, isset($_POST['is_active']) ? 1 : 0, $id]);
                } else {
                    db()->prepare('INSERT INTO product_variants (product_id,name,sku,color,price_adjustment,stock_quantity,is_active) VALUES (?,?,?,?,?,?,?)')->execute([$selectedProduct, $name, $sku, $color ?: null, $adjustment, $stock, isset($_POST['is_active']) ? 1 : 0]);
                }
                log_admin(($id ? 'Updated' : 'Created') . ' variant: ' . $name);
                flash('success', 'Variant saved.');
                redirect('admin/variants.php?product_id=' . $selectedProduct);
            } catch (PDOException) {
                flash('error', 'SKU must be unique.');
            }
        }
    }
    if ($action === 'delete_variant') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM product_variants WHERE id=?')->execute([$id]);
        log_admin('Deleted variant #' . $id);
        flash('success', 'Variant deleted.');
        redirect('admin/variants.php?product_id=' . $selectedProduct);
    }
}

$products = db()->query('SELECT id,name,sku FROM products ORDER BY name')->fetchAll();
$variants = [];
$edit = null;
if ($selectedProduct) {
    $statement = db()->prepare('SELECT * FROM product_variants WHERE product_id=? ORDER BY name');
    $statement->execute([$selectedProduct]);
    $variants = $statement->fetchAll();
    if (!empty($_GET['edit'])) {
        $statement = db()->prepare('SELECT * FROM product_variants WHERE id=? AND product_id=?');
        $statement->execute([(int)$_GET['edit'], $selectedProduct]);
        $edit = $statement->fetch() ?: null;
    }
}
$adminPage = 'variants';
$pageTitle = 'Product variants';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Catalogue</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Product variants</h1>
    <p class="mt-2 text-sm text-zinc-500">Create colour, lens or fit variations under each frame.</p>
</div>
<form class="mt-7 max-w-lg" method="get"><label class="label">Choose product</label>
    <div class="flex gap-2"><select class="input" name="product_id" onchange="this.form.submit()">
            <option value="">Select a product</option><?php foreach ($products as $product): ?><option
                    value="<?= $product['id'] ?>" <?= $selectedProduct === $product['id'] ? 'selected' : '' ?>>
                    <?= h($product['name'] . ' · ' . $product['sku']) ?></option><?php endforeach; ?>
        </select><button class="button button-secondary" type="submit">Open</button></div>
</form>
<?php if ($selectedProduct): ?><div class="mt-8 grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="overflow-hidden rounded-2xl border border-zinc-300 bg-white">
            <div class="border-b border-zinc-200 px-6 py-5">
                <h2 class="font-bold">Saved variants</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[650px] w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                        <tr>
                            <th class="px-6 py-4">Variant</th>
                            <th class="px-6 py-4">SKU</th>
                            <th class="px-6 py-4">Adjustment</th>
                            <th class="px-6 py-4">Stock</th>
                            <th class="px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200"><?php foreach ($variants as $variant): ?><tr>
                                <td class="px-6 py-4"><strong class="block"><?= h($variant['name']) ?></strong><span
                                        class="text-xs text-zinc-500"><?= h($variant['color'] ?: '—') ?> ·
                                        <?= $variant['is_active'] ? 'Live' : 'Hidden' ?></span></td>
                                <td class="px-6 py-4 font-mono text-xs"><?= h($variant['sku']) ?></td>
                                <td class="px-6 py-4">
                                    <?= $variant['price_adjustment'] >= 0 ? '+' : '' ?><?= money($variant['price_adjustment']) ?>
                                </td>
                                <td class="px-6 py-4 font-semibold"><?= (int)$variant['stock_quantity'] ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex gap-3"><a class="font-semibold underline"
                                            href="<?= h(url('admin/variants.php?product_id=' . $selectedProduct . '&edit=' . $variant['id'])) ?>">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this variant?')">
                                            <?= csrf_field() ?><input type="hidden" name="action" value="delete_variant"><input
                                                type="hidden" name="product_id" value="<?= $selectedProduct ?>"><input
                                                type="hidden" name="id" value="<?= $variant['id'] ?>"><button
                                                class="font-semibold text-red-700 underline" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div><?php if (!$variants): ?><p class="px-6 py-12 text-center text-sm text-zinc-500">No variants yet. Add one
                    for this product.</p><?php endif; ?>
        </section>
        <aside class="h-fit rounded-2xl border border-zinc-300 bg-white p-6">
            <div class="flex justify-between">
                <h2 class="font-bold"><?= $edit ? 'Edit' : 'Add' ?> variant</h2><?php if ($edit): ?><a
                        class="text-xs font-bold underline"
                        href="<?= h(url('admin/variants.php?product_id=' . $selectedProduct)) ?>">Cancel</a><?php endif; ?>
            </div>
            <form class="mt-5 space-y-4" method="post"><?= csrf_field() ?><input type="hidden" name="action"
                    value="save_variant"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><input
                    type="hidden" name="product_id" value="<?= $selectedProduct ?>">
                <div><label class="label">Variant name *</label><input class="input" name="name" required
                        value="<?= h($edit['name'] ?? '') ?>" placeholder="Blue light lens"></div>
                <div><label class="label">SKU *</label><input class="input" name="sku" required
                        value="<?= h($edit['sku'] ?? '') ?>"></div>
                <div><label class="label">Colour</label><input class="input" name="color"
                        value="<?= h($edit['color'] ?? '') ?>"></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="label">Price +/-</label><input class="input" step="1" name="price_adjustment"
                            type="number" value="<?= h((string)($edit['price_adjustment'] ?? 0)) ?>"></div>
                    <div><label class="label">Stock</label><input class="input" min="0" name="stock_quantity" required
                            type="number" value="<?= h((string)($edit['stock_quantity'] ?? 0)) ?>"></div>
                </div><label class="flex gap-2 text-sm"><input class="rounded border-zinc-300 text-black focus:ring-black"
                        name="is_active" type="checkbox" <?= !isset($edit) || $edit['is_active'] ? 'checked' : '' ?>>Visible
                    to
                    customers</label><button class="button button-primary w-full" type="submit">Save variant</button>
            </form>
        </aside>
    </div><?php else: ?><div
        class="mt-8 rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center text-sm text-zinc-500">Choose a
        product to view and manage its variants.</div><?php endif; ?>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>