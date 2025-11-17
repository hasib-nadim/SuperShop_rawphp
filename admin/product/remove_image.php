<?php
require_once __DIR__ . '/../../_imports.php';

header('Content-Type: application/json; charset=utf-8');

$admin = GetAdmin();
$conn = DB\getConnection();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
$img = isset($data['image']) ? trim($data['image']) : '';
if ($product_id <= 0 || $img === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing product_id or image']);
    exit;
}

// fetch current images
$stmt = $conn->prepare('SELECT images FROM products WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB prepare failed']);
    exit;
}
$stmt->bind_param('i', $product_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Product not found']);
    exit;
}

$images = [];
if (!empty($row['images'])) {
    $decoded = json_decode($row['images'], true);
    if (is_array($decoded)) $images = $decoded;
}

$found = false;
foreach ($images as $k => $v) {
    if ($v === $img) { $found = $k; break; }
}
if ($found === false) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Image not found']);
    exit;
}

// remove from array
array_splice($images, $found, 1);

// update DB
if (empty($images)) {
    $upd_sql = 'UPDATE products SET images = NULL WHERE id = ?';
    $upd = $conn->prepare($upd_sql);
    $upd->bind_param('i', $product_id);
    $ok = $upd->execute();
    $upd->close();
} else {
    $new_json = json_encode(array_values($images));
    $upd_sql = 'UPDATE products SET images = ? WHERE id = ?';
    $upd = $conn->prepare($upd_sql);
    $upd->bind_param('si', $new_json, $product_id);
    $ok = $upd->execute();
    $upd->close();
}

// attempt to unlink local file (best-effort)
$local = __DIR__ . '/../../' . ltrim($img, '/');
if (strpos(realpath($local) ?: '', realpath(__DIR__ . '/../../public/images/products') ?: '') === 0) {
    if (file_exists($local) && is_file($local)) {
        @unlink($local);
    }
}

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to update DB']);
    exit;
}

echo json_encode(['ok' => true]);
exit;
