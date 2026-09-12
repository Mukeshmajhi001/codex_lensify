<?php
require_once __DIR__ . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'contact') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    if (!$name || !$email || !$subject || !$message) {
        flash('error', 'Please complete all fields.');
    } elseif (!db_available()) {
        flash('error', 'Database is not ready. Import database/final.sql first.');
    } else {
        db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)')->execute([$name, $email, $subject, $message]);
        flash('success', 'Thank you — we’ll reply within one business day.');
        redirect('contact.php');
    }
}
$pageTitle = 'Contact us';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1180px] px-5 py-12 lg:px-10">
    <div class="grid gap-12 lg:grid-cols-[.8fr_1.2fr]">
        <div>
            <p class="label">Lensify support</p>
            <h1 class="mt-2 text-5xl font-bold tracking-[-.06em]">We’re here to help.</h1>
            <p class="mt-6 max-w-sm text-sm leading-7 text-zinc-600">From finding the right fit to caring for your
                favourite pair, our team has your back.</p>
            <div class="mt-10 space-y-5 text-sm">
                <div><span class="label">Email</span><a class="font-semibold underline"
                        href="mailto:hello@lensify.test">hello@lensify.test</a></div>
                <div><span class="label">Hours</span>
                    <p>Sunday–Friday · 10:00–18:00 NPT</p>
                </div>
                <div><span class="label">Returns</span>
                    <p>30 days, completely uncomplicated.</p>
                </div>
            </div>
        </div>
        <form class="rounded-xl border border-zinc-200 bg-white p-6 sm:p-8" method="post"><?= csrf_field() ?><input
                type="hidden" name="action" value="contact">
            <h2 class="text-2xl font-bold">Send a message</h2>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div><label class="label">Your name</label><input class="input" name="name" required
                        value="<?= h(current_user() ? current_user()['first_name'] . ' ' . current_user()['last_name'] : '') ?>">
                </div>
                <div><label class="label">Email address</label><input class="input" type="email" name="email" required
                        value="<?= h(current_user()['email'] ?? '') ?>"></div>
            </div>
            <div class="mt-4"><label class="label">Subject</label><input class="input" name="subject" required
                    placeholder="How can we help?"></div>
            <div class="mt-4"><label class="label">Message</label><textarea class="input min-h-36" name="message"
                    required placeholder="Tell us a little more..."></textarea></div><button
                class="button button-primary mt-6" type="submit">Send message</button>
        </form>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
