<?php
require_once __DIR__ . '/../_imports.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user = GetUser(true);
$conn = DB\getConnection();

pageHead('My Orders - Supershop', ['home.css']);
component('header.php', ['user' => $user]);

$orders = [];
$stmt = $conn->prepare('SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC');
if ($stmt) {
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $orders[] = $r;
    $stmt->close();
}

?>
<div style="max-width:1000px;margin:28px auto;padding:18px">
    <h2 style="margin:0 0 12px">My Orders</h2>
    <?php if (empty($orders)): ?>
        <div style="padding:18px;background:#fff;border-radius:10px;border:1px solid #eee;text-align:center">You have not placed any orders yet. <a href="<?php echo url('/product'); ?>">Shop now</a></div>
    <?php else: ?>
        <div style="background:#fff;padding:12px;border-radius:10px;border:1px solid #eef2f6">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="text-align:left;color:#374151;border-bottom:1px solid #f1f5f9">
                        <th style="padding:10px">Order</th>
                        <th style="padding:10px">Total</th>
                        <th style="padding:10px">Status</th>
                        <th style="padding:10px">Placed</th>
                        <th style="padding:10px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr style="border-bottom:1px solid #f8fafc">
                            <td style="padding:12px;vertical-align:middle">#<?php echo (int)$o['id']; ?></td>
                            <td style="padding:12px;vertical-align:middle">৳ <?php echo number_format((float)$o['total_amount'],2); ?></td>
                            <td style="padding:12px;vertical-align:middle"><?php echo htmlspecialchars($o['status']); ?></td>
                            <td style="padding:12px;vertical-align:middle"><?php echo htmlspecialchars($o['created_at']); ?></td>
                            <td style="padding:12px;vertical-align:middle"><a class="btn" href="<?php echo url('/orders/view.php') . '?id=' . (int)$o['id']; ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php pageFooter();
