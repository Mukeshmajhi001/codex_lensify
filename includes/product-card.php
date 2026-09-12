<?php

/** @var array $product */
$returnTo = $returnTo ?? basename($_SERVER['PHP_SELF'] ?? 'shop.php') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
$wishlisted = is_wishlisted((int) $product['id']);
?>
<article class="product-card relative">
    <a class="absolute inset-0 z-0" href="<?= h(url('product.php?slug=' . rawurlencode($product['slug']))) ?>"
        aria-label="View <?= h($product['name']) ?>"></a>
    <div class="product-media pointer-events-none">
        <img src="<?= h(display_image_url($product['image_url'] ?? null)) ?>" alt="<?= h($product['name']) ?>"
            loading="lazy">
        <div class="absolute left-3 top-3 z-10 flex gap-1.5">
            <?php if (!empty($product['badge'])): ?><span
                    class="badge <?= $product['badge'] === 'SALE' ? 'bg-green-600 text-white' : ($product['badge'] === 'BESTSELLER' ? 'border border-amber-600 bg-white text-amber-700' : 'bg-black text-white') ?>"><?= h($product['badge']) ?></span><?php endif; ?>
        </div>
        <form class="pointer-events-auto absolute right-3 top-3 z-10" method="post"
            action="<?= h(url('wishlist.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_wishlist">
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
            <button
                class="grid h-10 w-10 place-items-center rounded-full bg-white/95 transition hover:scale-105 <?= $wishlisted ? 'text-red-600' : 'text-zinc-900' ?>"
                aria-label="<?= $wishlisted ? 'Remove from wishlist' : 'Add to wishlist' ?>" type="submit"><span
                    class="material-symbols-outlined text-[21px]"
                    style="font-variation-settings:'FILL' <?= $wishlisted ? 1 : 0 ?>">favorite</span></button>
        </form>
    </div>
    <a class="relative z-10 mt-3 block" href="<?= h(url('product.php?slug=' . rawurlencode($product['slug']))) ?>">
        <p class="text-[10px] font-semibold uppercase tracking-[.14em] text-zinc-500"><?= h($product['brand']) ?></p>
        <h3 class="mt-1 text-sm font-semibold tracking-[-.01em] text-zinc-950"><?= h($product['name']) ?></h3>
        <div class="mt-1 flex items-center gap-2"><span
                class="text-sm font-bold"><?= money($product['price']) ?></span><?php if ($product['compare_price']): ?><span
                    class="text-xs text-zinc-400 line-through"><?= money($product['compare_price']) ?></span><?php endif; ?>
        </div>
    </a>
</article>