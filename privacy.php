<?php
require_once __DIR__ . '/app/bootstrap.php';
$pageTitle = 'Privacy policy';
require APP_ROOT . '/includes/header.php';
?>
<article class="prose-lensify mx-auto max-w-3xl px-5 py-14">
    <p class="label">Legal</p>
    <h1 class="text-4xl font-bold tracking-[-.05em]">Privacy policy</h1>
    <p class="mt-6">This demo store collects the information needed to create an account, complete an order and respond
        to support messages. It is built for local development and does not transmit customer data to third parties.</p>
    <h2>What we store</h2>
    <p>Account details, delivery information, order records and your saved frames are stored in the local MySQL
        database. Passwords are stored using PHP password hashing, never as plain text.</p>
    <h2>How we use it</h2>
    <p>We use your details only to provide store functionality: authentication, order fulfilment and customer support.
        Contact messages are visible to authorised administrators.</p>
    <h2>Your choices</h2>
    <p>Contact support to request an account or data removal. Before production launch, replace this demo policy with
        legal copy approved for your business and region.</p>
</article>
<?php require APP_ROOT . '/includes/footer.php'; ?>