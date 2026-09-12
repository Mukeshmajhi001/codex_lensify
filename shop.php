<?php
require_once __DIR__ . '/app/bootstrap.php';
handle_store_actions();
$filters = array_filter([
    'category' => $_GET['category'] ?? '',
    'shape' => $_GET['shape'] ?? '',
    'material' => $_GET['material'] ?? '',
    'gender' => $_GET['gender'] ?? '',
    'query' => trim($_GET['q'] ?? ''),
    'sort' => $_GET['sort'] ?? '',
]);
$catalogue = products($filters);
$pageTitle = !empty($filters['query']) ? 'Search results' : ($filters['category'] ?? 'All Frames');
$activeCategory = $filters['category'] ?? '';
require APP_ROOT . '/includes/header.php';
$options = ['shape' => ['Rectangular', 'Round', 'Aviator', 'Cat Eye', 'Square'], 'material' => ['Acetate', 'Metal', 'Titanium', 'Eco-friendly'], 'gender' => ['Men', 'Women', 'Kids', 'Unisex']];
?>
<section class="mx-auto max-w-[1440px] px-5 py-10 lg:px-10 lg:py-14">
    <div class="flex flex-col gap-6 border-b border-zinc-200 pb-8 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <nav class="mb-4 text-xs text-zinc-500"><a href="<?= h(url()) ?>">Home</a> <span class="px-1">›</span>
                <?= h($pageTitle) ?></nav>
            <h1 class="text-4xl font-bold tracking-[-.05em] sm:text-5xl"><?= h($pageTitle) ?></h1>
            <p class="mt-2 text-sm text-zinc-600">Showing <?= count($catalogue) ?>
                <?= count($catalogue) === 1 ? 'result' : 'results' ?></p>
        </div>
        <form class="flex flex-wrap items-center gap-2" method="get">
            <?php foreach (['category', 'shape', 'material', 'gender', 'q'] as $preservedFilter): ?>
                <?php if (!empty($_GET[$preservedFilter])): ?><input type="hidden" name="<?= h($preservedFilter) ?>"
                        value="<?= h($_GET[$preservedFilter]) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <label class="sr-only" for="catalogue-sort">Sort frames</label>
            <select class="input w-40 py-2.5" id="catalogue-sort" name="sort">
                <option value="">Featured</option>
                <option value="newest" <?= ($_GET['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="price_asc" <?= ($_GET['sort'] ?? '') === 'price_asc' ? 'selected' : '' ?>>Price: low to
                    high</option>
                <option value="price_desc" <?= ($_GET['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Price: high
                    to low</option>
            </select><button class="button button-primary px-4 py-2.5" type="submit">Sort</button>
        </form>
    </div>
    <div class="mt-10 grid gap-10 lg:grid-cols-[220px_1fr]">
        <aside>
            <form method="get" class="space-y-8"><input type="hidden" name="q" value="<?= h($_GET['q'] ?? '') ?>"><input
                    type="hidden" name="sort" value="<?= h($_GET['sort'] ?? '') ?>">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold">Filters</h2><a class="text-xs font-bold underline"
                        href="<?= h(url('shop.php')) ?>">Clear all</a>
                </div>
                <div><label class="label">Category</label>
                    <div class="space-y-3">
                        <?php foreach (array_keys(categories()) as $category): ?>
                            <?php $categoryId = 'filter-category-' . slugify($category); ?>
                            <div class="flex items-center gap-2 text-sm">
                                <input class="rounded border-zinc-300 text-black focus:ring-black" type="radio"
                                    id="<?= h($categoryId) ?>" name="category" value="<?= h($category) ?>"
                                    <?= ($filters['category'] ?? '') === $category ? 'checked' : '' ?>>
                                <label class="cursor-pointer" for="<?= h($categoryId) ?>"><?= h($category) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div><?php foreach ($options as $name => $values): ?><div><label class="label"><?= h($name) ?></label>
                        <div class="space-y-3"><?php foreach ($values as $value): ?><label
                                    class="flex cursor-pointer items-center gap-2 text-sm"><input
                                        class="rounded border-zinc-300 text-black focus:ring-black" type="radio"
                                        name="<?= h($name) ?>" value="<?= h($value) ?>"
                                        <?= ($filters[$name] ?? '') === $value ? 'checked' : '' ?>><span><?= h($value) ?></span></label><?php endforeach; ?>
                        </div>
                    </div><?php endforeach; ?><button class="button button-secondary w-full" type="submit">Filter
                    frames</button>
            </form>
        </aside>
        <div><?php if (!$catalogue): ?><div
                    class="rounded-2xl border border-dashed border-zinc-300 px-8 py-20 text-center"><span
                        class="material-symbols-outlined text-5xl text-zinc-400">search_off</span>
                    <h2 class="mt-4 text-xl font-bold">No frames found</h2>
                    <p class="mt-2 text-sm text-zinc-500">Try adjusting your filters or browse the full collection.</p><a
                        class="button button-primary mt-6" href="<?= h(url('shop.php')) ?>">View all frames</a>
                </div><?php else: ?><div class="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 lg:gap-6">
                    <?php foreach ($catalogue as $product): ?><?php $returnTo = 'shop.php?' . http_build_query($_GET);
                                                                require APP_ROOT . '/includes/product-card.php'; ?><?php endforeach; ?>
                </div><?php endif; ?></div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
