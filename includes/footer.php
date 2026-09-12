</main>
<footer class="mt-16 border-t border-ink/10 bg-gradient-to-b from-mist to-[#e7ecf8] lg:mt-24">
    <div
        class="mx-auto grid max-w-shell gap-x-8 gap-y-11 px-4 py-14 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:gap-x-10 lg:px-8 lg:py-16 xl:px-10">
        <div class="sm:col-span-2 lg:col-span-1"><a
                class="inline-block text-2xl font-extrabold tracking-[-.06em] transition duration-300 ease-soft hover:opacity-60 lg:text-3xl"
                href="<?= h(url()) ?>">Lensify</a>
            <p class="mt-4 max-w-sm text-sm leading-7 text-zinc-600">Premium eyewear designed for the modern individual.
                Quality, style and clarity above all else.</p>
        </div>
        <div>
            <h2 class="footer-heading">Shop</h2>
            <div class="footer-links"><a href="<?= h(url('shop.php?category=Eyeglasses')) ?>">Eyeglasses</a><a
                    href="<?= h(url('shop.php?category=Sunglasses')) ?>">Sunglasses</a><a
                    href="<?= h(url('shop.php?category=Computer+Glasses')) ?>">Computer Glasses</a><a
                    href="<?= h(url('wishlist.php')) ?>">Saved frames</a></div>
        </div>
        <div>
            <h2 class="footer-heading">Support</h2>
            <div class="footer-links"><a href="<?= h(url('contact.php')) ?>">Contact us</a><a
                    href="<?= h(url('orders.php')) ?>">Orders & returns</a><a
                    href="<?= h(url('privacy.php')) ?>">Privacy policy</a><a href="<?= h(url('terms.php')) ?>">Terms &
                    conditions</a></div>
        </div>
        <div class="lg:col-span-1">
            <h2 class="footer-heading">Stay in the frame</h2>
            <p class="mb-4 max-w-xs text-sm leading-relaxed text-zinc-600">New collections and member-only offers.</p>
            <form class="flex flex-col gap-2.5 xl:flex-row" action="#" onsubmit="return false"><input
                    class="input min-w-0 flex-1" aria-label="Email address" placeholder="Your email address"
                    type="email"><button class="button button-primary w-full shrink-0 px-5 xl:w-auto"
                    type="submit">Join</button></form>
        </div>
    </div>
    <div
        class="mx-auto flex max-w-shell flex-col gap-2 border-t border-ink/10 px-4 py-6 font-mono text-[13px] leading-loose text-zinc-500 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:text-[13px] lg:px-8 xl:px-10">
        <span>&#64;<?= date('Y') ?> Lensify. All rights reserved.</span>
        <span>Designed with precision in Nepal.</span>
    </div>
</footer>
<script src="<?= h(url('assets/js/app.js?v=' . filemtime(APP_ROOT . '/assets/js/app.js'))) ?>"></script>
</body>

</html>
