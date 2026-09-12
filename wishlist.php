<?php
require_once __DIR__ . '/app/bootstrap.php';
handle_store_actions();
$ids = wishlist_ids();
$saved = array_values(array_filter(products(), static fn(array $product): bool => in_array((int) $product['id'], $ids, true)));
$pageTitle = 'Saved frames';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-10">
    <p class="label">Your collection</p>
    <h1 class="text-4xl font-bold tracking-[-.05em]">Saved frames</h1>
    <p class="mt-2 text-sm text-zinc-600">Keep an eye on the pairs that caught yours.</p><?php if (!$saved): ?>
    <div class="mt-10 rounded-2xl border border-dashed border-zinc-300 px-6 py-20 text-center"><span
            class="material-symbols-outlined text-5xl text-zinc-400">favorite</span>
        <h2 class="mt-4 text-xl font-bold">Nothing saved yet</h2>
        <p class="mt-2 text-sm text-zinc-500">Tap the heart on any frame to build your shortlist.</p><a
            class="button button-primary mt-6" href="<?= h(url('shop.php')) ?>">Browse frames</a>
    </div><?php else: ?><div class="mt-10 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4 lg:gap-6">
        <?php foreach ($saved as $product): ?><?php $returnTo = 'wishlist.php';
                                                                                                    require APP_ROOT . '/includes/product-card.php'; ?><?php endforeach; ?>
    </div><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>