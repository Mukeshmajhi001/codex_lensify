<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    try {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([current_user()['id']]);
        flash('success', 'All notifications marked as read.');
    } catch (Throwable) {
        flash('error', 'Notifications are not ready yet. Please run the database migration.');
    }
    redirect('notifications.php');
}

$notifications = [];
try {
    $statement = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 60');
    $statement->execute([current_user()['id']]);
    $notifications = $statement->fetchAll();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([current_user()['id']]);
} catch (Throwable) {
    // A clear empty state is preferable while an existing installation is being migrated.
}

$pageTitle = 'Notifications';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-3xl px-5 py-10 lg:px-10">
    <div class="flex items-end justify-between gap-4">
        <div>
            <p class="label">My account</p>
            <h1 class="text-4xl font-bold tracking-[-.05em]">Notifications</h1>
            <p class="mt-2 text-sm text-zinc-600">Order confirmations and delivery updates appear here.</p>
        </div><?php if ($notifications): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action"
                    value="mark_all_read"><button class="text-xs font-bold underline underline-offset-4" type="submit">Mark
                    all read</button></form><?php endif; ?>
    </div>
    <?php if ($notifications): ?><div class="mt-8 space-y-3"><?php foreach ($notifications as $notification): ?><article
                    class="rounded-xl border p-5 <?= $notification['is_read'] ? 'border-zinc-200 bg-white' : 'border-blue-200 bg-blue-50/50' ?>">
                    <div class="flex gap-3"><span
                            class="material-symbols-outlined mt-0.5 text-zinc-600"><?= str_contains(strtolower($notification['title']), 'order') ? 'local_shipping' : 'notifications' ?></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h2 class="text-sm font-bold"><?= h($notification['title']) ?></h2><time
                                    class="text-[11px] text-zinc-500"
                                    datetime="<?= h($notification['created_at']) ?>"><?= date('d M Y, H:i', strtotime($notification['created_at'])) ?></time>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-zinc-600"><?= h($notification['message']) ?></p>
                            <?php if ($notification['link_url']): ?><a
                                    class="mt-3 inline-block text-xs font-bold underline underline-offset-4"
                                    href="<?= h(url($notification['link_url'])) ?>">View details</a><?php endif; ?>
                        </div>
                    </div>
                </article><?php endforeach; ?></div><?php else: ?><div
            class="mt-8 rounded-2xl border border-dashed border-zinc-300 px-6 py-20 text-center"><span
                class="material-symbols-outlined text-5xl text-zinc-400">notifications_none</span>
            <h2 class="mt-4 text-xl font-bold">No notifications yet</h2>
            <p class="mt-2 text-sm text-zinc-500">Order and delivery updates will appear here.</p>
        </div><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>