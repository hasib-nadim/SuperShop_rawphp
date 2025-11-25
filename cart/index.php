<?php
require_once __DIR__ . '/../_imports.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$conn = DB\getConnection();
$user = GetUser(); 
// helper to send JSON for XHR
function respond_json($d){ header('Content-Type: application/json'); echo json_encode($d); exit; }

// Handle POST actions: update quantity or remove item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    if ($product_id <= 0) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) respond_json(['ok'=>false,'error'=>'Invalid product']);
        header('Location: ' . url('/cart')); exit;
    }

    if ($action === 'update') {
        $qty = isset($_POST['qty']) ? max(1, (int)$_POST['qty']) : 1;
        if (!empty($user) && !empty($user['id'])) {
            $stmt = $conn->prepare('UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?');
            if ($stmt) { $stmt->bind_param('iii', $qty, $user['id'], $product_id); $stmt->execute(); $stmt->close(); }
        } else {
            if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];
            $key = (string)$product_id;
            if (isset($_SESSION['cart'][$key])) $_SESSION['cart'][$key]['qty'] = $qty;
        }
        // prepare cart summary
        $summary = ['ok'=>true,'action'=>'update'];
        // compute count and subtotal
        $count = 0; $subtotal = 0.0;
        if (!empty($user) && !empty($user['id'])) {
            $q = $conn->prepare('SELECT quantity, unit_price FROM carts WHERE user_id = ?');
            if ($q) { $q->bind_param('i', $user['id']); $q->execute(); $res = $q->get_result(); while($r = $res->fetch_assoc()){ $count += (int)$r['quantity']; $subtotal += (int)$r['quantity'] * (float)$r['unit_price']; } $q->close(); }
        } else {
            foreach ($_SESSION['cart'] ?? [] as $it) { $count += (int)$it['qty']; $subtotal += (int)$it['qty'] * (float)$it['price']; }
        }
        $summary['count'] = $count; $summary['subtotal'] = $subtotal;
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) respond_json($summary);
        redirect('/cart');
    }

    if ($action === 'remove') {
        if (!empty($user) && !empty($user['id'])) {
            $stmt = $conn->prepare('DELETE FROM carts WHERE user_id = ? AND product_id = ?');
            if ($stmt) { $stmt->bind_param('ii', $user['id'], $product_id); $stmt->execute(); $stmt->close(); }
        } else {
            if (isset($_SESSION['cart'][(string)$product_id])) unset($_SESSION['cart'][(string)$product_id]);
        }
        // recompute
        $count = 0; $subtotal = 0.0;
        if (!empty($user) && !empty($user['id'])) {
            $q = $conn->prepare('SELECT quantity, unit_price FROM carts WHERE user_id = ?');
            if ($q) { $q->bind_param('i', $user['id']); $q->execute(); $res = $q->get_result(); while($r = $res->fetch_assoc()){ $count += (int)$r['quantity']; $subtotal += (int)$r['quantity'] * (float)$r['unit_price']; } $q->close(); }
        } else {
            foreach ($_SESSION['cart'] ?? [] as $it) { $count += (int)$it['qty']; $subtotal += (int)$it['qty'] * (float)$it['price']; }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) respond_json(['ok'=>true,'action'=>'remove','count'=>$count,'subtotal'=>$subtotal]);
        redirect('/cart');
    }
}

// Render cart items for display
$items = [];
$total = 0.0; $count = 0;
if (!empty($user) && !empty($user['id'])) {
    $q = $conn->prepare('SELECT c.product_id, c.quantity, c.unit_price, p.title, p.slug, p.images FROM carts c LEFT JOIN products p ON p.id = c.product_id WHERE c.user_id = ?');
    if ($q) {
        $q->bind_param('i', $user['id']); $q->execute(); $res = $q->get_result();
        while ($r = $res->fetch_assoc()) {
            $imgs = [];
            if (!empty($r['images'])) { $d = json_decode($r['images'], true); if (is_array($d)) $imgs = $d; else $imgs = array_filter(array_map('trim', explode(',', $r['images']))); }
            $image = $imgs[0] ?? '/public/images/products/placeholder.png';
            $lineTotal = (int)$r['quantity'] * (float)$r['unit_price'];
            $items[] = ['product_id'=>(int)$r['product_id'],'title'=>$r['title'],'slug'=>$r['slug'],'image'=>$image,'qty'=>(int)$r['quantity'],'price'=> (float)$r['unit_price'],'line_total'=>$lineTotal];
            $total += $lineTotal; $count += (int)$r['quantity'];
        }
        $q->close();
    }
} else {
    foreach ($_SESSION['cart'] ?? [] as $k => $it) {
        $pid = isset($it['product_id']) ? (int)$it['product_id'] : (int)$k;
        $title = $it['title'] ?? 'Product';
        $image = $it['image'] ?? '/public/images/products/placeholder.png';
        $qty = isset($it['qty']) ? (int)$it['qty'] : (isset($it['quantity']) ? (int)$it['quantity'] : 1);
        $price = isset($it['price']) ? (float)$it['price'] : 0.0;
        $lineTotal = $qty * $price;
        $items[] = ['product_id'=>$pid,'title'=>$title,'slug'=>'','image'=>$image,'qty'=>$qty,'price'=>$price,'line_total'=>$lineTotal];
        $total += $lineTotal; $count += $qty;
    }
}

pageHead("Cart - Supershop", ["home.css"]);
component('header.php', ['user' => $user]);
?> 
<div style="max-width:1100px;margin:26px auto;padding:18px">
    <h2 style="margin:0 0 12px">Your cart</h2>
    <?php if (empty($items)): ?>
        <div style="padding:18px;background:#fff;border-radius:10px;border:1px solid #eee;text-align:center">Your cart is empty. <a href="<?php echo url('/product'); ?>">Continue shopping</a></div>
    <?php else: ?>
        <div style="display:flex;gap:20px;align-items:flex-start">
            <div style="flex:1;background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr style="text-align:left;color:#374151;border-bottom:1px solid #f1f5f9">
                            <th style="padding:10px">Product</th>
                            <th style="padding:10px;width:120px">Price</th>
                            <th style="padding:10px;width:140px">Quantity</th>
                            <th style="padding:10px;width:140px">Total</th>
                            <th style="padding:10px;width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                        <tr style="border-bottom:1px solid #f8fafc">
                            <td style="padding:12px;vertical-align:middle">
                                <div style="display:flex;align-items:center;gap:12px">
                                    <img src="<?php echo htmlspecialchars($it['image']); ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px">
                                    <div>
                                        <div style="font-weight:700;color:#0f172a"><a href="<?php echo $it['slug'] ? url('/product?slug=' . rawurlencode($it['slug'])) : '#'; ?>" style="color:inherit;text-decoration:none"><?php echo htmlspecialchars($it['title']); ?></a></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding:12px;vertical-align:middle">৳ <?php echo number_format($it['price'],2); ?></td>
                            <td style="padding:12px;vertical-align:middle">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <input data-pid="<?php echo (int)$it['product_id']; ?>" class="cart-qty" type="number" min="1" value="<?php echo (int)$it['qty']; ?>" style="width:72px;padding:8px;border:1px solid #e6edf3;border-radius:8px">
                                    <button class="btn-update" data-pid="<?php echo (int)$it['product_id']; ?>" style="padding:8px 10px;border-radius:8px;background:#0a74da;color:#fff;border:0;cursor:pointer">Update</button>
                                </div>
                            </td>
                            <td style="padding:12px;vertical-align:middle">৳ <?php echo number_format($it['line_total'],2); ?></td>
                            <td style="padding:12px;vertical-align:middle">
                                <form method="POST" style="display:inline" onsubmit="return confirm('Remove this item?');">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$it['product_id']; ?>">
                                    <button type="submit" style="background:#fff;border:0;color:#ef4444;cursor:pointer">Remove</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <aside style="width:320px">
                <div style="background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
                    <h3 style="margin:0 0 8px">Order summary</h3>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;color:#374151"> <div>Items</div> <div><?php echo $count; ?></div></div>
                    <div style="display:flex;justify-content:space-between;padding:8px 0;font-weight:700;color:#0f172a"> <div>Subtotal</div> <div>৳ <?php echo number_format($total,2); ?></div></div>
                    <div style="margin-top:12px">
                        <a href="<?php echo url('/cart/checkout'); ?>" style="display:block;text-align:center;padding:10px;border-radius:10px;background:#0b8457;color:#fff;text-decoration:none">Proceed to checkout</a>
                        <a href="<?php echo url('/product'); ?>" style="display:block;text-align:center;margin-top:8px;padding:10px;border-radius:10px;background:#fff;border:1px solid #e6edf3;color:#0f172a;text-decoration:none">Continue shopping</a>
                    </div>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<script>
// AJAX update/remove handlers to call this page and update header via window.updateCart
(function(){
    function postForm(data){
        return fetch(window.location.href, {method:'POST', credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(data)})
            .then(function(r){ return r.json ? r.json() : r.text().then(JSON.parse); });
    }

    document.querySelectorAll('.btn-update').forEach(function(btn){
        btn.addEventListener('click', function(e){
            var pid = btn.getAttribute('data-pid');
            var input = document.querySelector('input.cart-qty[data-pid="'+pid+'"]');
            var qty = input ? parseInt(input.value||'1') : 1;
            btn.disabled = true;
            postForm({action:'update', product_id: pid, qty: qty}).then(function(json){
                btn.disabled = false;
                if (json && json.ok){
                    if (window.updateCart) window.updateCart({count: json.count, subtotal: json.subtotal});
                    location.reload();
                } else alert((json && json.error) || 'Could not update cart');
            }).catch(function(){ btn.disabled = false; alert('Network error'); });
        });
    });

    // intercept remove forms to use AJAX
    document.querySelectorAll('form input[name="action"][value="remove"]').forEach(function(inp){
        var form = inp.form;
        form.addEventListener('submit', function(e){
            e.preventDefault();
            if (!confirm('Remove this item?')) return;
            var pid = form.querySelector('input[name="product_id"]').value;
            postForm({action:'remove', product_id: pid}).then(function(json){
                if (json && json.ok){ if (window.updateCart) window.updateCart({count: json.count, subtotal: json.subtotal}); location.reload(); } else alert('Could not remove item');
            }).catch(function(){ alert('Network error'); });
        });
    });
})();
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>
