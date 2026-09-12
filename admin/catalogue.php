<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$tab = ($_GET['tab'] ?? 'categories') === 'brands' ? 'brands' : 'categories';
$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $action = $_POST['action'] ?? '';
    $entity = $action === 'save_brand' || $action === 'delete_brand' ? 'brand' : 'category';
    $redirectTab = $entity === 'brand' ? 'brands' : 'categories';

    if (str_starts_with($action, 'save_')) {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? '')) ?: slugify($name);
        $description = trim((string) ($_POST['description'] ?? ''));
        if (!$name || !$slug) {
            flash('error', 'Name is required.');
        } else {
            try {
                $saved = true;
                if ($entity === 'category') {
                    $icon = trim((string) ($_POST['icon'] ?? 'eyeglasses')) ?: 'eyeglasses';
                    if ($id) {
                        db()->prepare('UPDATE categories SET name=?, slug=?, description=?, icon=? WHERE id=?')->execute([$name, $slug, $description ?: null, $icon, $id]);
                    } else {
                        db()->prepare('INSERT INTO categories (name,slug,description,icon) VALUES (?,?,?,?)')->execute([$name, $slug, $description ?: null, $icon]);
                    }
                } else {
                    $upload = upload_image('brand_logo', 'brands', 4194304, true);
                    if ($upload['error']) {
                        flash('error', $upload['error']);
                        $saved = false;
                    } else {
                        $logo = $upload['path'];
                        if ($id) {
                            if (!$logo) {
                                $existing = db()->prepare('SELECT logo_url FROM brands WHERE id=?');
                                $existing->execute([$id]);
                                $logo = $existing->fetchColumn() ?: null;
                            }
                            db()->prepare('UPDATE brands SET name=?, slug=?, description=?, logo_url=? WHERE id=?')->execute([$name, $slug, $description ?: null, $logo ?: null, $id]);
                        } else {
                            db()->prepare('INSERT INTO brands (name,slug,description,logo_url) VALUES (?,?,?,?)')->execute([$name, $slug, $description ?: null, $logo ?: null]);
                        }
                    }
                }
                if ($saved) {
                    log_admin(($id ? 'Updated ' : 'Created ') . $entity . ': ' . $name);
                    flash('success', ucfirst($entity) . ' saved.');
                    redirect('admin/catalogue.php?tab=' . $redirectTab);
                }
            } catch (PDOException) {
                flash('error', 'Name and URL slug must be unique.');
            }
        }
    }

    if (str_starts_with($action, 'delete_')) {
        $id = (int) ($_POST['id'] ?? 0);
        $table = $entity === 'brand' ? 'brands' : 'categories';
        $foreign = $entity === 'brand' ? 'brand_id' : 'category_id';
        $check = db()->prepare("SELECT COUNT(*) FROM products WHERE {$foreign} = ?");
        $check->execute([$id]);
        if ((int) $check->fetchColumn() > 0) {
            flash('error', 'This ' . $entity . ' is assigned to products and cannot be deleted yet.');
        } else {
            $label = db()->prepare("SELECT name FROM {$table} WHERE id = ?");
            $label->execute([$id]);
            $name = $label->fetchColumn() ?: ('#' . $id);
            db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
            log_admin('Deleted ' . $entity . ': ' . $name);
            flash('success', ucfirst($entity) . ' deleted.');
        }
        redirect('admin/catalogue.php?tab=' . $redirectTab);
    }
}

$categories = db()->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.name')->fetchAll();
$brands = db()->query('SELECT b.*, COUNT(p.id) AS product_count FROM brands b LEFT JOIN products p ON p.brand_id=b.id GROUP BY b.id ORDER BY b.name')->fetchAll();
$edit = null;
if ($editId) {
    $statement = db()->prepare('SELECT * FROM ' . ($tab === 'brands' ? 'brands' : 'categories') . ' WHERE id=?');
    $statement->execute([$editId]);
    $edit = $statement->fetch() ?: null;
}
$adminPage = 'catalogue';
$pageTitle = 'Categories & brands';
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="label">Catalogue structure</p>
        <h1 class="text-3xl font-bold tracking-[-.05em]">Categories & brands</h1>
        <p class="mt-2 text-sm text-zinc-500">Organise every frame so customers can browse precisely.</p>
    </div>
    <div class="flex rounded-lg bg-zinc-100 p-1 text-sm font-semibold"><a
            class="rounded-md px-4 py-2 <?= $tab === 'categories' ? 'bg-white shadow-sm' : 'text-zinc-500' ?>"
            href="<?= h(url('admin/catalogue.php?tab=categories')) ?>">Categories</a><a
            class="rounded-md px-4 py-2 <?= $tab === 'brands' ? 'bg-white shadow-sm' : 'text-zinc-500' ?>"
            href="<?= h(url('admin/catalogue.php?tab=brands')) ?>">Brands</a></div>
</div>
<div class="mt-8 grid gap-6 xl:grid-cols-[1fr_360px]">
    <section class="overflow-hidden rounded-2xl border border-zinc-300 bg-white">
        <div class="border-b border-zinc-200 px-6 py-5">
            <h2 class="font-bold"><?= $tab === 'brands' ? 'All brands' : 'All categories' ?></h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[680px] w-full text-left text-sm">
                <thead class="bg-zinc-50 text-[11px] uppercase tracking-[.1em] text-zinc-500">
                    <tr>
                        <th class="px-6 py-4"><?= $tab === 'brands' ? 'Brand' : 'Category' ?></th>
                        <th class="px-6 py-4">Slug</th>
                        <th class="px-6 py-4">Products</th>
                        <th class="px-6 py-4"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    <?php foreach ($tab === 'brands' ? $brands : $categories as $item): ?><tr>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <?php if ($tab === 'brands' && !empty($item['logo_url'])): ?><img
                                            class="h-9 w-9 rounded-lg object-cover" src="<?= h(preg_match('#^https?://#i', $item['logo_url']) ? $item['logo_url'] : url($item['logo_url'])) ?>"
                                            alt=""><?php else: ?><span
                                            class="grid h-9 w-9 place-items-center rounded-lg bg-zinc-100"><span
                                                class="material-symbols-outlined text-lg"><?= h($tab === 'brands' ? 'branding_watermark' : ($item['icon'] ?: 'category')) ?></span></span><?php endif; ?>
                                    <div><strong class="block"><?= h($item['name']) ?></strong><span
                                            class="mt-1 block max-w-xs truncate text-xs text-zinc-500"><?= h($item['description'] ?: '—') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-zinc-500"><?= h($item['slug']) ?></td>
                            <td class="px-6 py-4 font-semibold"><?= (int) $item['product_count'] ?></td>
                            <td class="px-6 py-4">
                                <div class="flex gap-3"><a class="font-semibold underline"
                                        href="<?= h(url('admin/catalogue.php?tab=' . $tab . '&edit=' . $item['id'])) ?>">Edit</a>
                                    <form method="post"
                                        onsubmit="return confirm('Delete this <?= $tab === 'brands' ? 'brand' : 'category' ?>?')">
                                        <?= csrf_field() ?><input type="hidden" name="action"
                                            value="delete_<?= $tab === 'brands' ? 'brand' : 'category' ?>"><input
                                            type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button
                                            class="font-semibold text-red-700 underline" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
    </section>
    <aside class="h-fit rounded-2xl border border-zinc-300 bg-white p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold"><?= $edit ? 'Edit' : 'Add new' ?> <?= $tab === 'brands' ? 'brand' : 'category' ?></h2>
            <?php if ($edit): ?><a class="text-xs font-bold underline"
                    href="<?= h(url('admin/catalogue.php?tab=' . $tab)) ?>">Cancel</a><?php endif; ?>
        </div>
        <form class="mt-5 space-y-4" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action"
                value="save_<?= $tab === 'brands' ? 'brand' : 'category' ?>"><input type="hidden" name="id"
                value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div><label class="label">Name *</label><input class="input" name="name" required
                    value="<?= h($edit['name'] ?? '') ?>"></div>
            <div><label class="label">URL slug</label><input class="input" name="slug"
                    value="<?= h($edit['slug'] ?? '') ?>" placeholder="Generated from name"></div>
            <div><label class="label">Description</label><textarea class="input min-h-24" maxlength="255"
                    name="description"><?= h($edit['description'] ?? '') ?></textarea></div>
            <?php if ($tab === 'brands'): ?><div><label class="label">Logo image upload</label><input
                        class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-black file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                        accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" type="file" name="brand_logo">
                </div><?php else: ?><div><label class="label">Material icon name</label><input class="input" name="icon"
                        value="<?= h($edit['icon'] ?? 'eyeglasses') ?>" placeholder="eyeglasses"></div>
            <?php endif; ?><button class="button button-primary w-full"
                type="submit"><?= $edit ? 'Save changes' : 'Add ' . ($tab === 'brands' ? 'brand' : 'category') ?></button>
        </form>
    </aside>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>
