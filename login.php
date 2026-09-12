<?php
require_once __DIR__ . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    if (!$email || !$password || !db_available()) {
        flash('error', db_available() ? 'Enter a valid email and password.' : 'Database is not ready. Import database/final.sql first.');
    } else {
        $statement = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            merge_guest_wishlist((int) $user['id']);
            db()->prepare('INSERT INTO user_login_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)')->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
            flash('success', 'Welcome back, ' . $user['first_name'] . '.');
            redirect($user['role'] === 'admin' ? 'admin/index.php' : 'index.php');
        }
        flash('error', 'Incorrect email or password.');
    }
}
if (current_user()) {
    redirect('index.php');
}
$pageTitle = 'Sign in';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto grid min-h-[70vh] max-w-[1100px] items-center gap-10 px-5 py-12 lg:grid-cols-2 lg:px-10">
    <div class="hidden rounded-2xl bg-black p-10 text-white lg:block">
        <p class="text-[11px] font-bold uppercase tracking-[.16em] text-white/60">Lensify members</p>
        <h1 class="mt-5 text-5xl font-bold leading-tight tracking-[-.06em]">Every frame,<br>all in one place.</h1>
        <p class="mt-6 max-w-sm text-sm leading-7 text-white/65">Save favourites, track orders and keep your vision
            profile ready for your next pair.</p>
    </div>
    <div class="mx-auto w-full max-w-md">
        <p class="label">Welcome back</p>
        <h1 class="text-4xl font-bold tracking-[-.05em]">Sign in</h1>
        <p class="mt-3 text-sm text-zinc-600">New to Lensify? <a class="font-bold underline"
                href="<?= h(url('register.php')) ?>">Create an account</a></p>
        <form class="mt-8 space-y-5" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="login">
            <div><label class="label">Email address</label><input class="input" type="email" name="email" required
                    autocomplete="email"></div>
            <div><label class="label" for="login-password">Password</label>
                <div class="relative"><input class="input pr-12" id="login-password" type="password" name="password"
                        required autocomplete="current-password"><button
                        class="absolute inset-y-0 right-0 grid w-12 place-items-center text-zinc-500 hover:text-black"
                        type="button" data-password-toggle="login-password" aria-label="Show password"><span
                            class="material-symbols-outlined text-lg">visibility</span></button></div>
            </div><button class="button button-primary w-full" type="submit">Sign in</button>
        </form>

    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
