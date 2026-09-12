<?php
require_once __DIR__ . '/app/bootstrap.php';
handle_store_actions();

$product = product_by_slug((string) ($_GET['slug'] ?? ''));
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Frame not found';
    require APP_ROOT . '/includes/header.php';
    echo '<section class="mx-auto max-w-xl px-5 py-24 text-center"><span class="material-symbols-outlined text-5xl text-zinc-400">search_off</span><h1 class="mt-5 text-3xl font-bold">This frame is out of focus.</h1><a class="button button-primary mt-6" href="' . h(url('shop.php')) . '">Browse frames</a></section>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $rating = (int) ($_POST['rating'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $returnTo = (($_POST['return_to'] ?? '') === 'orders.php')
        ? 'orders.php'
        : 'product.php?slug=' . rawurlencode($product['slug']);
    if (!current_user()) {
        flash('error', 'Please sign in before writing a review.');
    } elseif (!(review_eligibility((int) $product['id'])['eligible'])) {
        flash('error', 'A review can only be submitted after this frame is delivered and payment is marked paid.');
    } elseif ($rating < 1 || $rating > 5 || mb_strlen($body) < 10) {
        flash('error', 'Choose a rating and write at least 10 characters.');
    } else {
        $duplicate = db()->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
        $duplicate->execute([$product['id'], current_user()['id']]);
        if ($duplicate->fetch()) {
            flash('error', 'You have already submitted a review for this frame.');
        } else {
            db()->prepare('INSERT INTO reviews (product_id, user_id, reviewer_name, rating, title, body, status) VALUES (?, ?, ?, ?, ?, ?, "pending")')->execute([
                $product['id'],
                current_user()['id'],
                trim(current_user()['first_name'] . ' ' . current_user()['last_name']),
                $rating,
                $title ?: null,
                $body,
            ]);
            $reviewerName = trim(current_user()['first_name'] . ' ' . current_user()['last_name']) ?: 'A customer';
            notify_admins('New review received', "{$reviewerName} reviewed {$product['name']} with {$rating} " . ($rating === 1 ? 'star.' : 'stars.'), 'admin/reviews.php');
            flash('success', 'Thanks! Your review is waiting for approval.');
        }
    }
    redirect($returnTo);
}

$variants = product_variants((int) $product['id']);
$wishlisted = is_wishlisted((int) $product['id']);
$galleryImages = product_images((int) $product['id']);
if (!$galleryImages) {
    $galleryImages = [['id' => 0, 'image_url' => $product['image_url'] ?? '', 'alt_text' => $product['name']]];
}
$approvedReviews = approved_reviews((int) $product['id']);
$reviewEligibility = review_eligibility((int) $product['id']);
$pageTitle = $product['name'];
$activeCategory = $product['category'];
$related = array_values(array_filter(products(['category' => $product['category']]), static fn(array $item): bool => $item['id'] !== $product['id']));
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1440px] px-5 py-8 lg:px-10">
    <nav class="mb-7 text-xs text-zinc-500"><a href="<?= h(url()) ?>">Home</a><span class="px-1">›</span><a
            href="<?= h(url('shop.php?category=' . rawurlencode($product['category']))) ?>"><?= h($product['category']) ?></a><span
            class="px-1">›</span><?= h($product['name']) ?></nav>
    <div class="grid gap-8 lg:grid-cols-[1.25fr_.9fr] lg:gap-14">
        <div>
            <div class="relative aspect-square overflow-hidden rounded-xl bg-white" data-product-gallery>
                <img class="h-full w-full object-contain p-4 sm:p-6 lg:p-10"
                    id="product-gallery-main" src="<?= h(display_image_url($galleryImages[0]['image_url'] ?? null)) ?>"
                    alt="<?= h($galleryImages[0]['alt_text'] ?: $product['name']) ?>">
                <?php if (count($galleryImages) > 1): ?><button
                        class="absolute left-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full border border-zinc-200 bg-white/95 text-ink shadow-soft transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-ink/30"
                        type="button" data-gallery-prev aria-label="Show previous product image">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.5 5-7 7 7 7" />
                        </svg>
                    </button><button
                        class="absolute right-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full border border-zinc-200 bg-white/95 text-ink shadow-soft transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-ink/30"
                        type="button" data-gallery-next aria-label="Show next product image">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9.5 5 7 7-7 7" />
                        </svg>
                    </button><?php endif; ?>
            </div>
            <?php if (count($galleryImages) > 1): ?><div class="mt-3 grid grid-cols-4 gap-3 sm:grid-cols-5 lg:grid-cols-6">
                    <?php foreach ($galleryImages as $index => $image): ?><button
                            class="product-gallery-thumb aspect-square overflow-hidden rounded-lg border bg-white <?= $index === 0 ? 'border-2 border-black' : 'border-zinc-200' ?>"
                            type="button" data-gallery-image="<?= h(display_image_url($image['image_url'] ?? null)) ?>"
                            data-gallery-alt="<?= h($image['alt_text'] ?: $product['name']) ?>"
                            data-gallery-index="<?= $index ?>" aria-label="View image <?= $index + 1 ?>"><img
                                class="h-full w-full object-contain p-1 <?= $index === 0 ? '' : 'opacity-70' ?>"
                                src="<?= h(display_image_url($image['image_url'] ?? null)) ?>"
                                alt=""></button><?php endforeach; ?></div><?php endif; ?>
        </div>
        <div class="lg:pt-3">
            <div class="flex gap-2"><?php if ($product['badge']): ?><span
                        class="badge <?= $product['badge'] === 'SALE' ? 'bg-green-600 text-white' : 'bg-black text-white' ?>"><?= h($product['badge']) ?></span><?php endif; ?><span
                    class="badge bg-zinc-200 text-zinc-700">In stock</span></div>
            <p class="mt-4 text-[10px] font-bold uppercase tracking-[.16em] text-zinc-500"><?= h($product['brand']) ?>
            </p>
            <h1 class="mt-1 text-3xl font-bold tracking-[-.05em] sm:text-4xl"><?= h($product['name']) ?></h1>
            <div class="mt-3 flex items-center gap-2 text-sm"><span
                    class="text-amber-600">★★★★★</span><span><?= number_format($product['rating'], 1) ?></span><span
                    class="text-zinc-500">(<?= (int) $product['review_count'] ?> reviews)</span></div>
            <div class="mt-5 flex items-center gap-3 border-b border-zinc-200 pb-5"><span
                    class="text-2xl font-bold"><?= $variants ? 'From ' : '' ?><?= money($product['price']) ?></span><?php if ($product['compare_price']): ?><span
                        class="text-sm text-zinc-400 line-through"><?= money($product['compare_price']) ?></span><span
                        class="badge bg-green-100 text-green-700"><?= round((1 - $product['price'] / $product['compare_price']) * 100) ?>%
                        off</span><?php endif; ?></div>
            <dl class="mt-5 grid grid-cols-2 gap-y-4 text-sm">
                <div>
                    <dt class="text-xs text-zinc-500">Shape</dt>
                    <dd class="mt-1 font-medium"><?= h($product['shape']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Material</dt>
                    <dd class="mt-1 font-medium"><?= h($product['material']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Color</dt>
                    <dd class="mt-1 font-medium"><?= h($product['color']) ?></dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Category</dt>
                    <dd class="mt-1 font-medium"><?= h($product['gender']) ?></dd>
                </div>
            </dl>
            <form class="mt-7" method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="add_to_cart"><input type="hidden"
                    name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to"
                    value="product.php?slug=<?= h(rawurlencode($product['slug'])) ?>">
                <?php if ($variants): ?><div class="mb-5"><label class="label" for="variant">Select frame
                            option</label><select class="input" id="variant" name="variant_id" required>
                            <option value="">Choose an option</option><?php foreach ($variants as $variant): ?><option
                                    value="<?= (int) $variant['id'] ?>"
                                    <?= (int) $variant['stock_quantity'] < 1 ? 'disabled' : '' ?>>
                                    <?= h($variant['name']) ?><?= $variant['color'] ? ' · ' . h($variant['color']) : '' ?><?= (float) $variant['price_adjustment'] ? ' · ' . ((float) $variant['price_adjustment'] > 0 ? '+' : '') . money($variant['price_adjustment']) : '' ?><?= (int) $variant['stock_quantity'] < 1 ? ' · Sold out' : '' ?>
                                </option><?php endforeach; ?>
                        </select></div><?php endif; ?>
                <div class="mt-7 border-t border-zinc-200 pt-5">
                    <p class="label">Description</p>
                    <div class="rich-description is-collapsed mt-2 text-sm leading-6 text-zinc-600"
                        data-description-collapsible><?= render_rich_text($product['description']) ?></div>
                    <button class="mt-3 hidden text-xs font-bold underline underline-offset-4" type="button"
                        data-description-toggle>See more</button>
                </div>
                <input type="hidden" name="lens_type" value="Single Vision">
                <div class="mt-5" data-quantity-stepper>
                    <label class="label" for="product-quantity">Quantity</label>
                    <div class="mt-2 inline-flex items-center rounded-lg border border-zinc-300 bg-white">
                        <button class="grid h-10 w-10 place-items-center text-lg text-zinc-700 transition hover:bg-zinc-50"
                            type="button" data-quantity-decrease aria-label="Decrease quantity">−</button>
                        <input class="h-10 w-12 border-x border-y-0 border-zinc-300 bg-transparent p-0 text-center text-sm font-bold focus:ring-0"
                            id="product-quantity" name="quantity" type="number" min="1" max="10" value="1"
                            inputmode="numeric" aria-label="Quantity">
                        <button class="grid h-10 w-10 place-items-center text-lg text-zinc-700 transition hover:bg-zinc-50"
                            type="button" data-quantity-increase aria-label="Increase quantity">+</button>
                    </div>
                </div>
                <div class="mt-5 grid grid-cols-2 gap-3"><button class="button button-primary" type="submit">Add to
                        cart</button><button class="button button-secondary" formaction="<?= h(url('checkout.php')) ?>"
                        name="return_to" type="submit" value="checkout.php">Buy now</button></div>
            </form>
            <form class="mt-3" method="post" action="<?= h(url('wishlist.php')) ?>"><?= csrf_field() ?><input
                    type="hidden" name="action" value="toggle_wishlist"><input type="hidden" name="product_id"
                    value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to"
                    value="product.php?slug=<?= h(rawurlencode($product['slug'])) ?>"><button
                    class="button button-secondary w-full <?= $wishlisted ? 'border-red-200 bg-red-50 text-red-600' : '' ?>"
                    type="submit" aria-label="<?= $wishlisted ? 'Remove from wishlist' : 'Add to wishlist' ?>"><span
                        class="material-symbols-outlined text-base"
                        style="font-variation-settings:'FILL' <?= $wishlisted ? 1 : 0 ?>">favorite</span><?= $wishlisted ? 'Saved frame' : 'Save frame' ?></button>
            </form>
            <div class="mt-7 grid grid-cols-3 border-t border-zinc-200 pt-5 text-center text-[10px] text-zinc-600">
                <span><span class="material-symbols-outlined block text-green-700">local_shipping</span>Fast
                    delivery</span><span><span
                        class="material-symbols-outlined block text-green-700">replay</span>30-day
                    returns</span><span><span
                        class="material-symbols-outlined block text-green-700">verified_user</span>2-year
                    warranty</span>
            </div>
        </div>
    </div>
</section>
<section class="mx-auto max-w-[1180px] px-5 py-10 lg:px-10">
    <div class="grid gap-8 lg:grid-cols-[1.2fr_.8fr]">
        <div>
            <p class="label">Customer reviews</p>
            <h2 class="text-2xl font-bold tracking-[-.04em]">What people are saying</h2><?php if ($approvedReviews): ?>
                <div class="mt-6 space-y-5"><?php foreach ($approvedReviews as $review): ?><article
                            class="border-b border-zinc-200 pb-5">
                            <div class="flex items-center justify-between gap-4"><strong
                                    class="text-sm"><?= h($review['reviewer_name']) ?></strong><span
                                    class="text-xs text-amber-600"><?= str_repeat('★', (int) $review['rating']) ?></span></div>
                            <?php if ($review['title']): ?><h3 class="mt-3 text-sm font-bold"><?= h($review['title']) ?></h3>
                            <?php endif; ?><p class="mt-2 text-sm leading-6 text-zinc-600"><?= h($review['body']) ?></p><span
                                class="mt-3 block text-[11px] text-zinc-500"><?= date('d M Y', strtotime($review['created_at'])) ?></span>
                        </article><?php endforeach; ?></div><?php else: ?><p class="mt-5 text-sm text-zinc-500">No approved
                    reviews yet. Be the first to share your experience.</p><?php endif; ?>
        </div>
        <aside class="rounded-xl bg-mist p-5 sm:p-6" id="write-review">
            <h2 class="text-lg font-bold">Write a review</h2><?php if (!current_user()): ?><p
                    class="mt-3 text-sm leading-6 text-zinc-600">Anyone can read reviews. Sign in after your delivered, paid
                    purchase to write one.</p><a class="button button-secondary mt-5 w-full"
                    href="<?= h(url('login.php')) ?>">Sign in to review</a><?php elseif ($reviewEligibility['eligible']): ?>
                <form class="mt-5 space-y-4" method="post"><?= csrf_field() ?><input type="hidden" name="action"
                        value="submit_review">
                    <fieldset>
                        <legend class="label">Your rating</legend>
                        <div class="review-rating"><input id="review-rating-5" name="rating" required type="radio"
                                value="5"><label for="review-rating-5"><span class="sr-only">5 stars</span></label><input
                                id="review-rating-4" name="rating" type="radio" value="4"><label for="review-rating-4"><span
                                    class="sr-only">4 stars</span></label><input id="review-rating-3" name="rating"
                                type="radio" value="3"><label for="review-rating-3"><span class="sr-only">3
                                    stars</span></label><input id="review-rating-2" name="rating" type="radio"
                                value="2"><label for="review-rating-2"><span class="sr-only">2 stars</span></label><input
                                id="review-rating-1" name="rating" type="radio" value="1"><label for="review-rating-1"><span
                                    class="sr-only">1 star</span></label></div>
                    </fieldset>
                    <div><label class="label">Review title</label><input class="input" maxlength="180" name="title"
                            placeholder="Optional headline"></div>
                    <div><label class="label">Your review</label><textarea class="input min-h-28" maxlength="2000"
                            name="body" required placeholder="Tell us about your frame and fit..."></textarea></div><button
                        class="button button-primary w-full" type="submit">Submit for approval</button>
                    <p class="text-center text-[11px] text-zinc-500">Reviews are published after moderation.</p>
                </form><?php else: ?><div class="mt-4 rounded-lg border border-zinc-200 bg-white p-4"><span
                        class="material-symbols-outlined text-zinc-500">verified_user</span>
                    <p class="mt-2 text-sm leading-6 text-zinc-600"><?= h($reviewEligibility['reason']) ?></p>
                    <?php if (!$reviewEligibility['already_reviewed']): ?><a
                            class="mt-4 inline-block text-xs font-bold underline underline-offset-4"
                            href="<?= h(url('orders.php')) ?>">View my orders</a><?php endif; ?>
                </div><?php endif; ?>
        </aside>
    </div>
</section>
<?php if ($related): ?><section class="mx-auto max-w-[1440px] px-5 pb-12 lg:px-10">
        <h2 class="mb-7 text-2xl font-bold tracking-[-.04em]">You might also like</h2>
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4 lg:gap-6">
            <?php foreach (array_slice($related, 0, 4) as $relatedProduct): ?><?php $product = $relatedProduct;
                                                                                $returnTo = 'product.php?slug=' . rawurlencode($_GET['slug']);
                                                                                require APP_ROOT . '/includes/product-card.php'; ?><?php endforeach; ?>
        </div>
    </section><?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php'; ?>
