<?php
require_once __DIR__ . '/../_imports.php';
pageHead("Admin Dashboard - Supershop", ["admin_dashboard.css",]);
$admin = GetAdmin();
component('admin/nav.php',$admin);
?>

<?php
$conn = DB\getConnection();

// Products counts
$products_total = 0;
$products_in_stock = 0;
$products_out_stock = 0;
$products_low_stock = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM products");
if ($res) { $r = $res->fetch_assoc(); $products_total = (int)($r['cnt'] ?? 0); }
$res = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE stock > 0");
if ($res) { $r = $res->fetch_assoc(); $products_in_stock = (int)($r['cnt'] ?? 0); }
$res = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE stock <= 0");
if ($res) { $r = $res->fetch_assoc(); $products_out_stock = (int)($r['cnt'] ?? 0); }
$res = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE stock <= 5 AND stock > 0");
if ($res) { $r = $res->fetch_assoc(); $products_low_stock = (int)($r['cnt'] ?? 0); }

// Categories
$categories_total = 0;
$categories_active = 0;
$categories_inactive = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM categories");
if ($res) { $r = $res->fetch_assoc(); $categories_total = (int)($r['cnt'] ?? 0); }
$res = $conn->query("SELECT COUNT(*) AS cnt FROM categories WHERE is_active = 1");
if ($res) { $r = $res->fetch_assoc(); $categories_active = (int)($r['cnt'] ?? 0); }
$categories_inactive = $categories_total - $categories_active;

// Users and admins
$users_total = 0;
$admins_total = 0;
$superadmins = 0;
$res = $conn->query("SELECT COUNT(*) AS cnt FROM users");
if ($res) { $r = $res->fetch_assoc(); $users_total = (int)($r['cnt'] ?? 0); }
$res = $conn->query("SELECT COUNT(*) AS cnt FROM adminuser");
if ($res) { $r = $res->fetch_assoc(); $admins_total = (int)($r['cnt'] ?? 0); }
$res = $conn->query("SELECT COUNT(*) AS cnt FROM adminuser WHERE is_super = 1");
if ($res) { $r = $res->fetch_assoc(); $superadmins = (int)($r['cnt'] ?? 0); }

?>

<div class="admin-container">

	<div class="admin-entity-header">
		<h1>Dashboard</h1>
		<a href="/" target="_blank" rel="noopener noreferrer">View Site</a>
	</div>

	<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px">
		<div class="card" style="padding:14px">
			<div style="font-size:13px;color:#64748b">Products (total)</div>
			<div style="font-size:22px;font-weight:700;margin-top:6px"><?php echo $products_total; ?></div>
			<div style="margin-top:8px;color:#6b7280">In stock: <?php echo $products_in_stock; ?> · Out of stock: <?php echo $products_out_stock; ?> · Low (&le;5): <?php echo $products_low_stock; ?></div>
		</div>

		<div class="card" style="padding:14px">
			<div style="font-size:13px;color:#64748b">Categories</div>
			<div style="font-size:22px;font-weight:700;margin-top:6px"><?php echo $categories_total; ?></div>
			<div style="margin-top:8px;color:#6b7280">Active: <?php echo $categories_active; ?> · Inactive: <?php echo $categories_inactive; ?></div>
		</div>

		<div class="card" style="padding:14px">
			<div style="font-size:13px;color:#64748b">Users</div>
			<div style="font-size:22px;font-weight:700;margin-top:6px"><?php echo $users_total; ?></div>
			<div style="margin-top:8px;color:#6b7280">Admins: <?php echo $admins_total; ?> · Super admins: <?php echo $superadmins; ?></div>
		</div>

		<div class="card" style="padding:14px">
			<div style="font-size:13px;color:#64748b">Products status</div>
			<div style="font-size:22px;font-weight:700;margin-top:6px"><?php echo $products_in_stock; ?> available</div>
			<div style="margin-top:8px;color:#6b7280"><?php echo $products_out_stock; ?> products need restocking</div>
		</div>
	</div>

	<div class="card">
		<h3 style="margin:0 0 8px 0">Recent products</h3>
		<?php
		$rows = [];
		$stmt = $conn->prepare('SELECT id, title, sku, price, stock, images, is_active, created_at FROM products ORDER BY created_at DESC LIMIT 8');
		if ($stmt) {
			$stmt->execute();
			$res = $stmt->get_result();
			if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
			$stmt->close();
		}
		?>
		<?php if (empty($rows)): ?>
			<div class="muted" style="padding:10px">No recent products</div>
		<?php else: ?>
			<div style="overflow-x:auto">
			<table style="width:100%;border-collapse:collapse">
				<thead>
					<tr>
						<th style="text-align:left;padding:8px">ID</th>
						<th style="text-align:left;padding:8px">Title</th>
						<th style="text-align:left;padding:8px">SKU</th>
						<th style="text-align:left;padding:8px">Price</th>
						<th style="text-align:left;padding:8px">Stock</th>
						<th style="text-align:left;padding:8px">Active</th>
						<th style="text-align:left;padding:8px">Created</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $p): ?>
					<tr>
						<td style="padding:8px"><?php echo (int)$p['id']; ?></td>
						<td style="padding:8px"><?php echo htmlspecialchars($p['title']); ?></td>
						<td style="padding:8px"><?php echo htmlspecialchars($p['sku']); ?></td>
						<td style="padding:8px">$ <?php echo number_format((float)$p['price'],2); ?></td>
						<td style="padding:8px"><?php echo (int)$p['stock']; ?></td>
						<td style="padding:8px"><?php echo $p['is_active'] ? 'Yes' : 'No'; ?></td>
						<td style="padding:8px"><?php echo htmlspecialchars($p['created_at']); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>
	</div>

</div>