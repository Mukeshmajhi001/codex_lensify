<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([current_user()['id']]);
    flash('success', 'All notifications marked as read.');
    redirect('admin/notifications.php');
}

$statement = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 80');
$statement->execute([current_user()['id']]);
$notifications = $statement->fetchAll();
db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([current_user()['id']]);
$adminPage = 'notifications';
$pageTitle = 'Notifications';
require APP_ROOT . '/includes/admin-header.php';
?>
<div class="flex items-end justify-between gap-4">
    <div>
        <p class="label">System</p>
        <h1 class="text-3xl font-bold tracking-[-.05em]">Notifications</h1>
        <p class="mt-2 text-sm text-zinc-500">New orders and important store updates.</p>
    </div><?php if ($notifications): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action"
                value="mark_all_read"><button class="text-xs font-bold underline underline-offset-4" type="submit">Mark all
                read</button></form><?php endif; ?>
</div>
<?php if ($notifications): ?><section class="mt-8 space-y-3">
        <?php foreach ($notifications as $notification): ?><?php $isReviewNotification = str_contains((string) $notification['link_url'], 'reviews.php'); ?>
        <article
            class="rounded-2xl border p-5 <?= $notification['is_read'] ? 'border-zinc-300 bg-white' : 'border-blue-200 bg-blue-50/50' ?>">
            <div class="flex gap-3"><span
                    class="material-symbols-outlined mt-0.5 text-zinc-600"><?= $isReviewNotification ? 'star' : 'notifications' ?></span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-bold"><?= h($notification['title']) ?></h2><time
                            class="text-[11px] text-zinc-500"><?= date('d M Y, H:i', strtotime($notification['created_at'])) ?></time>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-zinc-600"><?= h($notification['message']) ?></p>
                    <?php if ($notification['link_url']): ?><a
                            class="mt-3 inline-block text-xs font-bold underline underline-offset-4"
                            href="<?= h(url($notification['link_url'])) ?>"><?= $isReviewNotification ? 'Open review' : 'Open order' ?></a><?php endif; ?>
                </div>
            </div>
        </article><?php endforeach; ?>
    </section><?php else: ?><div
        class="mt-8 rounded-2xl border border-dashed border-zinc-300 px-6 py-20 text-center text-sm text-zinc-500"><span
            class="material-symbols-outlined block text-5xl text-zinc-400">notifications_none</span>
        <p class="mt-4">No store notifications yet.</p>
    </div><?php endif; ?>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>