<?php
require_once APP_ROOT . '/app/bootstrap.php';
$pageTitle = $pageTitle ?? 'Premium Eyewear';
$activeCategory = $activeCategory ?? '';
$currentPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: 'index.php');
$success = flash('success');
$error = flash('error');
$viewer = current_user();
$viewerAvatar = avatar_url($viewer);
$unreadNotifications = $viewer ? unread_notification_count((int) $viewer['id']) : 0;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Lensify — premium eyewear for modern vision.">
    <title><?= h($pageTitle) ?> · Lensify</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    mono: ['"DM Mono"', 'ui-monospace', 'monospace']
                },
                colors: {
                    ink: '#151c27',
                    paper: '#f9f9ff',
                    mist: '#f0f3ff',
                    line: '#e5e7eb',
                    success: '#006e2d'
                },
                boxShadow: {
                    soft: '0 4px 20px rgba(0,0,0,.04)',
                    lift: '0 2px 4px rgba(21,28,39,.04), 0 18px 38px -20px rgba(21,28,39,.26)',
                    hair: '0 1px 0 0 rgba(21,28,39,.05)'
                },
                maxWidth: {
                    shell: '1440px'
                },
                transitionTimingFunction: {
                    soft: 'cubic-bezier(.32,.72,0,1)'
                }
            }
        }
    }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Mono&family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;1,300;1,400&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
        rel="stylesheet">
    <link href="<?= h(url('assets/css/app.css?v=' . filemtime(APP_ROOT . '/assets/css/app.css'))) ?>" rel="stylesheet">
</head>

<body class="min-h-screen bg-paper font-sans text-ink antialiased">
    <?php require APP_ROOT . '/includes/loader.php'; ?>
    <div
        class="bg-ink px-4 py-2.5 text-center font-mono text-[10px] uppercase leading-none tracking-[.16em] text-white/90 sm:text-[11px] sm:tracking-[.2em]">
        Free shipping on orders above ₹2,000</div>
    <header
        class="sticky top-0 z-40 border-b border-ink/10 bg-paper/80 shadow-hair backdrop-blur-xl backdrop-saturate-150">
        <div
            class="mx-auto flex h-16 max-w-shell items-center justify-between gap-3 px-4 sm:h-[76px] sm:px-6 lg:h-20 lg:px-8 xl:h-[88px] xl:px-10">
            <a class="group flex shrink-0 items-center gap-2 text-[19px] font-extrabold leading-none tracking-[-.06em] sm:gap-2.5 sm:text-2xl lg:text-[26px]"
                href="<?= h(url()) ?>"><span
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] bg-ink text-[12px] font-bold leading-none tracking-normal text-white shadow-lift ring-1 ring-inset ring-white/10 transition duration-300 ease-soft group-hover:-rotate-6 sm:h-9 sm:w-9 sm:rounded-xl sm:text-[13px] lg:h-10 lg:w-10 lg:text-sm">L</span>Lensify</a>
            <nav class="hidden items-center gap-1 xl:flex" aria-label="Main navigation">
                <?php foreach (['Eyeglasses', 'Sunglasses', 'Reading Glasses', 'Computer Glasses'] as $category): ?>
                <a class="nav-link <?= $activeCategory === $category ? 'nav-link-active' : '' ?>"
                    href="<?= h(url('shop.php?category=' . rawurlencode($category))) ?>"><?= h($category) ?></a>
                <?php endforeach; ?>
                <a class="nav-link" href="<?= h(url('shop.php?sort=price_asc')) ?>">Offers</a>
            </nav>
            <div class="flex items-center gap-0.5 sm:gap-1">
                <a class="icon-button hidden md:inline-flex" href="<?= h(url('shop.php')) ?>" aria-label="Search"><span
                        class="material-symbols-outlined">search</span></a>
                <a class="profile-nav-button" href="<?= h(url($viewer ? 'account.php' : 'login.php')) ?>"
                    aria-label="<?= $viewer ? 'My profile' : 'Sign in' ?>"><?php if ($viewerAvatar): ?><img
                        class="h-full w-full object-cover" src="<?= h($viewerAvatar) ?>"
                        alt="<?= h($viewer['first_name']) ?>"><?php elseif ($viewer): ?><span><?= h(initials($viewer)) ?></span><?php else: ?><span
                        class="material-symbols-outlined">person</span><?php endif; ?><span
                        class="hidden max-w-[8rem] truncate text-[11px] font-bold uppercase tracking-[.06em] xl:inline"><?= $viewer ? h($viewer['first_name']) : 'Sign in' ?></span></a>
                <?php if ($viewer && !is_admin()): ?><a class="icon-button relative"
                    href="<?= h(url('notifications.php')) ?>" aria-label="Notifications"><span
                        class="material-symbols-outlined">notifications</span><?php if ($unreadNotifications): ?><span
                        class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-success px-1 text-[9px] font-bold leading-none tabular-nums text-white ring-2 ring-paper"><?= $unreadNotifications ?></span><?php endif; ?></a><?php endif; ?>
                <a class="icon-button" href="<?= h(url('wishlist.php')) ?>" aria-label="Wishlist"><span
                        class="material-symbols-outlined">favorite</span></a>
                <a class="icon-button relative" href="<?= h(url('cart.php')) ?>" aria-label="Shopping bag"><span
                        class="material-symbols-outlined">shopping_bag</span><?php if (cart_count()): ?><span
                        class="absolute -right-0.5 -top-0.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-success px-1 text-[9px] font-bold leading-none tabular-nums text-white ring-2 ring-paper"><?= cart_count() ?></span><?php endif; ?></a>
                <button class="icon-button xl:hidden" type="button" data-menu-toggle aria-expanded="false"
                    aria-controls="mobile-navigation" aria-label="Open navigation"><span
                        class="material-symbols-outlined">menu</span></button>
            </div>
        </div>
        <div class="border-t border-ink/[.07] bg-white px-4 py-2.5 sm:px-6 xl:hidden">
            <form
                class="mx-auto flex max-w-shell items-center gap-2 rounded-full border border-line bg-mist p-1 pl-3.5 transition duration-200 ease-soft focus-within:border-ink focus-within:bg-white focus-within:shadow-soft"
                action="<?= h(url('shop.php')) ?>" method="get" role="search">
                <label class="sr-only" for="mobile-search">Search frames</label>
                <span class="material-symbols-outlined shrink-0 text-[20px] text-zinc-400">search</span>
                <?php foreach (['category', 'shape', 'material', 'gender', 'sort'] as $searchFilter): ?>
                <?php if (!empty($_GET[$searchFilter])): ?><input type="hidden" name="<?= h($searchFilter) ?>"
                    value="<?= h($_GET[$searchFilter]) ?>"><?php endif; ?>
                <?php endforeach; ?>
                <input
                    class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[13px] text-ink placeholder:text-zinc-400 focus:ring-0 sm:text-sm"
                    id="mobile-search" name="q" placeholder="Search frames, shapes or brands" type="search"
                    value="<?= h($_GET['q'] ?? '') ?>">
                <button
                    class="shrink-0 rounded-full bg-ink px-4 py-2 text-[10px] font-bold uppercase tracking-[.1em] text-white transition duration-200 ease-soft hover:bg-[#232c3b] active:scale-95 sm:text-[11px]"
                    type="submit">Search</button>
            </form>
        </div>
        <nav class="hidden border-t border-ink/[.07] bg-white px-4 pb-5 pt-3 shadow-soft sm:px-6 xl:hidden"
            id="mobile-navigation" data-menu aria-label="Mobile navigation">
            <div class="mx-auto grid max-w-shell gap-2 sm:grid-cols-2 md:grid-cols-3">
                <?php foreach (['Eyeglasses', 'Sunglasses', 'Reading Glasses', 'Computer Glasses'] as $category): ?>
                <a class="mobile-nav-link <?= $activeCategory === $category ? 'mobile-nav-link-active' : '' ?>"
                    href="<?= h(url('shop.php?category=' . rawurlencode($category))) ?>"><span><?= h($category) ?></span><span
                        class="material-symbols-outlined">arrow_forward</span></a>
                <?php endforeach; ?>
                <a class="mobile-nav-link" href="<?= h(url('shop.php?sort=price_asc')) ?>"><span>Offers</span><span
                        class="material-symbols-outlined">arrow_forward</span></a>
            </div>
        </nav>
    </header>
    <?php if ($success || $error): ?>
    <div class="fixed inset-x-4 bottom-5 z-50 rounded-xl border px-4 py-3.5 text-sm font-medium leading-snug shadow-lift sm:inset-x-auto sm:right-6 sm:max-w-sm <?= $error ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800' ?>"
        data-flash><?= h($error ?: $success) ?></div>
    <?php endif; ?>
    <main>
