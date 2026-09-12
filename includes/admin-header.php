<?php
require_once APP_ROOT . '/app/bootstrap.php';
require_admin();

function stock_alert_classes(int $stock): array
{
    if ($stock < 30) {
        return ['text' => 'text-red-700', 'badge' => 'bg-red-100 text-red-800', 'row' => 'border-red-200 bg-red-50/40'];
    }
    if ($stock < 40) {
        return ['text' => 'text-orange-700', 'badge' => 'bg-orange-100 text-orange-800', 'row' => 'border-orange-200 bg-orange-50/40'];
    }
    if ($stock < 50) {
        return ['text' => 'text-yellow-700', 'badge' => 'bg-yellow-100 text-yellow-800', 'row' => 'border-yellow-200 bg-yellow-50/40'];
    }
    return ['text' => 'text-zinc-700', 'badge' => 'bg-zinc-100 text-zinc-700', 'row' => 'border-zinc-200 bg-white'];
}

$adminPage = $adminPage ?? 'overview';
$pageTitle = $pageTitle ?? 'Admin console';
$success = flash('success');
$error = flash('error');
$admin = current_user();
$adminAvatar = avatar_url($admin);
$adminUnreadNotifications = unread_notification_count((int) $admin['id']);
$adminActivityCounts = [
    'orders' => unread_notification_count_by_title('New order received', (int) $admin['id']),
    'reviews' => unread_notification_count_by_title('New review received', (int) $admin['id']),
];
$adminSections = [
    'Store' => [
        'overview' => ['Dashboard', 'dashboard', 'admin/index.php'],
        'orders' => ['Orders', 'shopping_cart', 'admin/orders.php'],
        'returns' => ['Return requests', 'assignment_return', 'admin/returns.php'],
    ],
    'Catalogue' => [
        'products' => ['Products', 'inventory_2', 'admin/products.php'],
        'catalogue' => ['Categories & brands', 'category', 'admin/catalogue.php'],
        'variants' => ['Variants', 'tune', 'admin/variants.php'],
        'inventory' => ['Stock history', 'history', 'admin/inventory.php'],
        'banners' => ['Banners', 'view_carousel', 'admin/banners.php'],
        'error-page' => ['404 Page image', 'broken_image', 'admin/404.php'],
    ],
    'Growth' => [
        'coupons' => ['Coupons', 'local_offer', 'admin/coupons.php'],
        'reviews' => ['Reviews', 'star', 'admin/reviews.php'],
        'messages' => ['Messages', 'mail', 'admin/messages.php'],
        'reports' => ['Reports', 'monitoring', 'admin/reports.php'],
    ],
    'System' => [
        'customers' => ['Customers', 'group', 'admin/customers.php'],
        'activity' => ['Activity logs', 'policy', 'admin/activity.php'],
        'notifications' => ['Notifications', 'notifications', 'admin/notifications.php'],
        'settings' => ['Settings', 'settings', 'admin/settings.php'],
    ],
];
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> · Lensify Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    colors: {
                        canvas: '#fbf9f9'
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap"
        rel="stylesheet">
    <link href="<?= h(url('assets/css/app.css?v=' . filemtime(APP_ROOT . '/assets/css/app.css'))) ?>" rel="stylesheet">
</head>

<body class="min-h-screen bg-canvas font-sans text-zinc-900">
    <?php require APP_ROOT . '/includes/loader.php'; ?>
    <div class="flex min-h-screen">
        <aside
            class="sticky top-0 hidden h-screen w-[276px] shrink-0 flex-col border-r border-zinc-200 bg-white p-5 lg:flex">
            <a class="flex items-center gap-3 px-2 py-2" href="<?= h(url('admin/index.php')) ?>"><span
                    class="grid h-11 w-11 place-items-center rounded-xl bg-black text-white"><span
                        class="material-symbols-outlined">visibility</span></span><span><strong
                        class="block text-xl leading-none tracking-[-.07em]">Lensify</strong><small
                        class="mt-1 block text-[10px] font-semibold uppercase tracking-[.13em] text-zinc-500">Admin
                        console</small></span></a>
            <nav class="admin-scrollbar mt-7 min-h-0 flex-1 space-y-5 overflow-y-auto pr-1"
                aria-label="Admin navigation"><?php foreach ($adminSections as $section => $links): ?><div>
                        <p class="mb-1 px-3 text-[10px] font-bold uppercase tracking-[.13em] text-zinc-400">
                            <?= h($section) ?></p>
                        <?php foreach ($links as $key => [$label, $icon, $href]): ?><?php $activityCount = $adminActivityCounts[$key] ?? 0; ?><a
                            class="admin-nav justify-between <?= $adminPage === $key ? 'admin-nav-active' : '' ?>"
                            href="<?= h(url($href)) ?>"><span class="flex min-w-0 items-center gap-3"><span
                                    class="material-symbols-outlined"><?= h($icon) ?></span><?= h($label) ?></span><?php if ($activityCount): ?><span
                                    class="badge bg-amber-100 text-amber-800"
                                    aria-label="<?= $activityCount ?> new <?= h($label) ?>"><?= $activityCount ?></span><?php endif; ?></a><?php endforeach; ?>
                    </div><?php endforeach; ?></nav>
            <div class="mt-4 border-t border-zinc-200 pt-4"><a class="admin-nav justify-between"
                    href="<?= h(url('admin/notifications.php')) ?>"><span class="flex items-center gap-3"><span
                            class="material-symbols-outlined">notifications</span>Notifications</span><?php if ($adminUnreadNotifications): ?><span
                            class="badge bg-green-100 text-green-700"><?= $adminUnreadNotifications ?></span><?php endif; ?></a><a
                    class="admin-nav" href="<?= h(url()) ?>"><span
                        class="material-symbols-outlined">storefront</span>View storefront</a><a class="admin-nav"
                    href="<?= h(url('logout.php')) ?>"><span class="material-symbols-outlined">logout</span>Log
                    out</a><a class="mt-2 flex items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-zinc-100"
                    href="<?= h(url('admin/settings.php')) ?>"><?php if ($adminAvatar): ?><img
                            class="h-10 w-10 rounded-full object-cover" src="<?= h($adminAvatar) ?>"
                            alt="<?= h($admin['first_name']) ?>"><?php else: ?><span
                            class="grid h-10 w-10 place-items-center rounded-full bg-zinc-900 text-xs font-bold text-white"><?= h(initials($admin)) ?></span><?php endif; ?><span
                        class="min-w-0"><strong
                            class="block truncate text-sm"><?= h($admin['first_name'] . ' ' . $admin['last_name']) ?></strong><small
                            class="block text-xs text-zinc-500">Profile settings</small></span></a></div>
        </aside>
        <div class="min-w-0 flex-1">
            <header
                class="sticky top-0 z-30 flex h-20 items-center justify-between border-b border-zinc-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:hidden">
                <a class="flex items-center gap-2 text-lg font-extrabold tracking-[-.06em]"
                    href="<?= h(url('admin/index.php')) ?>"><span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-black text-xs tracking-normal text-white">L</span>Lensify
                    <span class="font-medium text-zinc-400">/ Admin</span></a>
                <div class="flex items-center gap-2"><a class="profile-nav-button"
                        href="<?= h(url('admin/settings.php')) ?>"
                        aria-label="Admin profile"><?php if ($adminAvatar): ?><img src="<?= h($adminAvatar) ?>"
                                alt="<?= h($admin['first_name']) ?>"><?php else: ?><span><?= h(initials($admin)) ?></span><?php endif; ?></a><button
                        class="icon-button" type="button" data-menu-toggle data-menu-target="#admin-mobile-menu"
                        aria-expanded="false" aria-controls="admin-mobile-menu" aria-label="Open admin navigation"><span
                            class="material-symbols-outlined">menu</span></button></div>
            </header>
            <nav class="hidden border-b border-zinc-200 bg-white p-4 shadow-soft lg:hidden" id="admin-mobile-menu"
                data-menu aria-label="Mobile admin navigation">
                <div class="grid gap-5 sm:grid-cols-2"><?php foreach ($adminSections as $section => $links): ?><div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-[.13em] text-zinc-400">
                                <?= h($section) ?></p>
                            <div class="space-y-1">
                                <?php foreach ($links as $key => [$label, $icon, $href]): ?><?php $activityCount = $adminActivityCounts[$key] ?? 0; ?><a
                                    class="admin-nav justify-between <?= $adminPage === $key ? 'admin-nav-active' : '' ?>"
                                    href="<?= h(url($href)) ?>"><span class="flex min-w-0 items-center gap-3"><span
                                            class="material-symbols-outlined"><?= h($icon) ?></span><?= h($label) ?></span><?php if ($activityCount): ?><span
                                            class="badge bg-amber-100 text-amber-800"
                                            aria-label="<?= $activityCount ?> new <?= h($label) ?>"><?= $activityCount ?></span><?php endif; ?></a><?php endforeach; ?>
                            </div>
                        </div><?php endforeach; ?></div>
                <div class="mt-5 flex gap-2 border-t border-zinc-200 pt-4"><a
                        class="button button-secondary flex-1 py-2" href="<?= h(url()) ?>">View store</a><a
                        class="button button-primary flex-1 py-2" href="<?= h(url('logout.php')) ?>">Log out</a></div>
            </nav>
            <main class="p-4 sm:p-6 lg:p-7 xl:p-10"><?php if ($success || $error): ?><div
                        class="mb-5 rounded-xl border px-4 py-3 text-sm <?= $error ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800' ?>"
                        data-flash><?= h($error ?: $success) ?></div><?php endif; ?>
