<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'save_admin_profile') {
        $first = trim((string)($_POST['first_name'] ?? ''));
        $last = trim((string)($_POST['last_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        $upload = upload_image('profile_image');
        if (!$first || !$last) {
            flash('error', 'First and last names are required.');
        } elseif ($upload['error']) {
            flash('error', $upload['error']);
        } else {
            $image = $upload['path'] ?: current_user()['profile_image'];
            db()->prepare('UPDATE users SET first_name=?,last_name=?,phone=?,bio=?,email_notifications=?,profile_image=? WHERE id=?')->execute([$first, $last, $phone ?: null, $bio ?: null, isset($_POST['email_notifications']) ? 1 : 0, $image, current_user()['id']]);
            log_admin('Updated admin profile');
            flash('success', 'Your admin profile has been saved.');
            redirect('admin/settings.php');
        }
    }
    if ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $statement = db()->prepare('SELECT password_hash FROM users WHERE id=?');
        $statement->execute([current_user()['id']]);
        $hash = $statement->fetchColumn();
        if (!password_verify($current, (string)$hash)) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must have at least 8 characters.');
        } else {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), current_user()['id']]);
            log_admin('Changed admin password');
            flash('success', 'Password updated.');
        }
        redirect('admin/settings.php');
    }
    if ($action === 'save_store_settings') {
        $keys = ['store_name', 'support_email', 'free_shipping_threshold', 'announcement_text'];
        foreach ($keys as $key) {
            $value = trim((string)($_POST[$key] ?? ''));
            db()->prepare('INSERT INTO site_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute([$key, $value, current_user()['id']]);
        }
        $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
        db()->prepare('INSERT INTO site_settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute(['maintenance_mode', $maintenance, current_user()['id']]);
        log_admin('Updated store settings');
        flash('success', 'Store settings saved.');
        redirect('admin/settings.php');
    }
}
$settings = [];
foreach (db()->query('SELECT setting_key,setting_value FROM site_settings')->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$admin = current_user();
$adminPage = 'settings';
$pageTitle = 'Settings';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">System</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Settings</h1>
    <p class="mt-2 text-sm text-zinc-500">Manage your admin identity, security and store-wide defaults.</p>
</div>
<div class="mt-8 grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl border border-zinc-300 bg-white p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold">Admin profile</h2>
                <p class="mt-1 text-sm text-zinc-500">Shown in the console navigation.</p>
            </div><?php if ($avatar = avatar_url($admin)): ?><img class="h-14 w-14 rounded-full object-cover"
                    src="<?= h($avatar) ?>" alt=""><?php else: ?><span
                    class="grid h-14 w-14 place-items-center rounded-full bg-black text-sm font-bold text-white"><?= h(initials($admin)) ?></span><?php endif; ?>
        </div>
        <form class="mt-6 space-y-4" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden"
                name="action" value="save_admin_profile">
            <div><label class="label">Profile photo</label><input
                    class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-black file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                    accept="image/jpeg,image/png,image/webp,image/gif" name="profile_image" type="file"></div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div><label class="label">First name</label><input class="input" name="first_name" required
                        value="<?= h($admin['first_name']) ?>"></div>
                <div><label class="label">Last name</label><input class="input" name="last_name" required
                        value="<?= h($admin['last_name']) ?>"></div>
            </div>
            <div><label class="label">Phone</label><input class="input" name="phone"
                    value="<?= h($admin['phone'] ?? '') ?>"></div>
            <div><label class="label">Bio</label><textarea class="input min-h-20" maxlength="255"
                    name="bio"><?= h($admin['bio'] ?? '') ?></textarea></div><label class="flex gap-2 text-sm"><input
                    class="rounded border-zinc-300 text-black focus:ring-black" name="email_notifications"
                    type="checkbox" <?= !empty($admin['email_notifications']) ? 'checked' : '' ?>>Receive activity
                summaries
                by email</label><button class="button button-primary" type="submit">Save profile</button>
        </form>
    </section>
    <section class="rounded-2xl border border-zinc-300 bg-white p-6">
        <h2 class="font-bold">Security</h2>
        <p class="mt-1 text-sm text-zinc-500">Choose a strong unique password for the admin console.</p>
        <form class="mt-6 space-y-4" method="post"><?= csrf_field() ?><input type="hidden" name="action"
                value="change_password">
            <div><label class="label">Current password</label><input class="input" name="current_password" required
                    type="password"></div>
            <div><label class="label">New password</label><input class="input" minlength="8" name="new_password"
                    required type="password">
                <p class="mt-2 text-[11px] text-zinc-500">Minimum 8 characters.</p>
            </div><button class="button button-secondary" type="submit">Change password</button>
        </form>
    </section>
    <section class="rounded-2xl border border-zinc-300 bg-white p-6 xl:col-span-2">
        <h2 class="font-bold">Store settings</h2>
        <p class="mt-1 text-sm text-zinc-500">Update operational details shown across the storefront.</p>
        <form class="mt-6 grid gap-4 md:grid-cols-2" method="post"><?= csrf_field() ?><input type="hidden" name="action"
                value="save_store_settings">
            <div><label class="label">Store name</label><input class="input" name="store_name"
                    value="<?= h($settings['store_name'] ?? 'Lensify') ?>"></div>
            <div><label class="label">Support email</label><input class="input" name="support_email" type="email"
                    value="<?= h($settings['support_email'] ?? '') ?>"></div>
            <div><label class="label">Free shipping threshold (NPR)</label><input class="input" min="0"
                    name="free_shipping_threshold" type="number"
                    value="<?= h($settings['free_shipping_threshold'] ?? '2000') ?>"></div>
            <div class="md:col-span-2"><label class="label">Announcement text</label><input class="input"
                    name="announcement_text" value="<?= h($settings['announcement_text'] ?? '') ?>"></div><label
                class="flex items-start gap-3 rounded-lg bg-amber-50 p-4 text-sm md:col-span-2"><input
                    class="mt-0.5 rounded border-amber-300 text-black focus:ring-black" name="maintenance_mode"
                    type="checkbox" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>><span><strong
                        class="block">Maintenance mode</strong><small class="mt-1 block text-zinc-600">Storefront
                        maintenance screen should be enabled before using this in production.</small></span></label>
            <div class="md:col-span-2"><button class="button button-primary" type="submit">Save store settings</button>
            </div>
        </form>
    </section>
</div>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>