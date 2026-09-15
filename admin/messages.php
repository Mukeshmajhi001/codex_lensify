<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['mark_read', 'delete_message'], true)) {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete_message') {
        db()->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
        db()->prepare('DELETE FROM notifications WHERE link_url = ?')->execute(['admin/messages.php?id=' . $id]);
        flash('success', 'Message deleted.');
        redirect('admin/messages.php');
    }
    db()->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([$id]);
    mark_notifications_read_for_target('admin/messages.php?id=' . $id);
    flash('success', 'Message marked as read.');
    redirect('admin/messages.php');
}
$openedMessageId = (int) ($_GET['id'] ?? 0);
if ($openedMessageId > 0) {
    db()->prepare('UPDATE contact_messages SET is_read=1 WHERE id=?')->execute([$openedMessageId]);
    mark_notifications_read_for_target('admin/messages.php?id=' . $openedMessageId);
}
$messages = db_available() ? db()->query('SELECT * FROM contact_messages ORDER BY is_read ASC, created_at DESC')->fetchAll() : [];
$adminPage = 'messages';
$pageTitle = 'Customer messages';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Support</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Customer messages</h1>
    <p class="mt-2 text-sm text-zinc-500">Reply to questions that need a human touch.</p>
</div>
<section class="mt-8 space-y-4"><?php foreach ($messages as $message): ?><article
            class="rounded-2xl border <?= $message['is_read'] ? 'border-zinc-200 bg-white' : 'border-zinc-300 bg-zinc-50' ?> p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <strong><?= h($message['name']) ?></strong><?php if (!$message['is_read']): ?><span
                                class="badge bg-blue-100 text-blue-700">New</span><?php endif; ?>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500"><?= h($message['email']) ?> ·
                        <?= date('d M Y, H:i', strtotime($message['created_at'])) ?></p>
            </div><div class="flex flex-wrap gap-2"><?php if (!$message['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden"
                            name="action" value="mark_read"><input type="hidden" name="id" value="<?= $message['id'] ?>"><button
                            class="button button-secondary py-2" type="submit">Mark read</button></form><?php endif; ?>
                    <form method="post" onsubmit="return confirm('Delete this message permanently?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int) $message['id'] ?>"><button class="button border border-red-200 bg-red-50 py-2 text-red-700 hover:bg-red-100" type="submit">Delete</button></form></div>
            </div>
            <h2 class="mt-5 text-base font-bold"><a class="hover:underline" href="<?= h(url('admin/messages.php?id=' . (int) $message['id'])) ?>"><?= h($message['subject']) ?></a></h2>
            <p class="mt-2 max-w-3xl whitespace-pre-line text-sm leading-7 text-zinc-600"><?= h($message['message']) ?></p>
        </article><?php endforeach; ?><?php if (!$messages): ?><div
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center text-sm text-zinc-500">Customer
            messages will appear here.</div><?php endif; ?></section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>
