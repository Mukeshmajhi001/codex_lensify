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
    if ($action === 'save_banner') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $subtitle = trim((string)($_POST['subtitle'] ?? ''));
        $label = trim((string)($_POST['button_label'] ?? ''));
        $buttonUrl = trim((string)($_POST['button_url'] ?? ''));
        $order = (int)($_POST['sort_order'] ?? 0);
        $imageUrl = trim((string)($_POST['image_url'] ?? ''));
        $upload = upload_image('banner_image', 'banners');
        if ($upload['error']) {
            flash('error', $upload['error']);
        } elseif (!$title) {
            flash('error', 'Banner title is required.');
        } else {
            if ($upload['path']) {
                $imageUrl = $upload['path'];
            }
            try {
                if ($id) {
                    $old = db()->prepare('SELECT image_url FROM banners WHERE id=?');
                    $old->execute([$id]);
                    $imageUrl = $imageUrl ?: ($old->fetchColumn() ?: null);
                    db()->prepare('UPDATE banners SET title=?,subtitle=?,image_url=?,button_label=?,button_url=?,sort_order=?,is_active=? WHERE id=?')->execute([$title, $subtitle ?: null, $imageUrl, $label ?: null, $buttonUrl ?: null, $order, isset($_POST['is_active']) ? 1 : 0, $id]);
                } else {
                    db()->prepare('INSERT INTO banners (title,subtitle,image_url,button_label,button_url,sort_order,is_active) VALUES (?,?,?,?,?,?,?)')->execute([$title, $subtitle ?: null, $imageUrl ?: null, $label ?: null, $buttonUrl ?: null, $order, isset($_POST['is_active']) ? 1 : 0]);
                }
                log_admin(($id ? 'Updated' : 'Created') . ' banner: ' . $title);
                flash('success', 'Banner saved.');
                redirect('admin/banners.php');
            } catch (Throwable) {
                flash('error', 'Could not save banner.');
            }
        }
    }
    if ($action === 'delete_banner') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM banners WHERE id=?')->execute([$id]);
        log_admin('Deleted banner #' . $id);
        flash('success', 'Banner deleted.');
        redirect('admin/banners.php');
    }
}
$banners = db()->query('SELECT * FROM banners ORDER BY sort_order,id')->fetchAll();
$edit = null;
if ($editId) {
    $statement = db()->prepare('SELECT * FROM banners WHERE id=?');
    $statement->execute([$editId]);
    $edit = $statement->fetch() ?: null;
}
$adminPage = 'banners';
$pageTitle = 'Banners';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Catalogue</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Banners</h1>
    <p class="mt-2 text-sm text-zinc-500">Control editorial campaigns on the storefront.</p>
</div>
<div class="mt-8 grid gap-6 xl:grid-cols-[1fr_380px]">
    <section class="grid gap-5 md:grid-cols-2"><?php foreach ($banners as $banner): ?><article
                class="overflow-hidden rounded-2xl border border-zinc-300 bg-white">
                <div class="aspect-[16/8] bg-zinc-100"><?php if ($banner['image_url']): ?><img
                            class="h-full w-full object-cover"
                            src="<?= h(str_starts_with($banner['image_url'], 'http') ? $banner['image_url'] : url($banner['image_url'])) ?>"
                            alt=""><?php else: ?><div class="grid h-full place-items-center text-zinc-400"><span
                                class="material-symbols-outlined text-4xl">image</span></div><?php endif; ?></div>
                <div class="p-5">
                    <div class="flex justify-between gap-3">
                        <div><span
                                class="badge <?= $banner['is_active'] ? 'bg-green-100 text-green-700' : 'bg-zinc-200 text-zinc-600' ?>"><?= $banner['is_active'] ? 'Live' : 'Hidden' ?></span>
                            <h2 class="mt-3 font-bold"><?= h($banner['title']) ?></h2>
                            <p class="mt-1 text-sm text-zinc-500"><?= h($banner['subtitle'] ?: 'No subtitle') ?></p>
                        </div><span class="text-xs text-zinc-500">#<?= $banner['sort_order'] ?></span>
                    </div>
                    <div class="mt-5 flex gap-3"><a class="font-semibold underline"
                            href="<?= h(url('admin/banners.php?edit=' . $banner['id'])) ?>">Edit</a>
                        <form method="post" onsubmit="return confirm('Delete this banner?')"><?= csrf_field() ?><input
                                type="hidden" name="action" value="delete_banner"><input type="hidden" name="id"
                                value="<?= $banner['id'] ?>"><button class="font-semibold text-red-700 underline"
                                type="submit">Delete</button></form>
                    </div>
                </div>
            </article><?php endforeach; ?><?php if (!$banners): ?><div
                class="col-span-full rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center text-sm text-zinc-500">
                No banners yet. Create the first campaign.</div><?php endif; ?></section>
    <aside class="h-fit rounded-2xl border border-zinc-300 bg-white p-6">
        <div class="flex justify-between">
            <h2 class="font-bold"><?= $edit ? 'Edit' : 'Add' ?> banner</h2><?php if ($edit): ?><a
                    class="text-xs font-bold underline" href="<?= h(url('admin/banners.php')) ?>">Cancel</a><?php endif; ?>
        </div>
        <form class="mt-5 space-y-4" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden"
                name="action" value="save_banner"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div><label class="label">Title *</label><input class="input" name="title" required
                    value="<?= h($edit['title'] ?? '') ?>"></div>
            <div><label class="label">Subtitle</label><textarea class="input min-h-20"
                    name="subtitle"><?= h($edit['subtitle'] ?? '') ?></textarea></div>
            <div><label class="label">Banner image upload</label><input
                    class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-black file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                    accept="image/jpeg,image/png,image/webp,image/gif" name="banner_image" type="file"></div>
            <div><label class="label">Or image URL</label><input class="input" name="image_url" type="url"
                    value="<?= h($edit['image_url'] ?? '') ?>"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="label">Button text</label><input class="input" name="button_label"
                        value="<?= h($edit['button_label'] ?? '') ?>"></div>
                <div><label class="label">Button URL</label><input class="input" name="button_url"
                        value="<?= h($edit['button_url'] ?? '') ?>" placeholder="shop.php"></div>
            </div>
            <div><label class="label">Display order</label><input class="input" name="sort_order" type="number"
                    value="<?= h((string)($edit['sort_order'] ?? 0)) ?>"></div><label class="flex gap-2 text-sm"><input
                    class="rounded border-zinc-300 text-black focus:ring-black" name="is_active" type="checkbox"
                    <?= !isset($edit) || $edit['is_active'] ? 'checked' : '' ?>>Visible on storefront</label><button
                class="button button-primary w-full" type="submit">Save banner</button>
        </form>
    </aside>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>