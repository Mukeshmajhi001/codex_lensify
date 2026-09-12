<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        http_response_code(419);
        exit('This form has expired.');
    }
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $reviewLookup = db()->prepare('SELECT product_id FROM reviews WHERE id = ?');
    $reviewLookup->execute([$id]);
    $productId = (int) $reviewLookup->fetchColumn();

    if ($action === 'review_status') {
        $status = $_POST['status'] ?? '';
        if ($productId && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            db()->prepare('UPDATE reviews SET status=? WHERE id=?')->execute([$status, $id]);
            refresh_product_review_summary($productId);
            log_admin('Updated review #' . $id . ' to ' . $status);
            flash('success', 'Review status updated.');
        }
    }
    if ($action === 'delete_review' && $productId) {
        db()->prepare('DELETE FROM reviews WHERE id=?')->execute([$id]);
        refresh_product_review_summary($productId);
        log_admin('Deleted review #' . $id);
        flash('success', 'Review deleted.');
    }
    redirect('admin/reviews.php');
}

$reviews = db()->query('SELECT r.*, p.name AS product_name FROM reviews r JOIN products p ON p.id = r.product_id ORDER BY FIELD(r.status, "pending", "approved", "rejected"), r.created_at DESC')->fetchAll();
$adminPage = 'reviews';
$pageTitle = 'Reviews';
require APP_ROOT . '/includes/admin-header.php';
?>
<div>
    <p class="label">Growth</p>
    <h1 class="text-3xl font-bold tracking-[-.05em]">Reviews</h1>
    <p class="mt-2 text-sm text-zinc-500">Moderate feedback before it appears on the storefront.</p>
</div>
<section class="mt-8 space-y-4">
    <?php foreach ($reviews as $review): ?>
        <article class="rounded-2xl border border-zinc-300 bg-white p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2"><strong><?= h($review['reviewer_name']) ?></strong><span
                            class="text-amber-600"><?= str_repeat('★', (int) $review['rating']) ?></span><span
                            class="badge <?= $review['status'] === 'approved' ? 'bg-green-100 text-green-700' : ($review['status'] === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>"><?= h($review['status']) ?></span>
                    </div>
                    <p class="mt-1 text-xs text-zinc-500"><?= h($review['product_name']) ?> ·
                        <?= date('d M Y', strtotime($review['created_at'])) ?></p>
                </div>
                <div class="flex gap-2">
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="review_status"><input
                            type="hidden" name="id" value="<?= $review['id'] ?>"><input type="hidden" name="status"
                            value="<?= $review['status'] === 'approved' ? 'pending' : 'approved' ?>"><button
                            class="button button-secondary px-3 py-2"
                            type="submit"><?= $review['status'] === 'approved' ? 'Unpublish' : 'Approve' ?></button></form>
                    <form method="post" onsubmit="return confirm('Delete this review?')"><?= csrf_field() ?><input
                            type="hidden" name="action" value="delete_review"><input type="hidden" name="id"
                            value="<?= $review['id'] ?>"><button
                            class="button border border-red-200 bg-red-50 px-3 py-2 text-red-700"
                            type="submit">Delete</button></form>
                </div>
            </div><?php if ($review['title']): ?><h2 class="mt-5 font-bold"><?= h($review['title']) ?></h2><?php endif; ?><p
                class="mt-2 max-w-3xl text-sm leading-7 text-zinc-600"><?= h($review['body']) ?></p>
        </article>
    <?php endforeach; ?>
    <?php if (!$reviews): ?><div
            class="rounded-2xl border border-dashed border-zinc-300 px-6 py-16 text-center text-sm text-zinc-500">No
            customer reviews have been submitted yet.</div><?php endif; ?>
</section>
<?php require APP_ROOT . '/includes/admin-footer.php'; ?>