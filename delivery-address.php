<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/address-schema.php';
require_login();
ensure_address_fields();

$nepalProvinces = ['Koshi', 'Madhesh', 'Bagmati', 'Gandaki', 'Lumbini', 'Karnali', 'Sudurpashchim'];
$userId = (int) current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $addressId = (int) ($_POST['address_id'] ?? 0);
    $check = db()->prepare('SELECT id FROM addresses WHERE id = ? AND user_id = ? LIMIT 1');
    if (in_array($action, ['delete_address', 'default_address'], true)) {
        $check->execute([$addressId, $userId]);
        if (!$check->fetchColumn()) {
            flash('error', 'That saved address was not found.');
        } elseif ($action === 'delete_address') {
            db()->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?')->execute([$addressId, $userId]);
            flash('success', 'Saved address removed.');
        } else {
            db()->beginTransaction();
            db()->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
            db()->prepare('UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?')->execute([$addressId, $userId]);
            db()->commit();
            flash('success', 'Default delivery address updated.');
        }
        redirect('delivery-address.php');
    }
    if ($action === 'save_address') {
        $recipientName = trim((string) ($_POST['recipient_name'] ?? ''));
        $phone = trim((string) ($_POST['address_phone'] ?? ''));
        $state = trim((string) ($_POST['state'] ?? ''));
        $district = trim((string) ($_POST['district'] ?? ''));
        $municipality = trim((string) ($_POST['municipality'] ?? ''));
        $wardNumber = trim((string) ($_POST['ward_number'] ?? ''));
        $toleLocality = trim((string) ($_POST['tole_locality'] ?? ''));
        $streetChowk = trim((string) ($_POST['street_chowk'] ?? ''));
        $houseNumber = trim((string) ($_POST['house_number'] ?? ''));
        $nearbyLandmark = trim((string) ($_POST['nearby_landmark'] ?? ''));
        $city = trim((string) ($_POST['address_city'] ?? ''));
        $postalCode = trim((string) ($_POST['address_postal_code'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? 'Home')) ?: 'Home';
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $line1 = trim(implode(', ', array_filter([$houseNumber, $streetChowk, $toleLocality], static fn ($part) => $part !== '')));
        if (!$recipientName || !$phone || !in_array($state, $nepalProvinces, true) || !$district || !$municipality || !$wardNumber || !$toleLocality || !$nearbyLandmark || !$city) {
            flash('error', 'Please complete all required delivery address fields.');
        } else {
            $count = db()->prepare('SELECT COUNT(*) FROM addresses WHERE user_id = ?');
            $count->execute([$userId]);
            db()->beginTransaction();
            if ($isDefault || !(int) $count->fetchColumn()) {
                db()->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
                $isDefault = 1;
            }
            db()->prepare('INSERT INTO addresses (user_id, label, recipient_name, phone, line1, line2, city, state, district, municipality, ward_number, tole_locality, street_chowk, house_number, nearby_landmark, postal_code, country, is_default) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$userId, $label, $recipientName, $phone, $line1, $city, $state, $district, $municipality, $wardNumber, $toleLocality, $streetChowk ?: null, $houseNumber ?: null, $nearbyLandmark, $postalCode, 'Nepal', $isDefault]);
            db()->commit();
            flash('success', 'Delivery address saved.');
            redirect('delivery-address.php');
        }
    }
}

$statement = db()->prepare('SELECT id, label, recipient_name, phone, city, state, district, municipality, ward_number, tole_locality, street_chowk, house_number, nearby_landmark, postal_code, country, is_default FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC');
$statement->execute([$userId]);
$savedAddresses = $statement->fetchAll();
$user = current_user();
$pageTitle = 'Delivery addresses';
require APP_ROOT . '/includes/header.php';
?>
<section class="mx-auto max-w-[1180px] px-5 py-10 lg:px-10">
    <div class="mb-9"><p class="label">Your account</p><h1 class="text-4xl font-bold tracking-[-.05em]">Delivery addresses</h1><p class="mt-2 max-w-2xl text-sm text-zinc-500">Save your complete Nepal delivery details once and select them quickly at checkout.</p></div>
    <div class="grid gap-8 lg:grid-cols-[220px_1fr]">
        <aside class="h-fit rounded-xl bg-mist p-3"><a class="admin-nav" href="<?= h(url('account.php')) ?>"><span class="material-symbols-outlined">person</span>Profile</a><a class="admin-nav" href="<?= h(url('orders.php')) ?>"><span class="material-symbols-outlined">receipt_long</span>My orders</a><a class="admin-nav" href="<?= h(url('wishlist.php')) ?>"><span class="material-symbols-outlined">favorite</span>Saved frames</a><a class="admin-nav admin-nav-active" href="<?= h(url('delivery-address.php')) ?>"><span class="material-symbols-outlined">location_on</span>Delivery address</a><a class="admin-nav" href="<?= h(url('logout.php')) ?>"><span class="material-symbols-outlined">logout</span>Sign out</a></aside>
        <div class="space-y-6">
            <?php if ($savedAddresses): ?><section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7"><h2 class="text-xl font-bold">Saved addresses</h2><div class="mt-6 grid gap-3 sm:grid-cols-2"><?php foreach ($savedAddresses as $address): ?><article class="rounded-lg border border-zinc-200 p-4 <?= $address['is_default'] ? 'ring-2 ring-black' : '' ?>"><p class="font-bold"><?= h($address['label']) ?> <?php if ($address['is_default']): ?><span class="ml-1 text-[10px] uppercase tracking-wider text-green-700">Default</span><?php endif; ?></p><p class="mt-1 text-sm"><?= h($address['recipient_name']) ?> · <?= h($address['phone']) ?></p><p class="mt-3 text-sm leading-6 text-zinc-600"><?= h($address['house_number']) ?><?= $address['street_chowk'] ? ', ' . h($address['street_chowk']) : '' ?><br><?= h($address['tole_locality']) ?>, <?= h($address['municipality']) ?>, <?= h($address['district']) ?><br>Ward <?= h($address['ward_number']) ?>, <?= h($address['state']) ?><br>Near <?= h($address['nearby_landmark']) ?></p><div class="mt-4 flex flex-wrap gap-3 text-xs font-bold"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete_address"><input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>"><button class="text-red-700" type="submit">Remove</button></form><?php if (!$address['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="default_address"><input type="hidden" name="address_id" value="<?= (int) $address['id'] ?>"><button class="underline" type="submit">Make default</button></form><?php endif; ?></div></article><?php endforeach; ?></div></section><?php endif; ?>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-7"><h2 class="text-xl font-bold">Add delivery address</h2><form class="mt-7 space-y-5" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_address"><div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Label</label><input class="input" name="label" maxlength="40" placeholder="Home, Office"></div><div><label class="label">Full name</label><input class="input" name="recipient_name" required maxlength="160" value="<?= h($user['first_name'] . ' ' . $user['last_name']) ?>"></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Mobile number</label><input class="input" name="address_phone" required maxlength="40" value="<?= h($user['phone'] ?? '') ?>" placeholder="98XXXXXXXX"></div><div><label class="label">Province</label><select class="input" name="state" required><option value="">Select province</option><?php foreach ($nepalProvinces as $province): ?><option value="<?= h($province) ?>"><?= h($province) ?></option><?php endforeach; ?></select></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="label">District</label><input class="input" name="district" required maxlength="100" placeholder="Kathmandu"></div><div><label class="label">Municipality / Rural Municipality</label><input class="input" name="municipality" required maxlength="140" placeholder="Kathmandu Metropolitan City"></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Ward number</label><input class="input" name="ward_number" required maxlength="20" placeholder="10"></div><div><label class="label">Tole / Locality</label><input class="input" name="tole_locality" required maxlength="140" placeholder="Baneshwor Tole"></div></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="label">Street / Chowk</label><input class="input" name="street_chowk" maxlength="140" placeholder="New Baneshwor Chowk"></div><div><label class="label">House number</label><input class="input" name="house_number" maxlength="80" placeholder="House 12, 3rd floor"></div></div><div><label class="label">Nearby landmark</label><input class="input" name="nearby_landmark" required maxlength="190" placeholder="Near Bhatbhateni, opposite school"></div><div class="grid gap-4 sm:grid-cols-2"><div><label class="label">City</label><input class="input" name="address_city" required maxlength="100" placeholder="Kathmandu"></div><div><label class="label">Postal code <span class="font-normal text-zinc-500">(optional)</span></label><input class="input" name="address_postal_code" maxlength="20" placeholder="44600"></div></div><label class="flex items-start gap-3 rounded-lg bg-mist p-4 text-sm"><input class="mt-0.5 rounded border-zinc-300 text-black focus:ring-black" type="checkbox" name="is_default"><span><strong class="block">Make this my default address</strong><small class="mt-1 block text-zinc-500">It will be preselected at checkout.</small></span></label><button class="button button-primary" type="submit">Save delivery address</button></form></section>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/includes/footer.php'; ?>
