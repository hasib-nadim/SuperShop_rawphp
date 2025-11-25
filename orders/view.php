<?php
require_once __DIR__ . '/../_imports.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user = GetUser(true);
$conn = DB\getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { $_SESSION['flash_error'] = 'Invalid order id.'; redirect('/orders'); }

// fetch order belonging to user
$order = null;
$stmt = $conn->prepare('SELECT id, total_amount, status, created_at, shipping_first_name, shipping_last_name, shipping_phone, shipping_address FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('ii', $id, $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res->fetch_assoc();
    $stmt->close();
}

if (!$order) { $_SESSION['flash_error'] = 'Order not found.'; redirect('/orders'); }

// fetch items
$items = [];
$q = $conn->prepare('SELECT oi.product_id, oi.quantity, oi.unit_price, p.title, p.images FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
if ($q) {
    $q->bind_param('i', $id);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $imgs = [];
        if (!empty($r['images'])) { $d = json_decode($r['images'], true); if (is_array($d)) $imgs = $d; else $imgs = array_filter(array_map('trim', explode(',', $r['images']))); }
        $image = $imgs[0] ?? '/public/images/products/placeholder.png';
        $items[] = ['product_id'=>(int)$r['product_id'],'title'=>$r['title'],'image'=>$image,'qty'=>(int)$r['quantity'],'price'=> (float)$r['unit_price'],'line_total'=> ((int)$r['quantity'] * (float)$r['unit_price'])];
    }
    $q->close();
}

pageHead('Order #' . (int)$order['id'] . ' - Supershop', ['home.css']);
component('header.php', ['user' => $user]);

?>
<div style="max-width:1000px;margin:28px auto;padding:18px">
    <h2 style="margin:0 0 12px">Order #<?php echo (int)$order['id']; ?></h2>
    <div style="display:flex;gap:20px;align-items:flex-start">
        <div style="flex:1;background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
            <h3 style="margin-top:0">Shipping</h3>
            <div style="padding:8px 0">
                <div><strong><?php echo htmlspecialchars(trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? ''))); ?></strong></div>
                <div><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? '')); ?></div>
                <div><?php echo htmlspecialchars($order['shipping_phone'] ?? ''); ?></div>
            </div>

            <h3>Items</h3>
            <div>
                <?php foreach ($items as $it): ?>
                    <div style="display:flex;gap:8px;padding:8px 0;border-bottom:1px solid #f7fafc">
                        <img src="<?php echo htmlspecialchars($it['image']); ?>" style="width:64px;height:64px;object-fit:cover;border-radius:6px">
                        <div style="flex:1">
                            <div style="font-weight:700;color:#0f172a"><?php echo htmlspecialchars($it['title']); ?></div>
                            <div style="color:#64748b">Qty: <?php echo (int)$it['qty']; ?> × ৳ <?php echo number_format($it['price'],2); ?></div>
                        </div>
                        <div style="font-weight:700">৳ <?php echo number_format($it['line_total'],2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <aside style="width:320px">
            <div style="background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
                <h3 style="margin:0 0 8px">Order summary</h3>
                <div style="display:flex;justify-content:space-between;padding:8px 0;color:#374151"> <div>Items</div> <div><?php echo array_sum(array_column($items,'qty')); ?></div></div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;font-weight:700;color:#0f172a"> <div>Total</div> <div>৳ <?php echo number_format((float)$order['total_amount'],2); ?></div></div>
                <div style="margin-top:12px;color:#64748b">Status: <?php echo htmlspecialchars($order['status']); ?></div>
            </div>
        </aside>
    </div>
</div>

<?php pageFooter();
