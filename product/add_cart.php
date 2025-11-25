<?php
require_once __DIR__ . '/../_imports.php';

// ensure session for guest carts
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = DB\getConnection();
$user = GetUser(); // returns null or user array

$product_id = isset($_REQUEST['product_id']) ? (int)$_REQUEST['product_id'] : 0;
$qty = isset($_REQUEST['qty']) ? max(1, (int)$_REQUEST['qty']) : 1;

// helper to return JSON for XHR
function respond_json($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($product_id <= 0) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        respond_json(['ok' => false, 'error' => 'Invalid product']);
    }
    header('Location: /product'); exit;
}

// fetch product to get price and availability
$stmt = $conn->prepare('SELECT id,title,slug,price,stock,images FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
if (!$stmt) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') respond_json(['ok'=>false,'error'=>'DB prepare failed']);
    header('Location: /product'); exit;
}
$stmt->bind_param('i', $product_id);
$stmt->execute();
$res = $stmt->get_result();
$product = $res->fetch_assoc();
$stmt->close();

if (!$product) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') respond_json(['ok'=>false,'error'=>'Product not found']);
    header('Location: /product'); exit;
}

// logged-in user: persist to carts table
if (!empty($user) && !empty($user['id'])) {
    $user_id = (int)$user['id'];
    // check existing row
    $s = $conn->prepare('SELECT id,quantity FROM carts WHERE user_id = ? AND product_id = ? LIMIT 1');
    if ($s) {
        $s->bind_param('ii', $user_id, $product_id);
        $s->execute();
        $s->store_result();
        if ($s->num_rows > 0) {
            $s->bind_result($row_id, $existing_qty);
            $s->fetch();
            $s->close();
            $new_qty = $existing_qty + $qty;
            $u = $conn->prepare('UPDATE carts SET quantity = ? WHERE id = ?');
            if ($u) { $u->bind_param('ii', $new_qty, $row_id); $u->execute(); $u->close(); }
        } else {
            $s->close();
            $unit_price = (float)$product['price'];
            $i = $conn->prepare('INSERT INTO carts (user_id,product_id,quantity,unit_price) VALUES (?,?,?,?)');
            if ($i) { $i->bind_param('iiid', $user_id, $product_id, $qty, $unit_price); $i->execute(); $i->close(); }
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        respond_json(['ok' => true, 'method' => 'db']);
    }

    header('Location: /cart'); exit;

} else {
    // guest: use session cart
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
    $key = (string)$product_id;
    if (!isset($_SESSION['cart'][$key])) {
        // normalize images
        $imgs = [];
        if (!empty($product['images'])) {
            $decoded = json_decode($product['images'], true);
            if (is_array($decoded)) $imgs = $decoded;
            else $imgs = array_filter(array_map('trim', explode(',', $product['images'])));
        }
        $_SESSION['cart'][$key] = [
            'product_id' => $product_id,
            'title' => $product['title'],
            'price' => (float)$product['price'],
            'qty' => $qty,
            'image' => $imgs[0] ?? '/public/images/products/placeholder.png'
        ];
    } else {
        $_SESSION['cart'][$key]['qty'] = max(1, $_SESSION['cart'][$key]['qty'] + $qty);
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        respond_json(['ok' => true, 'method' => 'session', 'cart_count' => array_sum(array_column($_SESSION['cart'], 'qty'))]);
    }

    header('Location: /cart'); exit;
}

?>
