<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
$activities = db()->query("SELECT 'admin' source,al.event,al.ip_address,al.created_at,u.first_name,u.last_name FROM admin_logs al LEFT JOIN users u ON u.id=al.admin_id UNION ALL SELECT 'login' source,'User sign in' event,ul.ip_address,ul.created_at,u.first_name,u.last_name FROM user_login_logs ul JOIN users u ON u.id=ul.user_id ORDER BY created_at DESC LIMIT 150")->fetchAll();
$adminPage = 'activity';
$pageTitle = 'Activity logs';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">System</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Activity logs</h1>
    <p class="mt-2 text-sm text-zinc-500">Security and management activity across your store.</p>
</div>
<section class="mt-8 overflow-hidden rounded-2xl border border-zinc-300 bg-white">
    <div class="divide-y divide-zinc-200"><?php foreach ($activities as $activity): ?><article
                class="flex gap-4 px-5 py-5 sm:px-6"><span
                    class="grid h-10 w-10 flex-none place-items-center rounded-full <?= $activity['source'] === 'login' ? 'bg-blue-100 text-blue-700' : 'bg-zinc-100 text-zinc-700' ?>"><span
                        class="material-symbols-outlined"><?= $activity['source'] === 'login' ? 'login' : 'policy' ?></span></span>
                <div class="min-w-0 flex-1"><strong class="block text-sm"><?= h($activity['event']) ?></strong>
                    <p class="mt-1 text-xs text-zinc-500">
                        <?= h(trim(($activity['first_name'] ?? 'System') . ' ' . ($activity['last_name'] ?? ''))) ?> ·
                        <?= h($activity['ip_address'] ?: 'Local') ?></p>
                </div><time
                    class="whitespace-nowrap text-xs text-zinc-500"><?= date('d M, H:i', strtotime($activity['created_at'])) ?></time>
            </article><?php endforeach; ?><?php if (!$activities): ?><p
                class="px-6 py-14 text-center text-sm text-zinc-500">
                Activity will appear here as the store is used.</p><?php endif; ?></div>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>