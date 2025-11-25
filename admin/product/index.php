<?php
require_once __DIR__ . '/../../_imports.php';
pageHead("Products - Supershop", ["admin_dashboard.css"]);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$perPage = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// total count
$total = 0;
$cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM products");
if ($cntRes) {
    $r = $cntRes->fetch_assoc();
    $total = (int)($r['cnt'] ?? 0);
}

$products = [];
$stmt = $conn->prepare("SELECT p.id, p.sku, p.title, p.slug, p.price, p.stock, p.images, p.is_active, p.created_at, c.name AS category_name FROM products p LEFT JOIN categories c ON p.primary_category_id = c.id ORDER BY p.created_at DESC LIMIT ?, ?");
if ($stmt) {
    $stmt->bind_param('ii', $offset, $perPage);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $stmt->close();
} else {
    $products = [];
}

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

?>

<div class="admin-container">

    <div class="admin-entity-header">
        <h1>Products</h1>
        <div>
            <a class="btn" href="<?php echo url('/admin/product/new'); ?>">Add New</a>
        </div>
    </div>

    <div class="card">
        <div style="overflow-x:auto">
            <table class="admin-table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px">ID</th>
                        <th style="text-align:left;padding:8px">Image</th>
                        <th style="text-align:left;padding:8px">Title</th>
                        <th style="text-align:left;padding:8px">SKU</th>
                        <th style="text-align:left;padding:8px">Price</th>
                        <th style="text-align:left;padding:8px">Stock</th>
                        <th style="text-align:left;padding:8px">Category</th>
                        <th style="text-align:left;padding:8px">Active</th>
                        <th style="text-align:left;padding:8px">Created</th>
                        <th style="text-align:left;padding:8px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="10" style="padding:12px">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p):
                            // prepare thumbnail from images JSON
                            $thumb = '';
                            if (!empty($p['images'])) {
                                $j = json_decode($p['images'], true);
                                if (is_array($j) && count($j)) $thumb = $j[0];
                            }
                        ?>
                        <tr>
                            <td style="padding:8px;vertical-align:middle"><?php echo (int)$p['id']; ?></td>
                            <td style="padding:8px;vertical-align:middle">
                                <?php if ($thumb): ?>
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" alt="" style="width:64px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #eee">
                                <?php else: ?>
                                    <div style="width:64px;height:48px;background:#f3f4f6;border-radius:6px;display:inline-block"></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:8px;vertical-align:middle"><a href="<?php echo url('/admin/product/edit.php') . '?id=' . (int)$p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></a></td>
                            <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($p['sku']); ?></td>
                            <td style="padding:8px;vertical-align:middle">$ <?php echo number_format((float)$p['price'], 2); ?></td>
                            <td style="padding:8px;vertical-align:middle"><?php echo (int)$p['stock']; ?></td>
                            <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                            <td style="padding:8px;vertical-align:middle"><?php echo $p['is_active'] ? 'Yes' : 'No'; ?></td>
                            <td style="padding:8px;vertical-align:middle"><?php echo htmlspecialchars($p['created_at']); ?></td>
                            <td style="padding:8px;vertical-align:middle">
                                <a class="btn" href="<?php echo url('/admin/product/edit.php') . '?id=' . (int)$p['id']; ?>">Edit</a>
                                <a class="btn" href="<?php echo url('/admin/product/delete.php') . '?id=' . (int)$p['id']; ?>" onclick="return confirm('Delete this product?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?php if ($page > 1): ?>
                <a class="btn" href="?page=<?php echo $page - 1; ?>">« Prev</a>
            <?php endif; ?>

            <?php
            // show small window of pages
            $start = max(1, $page - 3);
            $end = min($totalPages, $page + 3);
            for ($p = $start; $p <= $end; $p++): ?>
                <?php if ($p === $page): ?>
                    <strong style="padding:6px 10px;border-radius:6px;background:#eef2ff"><?php echo $p; ?></strong>
                <?php else: ?>
                    <a class="btn" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a class="btn" href="?page=<?php echo $page + 1; ?>">Next »</a>
            <?php endif; ?>
            <div style="margin-left:8px;color:#64748b">Page <?php echo $page; ?> of <?php echo $totalPages; ?> — <?php echo $total; ?> products</div>
        </div>
    <?php endif; ?>

</div>

<?php
pageFooter();
?>
