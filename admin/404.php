<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }

    $upload = upload_image('error_page_image', 'errors');
    if ($upload['error']) {
        flash('error', $upload['error']);
    } elseif (!$upload['path']) {
        flash('error', 'Choose an image to upload.');
    } else {
        db()->prepare('INSERT INTO site_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute([
            'error_page_image',
            $upload['path'],
            current_user()['id'],
        ]);
        log_admin('Updated 404 page image');
        flash('success', '404 page image updated.');
        redirect('admin/404.php');
    }
}

$errorImage = site_setting('error_page_image');
$adminPage = 'error-page';
$pageTitle = '404 Page image';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Catalogue</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">404 Page image</h1>
    <p class="mt-2 max-w-xl text-sm text-zinc-500">Upload the image shown on the missing-page screen. It will be used instead of the catalogue fallback.</p>
</div>

<section class="mt-8 max-w-2xl rounded-2xl border border-zinc-300 bg-white p-6 sm:p-8">
    <div class="flex min-h-64 items-center justify-center overflow-hidden rounded-xl bg-zinc-100">
        <?php if ($errorImage): ?>
            <img class="h-64 w-full object-cover" src="<?= h(display_image_url($errorImage)) ?>" alt="Current 404 page image">
        <?php else: ?>
            <div class="text-center text-zinc-400"><span class="material-symbols-outlined text-5xl">broken_image</span><p class="mt-2 text-sm">No custom image uploaded yet.</p></div>
        <?php endif; ?>
    </div>
    <form class="mt-6 space-y-5" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div>
            <label class="label" for="error-page-image">Choose image</label>
            <input id="error-page-image" class="mt-2 block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-black file:px-3 file:py-2 file:text-xs file:font-bold file:text-white" accept="image/jpeg,image/png,image/webp,image/gif" name="error_page_image" type="file" required>
            <p class="mt-2 text-xs text-zinc-500">JPG, PNG, WebP or GIF · maximum 4 MB.</p>
        </div>
        <button class="button button-primary" type="submit"><span class="material-symbols-outlined text-base">upload</span>Upload 404 image</button>
    </form>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>
