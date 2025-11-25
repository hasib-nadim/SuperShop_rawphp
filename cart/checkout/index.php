<?php
require_once __DIR__ . '/../../_imports.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Require login for checkout
$user = GetUser(true);
$conn = DB\getConnection();


// load cart items for user
$items = [];
$total = 0.0;
$count = 0;
$q = $conn->prepare('SELECT c.product_id, c.quantity, c.unit_price, p.title, p.slug, p.images FROM carts c LEFT JOIN products p ON p.id = c.product_id WHERE c.user_id = ?');
if ($q) {
    $q->bind_param('i', $user['id']);
    $q->execute();
    $res = $q->get_result();
    while ($r = $res->fetch_assoc()) {
        $imgs = [];
        if (!empty($r['images'])) {
            $d = json_decode($r['images'], true);
            if (is_array($d)) $imgs = $d;
            else $imgs = array_filter(array_map('trim', explode(',', $r['images'])));
        }
        $image = $imgs[0] ?? '/public/images/products/placeholder.png';
        $lineTotal = (int)$r['quantity'] * (float)$r['unit_price'];
        $items[] = ['product_id' => (int)$r['product_id'], 'title' => $r['title'], 'slug' => $r['slug'], 'image' => $image, 'qty' => (int)$r['quantity'], 'price' => (float)$r['unit_price'], 'line_total' => $lineTotal];
        $total += $lineTotal;
        $count += (int)$r['quantity'];
    }
    $q->close();
}

// If no items, redirect back to cart
if (empty($items)) {
    $_SESSION['flash_error'] = 'Your cart is empty.';
    redirect('/cart');
}

// Handle POST: create order
if (Req\isPost()) {
    // basic shipping fields (you can expand validation as needed)
    [$inputs, $errors] = Req\validate([
        'first_name' => 'required|trim|min:3',
        'last_name' => 'trim',
        'phone' => 'required|trim|min:10',
        'address' => 'required|trim|min:5',
    ]);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = 'Please correct the errors in the form.';
        $_SESSION['old_checkout'] = $inputs;
        header('Location: /cart/checkout');
        return;
    }
    // Start transaction
    try {
        $conn->begin_transaction();

        // compute total again from DB rows (defensive)
        $totalAmount = 0.0;
        foreach ($items as $it) {
            $totalAmount += $it['line_total'];
        }

        $stmt = $conn->prepare('INSERT INTO orders (`user_id`,`total_amount`,`status`,`shipping_first_name`,`shipping_last_name`,`shipping_phone`,`shipping_address`) VALUES (?,?,?,?,?,?,?)');
        if (!$stmt) throw new Exception('Failed to prepare order insert');
        $status = 'pending';
        $first_name = $inputs['first_name'];
        $last_name = $inputs['last_name'] ?? '';
        $phone = $inputs['phone'];
        $address = $inputs['address'];
        $stmt->bind_param('idsssss', $user['id'], $totalAmount, $status, $first_name, $last_name, $phone, $address);
        $stmt->execute();
        $orderId = $conn->insert_id;
        $stmt->close();

        // insert order items
        $oi = $conn->prepare('INSERT INTO order_items (`order_id`,`product_id`,`quantity`,`unit_price`) VALUES (?,?,?,?)');
        if (!$oi) throw new Exception('Failed to prepare order_items insert');
        foreach ($items as $it) {
            $pid = (int)$it['product_id'];
            $qty = (int)$it['qty'];
            $unit = (float)$it['price'];
            $oi->bind_param('iiid', $orderId, $pid, $qty, $unit);
            $oi->execute();
        }
        $oi->close();

        // Optionally store shipping info in orders table or separate table. For now store in orders as a simple pattern (if columns exist) - else skip.
        // Clear user's cart
        $del = $conn->prepare('DELETE FROM carts WHERE user_id = ?');
        if ($del) {
            $del->bind_param('i', $user['id']);
            $del->execute();
            $del->close();
        }

        $conn->commit();
        $_SESSION['flash_success'] = 'Order placed successfully. Order #' . $orderId;
        redirect('/orders');
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        // preserve old input so user doesn't lose entered data
        $_SESSION['old_checkout'] = ['first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'address' => $address];
        $_SESSION['flash_error'] = 'Failed to place order: ' . $e->getMessage();
        redirect('/cart/checkout');
    }
}

?>

<?php
pageHead("Checkout - Supershop", ["home.css"]);
component('header.php', ['user' => $user]);
?>

<div style="max-width:1100px;margin:26px auto;padding:18px">
    <h2 style="margin:0 0 12px">Checkout</h2>
    <!-- show error message -->
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div style="padding:12px;margin-bottom:12px;background:#fee2e2;color:#b91c1c;border-radius:8px;border:1px solid #fca5a5">
            <?php echo htmlspecialchars($_SESSION['flash_error']); ?>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
    <div style="display:flex;gap:20px;align-items:flex-start">
        <div style="flex:1;background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
            <form method="POST" action="">
                <?php
                // repopulate old input after redirect when validation fails
                $old = $_SESSION['old_checkout'] ?? null;
                if ($old) {
                    $first_name_val = htmlspecialchars($old['first_name'] ?? '');
                    $last_name_val = htmlspecialchars($old['last_name'] ?? '');
                    $phone_val = htmlspecialchars($old['phone'] ?? '');
                    $address_val = htmlspecialchars($old['address'] ?? '');
                    unset($_SESSION['old_checkout']);
                } else {
                    $first_name_val = htmlspecialchars($user['username'] ?? '');
                    $last_name_val = '';
                    $phone_val = '';
                    $address_val = '';
                }
                ?>
                <div style="display:flex;gap:12px;margin-bottom:12px">
                    <input name="first_name" required placeholder="First name" value="<?php echo $first_name_val; ?>" style="flex:1;padding:10px;border:1px solid #e6edf3;border-radius:8px">
                    <input name="last_name" placeholder="Last name" value="<?php echo $last_name_val; ?>" style="flex:1;padding:10px;border:1px solid #e6edf3;border-radius:8px">
                </div>
                <div style="margin-bottom:12px">
                    <input name="phone" required placeholder="Phone" value="<?php echo $phone_val; ?>" style="width:320px;padding:10px;border:1px solid #e6edf3;border-radius:8px">
                </div>
                <div style="margin-bottom:12px">
                    <textarea name="address" required placeholder="Shipping address" rows="4" style="width:100%;padding:10px;border:1px solid #e6edf3;border-radius:8px"><?php echo $address_val; ?></textarea>
                </div>
                <div style="margin-top:8px">
                    <button type="submit" style="padding:12px 16px;border-radius:8px;background:#0b8457;color:#fff;border:0;cursor:pointer">Place order</button>
                </div>
            </form>
        </div>

        <aside style="width:360px">
            <div style="background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
                <h3 style="margin:0 0 8px">Order summary</h3>
                <div style="max-height:320px;overflow:auto;margin-bottom:8px">
                    <?php foreach ($items as $it): ?>
                        <div style="display:flex;gap:8px;padding:8px 0;border-bottom:1px solid #f7fafc">
                            <img src="<?php echo htmlspecialchars($it['image']); ?>" style="width:64px;height:64px;object-fit:cover;border-radius:6px">
                            <div style="flex:1">
                                <div style="font-weight:700;color:#0f172a"><?php echo htmlspecialchars($it['title']); ?></div>
                                <div style="color:#64748b">Qty: <?php echo (int)$it['qty']; ?> × ৳ <?php echo number_format($it['price'], 2); ?></div>
                            </div>
                            <div style="font-weight:700">৳ <?php echo number_format($it['line_total'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;color:#374151">
                    <div>Items</div>
                    <div><?php echo $count; ?></div>
                </div>
                <div style="display:flex;justify-content:space-between;padding:8px 0;font-weight:700;color:#0f172a">
                    <div>Subtotal</div>
                    <div>৳ <?php echo number_format($total, 2); ?></div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php pageFooter();
