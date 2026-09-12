<?php
require_once __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($query) < 2) {
    echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$results = [];
foreach (array_slice(products(['query' => mb_substr($query, 0, 80)]), 0, 6) as $product) {
    $results[] = [
        'name' => (string) $product['name'],
        'brand' => (string) ($product['brand'] ?? ''),
        'slug' => (string) $product['slug'],
        'price' => money((float) $product['price']),
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
