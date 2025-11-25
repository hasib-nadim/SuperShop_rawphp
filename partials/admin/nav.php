 

<nav class="admin-nav">
	<div class="admin-nav-inner">
		<a class="brand" href="<?php echo url("/admin"); ?>">🛒 Super shop</a>

		<ul class="nav-links">
			<li><a href="/admin">Dashboard</a></li>
			<li><a href="/admin/product">Products</a></li>
			<li><a href="/admin/category">Categories</a></li>
			<li><a href="/admin/orders">Orders</a></li>
			<li><a href="/admin/adminuser">Admins</a></li>
			<li><a href="/admin/users">Users</a></li> 
		</ul>

		<div class="admin-actions">
			<span class="admin-welcome">Hello, <?php echo htmlspecialchars( $username?? 'Admin'); ?></span>
			<a class="btn-logout" href="/admin/logout.php" rel="nofollow">Logout</a>
		</div>
	</div>
</nav>

<style>
/* Minimal admin nav styles (can be moved to public/css/admin_dashboard.css) */
.admin-nav{background:#fff;border-bottom:1px solid #e6e9ef}
.admin-nav-inner{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;padding:12px 20px}
.admin-nav .brand{font-weight:700;color:#2b6cb0;text-decoration:none;margin-right:18px}
.nav-links{list-style:none;margin:0;padding:0;display:flex;gap:12px;align-items:center}
.nav-links a{color:#334155;text-decoration:none;padding:8px 10px;border-radius:6px}
.nav-links a:hover{background:#f1f5f9}
.admin-actions{display:flex;gap:12px;align-items:center}
.admin-welcome{color:#374151;font-size:14px}
.btn-logout{background:#ef4444;color:#fff;padding:8px 10px;border-radius:6px;text-decoration:none;font-weight:600}
.btn-logout:hover{opacity:0.95}
@media (max-width:800px){.nav-links{display:none}}
</style>