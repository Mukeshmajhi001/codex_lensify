<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Keep the login session across browser restarts. The session is still
    // removed immediately by logout.php, while inactive server-side sessions
    // are retained for one year so the persistent cookie remains useful.
    $sessionLifetime = 60 * 60 * 24 * 365;
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $sessionDirectory = APP_ROOT . '/storage/sessions';
    if (!is_dir($sessionDirectory)) {
        mkdir($sessionDirectory, 0755, true);
    }
    if (is_dir($sessionDirectory) && is_writable($sessionDirectory)) {
        session_save_path($sessionDirectory);
    }
    session_start();
}

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$detectedBase = preg_replace('#/(?:admin/)?[^/]+$#', '', $scriptName) ?: '';
define('APP_BASE_URL', rtrim(getenv('LENSIFY_BASE_URL') ?: $detectedBase, '/'));

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'lensify';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';

    $connection = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $connection;
}

function db_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        db();
        return $available = true;
    } catch (Throwable) {
        return $available = false;
    }
}

function site_setting(string $key, ?string $default = null): ?string
{
    if (!db_available()) {
        return $default;
    }

    try {
        $statement = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        return $value === false ? $default : (string) $value;
    } catch (Throwable) {
        return $default;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token']) && hash_equals(csrf_token(), (string) $_POST['csrf_token']);
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}

function current_user(): ?array
{
    static $userLoaded = false;
    static $user = null;

    if ($userLoaded) {
        return $user;
    }
    $userLoaded = true;

    if (empty($_SESSION['user_id']) || !db_available()) {
        return null;
    }

    $statement = db()->prepare('SELECT id, first_name, last_name, email, phone, profile_image, bio, email_notifications, role, created_at FROM users WHERE id = ? LIMIT 1');
    $statement->execute([(int) $_SESSION['user_id']]);
    $user = $statement->fetch() ?: null;
    return $user;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('error', 'Please sign in with an administrator account to continue.');
        redirect('login.php');
    }
}

function money(float|int|string $amount): string
{
    return '₹' . number_format((float) $amount, 0);
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    return trim((string) preg_replace('~[^a-z0-9]+~', '-', $value), '-');
}

function initials(array $person): string
{
    $first = trim((string) ($person['first_name'] ?? ''));
    $last = trim((string) ($person['last_name'] ?? ''));
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: 'L';
}

function avatar_url(?array $person): ?string
{
    $image = trim((string) ($person['profile_image'] ?? ''));
    if ($image === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }
    return url(ltrim($image, '/'));
}

/**
 * Saves an uploaded JPEG, PNG, WebP or GIF and returns an application-relative path.
 *
 * @param array<string, mixed> $file
 * @return array{path: ?string, error: ?string}
 */
function save_uploaded_image(array $file, string $bucket = 'avatars', int $maxBytes = 4194304): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['path' => null, 'error' => 'Image upload failed. Please try a smaller file.'];
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return ['path' => null, 'error' => 'Image must be 4 MB or smaller.'];
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($extensions[$mime]) || @getimagesize((string) $file['tmp_name']) === false) {
        return ['path' => null, 'error' => 'Upload a valid JPG, PNG, WebP or GIF image.'];
    }

    $directory = APP_ROOT . '/uploads/' . trim($bucket, '/');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return ['path' => null, 'error' => 'Could not prepare the image upload folder.'];
    }

    // A content-addressed filename means selecting the same file twice does not
    // create two gallery entries with different random paths.
    $contentHash = hash_file('sha256', (string) $file['tmp_name']);
    $filename = ($contentHash ?: bin2hex(random_bytes(16))) . '.' . $extensions[$mime];
    $destination = $directory . '/' . $filename;
    if (!is_file($destination) && !move_uploaded_file((string) $file['tmp_name'], $destination)) {
        return ['path' => null, 'error' => 'Could not save the uploaded image.'];
    }
    return ['path' => 'uploads/' . trim($bucket, '/') . '/' . $filename, 'error' => null];
}

/**
 * Saves one JPEG, PNG, WebP or GIF image uploaded by the current visitor.
 *
 * @return array{path: ?string, error: ?string}
 */
function upload_image(string $field, string $bucket = 'avatars', int $maxBytes = 4194304): array
{
    $file = $_FILES[$field] ?? null;
    return is_array($file) ? save_uploaded_image($file, $bucket, $maxBytes) : ['path' => null, 'error' => null];
}

/**
 * Saves a multi-file image field. The first error is returned without silently accepting it.
 *
 * @return array{paths: list<string>, error: ?string}
 */
function upload_images(string $field, string $bucket = 'products', int $maxBytes = 4194304): array
{
    $files = $_FILES[$field] ?? null;
    if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        return ['paths' => [], 'error' => null];
    }

    $paths = [];
    foreach (array_keys($files['name']) as $index) {
        $file = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
        $result = save_uploaded_image($file, $bucket, $maxBytes);
        if ($result['error']) {
            return ['paths' => $paths, 'error' => $result['error']];
        }
        if ($result['path']) {
            $paths[] = $result['path'];
        }
    }
    return ['paths' => $paths, 'error' => null];
}

function display_image_url(?string $imageUrl, string $fallback = 'https://placehold.co/800x800?text=Lensify'): string
{
    $imageUrl = trim((string) $imageUrl);
    if ($imageUrl === '') {
        return $fallback;
    }
    return preg_match('#^(?:https?:)?//#i', $imageUrl) || str_starts_with($imageUrl, 'data:image/')
        ? $imageUrl
        : url(ltrim($imageUrl, '/'));
}

function log_admin(string $event): void
{
    if (!is_admin() || !db_available()) {
        return;
    }
    db()->prepare('INSERT INTO admin_logs (admin_id, event, ip_address) VALUES (?, ?, ?)')->execute([
        current_user()['id'],
        $event,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function categories(): array
{
    if (db_available()) {
        $rows = db()->query('SELECT name, description, icon FROM categories ORDER BY name')->fetchAll();
        if ($rows) {
            $result = [];
            foreach ($rows as $row) {
                $result[$row['name']] = ['icon' => $row['icon'] ?: 'eyeglasses', 'description' => $row['description'] ?: 'Premium frames'];
            }
            return $result;
        }
    }
    return [
        'Eyeglasses' => ['icon' => 'eyeglasses', 'description' => 'Everyday frames, precisely made'],
        'Sunglasses' => ['icon' => 'sunglasses', 'description' => 'Protection with a point of view'],
        'Reading Glasses' => ['icon' => 'menu_book', 'description' => 'Focused comfort for close work'],
        'Computer Glasses' => ['icon' => 'computer', 'description' => 'Designed for screen time'],
    ];
}

function active_banners(): array
{
    if (!db_available()) {
        return [];
    }

    try {
        return db()->query('SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function fallback_products(): array
{
    return [
        ['id' => 1, 'slug' => 'noir-arch-rectangular', 'name' => 'Noir Arch Rectangular', 'brand' => 'Lumina Eyewear', 'category' => 'Eyeglasses', 'gender' => 'Men', 'shape' => 'Rectangular', 'material' => 'Acetate', 'color' => 'Midnight Black', 'price' => 4800, 'compare_price' => 6200, 'rating' => 4.8, 'review_count' => 124, 'badge' => 'SALE', 'image_url' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=1000&q=85', 'description' => 'A confident everyday silhouette in hand-finished Italian acetate, made to keep its poise from first meeting to last light.'],
        ['id' => 2, 'slug' => 'aurelia-round-metal', 'name' => 'Aurelia Round Metal', 'brand' => 'Vintage Co.', 'category' => 'Eyeglasses', 'gender' => 'Women', 'shape' => 'Round', 'material' => 'Metal', 'color' => 'Champagne Gold', 'price' => 5900, 'compare_price' => null, 'rating' => 4.9, 'review_count' => 89, 'badge' => 'BESTSELLER', 'image_url' => 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?auto=format&fit=crop&w=1000&q=85', 'description' => 'An ultra-light round metal frame with warm gold detailing and a refined, almost weightless fit.'],
        ['id' => 3, 'slug' => 'amber-cat-eye', 'name' => 'Amber Cat Eye', 'brand' => 'Moderna', 'category' => 'Eyeglasses', 'gender' => 'Women', 'shape' => 'Cat Eye', 'material' => 'Acetate', 'color' => 'Tortoise', 'price' => 6900, 'compare_price' => null, 'rating' => 4.7, 'review_count' => 61, 'badge' => '', 'image_url' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=1000&q=85', 'description' => 'A sculpted cat-eye profile in rich tortoise acetate, cut for an effortless, editorial statement.'],
        ['id' => 4, 'slug' => 'titan-flight-aviator', 'name' => 'Titan Flight Aviator', 'brand' => 'Steel & Sky', 'category' => 'Sunglasses', 'gender' => 'Unisex', 'shape' => 'Aviator', 'material' => 'Titanium', 'color' => 'Graphite', 'price' => 7450, 'compare_price' => null, 'rating' => 4.9, 'review_count' => 156, 'badge' => 'NEW', 'image_url' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?auto=format&fit=crop&w=1000&q=85&sat=-100', 'description' => 'Titanium aviators with a crisp profile, featherlight fit and complete UV protection.'],
        ['id' => 5, 'slug' => 'ice-square-frame', 'name' => 'Ice Square Frame', 'brand' => 'Crystal Vision', 'category' => 'Eyeglasses', 'gender' => 'Unisex', 'shape' => 'Square', 'material' => 'Acetate', 'color' => 'Crystal Clear', 'price' => 5100, 'compare_price' => null, 'rating' => 4.6, 'review_count' => 47, 'badge' => '', 'image_url' => 'https://images.unsplash.com/photo-1514308582979-6b00088e7f57?auto=format&fit=crop&w=1000&q=85', 'description' => 'Transparent acetate turns a precise square frame into a clean, modern essential.'],
        ['id' => 6, 'slug' => 'eco-round-matte-green', 'name' => 'Eco-Round Matte Green', 'brand' => 'Earth Eyewear', 'category' => 'Eyeglasses', 'gender' => 'Unisex', 'shape' => 'Round', 'material' => 'Eco-friendly', 'color' => 'Forest Green', 'price' => 4500, 'compare_price' => 5200, 'rating' => 4.5, 'review_count' => 38, 'badge' => 'SALE', 'image_url' => 'https://images.unsplash.com/photo-1577803645773-f96470509666?auto=format&fit=crop&w=1000&q=85', 'description' => 'A gently rounded, low-impact frame with a velvety matte finish and spring hinges.'],
        ['id' => 7, 'slug' => 'slate-aviator', 'name' => 'Slate Aviator', 'brand' => 'Metals', 'category' => 'Sunglasses', 'gender' => 'Men', 'shape' => 'Aviator', 'material' => 'Metal', 'color' => 'Slate', 'price' => 6200, 'compare_price' => null, 'rating' => 4.8, 'review_count' => 73, 'badge' => 'NEW', 'image_url' => 'https://images.unsplash.com/photo-1508296695146-257a814070b4?auto=format&fit=crop&w=1000&q=85', 'description' => 'Dark lenses and a minimal metal bridge make this a versatile warm-weather classic.'],
        ['id' => 8, 'slug' => 'scarlet-cat', 'name' => 'Scarlet Cat', 'brand' => 'Retro Gold', 'category' => 'Sunglasses', 'gender' => 'Women', 'shape' => 'Cat Eye', 'material' => 'Acetate', 'color' => 'Scarlet', 'price' => 5400, 'compare_price' => null, 'rating' => 4.7, 'review_count' => 52, 'badge' => 'LIMITED', 'image_url' => 'https://images.unsplash.com/photo-1512316609839-ce289d3eba0a?auto=format&fit=crop&w=1000&q=85', 'description' => 'A sun-ready cat-eye in an optimistic red with full UV400 protection.'],
    ];
}

function normalise_product(array $product): array
{
    $product['price'] = (float) $product['price'];
    $product['compare_price'] = $product['compare_price'] !== null ? (float) $product['compare_price'] : null;
    $product['rating'] = (float) ($product['rating'] ?? 4.8);
    $product['review_count'] = (int) ($product['review_count'] ?? 0);
    return $product;
}

function products(array $filters = []): array
{
    if (db_available()) {
        $sql = 'SELECT p.*, b.name AS brand, c.name AS category, (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY sort_order, id LIMIT 1) AS image_url FROM products p JOIN brands b ON b.id = p.brand_id JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1';
        $params = [];
        foreach (['category', 'shape', 'material', 'gender'] as $field) {
            if (!empty($filters[$field])) {
                $column = $field === 'category' ? 'c.name' : "p.{$field}";
                $sql .= " AND {$column} = ?";
                $params[] = $filters[$field];
            }
        }
        if (!empty($filters['query'])) {
            $sql .= ' AND (p.name LIKE ? OR b.name LIKE ? OR p.shape LIKE ?)';
            $like = '%' . $filters['query'] . '%';
            array_push($params, $like, $like, $like);
        }
        $sort = match ($filters['sort'] ?? '') {
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'newest' => 'p.created_at DESC',
            default => 'p.is_featured DESC, p.created_at DESC',
        };
        $statement = db()->prepare($sql . ' ORDER BY ' . $sort);
        $statement->execute($params);
        return array_map('normalise_product', $statement->fetchAll());
    }

    $catalogue = fallback_products();
    return array_values(array_filter($catalogue, static function (array $product) use ($filters): bool {
        foreach (['category', 'shape', 'material', 'gender'] as $field) {
            if (!empty($filters[$field]) && $product[$field] !== $filters[$field]) {
                return false;
            }
        }
        if (!empty($filters['query'])) {
            $haystack = strtolower(implode(' ', [$product['name'], $product['brand'], $product['shape']]));
            if (!str_contains($haystack, strtolower($filters['query']))) {
                return false;
            }
        }
        return true;
    }));
}

function product_by_slug(string $slug): ?array
{
    foreach (products() as $product) {
        if ($product['slug'] === $slug) {
            return $product;
        }
    }
    return null;
}

function product_by_id(int $id): ?array
{
    foreach (products() as $product) {
        if ((int) $product['id'] === $id) {
            return $product;
        }
    }
    return null;
}

function product_variants(int $productId): array
{
    if (!db_available()) {
        return [];
    }

    try {
        $statement = db()->prepare('SELECT * FROM product_variants WHERE product_id = ? AND is_active = 1 ORDER BY name');
        $statement->execute([$productId]);
        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function product_variant(int $productId, int $variantId): ?array
{
    if ($variantId <= 0 || !db_available()) {
        return null;
    }

    try {
        $statement = db()->prepare('SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1 LIMIT 1');
        $statement->execute([$variantId, $productId]);
        return $statement->fetch() ?: null;
    } catch (Throwable) {
        return null;
    }
}

/** @return list<array<string, mixed>> */
function product_images(int $productId): array
{
    if ($productId <= 0 || !db_available()) {
        return [];
    }

    try {
        // Keep the gallery stable even if an older database contains duplicate
        // image rows. Only the earliest row for an identical image is returned.
        $statement = db()->prepare('SELECT pi.id, pi.image_url, pi.alt_text, pi.sort_order
            FROM product_images pi
            WHERE pi.product_id = ?
              AND NOT EXISTS (
                  SELECT 1 FROM product_images earlier
                  WHERE earlier.product_id = pi.product_id
                    AND earlier.image_url = pi.image_url
                    AND (earlier.sort_order < pi.sort_order OR (earlier.sort_order = pi.sort_order AND earlier.id < pi.id))
              )
            ORDER BY pi.sort_order, pi.id');
        $statement->execute([$productId]);
        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function approved_reviews(int $productId): array
{
    if (!db_available()) {
        return [];
    }

    try {
        $statement = db()->prepare("SELECT reviewer_name, rating, title, body, created_at FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 12");
        $statement->execute([$productId]);
        return $statement->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

function refresh_product_review_summary(int $productId): void
{
    if (!db_available()) {
        return;
    }

    $statement = db()->prepare("SELECT COUNT(*) AS review_count, AVG(rating) AS rating FROM reviews WHERE product_id = ? AND status = 'approved'");
    $statement->execute([$productId]);
    $summary = $statement->fetch();
    db()->prepare('UPDATE products SET review_count = ?, rating = ? WHERE id = ?')->execute([
        (int) ($summary['review_count'] ?? 0),
        $summary['rating'] !== null ? round((float) $summary['rating'], 1) : 4.8,
        $productId,
    ]);
}

/**
 * A review is only available to the account that paid for and received the frame.
 *
 * @return array{eligible: bool, reason: string, already_reviewed: bool}
 */
function review_eligibility(int $productId, ?int $userId = null): array
{
    $userId ??= (int) (current_user()['id'] ?? 0);
    $result = ['eligible' => false, 'reason' => 'Sign in to write a review.', 'already_reviewed' => false];
    if ($userId <= 0) {
        return $result;
    }
    if (!db_available()) {
        $result['reason'] = 'Reviews are unavailable until the database is ready.';
        return $result;
    }

    try {
        $review = db()->prepare('SELECT id FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
        $review->execute([$productId, $userId]);
        if ($review->fetchColumn()) {
            return ['eligible' => false, 'reason' => 'You have already submitted a review for this frame.', 'already_reviewed' => true];
        }

        $purchase = db()->prepare("SELECT 1 FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.user_id = ? AND o.order_status = 'delivered' AND o.payment_status = 'paid' LIMIT 1");
        $purchase->execute([$productId, $userId]);
        if ($purchase->fetchColumn()) {
            return ['eligible' => true, 'reason' => '', 'already_reviewed' => false];
        }
    } catch (Throwable) {
        $result['reason'] = 'We could not verify this purchase yet. Please try again shortly.';
        return $result;
    }

    $result['reason'] = 'Reviews unlock after this frame is delivered and payment is marked paid.';
    return $result;
}

/**
 * Customers may stop fulfilment only before an order is shipped. Admins have
 * a separate cancellation action and are intentionally not restricted by this
 * customer-facing rule.
 */
function customer_can_cancel_order(string $orderStatus): bool
{
    return in_array($orderStatus, ['pending', 'confirmed', 'processing'], true);
}

function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(array_column(cart(), 'quantity'));
}

function cart_details(): array
{
    $items = [];
    foreach (cart() as $cartKey => $cartItem) {
        $productId = (int) ($cartItem['product_id'] ?? $cartKey);
        $product = product_by_id($productId);
        if (!$product) {
            continue;
        }
        $quantity = max(1, (int) $cartItem['quantity']);
        $variant = product_variant($productId, (int) ($cartItem['variant_id'] ?? 0));
        $unitPrice = $product['price'] + (float) ($variant['price_adjustment'] ?? 0);
        $product['cart_key'] = (string) $cartKey;
        $product['quantity'] = $quantity;
        $product['lens_type'] = $cartItem['lens_type'] ?? 'Single Vision';
        $product['variant_id'] = $variant['id'] ?? null;
        $product['variant_name'] = $variant['name'] ?? null;
        $product['variant_sku'] = $variant['sku'] ?? null;
        $product['unit_price'] = $unitPrice;
        $product['line_total'] = $unitPrice * $quantity;
        $items[] = $product;
    }
    return $items;
}

function cart_total(): float
{
    return max(0, cart_subtotal() - cart_discount() + cart_shipping());
}

function cart_subtotal(): float
{
    return array_sum(array_column(cart_details(), 'line_total'));
}

function active_coupon(): ?array
{
    $code = strtoupper(trim((string) ($_SESSION['coupon_code'] ?? '')));
    if ($code === '' || !db_available()) {
        return null;
    }

    try {
        $statement = db()->prepare('SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (expires_at IS NULL OR expires_at >= NOW()) AND (usage_limit IS NULL OR used_count < usage_limit) LIMIT 1');
        $statement->execute([$code]);
        $coupon = $statement->fetch() ?: null;
        if (!$coupon || cart_subtotal() < (float) $coupon['minimum_order']) {
            unset($_SESSION['coupon_code']);
            return null;
        }
        return $coupon;
    } catch (Throwable) {
        return null;
    }
}

function cart_discount(): float
{
    $coupon = active_coupon();
    if (!$coupon) {
        return 0;
    }

    $subtotal = cart_subtotal();
    $discount = $coupon['type'] === 'percent'
        ? $subtotal * ((float) $coupon['value'] / 100)
        : (float) $coupon['value'];

    return min($subtotal, round($discount, 2));
}

function free_shipping_threshold(): ?float
{
    $value = trim((string) site_setting('free_shipping_threshold', '2000'));
    if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
        return null;
    }
    return round((float) $value, 2);
}

function cart_shipping(): float
{
    $chargeableSubtotal = max(0, cart_subtotal() - cart_discount());
    $threshold = free_shipping_threshold();
    if ($threshold !== null && $chargeableSubtotal >= $threshold) {
        return 0;
    }
    return round($chargeableSubtotal * 0.195, 2);
}

function merge_guest_wishlist(int $userId): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_SESSION['wishlist'] ?? [])), static fn(int $id): bool => $id > 0)));
    if ($userId <= 0 || !$ids || !db_available()) {
        return;
    }

    try {
        $productExists = db()->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
        $insert = db()->prepare('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)');
        foreach ($ids as $productId) {
            $productExists->execute([$productId]);
            if ($productExists->fetchColumn()) {
                $insert->execute([$userId, $productId]);
            }
        }
        unset($_SESSION['wishlist']);
    } catch (Throwable) {
        // Keep the session collection so it can be merged on the next successful sign-in.
    }
}

function create_notification(int $userId, string $title, string $message, ?string $link = null): void
{
    if ($userId <= 0 || !db_available()) {
        return;
    }

    try {
        db()->prepare('INSERT INTO notifications (user_id, title, message, link_url) VALUES (?, ?, ?, ?)')->execute([
            $userId,
            mb_substr(trim($title), 0, 160),
            mb_substr(trim($message), 0, 1000),
            $link ? mb_substr($link, 0, 255) : null,
        ]);
    } catch (Throwable) {
        // Notifications must never prevent checkout or fulfilment from completing.
    }
}

function notify_admins(string $title, string $message, ?string $link = null): void
{
    if (!db_available()) {
        return;
    }

    try {
        $adminIds = db()->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        foreach ($adminIds as $admin) {
            create_notification((int) $admin['id'], $title, $message, $link);
        }
    } catch (Throwable) {
        // A missing optional table is tolerated during a staged migration.
    }
}

function unread_notification_count(?int $userId = null): int
{
    $userId ??= (int) (current_user()['id'] ?? 0);
    if ($userId <= 0 || !db_available()) {
        return 0;
    }

    try {
        $statement = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $statement->execute([$userId]);
        return (int) $statement->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

/**
 * Return the unread total for one notification title. Admin activity uses
 * stable titles so the Orders and Reviews badges can show their own exact
 * counts instead of mixing unrelated notifications together.
 */
function unread_notification_count_by_title(string $title, ?int $userId = null): int
{
    $userId ??= (int) (current_user()['id'] ?? 0);
    if ($userId <= 0 || !db_available()) {
        return 0;
    }

    try {
        $statement = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND title = ? AND is_read = 0');
        $statement->execute([$userId, $title]);
        return (int) $statement->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function wishlist_ids(): array
{
    if (current_user() && db_available()) {
        $statement = db()->prepare('SELECT product_id FROM wishlists WHERE user_id = ?');
        $statement->execute([current_user()['id']]);
        return array_map('intval', array_column($statement->fetchAll(), 'product_id'));
    }
    return array_map('intval', $_SESSION['wishlist'] ?? []);
}

function is_wishlisted(int $productId): bool
{
    return in_array($productId, wishlist_ids(), true);
}

function handle_store_actions(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['action'])) {
        return;
    }
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired. Please return to the page and try again.');
    }

    $action = (string) $_POST['action'];
    $returnTo = trim((string) ($_POST['return_to'] ?? 'cart.php'), '/');

    if ($action === 'add_to_cart') {
        if (!current_user()) {
            flash('error', 'Please sign in before shopping or placing an order.');
            redirect('login.php');
        }
        $productId = (int) ($_POST['product_id'] ?? 0);
        $product = product_by_id($productId);
        $variantId = (int) ($_POST['variant_id'] ?? 0);
        $variant = product_variant($productId, $variantId);
        if (!$product) {
            flash('error', 'That frame is no longer available.');
        } elseif ($variantId && !$variant) {
            flash('error', 'That frame option is no longer available.');
        } elseif ($variant && (int) $variant['stock_quantity'] < 1) {
            flash('error', 'That frame option is currently out of stock.');
        } else {
            $cartKey = $productId . ':' . $variantId;
            $_SESSION['cart'][$cartKey] = [
                'product_id' => $productId,
                'variant_id' => $variantId ?: null,
                'quantity' => min(10, (int) (($_SESSION['cart'][$cartKey]['quantity'] ?? 0) + 1)),
                'lens_type' => (string) ($_POST['lens_type'] ?? 'Single Vision'),
            ];
            flash('success', 'Frame added to your bag.');
        }
        redirect($returnTo);
    }

    if ($action === 'apply_coupon') {
        $code = strtoupper(trim((string) ($_POST['coupon_code'] ?? '')));
        if ($code === '') {
            flash('error', 'Enter a coupon code first.');
        } elseif (!cart()) {
            flash('error', 'Add a frame before applying a coupon.');
        } else {
            $_SESSION['coupon_code'] = $code;
            if (active_coupon()) {
                flash('success', 'Coupon ' . $code . ' has been applied.');
            } else {
                flash('error', 'This coupon is invalid, expired, or does not meet the minimum order value.');
            }
        }
        redirect($returnTo);
    }

    if ($action === 'remove_coupon') {
        unset($_SESSION['coupon_code']);
        flash('success', 'Coupon removed.');
        redirect($returnTo);
    }

    if ($action === 'update_cart') {
        foreach ((array) ($_POST['quantity'] ?? []) as $cartKey => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$cartKey]);
            } elseif (isset($_SESSION['cart'][$cartKey])) {
                $_SESSION['cart'][$cartKey]['quantity'] = min(10, $quantity);
            }
        }
        flash('success', 'Your bag has been updated.');
        redirect('cart.php');
    }

    if ($action === 'remove_cart_item') {
        unset($_SESSION['cart'][(string) ($_POST['cart_key'] ?? '')]);
        flash('success', 'Frame removed from your bag.');
        redirect('cart.php');
    }

    if ($action === 'toggle_wishlist') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if (!product_by_id($productId)) {
            flash('error', 'That frame is no longer available.');
        } elseif (current_user() && db_available()) {
            $statement = db()->prepare('SELECT id FROM wishlists WHERE user_id = ? AND product_id = ?');
            $statement->execute([current_user()['id'], $productId]);
            if ($statement->fetch()) {
                db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?')->execute([current_user()['id'], $productId]);
            } else {
                db()->prepare('INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)')->execute([current_user()['id'], $productId]);
            }
        } else {
            $ids = wishlist_ids();
            $_SESSION['wishlist'] = in_array($productId, $ids, true) ? array_values(array_diff($ids, [$productId])) : [...$ids, $productId];
        }
        redirect($returnTo);
    }
}
