<?php
require_once __DIR__ . '/../../_imports.php';
pageHead('Orders - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$total = 0;
$cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM orders");
if ($cntRes) { $r = $cntRes->fetch_assoc(); $total = (int)($r['cnt'] ?? 0); }

$orders = [];
$stmt = $conn->prepare("SELECT o.id, o.user_id, o.total_amount, o.status, o.created_at, u.first_name, u.last_name, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT ?, ?");
if ($stmt) {
    $stmt->bind_param('ii', $offset, $perPage);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) while ($row = $res->fetch_assoc()) $orders[] = $row;
    $stmt->close();
}

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

?>

<div class="admin-container">

    <div class="admin-entity-header">
        <h1>Orders</h1>
        <div>
            <!-- potential actions -->
        </div>
    </div>

    <div class="card">
        <?php if ($stmt === false && empty($orders)): ?>
            <div style="padding:18px">No orders or the `orders` table does not exist. Please check your database schema.</div>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:8px">ID</th>
                            <th style="text-align:left;padding:8px">Customer</th>
                            <th style="text-align:left;padding:8px">Email</th>
                            <th style="text-align:left;padding:8px">Total</th>
                            <th style="text-align:left;padding:8px">Status</th>
                            <th style="text-align:left;padding:8px">Created</th>
                            <th style="text-align:left;padding:8px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7" style="padding:12px">No orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <tr>
                                    <td style="padding:8px;vertical-align:middle"><?php echo (int)$o['id']; ?></td>
                                    <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars(trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) ?: 'Guest'); ?></td>
                                    <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($o['email'] ?? '-'); ?></td>
                                    <td style="padding:8px;vertical-align:middle">₹ <?php echo number_format((float)($o['total_amount'] ?? 0), 2); ?></td>
                                    <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($o['status'] ?? ''); ?></td>
                                    <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($o['created_at'] ?? ''); ?></td>
                                    <td style="padding:8px;vertical-align:middle">
                                        <a class="btn" href="<?php echo url('/admin/orders/view.php') . '?id=' . (int)$o['id']; ?>">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?php if ($page > 1): ?><a class="btn" href="?page=<?php echo $page - 1; ?>">« Prev</a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $page): ?>
                    <strong style="padding:6px 10px;border-radius:6px;background:#eef2ff"><?php echo $p; ?></strong>
                <?php else: ?>
                    <a class="btn" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a class="btn" href="?page=<?php echo $page + 1; ?>">Next »</a><?php endif; ?>
            <div style="margin-left:8px;color:#64748b">Page <?php echo $page; ?> of <?php echo $totalPages; ?> — <?php echo $total; ?> orders</div>
        </div>
    <?php endif; ?>

</div>

<?php
pageFooter();
?>
