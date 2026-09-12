<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust_stock') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $productId = (int)($_POST['product_id'] ?? 0);
    $change = (int)($_POST['quantity_change'] ?? 0);
    $note = trim((string)($_POST['note'] ?? ''));
    $statement = db()->prepare('SELECT stock_quantity,name FROM products WHERE id=? FOR UPDATE');
    try {
        db()->beginTransaction();
        $statement->execute([$productId]);
        $product = $statement->fetch();
        if (!$product || $change === 0) {
            throw new RuntimeException('Select a product and a non-zero adjustment.');
        }
        $before = (int)$product['stock_quantity'];
        $after = max(0, $before + $change);
        $actual = $after - $before;
        db()->prepare('UPDATE products SET stock_quantity=? WHERE id=?')->execute([$after, $productId]);
        db()->prepare('INSERT INTO stock_history (product_id,admin_id,quantity_before,quantity_change,quantity_after,note) VALUES (?,?,?,?,?,?)')->execute([$productId, current_user()['id'], $before, $actual, $after, $note ?: null]);
        db()->commit();
        log_admin('Adjusted stock for ' . $product['name'] . ' by ' . $actual);
        flash('success', 'Stock adjusted.');
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', $e->getMessage());
    }
    redirect('admin/inventory.php');
}
$products = db()->query('SELECT id,name,sku,stock_quantity FROM products ORDER BY name')->fetchAll();
$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalHistory = (int) db()->query('SELECT COUNT(*) FROM stock_history')->fetchColumn();
$totalPages = max(1, (int) ceil($totalHistory / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$historyStatement = db()->prepare('SELECT sh.*,p.name product_name,p.sku,u.first_name,u.last_name FROM stock_history sh JOIN products p ON p.id=sh.product_id LEFT JOIN users u ON u.id=sh.admin_id ORDER BY sh.created_at DESC LIMIT ? OFFSET ?');
$historyStatement->bindValue(1, $perPage, PDO::PARAM_INT);
$historyStatement->bindValue(2, $offset, PDO::PARAM_INT);
$historyStatement->execute();
$history = $historyStatement->fetchAll();
$adminPage = 'inventory';
$pageTitle = 'Stock history';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Catalogue</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Stock history</h1>
    <p class="mt-2 text-sm text-zinc-500">Keep a clear audit trail for every inventory adjustment.</p>
</div>
<div class="mt-8 grid gap-6 xl:grid-cols-[1fr_360px]">
    <section class="overflow-hidden rounded-2xl border border-zinc-300 bg-white">
        <div class="border-b border-zinc-200 px-6 py-5">
            <h2 class="font-bold">Recent adjustments</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[740px] w-full text-left text-sm">
                <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                    <tr>
                        <th class="px-6 py-4">Product</th>
                        <th class="px-6 py-4">Change</th>
                        <th class="px-6 py-4">Stock</th>
                        <th class="px-6 py-4">Note</th>
                        <th class="px-6 py-4">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200"><?php foreach ($history as $row): ?><tr>
                        <td class="px-6 py-4"><strong><?= h($row['product_name']) ?></strong><span
                                class="ml-2 font-mono text-xs text-zinc-500"><?= h($row['sku']) ?></span></td>
                        <td
                            class="px-6 py-4 font-bold <?= $row['quantity_change'] >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                            <?= $row['quantity_change'] >= 0 ? '+' : '' ?><?= $row['quantity_change'] ?></td>
                        <td class="px-6 py-4"><?= $row['quantity_before'] ?> →
                            <strong><?= $row['quantity_after'] ?></strong>
                        </td>
                        <td class="px-6 py-4 text-zinc-600"><?= h($row['note'] ?: '—') ?></td>
                        <td class="px-6 py-4 text-xs text-zinc-500">
                            <?= date('d M, H:i', strtotime($row['created_at'])) ?>
                        </td>
                    </tr><?php endforeach; ?></tbody>
            </table>
        </div><?php if (!$history): ?><p class="px-6 py-14 text-center text-sm text-zinc-500">Stock changes will appear
            here.</p><?php endif; ?><?php if ($totalHistory > $perPage): ?><div
            class="flex flex-col gap-3 border-t border-zinc-200 px-6 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
            <span class="text-zinc-500">Page <?= $page ?> of <?= $totalPages ?> · <?= $totalHistory ?>
                adjustments</span>
            <div class="flex gap-2"><?php if ($page > 1): ?><a class="button button-secondary px-3 py-2"
                    href="<?= h(url('admin/inventory.php?page=' . ($page - 1))) ?>">Previous</a><?php endif; ?><?php if ($page < $totalPages): ?><a
                    class="button button-secondary px-3 py-2"
                    href="<?= h(url('admin/inventory.php?page=' . ($page + 1))) ?>">Next</a><?php endif; ?></div>
        </div><?php endif; ?>
    </section>
    <aside class="h-fit rounded-2xl border border-zinc-300 bg-white p-6">
        <h2 class="font-bold">Adjust stock</h2>
        <form class="mt-5 space-y-4" method="post"><?= csrf_field() ?><input type="hidden" name="action"
                value="adjust_stock">
            <div><label class="label">Product</label><select class="input" name="product_id" required>
                    <option value="">Choose product</option><?php foreach ($products as $product): ?><option
                        value="<?= $product['id'] ?>"><?= h($product['name']) ?> (<?= $product['stock_quantity'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="label">Quantity change</label><input class="input" name="quantity_change" required
                    placeholder="e.g. 12 or -2" type="number"></div>
            <div><label class="label">Note</label><textarea class="input min-h-20" name="note"
                    placeholder="New supplier shipment"></textarea></div><button class="button button-primary w-full"
                type="submit">Update stock</button>
        </form>
    </aside>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>