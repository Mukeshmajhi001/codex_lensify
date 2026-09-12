<?php
require_once __DIR__ . '/app/bootstrap.php';
handle_store_actions();
$pageTitle = 'Premium Eyewear';
$featured = array_slice(products(), 0, 4);
$newArrivals = array_slice(products(['sort' => 'newest']), 4, 4) ?: $featured;
$campaigns = active_banners();
require APP_ROOT . '/includes/header.php';
?>
<section
    class="mx-auto grid max-w-[1440px] items-center gap-10 px-5 py-10 sm:py-14 lg:grid-cols-2 lg:gap-16 lg:px-10 lg:py-16">
    <div>
        <span class="badge bg-zinc-100 text-zinc-700">
            Summer collection — now live
        </span>
        <h1 class="mt-6 max-w-xl text-5xl font-extrabold leading-[1.05] tracking-[-.065em] sm:text-6xl lg:text-7xl">
            Frames that <em class="font-light">define</em> you.</h1>
        <p class="mt-6 max-w-lg text-base leading-7 text-zinc-600">Engineered precision meets timeless design. Discover
            a curated selection of frames built for the way you see the world.</p>
        <div class="mt-8 flex flex-wrap gap-3"><a class="button button-primary" href="<?= h(url('shop.php')) ?>">Shop
                all frames <span class="material-symbols-outlined text-base">arrow_forward</span></a><a
                class="button button-secondary" href="<?= h(url('shop.php?category=Computer+Glasses')) ?>">Explore
                lenses</a></div>
        <div class="mt-10 grid max-w-lg grid-cols-3 border-t border-zinc-200 pt-6">
            <div class="border-r border-zinc-200"><strong class="block text-xl tracking-[-.04em]">4.9<span
                        class="text-amber-600">★</span></strong><span class="text-[12px] text-zinc-500">App
                    rating</span></div>
            <div class="border-r border-zinc-200 pl-5"><strong
                    class="block text-xl tracking-[-.04em]">1.2M+</strong><span class="text-[12px] text-zinc-500">Happy
                    customers</span></div>
            <div class="pl-5"><strong class="block text-xl tracking-[-.04em]">30</strong><span
                    class="text-[12px] text-zinc-500">Day free returns</span></div>
        </div>
    </div>
    <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
        <div class="aspect-[4/5] overflow-hidden rounded-2xl bg-zinc-100"><img class="h-full w-full object-cover"
                src="https://images.unsplash.com/photo-1577803645773-f96470509666?auto=format&fit=crop&w=1400&q=90"
                alt="A woman wearing black sunglasses"></div>
        <a class="absolute -bottom-5 left-4 hidden w-44 rounded-xl border border-zinc-200 bg-white p-3 shadow-soft sm:block lg:-left-8"
            href="<?= h(url('product.php?slug=aurelia-round-metal')) ?>"><span
                class="text-[9px] font-bold uppercase tracking-[.1em] text-zinc-500">Trending now</span><img
                class="mt-2 aspect-square w-full rounded-lg object-cover"
                src="https://images.unsplash.com/photo-1574258495973-f010dfbb5371?auto=format&fit=crop&w=400&q=80"
                alt="Aurelia Round Metal"><span class="mt-2 block text-xs font-bold">Aurelia Round Metal</span><span
                class="text-xs text-zinc-500">₹5,900</span></a>
    </div>
</section>

<?php if ($campaigns): ?>
<section class="mx-auto max-w-[1440px] px-5 pb-5 lg:px-10">
    <div class="grid gap-5 <?= count($campaigns) > 1 ? 'lg:grid-cols-2' : '' ?>">
        <?php foreach (array_slice($campaigns, 0, 2) as $campaign): ?> <?php $campaignUrl = trim((string) ($campaign['button_url'] ?? ''));
                           $campaignHref = preg_match('#^https?://#i', $campaignUrl) ? $campaignUrl : url($campaignUrl ?: 'shop.php');
                           $campaignImage = trim((string) ($campaign['image_url'] ?? ''));
                           if ($campaignImage !== '' && !preg_match('#^https?://#i', $campaignImage)) {
                               $campaignImage = url($campaignImage);
                           } ?>
        <article class="relative min-h-72 overflow-hidden rounded-2xl bg-zinc-900 px-6 py-12 text-white sm:px-10">
            <div class="absolute inset-0 opacity-40"><?php if ($campaignImage): ?><img
                    class="h-full w-full object-cover" src="<?= h($campaignImage) ?>" alt=""><?php endif; ?></div>
            <div class="relative max-w-md">
                <p class="text-[11px] font-bold uppercase tracking-[.16em] text-white/75">Lensify campaign</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-.05em]"><?= h($campaign['title']) ?></h2>
                <?php if ($campaign['subtitle']): ?>
                <p class="mt-3 text-sm leading-6 text-white/85">
                    <?= h($campaign['subtitle']) ?>
                </p><?php endif; ?><a class="button mt-6 bg-white text-black hover:bg-zinc-200"
                    href="<?= h($campaignHref) ?>"><?= h($campaign['button_label'] ?: 'Explore collection') ?></a>
            </div>
        </article><?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $categoryIcons = ['Eyeglasses' => 'visibility', 'Sunglasses' => 'wb_sunny', 'Reading Glasses' => 'menu_book', 'Computer Glasses' => 'computer']; ?>
<section class="bg-mist py-14 sm:py-16">
    <div class="mx-auto max-w-[1440px] px-5 lg:px-10">
        <div class="mb-8 flex items-end justify-between">
            <div>
                <h2 class="text-3xl font-bold tracking-[-.04em]">Shop by category</h2>
                <p class="mt-1 text-sm text-zinc-500">Find the perfect pair for your lifestyle.</p>
            </div><a class="text-xs font-bold underline underline-offset-4" href="<?= h(url('shop.php')) ?>">View
                all</a>
        </div>
        <?php
        $categories = categories();
        array_pop($categories);
        ?>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:gap-6">
            <?php foreach ($categories as $name => $category): ?>
            <?php $icon = $categoryIcons[$name] ?? ($category['icon'] ?: 'visibility'); ?>

            <a class="category-card" href="<?= h(url('shop.php?category=' . rawurlencode($name))) ?>">
                <span class="category-card-icon material-symbols-outlined"><?= h($icon) ?></span>

                <strong class="mt-3 block text-sm"><?= h($name) ?></strong>

                <span class="mt-1 block text-[11px] text-zinc-500">
                    <?= h($category['description']) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="mx-auto max-w-[1440px] px-5 py-16 lg:px-10">
    <div class="mb-8 text-center">
        <h2 class="text-3xl font-bold tracking-[-.04em]">The bestsellers</h2>
        <p class="mt-1 text-sm text-zinc-500">Our most-loved frames, chosen by millions across the globe.</p>
    </div>
    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4 lg:gap-6">
        <?php foreach ($featured as $product): ?> <?php $returnTo = 'index.php';
                 require APP_ROOT . '/includes/product-card.php'; ?><?php endforeach; ?>
    </div>
</section>

<section class="mx-auto max-w-[1440px] px-5 lg:px-10">
    <div class="relative overflow-hidden rounded-2xl bg-zinc-800 px-6 py-16 text-center text-white sm:px-12 sm:py-20">
        <img class="absolute inset-0 h-full w-full object-cover opacity-45"
            src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1800&q=85"
            alt="Coastline at sunset">
        <div class="relative mx-auto max-w-lg">
            <p class="text-[11px] font-bold uppercase tracking-[.16em] text-white/75">Limited time offer</p>
            <h2 class="mt-3 text-4xl font-bold tracking-[-.05em] sm:text-5xl">Up to 40% off on Premium Sunglasses</h2>
            <p class="mt-4 text-sm text-white/80">Elevate your summer style with our most iconic sun-ready frames.</p><a
                class="button mt-7 bg-white text-black hover:bg-zinc-200"
                href="<?= h(url('shop.php?category=Sunglasses')) ?>">Explore sale</a>
        </div>
    </div>
</section>

<section class="mx-auto max-w-[1440px] px-5 py-16 lg:px-10">
    <div class="mb-8 flex items-end justify-between">
        <div>
            <h2 class="text-3xl font-bold tracking-[-.04em]">New arrivals</h2>
            <p class="mt-1 text-sm text-zinc-500">Be the first to wear the future of eyewear.</p>
        </div><a class="text-xs font-bold underline underline-offset-4"
            href="<?= h(url('shop.php?sort=newest')) ?>">View collection</a>
    </div>
    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4 lg:gap-6">
        <?php foreach ($newArrivals as $product): ?> <?php $returnTo = 'index.php';
                 require APP_ROOT . '/includes/product-card.php'; ?><?php endforeach; ?>
    </div>
</section>

<section class="border-y border-zinc-200 bg-white">
    <div class="mx-auto grid max-w-[1440px] gap-6 px-5 py-8 sm:grid-cols-2 lg:grid-cols-4 lg:px-10">
        <div class="flex gap-3"><span class="material-symbols-outlined text-green-700">local_shipping</span>
            <div><strong class="text-base">Free shipping</strong>
                <p class="mt-1 text-[13px] text-zinc-500">On all orders above ₹2,000.</p>
            </div>
        </div>
        <div class="flex gap-3"><span class="material-symbols-outlined text-green-700">replay</span>
            <div><strong class="text-base">30-day returns</strong>
                <p class="mt-1 text-[13px] text-zinc-500">Hassle-free returns, no questions.</p>
            </div>
        </div>
        <div class="flex gap-3"><span class="material-symbols-outlined text-green-700">verified_user</span>
            <div><strong class="text-base">2-year warranty</strong>
                <p class="mt-1 text-[13px] text-zinc-500">Included with every frame.</p>
            </div>
        </div>
        <div class="flex gap-3"><span class="material-symbols-outlined text-green-700">home</span>
            <div><strong class="text-base">Try at home</strong>
                <p class="mt-1 text-[13px] text-zinc-500">Pick five frames, try in comfort.</p>
            </div>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>