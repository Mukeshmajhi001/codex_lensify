<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $slug = slugify($slugInput ?: $name);
    $sku = strtoupper(trim((string) ($_POST['sku'] ?? '')));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $brandId = (int) ($_POST['brand_id'] ?? 0);
    $price = (float) ($_POST['price'] ?? 0);
    $compare = trim((string) ($_POST['compare_price'] ?? ''));
    $stock = max(0, (int) ($_POST['stock_quantity'] ?? 0));
    $shape = trim((string) ($_POST['shape'] ?? ''));
    $material = trim((string) ($_POST['material'] ?? ''));
    $color = trim((string) ($_POST['color'] ?? ''));
    $gender = in_array($_POST['gender'] ?? '', ['Men', 'Women', 'Kids', 'Unisex'], true) ? $_POST['gender'] : 'Unisex';
    $badge = trim((string) ($_POST['badge'] ?? ''));
    $description = sanitize_rich_text((string) ($_POST['description'] ?? ''));
    $imageUrls = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) ($_POST['image_urls'] ?? '')) ?: []))));
    $removeImageIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['remove_image_ids'] ?? [])), static fn(int $imageId): bool => $imageId > 0)));

    if (!$name || !$slug || !$sku || !$categoryId || !$brandId || $price <= 0 || !$shape || !$material || !$color) {
        flash('error', 'Complete all required product fields.');
    } elseif (array_filter($imageUrls, static fn(string $imageUrl): bool => !filter_var($imageUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $imageUrl))) {
        flash('error', 'Each gallery image URL must start with http:// or https://.');
    } else {
        $upload = upload_images('product_images', 'products');
        if ($upload['error']) {
            flash('error', $upload['error']);
        } else {
            try {
                $wasExisting = $id > 0;
                db()->beginTransaction();
                if ($id) {
                    $statement = db()->prepare('UPDATE products SET category_id=?, brand_id=?, name=?, slug=?, sku=?, description=?, short_description=?, price=?, compare_price=?, stock_quantity=?, gender=?, shape=?, material=?, color=?, badge=?, is_featured=?, is_active=? WHERE id=?');
                    $statement->execute([$categoryId, $brandId, $name, $slug, $sku, $description, mb_substr($description, 0, 500), $price, $compare !== '' ? (float) $compare : null, $stock, $gender, $shape, $material, $color, $badge ?: null, isset($_POST['is_featured']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0, $id]);
                } else {
                    $statement = db()->prepare('INSERT INTO products (category_id, brand_id, name, slug, sku, description, short_description, price, compare_price, stock_quantity, gender, shape, material, color, badge, is_featured, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $statement->execute([$categoryId, $brandId, $name, $slug, $sku, $description, mb_substr($description, 0, 500), $price, $compare !== '' ? (float) $compare : null, $stock, $gender, $shape, $material, $color, $badge ?: null, isset($_POST['is_featured']) ? 1 : 0, isset($_POST['is_active']) ? 1 : 0]);
                    $id = (int) db()->lastInsertId();
                }

                if ($removeImageIds && $wasExisting) {
                    $placeholders = implode(',', array_fill(0, count($removeImageIds), '?'));
                    $remove = db()->prepare("DELETE FROM product_images WHERE product_id = ? AND id IN ({$placeholders})");
                    $remove->execute([$id, ...$removeImageIds]);
                }

                // Uploaded file paths are content-addressed; combine and de-duplicate
                // once more so each selected image appears exactly once in the gallery.
                $newImages = array_values(array_unique([...$upload['paths'], ...$imageUrls]));
                if ($newImages) {
                    $existingImage = db()->prepare('SELECT id FROM product_images WHERE product_id = ? AND image_url = ? LIMIT 1');
                    $nextSort = (int) db()->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM product_images WHERE product_id = ' . (int) $id)->fetchColumn();
                    $addImage = db()->prepare('INSERT INTO product_images (product_id, image_url, alt_text, sort_order) VALUES (?, ?, ?, ?)');
                    foreach ($newImages as $imageUrl) {
                        $existingImage->execute([$id, $imageUrl]);
                        if (!$existingImage->fetchColumn()) {
                            $addImage->execute([$id, $imageUrl, $name, $nextSort++]);
                        }
                    }
                }

                $imageRows = db()->prepare('SELECT id FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
                $imageRows->execute([$id]);
                $setSort = db()->prepare('UPDATE product_images SET sort_order = ?, alt_text = ? WHERE id = ?');
                foreach ($imageRows->fetchAll() as $sortOrder => $image) {
                    $setSort->execute([$sortOrder, $name, $image['id']]);
                }
                db()->commit();
                log_admin(($wasExisting ? 'Updated' : 'Created') . ' product: ' . $name);
                flash('success', 'Product saved successfully.');
                redirect('admin/products.php');
            } catch (Throwable) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('error', 'Could not save: the slug and SKU must be unique.');
            }
        }
    }
}

$categories = db()->query('SELECT id,name FROM categories ORDER BY name')->fetchAll();
$brands = db()->query('SELECT id,name FROM brands ORDER BY name')->fetchAll();
$product = ['id' => 0, 'name' => '', 'slug' => '', 'sku' => '', 'category_id' => '', 'brand_id' => '', 'price' => '', 'compare_price' => '', 'stock_quantity' => 0, 'gender' => 'Unisex', 'shape' => '', 'material' => '', 'color' => '', 'badge' => '', 'description' => '', 'is_featured' => 0, 'is_active' => 1, 'image_url' => ''];
if ($id) {
    $statement = db()->prepare('SELECT p.*, (SELECT image_url FROM product_images WHERE product_id=p.id ORDER BY sort_order,id LIMIT 1) image_url FROM products p WHERE p.id=?');
    $statement->execute([$id]);
    $product = $statement->fetch() ?: $product;
    if (!$product['id']) {
        flash('error', 'Product not found.');
        redirect('admin/products.php');
    }
}
$productImages = $product['id'] ? product_images((int) $product['id']) : [];
$adminPage = 'products';
$pageTitle = $id ? 'Edit product' : 'Add product';
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="flex items-end justify-between">
    <div>
        <p class="label">Catalogue</p>
        <h1 class="text-3xl font-bold tracking-[-.05em]"><?= $id ? 'Edit product' : 'Add a product' ?></h1>
        <p class="mt-2 text-sm text-zinc-500">Use clean, factual information that helps customers decide.</p>
    </div><a class="text-xs font-bold underline" href="<?= h(url('admin/products.php')) ?>">← Back to products</a>
</div>
<form class="mt-8 grid gap-6 xl:grid-cols-[1fr_320px]" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id"
        value="<?= (int) $product['id'] ?>">
    <div class="space-y-6">
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <h2 class="text-lg font-bold">Product information</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><label class="label">Product name *</label><input class="input" name="name" required
                        value="<?= h($product['name']) ?>"></div>
                <div><label class="label">SKU *</label><input class="input" name="sku" required
                        value="<?= h($product['sku']) ?>" placeholder="LUM-NOIR-001"></div>
            </div>
            <div class="mt-4"><label class="label">URL slug *</label><input class="input" name="slug"
                    value="<?= h($product['slug']) ?>" placeholder="noir-arch-rectangular">
                <p class="mt-2 text-[11px] text-zinc-500">Leave blank to generate from product name.</p>
            </div>
            <div class="mt-4">
                <label class="label" for="product-description-editor">Description</label>
                <div class="rich-editor mt-2" data-rich-editor>
                    <div class="flex flex-wrap items-center gap-1 border-b border-zinc-200 bg-zinc-50 p-2"
                        role="toolbar" aria-label="Description formatting">
                        <button class="rich-editor-button" type="button" data-rich-command="bold"
                            aria-label="Bold"><strong>B</strong></button>
                        <button class="rich-editor-button italic" type="button" data-rich-command="italic"
                            aria-label="Italic">I</button>
                        <button class="rich-editor-button underline" type="button" data-rich-command="underline"
                            aria-label="Underline">U</button>
                        <button class="rich-editor-button line-through" type="button" data-rich-command="strikeThrough"
                            aria-label="Strikethrough">S</button>
                        <span class="mx-1 h-5 w-px bg-zinc-200" aria-hidden="true"></span>
                        <button class="rich-editor-button" type="button" data-rich-command="insertUnorderedList"
                            aria-label="Bulleted list">• List</button>
                        <button class="rich-editor-button" type="button" data-rich-command="insertOrderedList"
                            aria-label="Numbered list">1. List</button>
                        <button class="rich-editor-button" type="button" data-rich-command="formatBlock"
                            data-rich-value="h3" aria-label="Heading">Heading</button>
                        <button class="rich-editor-button ml-auto" type="button" data-rich-command="removeFormat"
                            aria-label="Clear formatting">Clear</button>
                    </div>
                    <div class="rich-editor-surface input min-h-40 rounded-none border-0 focus:ring-0"
                        id="product-description-editor" contenteditable="true" data-rich-editor-surface
                        aria-label="Product description"><?= render_rich_text($product['description']) ?></div>
                    <textarea class="sr-only" name="description" data-rich-editor-input
                        aria-hidden="true"><?= h($product['description']) ?></textarea>
                </div>
                <p class="mt-2 text-[11px] text-zinc-500">Use bold, headings, lists, underline and strikethrough to make the product details easy to scan.</p>
            </div>
        </section>
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <h2 class="text-lg font-bold">Frame specifications</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><label class="label">Category *</label><select class="input" name="category_id" required>
                        <option value="">Select category</option><?php foreach ($categories as $category): ?><option
                                value="<?= $category['id'] ?>"
                                <?= (int) $product['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                <?= h($category['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="label">Brand *</label><select class="input" name="brand_id" required>
                        <option value="">Select brand</option><?php foreach ($brands as $brand): ?><option
                                value="<?= $brand['id'] ?>"
                                <?= (int) $product['brand_id'] === (int) $brand['id'] ? 'selected' : '' ?>>
                                <?= h($brand['name']) ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="label">Shape *</label><input class="input" name="shape" required
                        value="<?= h($product['shape']) ?>" placeholder="Rectangular"></div>
                <div><label class="label">Material *</label><input class="input" name="material" required
                        value="<?= h($product['material']) ?>" placeholder="Acetate"></div>
                <div><label class="label">Color *</label><input class="input" name="color" required
                        value="<?= h($product['color']) ?>" placeholder="Midnight Black"></div>
                <div><label class="label">Gender</label><select class="input"
                        name="gender"><?php foreach (['Men', 'Women', 'Kids', 'Unisex'] as $gender): ?><option
                                <?= $product['gender'] === $gender ? 'selected' : '' ?>><?= h($gender) ?></option>
                        <?php endforeach; ?></select></div>
            </div>
        </section>
    </div>
    <aside class="space-y-6">
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <h2 class="text-lg font-bold">Pricing & stock</h2>
            <div class="mt-5 space-y-4">
                <div><label class="label">Price (NPR) *</label><input class="input" min="1" name="price" required
                        step="1" type="number" value="<?= h((string) $product['price']) ?>"></div>
                <div><label class="label">Compare-at price</label><input class="input" min="1" name="compare_price"
                        step="1" type="number" value="<?= h((string) $product['compare_price']) ?>"></div>
                <div><label class="label">Stock quantity *</label><input class="input" min="0" name="stock_quantity"
                        required type="number" value="<?= h((string) $product['stock_quantity']) ?>"></div>
                <div><label class="label">Badge</label><select class="input" name="badge">
                        <option value="">None</option>
                        <?php foreach (['NEW', 'SALE', 'BESTSELLER', 'LIMITED'] as $badge): ?><option
                                value="<?= h($badge) ?>" <?= $product['badge'] === $badge ? 'selected' : '' ?>>
                                <?= h($badge) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
        </section>
        <section class="rounded-2xl border border-zinc-300 bg-white p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold">Product gallery</h2>
                    <p class="mt-1 text-xs leading-5 text-zinc-500">Only the images below will appear as thumbnails on
                        the product page.</p>
                </div><span class="badge bg-zinc-100 text-zinc-600"><?= count($productImages) ?>
                    image<?= count($productImages) === 1 ? '' : 's' ?></span>
            </div><?php if ($productImages): ?><div class="mt-5 grid grid-cols-3 gap-3">
                    <?php foreach ($productImages as $index => $image): ?><label
                            class="group relative block overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100"><img
                                class="aspect-square w-full object-cover" src="<?= h(display_image_url($image['image_url'])) ?>"
                                alt=""><span
                                class="absolute left-2 top-2 rounded bg-black/75 px-1.5 py-1 text-[10px] font-bold text-white"><?= $index === 0 ? 'MAIN' : $index + 1 ?></span><span
                                class="absolute inset-x-0 bottom-0 bg-black/75 px-2 py-2 text-center text-[10px] font-bold text-white opacity-0 transition group-hover:opacity-100"><input
                                    class="mr-1 rounded border-zinc-300 text-black focus:ring-black" name="remove_image_ids[]"
                                    type="checkbox" value="<?= (int) $image['id'] ?>">Remove</span></label><?php endforeach; ?>
                </div><?php else: ?><p
                    class="mt-5 rounded-lg border border-dashed border-zinc-300 p-4 text-xs text-zinc-500">No gallery image
                    yet. Add one or more images below.</p><?php endif; ?><div class="mt-5"><label class="label">Upload
                    gallery images</label><input
                    class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-black file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                    accept="image/jpeg,image/png,image/webp,image/gif" multiple name="product_images[]" type="file">
                <p class="mt-2 text-[11px] text-zinc-500">Select several JPG, PNG, WebP or GIF files. Each file can be
                    up to 4 MB.</p>
            </div>
            <div class="mt-4"><label class="label">Or add image URLs</label><textarea class="input min-h-24"
                    name="image_urls"
                    placeholder="https://example.com/frame-front.jpg&#10;https://example.com/frame-side.jpg"></textarea>
                <p class="mt-2 text-[11px] text-zinc-500">One http(s) URL per line. Existing images stay unless you tick
                    Remove.</p>
            </div>
            <div class="mt-5 space-y-3 text-sm"><label class="flex items-center gap-2"><input
                        class="rounded border-zinc-300 text-black focus:ring-black" name="is_active" type="checkbox"
                        <?= $product['is_active'] ? 'checked' : '' ?>>Visible in store</label><label
                    class="flex items-center gap-2"><input class="rounded border-zinc-300 text-black focus:ring-black"
                        name="is_featured" type="checkbox" <?= $product['is_featured'] ? 'checked' : '' ?>>Feature on
                    homepage</label></div>
        </section>
        <button class="button button-primary w-full" type="submit">Save product</button>
    </aside>
</form>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>
