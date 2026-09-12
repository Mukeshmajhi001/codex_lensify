<?php
require_once __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    if (!$first || !$last) {
        flash('error', 'First and last names are required.');
    } else {
        $upload = upload_image('profile_image');
        if ($upload['error']) {
            flash('error', $upload['error']);
        } else {
            $image = $upload['path'] ?: (current_user()['profile_image'] ?? null);
            db()->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ?, bio = ?, email_notifications = ?, profile_image = ? WHERE id = ?')->execute([
                $first,
                $last,
                $phone ?: null,
                $bio ?: null,
                isset($_POST['email_notifications']) ? 1 : 0,
                $image,
                current_user()['id'],
            ]);
            flash('success', 'Your profile has been updated.');
            redirect('account.php');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!$currentPassword || !$newPassword || !$confirmPassword) {
        flash('error', 'Complete all password fields.');
    } elseif (strlen($newPassword) < 8) {
        flash('error', 'Your new password must be at least 8 characters.');
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        flash('error', 'The new passwords do not match.');
    } else {
        $statement = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $statement->execute([current_user()['id']]);
        $storedHash = (string) $statement->fetchColumn();
        if (!$storedHash || !password_verify($currentPassword, $storedHash)) {
            flash('error', 'Your current password is incorrect.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), current_user()['id']]);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect('account.php');
        }
    }
}

$user = current_user();
$pageTitle = 'My account';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1180px] px-5 py-10 lg:px-10">
    <div class="mb-9">
        <p class="label">My account</p>
        <h1 class="text-4xl font-bold tracking-[-.05em]">Hello, <?= h($user['first_name']) ?>.</h1>
    </div>
    <div class="grid gap-8 lg:grid-cols-[220px_1fr]">
        <aside class="h-fit rounded-xl bg-mist p-3">
            <a class="admin-nav admin-nav-active" href="<?= h(url('account.php')) ?>"><span
                    class="material-symbols-outlined">person</span>Profile</a>
            <a class="admin-nav" href="<?= h(url('orders.php')) ?>"><span
                    class="material-symbols-outlined">receipt_long</span>My orders</a>
            <a class="admin-nav" href="<?= h(url('wishlist.php')) ?>"><span
                    class="material-symbols-outlined">favorite</span>Saved frames</a>
            <?php if (is_admin()): ?><a class="admin-nav" href="<?= h(url('admin/index.php')) ?>"><span
                        class="material-symbols-outlined">admin_panel_settings</span>Admin console</a><?php endif; ?>
            <a class="admin-nav" href="<?= h(url('logout.php')) ?>"><span
                    class="material-symbols-outlined">logout</span>Sign out</a>
        </aside>
        <div class="space-y-6">
            <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold">Profile details</h2>
                        <p class="mt-1 text-sm text-zinc-500">Keep your Lensify details current.</p>
                    </div><?php if ($avatar = avatar_url($user)): ?><img
                            class="h-14 w-14 rounded-full object-cover ring-2 ring-zinc-200" src="<?= h($avatar) ?>"
                            alt="<?= h($user['first_name']) ?>"><?php else: ?><span
                            class="grid h-14 w-14 place-items-center rounded-full bg-black text-sm font-bold text-white"><?= h(initials($user)) ?></span><?php endif; ?>
                </div>
                <form class="mt-7 max-w-xl space-y-5" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_profile">
                    <div><label class="label">Profile photo</label><input
                            class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-black file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                            accept="image/jpeg,image/png,image/webp,image/gif" name="profile_image" type="file">
                        <p class="mt-2 text-[11px] text-zinc-500">Optional · JPG, PNG, WebP or GIF · Max 4 MB</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="label">First name</label><input class="input" name="first_name" required
                                value="<?= h($user['first_name']) ?>"></div>
                        <div><label class="label">Last name</label><input class="input" name="last_name" required
                                value="<?= h($user['last_name']) ?>"></div>
                    </div>
                    <div><label class="label">Email address</label><input class="input bg-zinc-50 text-zinc-500"
                            disabled value="<?= h($user['email']) ?>">
                        <p class="mt-2 text-[11px] text-zinc-500">Email changes are handled by support for account
                            security.</p>
                    </div>
                    <div><label class="label">Phone number</label><input class="input" name="phone"
                            value="<?= h($user['phone'] ?? '') ?>" placeholder="98XXXXXXXX"></div>
                    <div><label class="label">About you</label><textarea class="input min-h-24" maxlength="255"
                            name="bio"
                            placeholder="A short note about your style or vision needs..."><?= h($user['bio'] ?? '') ?></textarea>
                    </div>
                    <label class="flex items-start gap-3 rounded-lg bg-mist p-4 text-sm"><input
                            class="mt-0.5 rounded border-zinc-300 text-black focus:ring-black"
                            name="email_notifications" type="checkbox"
                            <?= !empty($user['email_notifications']) ? 'checked' : '' ?>><span><strong
                                class="block">Email updates</strong><small class="mt-1 block text-zinc-500">Order
                                tracking, new releases and member-only offers.</small></span></label>
                    <button class="button button-primary" type="submit">Save changes</button>
                </form>
            </section>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7">
                <div>
                    <h2 class="text-xl font-bold">Change password</h2>
                    <p class="mt-1 text-sm text-zinc-500">Use a new password of at least 8 characters.</p>
                </div>
                <form class="mt-7 max-w-xl space-y-5" method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
                    <div><label class="label">Current password</label><input class="input"
                            autocomplete="current-password" name="current_password" required type="password"></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><label class="label">New password</label><input class="input" autocomplete="new-password"
                                minlength="8" name="new_password" required type="password"></div>
                        <div><label class="label">Confirm new password</label><input class="input"
                                autocomplete="new-password" minlength="8" name="confirm_password" required
                                type="password"></div>
                    </div>
                    <button class="button button-secondary" type="submit">Update password</button>
                </form>
            </section>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>