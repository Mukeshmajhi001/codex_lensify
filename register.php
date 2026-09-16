<?php
require_once __DIR__ . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $first = trim((string) ($_POST['first_name'] ?? ''));
    $last = trim((string) ($_POST['last_name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    if (!$first || !$last || !$email || strlen($password) < 8) {
        flash('error', 'Enter your name, a valid email and a password of at least 8 characters.');
    } elseif (!db_available()) {
        flash('error', 'Database is not ready. Import database/final.sql first.');
    } else {
        try {
            $statement = db()->prepare('INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)');
             $statement->execute([$first, $last, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) db()->lastInsertId();
            $upload = upload_image('profile_image');
            if ($upload['error']) {
                flash('error', $upload['error']);
            } elseif ($upload['path']) {
                db()->prepare('UPDATE users SET profile_image = ? WHERE id = ?')->execute([$upload['path'], $userId]);
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            merge_guest_data($userId);
            flash('success', 'Your Lensify account is ready. Your bag and saved frames have been kept.');
            redirect('account.php');
        } catch (PDOException) {
            flash('error', 'An account with this email already exists.');
        }
    }
}
if (current_user()) {
    redirect('account.php');
}
$pageTitle = 'Create account';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-md px-5 py-16">
    <p class="label">Lensify members</p>
    <h1 class="text-4xl font-bold tracking-[-.05em]">Create an account</h1>
    <p class="mt-3 text-sm text-zinc-600">Already have one? <a class="font-bold underline"
            href="<?= h(url('login.php')) ?>">Sign in</a></p>
    <form class="mt-8 space-y-5" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden"
            name="action" value="register">
        <div class="flex items-center gap-4 rounded-xl bg-mist p-4"><span
                class="grid h-12 w-12 place-items-center rounded-full bg-black text-white"><span
                    class="material-symbols-outlined">add_a_photo</span></span>
            <div class="min-w-0 flex-1"><label class="label mb-1">Profile photo <span
                        class="normal-case font-normal tracking-normal text-zinc-400">optional</span></label><input
                    class="block w-full text-xs file:mr-3 file:rounded-md file:border-0 file:bg-black file:px-3 file:py-2 file:text-xs file:font-bold file:text-white"
                    accept="image/jpeg,image/png,image/webp,image/gif" name="profile_image" type="file">
                <p class="mt-1 text-[10px] text-zinc-500">JPG, PNG, WebP or GIF · Max 4 MB</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="label">First name</label><input class="input" name="first_name" required
                    autocomplete="given-name"></div>
            <div><label class="label">Last name</label><input class="input" name="last_name" required
                    autocomplete="family-name"></div>
        </div>
        <div><label class="label">Email address</label><input class="input" name="email" type="email" required
                autocomplete="email"></div>
        <div><label class="label" for="register-password">Password</label>
            <div class="relative"><input class="input pr-12" id="register-password" name="password" type="password"
                    minlength="8" required autocomplete="new-password"><button
                    class="absolute inset-y-0 right-0 grid w-12 place-items-center text-zinc-500 hover:text-black"
                    type="button" data-password-toggle="register-password" aria-label="Show password"><span
                        class="material-symbols-outlined text-lg">visibility</span></button></div>
            <p class="mt-2 text-[11px] text-zinc-500">Use at least 8 characters.</p>
        </div><button class="button button-primary w-full" type="submit">Create account</button>
    </form>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>