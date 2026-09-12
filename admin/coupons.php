<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$editId = (int)($_GET['edit'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'save_coupon') {
        $id = (int)($_POST['id'] ?? 0);
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $type = $_POST['type'] === 'fixed' ? 'fixed' : 'percent';
        $value = (float)($_POST['value'] ?? 0);
        $minimum = (float)($_POST['minimum_order'] ?? 0);
        $limit = trim((string)($_POST['usage_limit'] ?? ''));
        $starts = trim((string)($_POST['starts_at'] ?? ''));
        $expires = trim((string)($_POST['expires_at'] ?? ''));
        if (!$code || $value <= 0) {
            flash('error', 'Coupon code and a positive value are required.');
        } else {
            try {
                if ($id) {
                    db()->prepare('UPDATE coupons SET code=?,type=?,value=?,minimum_order=?,starts_at=?,expires_at=?,usage_limit=?,is_active=? WHERE id=?')->execute([$code, $type, $value, $minimum, $starts ?: null, $expires ?: null, $limit !== '' ? (int)$limit : null, isset($_POST['is_active']) ? 1 : 0, $id]);
                } else {
                    db()->prepare('INSERT INTO coupons (code,type,value,minimum_order,starts_at,expires_at,usage_limit,is_active) VALUES (?,?,?,?,?,?,?,?)')->execute([$code, $type, $value, $minimum, $starts ?: null, $expires ?: null, $limit !== '' ? (int)$limit : null, isset($_POST['is_active']) ? 1 : 0]);
                }
                log_admin(($id ? 'Updated' : 'Created') . ' coupon: ' . $code);
                flash('success', 'Coupon saved.');
                redirect('admin/coupons.php');
            } catch (PDOException) {
                flash('error', 'Coupon code must be unique.');
            }
        }
    }
    if ($action === 'delete_coupon') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM coupons WHERE id=?')->execute([$id]);
        log_admin('Deleted coupon #' . $id);
        flash('success', 'Coupon deleted.');
        redirect('admin/coupons.php');
    }
}
$coupons = db()->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();
$edit = null;
if ($editId) {
    $statement = db()->prepare('SELECT * FROM coupons WHERE id=?');
    $statement->execute([$editId]);
    $edit = $statement->fetch() ?: null;
}
$adminPage = 'coupons';
$pageTitle = 'Coupons';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Growth</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Coupons</h1>
    <p class="mt-2 text-sm text-zinc-500">Create discounts with deliberate limits and expiry dates.</p>
</div>
<div class="mt-8 grid gap-6 xl:grid-cols-[1fr_360px]">
    <section class="overflow-hidden rounded-2xl border border-zinc-300 bg-white">
        <div class="border-b border-zinc-200 px-6 py-5">
            <h2 class="font-bold">All coupons</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[720px] w-full text-left text-sm">
                <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                    <tr>
                        <th class="px-6 py-4">Code</th>
                        <th class="px-6 py-4">Offer</th>
                        <th class="px-6 py-4">Usage</th>
                        <th class="px-6 py-4">Expires</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200"><?php foreach ($coupons as $coupon): ?><tr>
                            <td class="px-6 py-4"><strong class="font-mono"><?= h($coupon['code']) ?></strong><span
                                    class="ml-2 badge <?= $coupon['is_active'] ? 'bg-green-100 text-green-700' : 'bg-zinc-200 text-zinc-600' ?>"><?= $coupon['is_active'] ? 'Active' : 'Off' ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <?= $coupon['type'] === 'percent' ? $coupon['value'] . '% off' : money($coupon['value']) . ' off' ?><span
                                    class="ml-1 text-xs text-zinc-500">min <?= money($coupon['minimum_order']) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <?= $coupon['used_count'] ?><?= $coupon['usage_limit'] !== null ? ' / ' . $coupon['usage_limit'] : '' ?>
                            </td>
                            <td class="px-6 py-4 text-zinc-600">
                                <?= $coupon['expires_at'] ? date('d M Y', strtotime($coupon['expires_at'])) : 'No expiry' ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-3"><a class="font-semibold underline"
                                        href="<?= h(url('admin/coupons.php?edit=' . $coupon['id'])) ?>">Edit</a>
                                    <form method="post" onsubmit="return confirm('Delete this coupon?')">
                                        <?= csrf_field() ?><input type="hidden" name="action" value="delete_coupon"><input
                                            type="hidden" name="id" value="<?= $coupon['id'] ?>"><button
                                            class="font-semibold text-red-700 underline" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section>
    <aside class="h-fit rounded-2xl border border-zinc-300 bg-white p-6">
        <div class="flex justify-between">
            <h2 class="font-bold"><?= $edit ? 'Edit' : 'Create' ?> coupon</h2><?php if ($edit): ?><a
                    class="text-xs font-bold underline" href="<?= h(url('admin/coupons.php')) ?>">Cancel</a><?php endif; ?>
        </div>
        <form class="mt-5 space-y-4" method="post"><?= csrf_field() ?><input type="hidden" name="action"
                value="save_coupon"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div><label class="label">Coupon code *</label><input class="input font-mono uppercase" name="code" required
                    value="<?= h($edit['code'] ?? '') ?>" placeholder="WELCOME10"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Type</label><select class="input" name="type">
                        <option value="percent" <?= ($edit['type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent
                        </option>
                        <option value="fixed" <?= ($edit['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed NPR
                        </option>
                    </select></div>
                <div><label class="label">Value *</label><input class="input" min="1" name="value" required step="1"
                        type="number" value="<?= h((string)($edit['value'] ?? '')) ?>"></div>
            </div>
            <div><label class="label">Minimum order</label><input class="input" min="0" name="minimum_order" step="1"
                    type="number" value="<?= h((string)($edit['minimum_order'] ?? 0)) ?>"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Starts</label><input class="input" name="starts_at" type="datetime-local"
                        value="<?= h(!empty($edit['starts_at']) ? date('Y-m-d\TH:i', strtotime($edit['starts_at'])) : '') ?>">
                </div>
                <div><label class="label">Expires</label><input class="input" name="expires_at" type="datetime-local"
                        value="<?= h(!empty($edit['expires_at']) ? date('Y-m-d\TH:i', strtotime($edit['expires_at'])) : '') ?>">
                </div>
            </div>
            <div><label class="label">Usage limit</label><input class="input" min="1" name="usage_limit" type="number"
                    value="<?= h((string)($edit['usage_limit'] ?? '')) ?>" placeholder="Unlimited"></div><label
                class="flex gap-2 text-sm"><input class="rounded border-zinc-300 text-black focus:ring-black"
                    name="is_active" type="checkbox" <?= !isset($edit) || $edit['is_active'] ? 'checked' : '' ?>>Active
                now</label><button class="button button-primary w-full" type="submit">Save coupon</button>
        </form>
    </aside>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>