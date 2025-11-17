<?php
require_once __DIR__ . '/../../_imports.php';

$admin = GetAdmin();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /admin/product/index.php');
    exit;
}

$conn = DB\getConnection();

// fetch product and its images
$stmt = $conn->prepare('SELECT id, images FROM products WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $cur = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} else {
    $cur = null;
}

if (!$cur) {
    header('Location: /admin/product/index.php');
    exit;
}

// remove image files if present (best-effort)
if (!empty($cur['images'])) {
    $imgs = json_decode($cur['images'], true);
    if (is_array($imgs)) {
        foreach ($imgs as $p) {
            // only unlink local uploads under public/images/products
            $local = __DIR__ . '/../../' . ltrim($p, '/');
            if (file_exists($local) && is_file($local)) {
                @unlink($local);
            }
        }
    }
}

// delete product
$del = $conn->prepare('DELETE FROM products WHERE id = ?');
if ($del) {
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
}

header('Location: /admin/product/index.php');
exit;
