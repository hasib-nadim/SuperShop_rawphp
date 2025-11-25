<?php

use function Req\input;

require_once __DIR__ . '/../_imports.php';
pageHead("Products - Supershop", ["home.css"]);


$conn = DB\getConnection();

// query params
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$min_price = isset($_GET['min_price']) ? $_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) ? $_GET['max_price'] : '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// build where clauses
$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
    $types .= 'ss';
}

if ($category !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $category;
    $types .= 's';
}

if ($min_price !== '' && is_numeric($min_price)) {
    $where[] = 'p.price >= ?';
    $params[] = (float)$min_price;
    $types .= 'd';
}
if ($max_price !== '' && is_numeric($max_price)) {
    $where[] = 'p.price <= ?';
    $params[] = (float)$max_price;
    $types .= 'd';
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where);
}

// sorting
switch ($sort) {
    case 'price_asc':
        $order_sql = 'ORDER BY p.price ASC';
        break;
    case 'price_desc':
        $order_sql = 'ORDER BY p.price DESC';
        break;
    case 'oldest':
        $order_sql = 'ORDER BY p.created_at ASC';
        break;
    default:
        $order_sql = 'ORDER BY p.created_at DESC';
}

// count total
$countSql = "SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.primary_category_id" . $where_sql;
$total = 0;
if ($countStmt = $conn->prepare($countSql)) {
    if ($types !== '') {
        $tmp = array_merge([$types], $params);
        $refs = [];
        foreach ($tmp as $k => $v) $refs[$k] = &$tmp[$k];
        call_user_func_array([$countStmt, 'bind_param'], $refs);
    }
    $countStmt->execute();
    $countStmt->bind_result($total);
    $countStmt->fetch();
    $countStmt->close();
}

// fetch products
$sql = "SELECT p.id,p.title,p.slug,p.price,p.images,p.stock,p.created_at, c.name as category_name, c.slug as category_slug "
    . "FROM products p LEFT JOIN categories c ON c.id = p.primary_category_id "
    . $where_sql . " " . $order_sql . " LIMIT ?,?";

$stmt = $conn->prepare($sql);
$products = [];
if ($stmt) {
    // bind params + offset/limit
    $allParams = $params;
    $allTypes = $types;
    $allParams[] = $offset;
    $allParams[] = $perPage;
    $allTypes .= 'ii';

    $tmp = array_merge([$allTypes], $allParams);
    $refs = [];
    foreach ($tmp as $k => $v) $refs[$k] = &$tmp[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // normalize images (stored as JSON or comma list)
        $imgs = [];
        if (!empty($row['images'])) {
            $raw = $row['images'];
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $imgs = $decoded;
            else $imgs = array_filter(array_map('trim', explode(',', $raw)));
        }
        $row['images_arr'] = $imgs;
        $products[] = $row;
    }
    $stmt->close();
}

// fetch categories for filter select (all active)
$cats = [];
$cres = $conn->query("SELECT id,name,slug FROM categories WHERE is_active = 1 ORDER BY name ASC");
if ($cres) {
    while ($crow = $cres->fetch_assoc()) $cats[] = $crow;
}

// pagination helper
$totalPages = max(1, (int)ceil($total / $perPage));
$user = GetUser();
component('header.php', ['user' => $user]);
component('nav.php');

?>

<?php
$product_slug = input('slug');
if(!empty($product_slug)):
component('product_details.php', ['slug' => $product_slug]);
else: ?>

<style>
    :root{
        --bg:#f6f8fa;--card:#ffffff;--muted:#6b7280;--accent:#0a74da;--accent-2:#0b8457;--surface:#eef2f6;
        --radius:10px;--gap:20px;
    }
    .products-layout{display:flex;gap:var(--gap);align-items:flex-start;margin-top:8px}
    .filters-sidebar{width:300px;flex:0 0 300px}
    .filters-card{border-radius:var(--radius);padding:16px;background:var(--card);box-shadow:0 6px 18px rgba(20,20,30,0.04);border:1px solid rgba(15,23,42,0.04)}
    .filters-card h3{margin:0 0 12px 0;font-size:16px}
    .filters-card .form-row{margin-bottom:12px}
    .filters-card input[type="text"],.filters-card input[type="number"],.filters-card select{width:100%;padding:10px;border:1px solid #e6e9ef;border-radius:8px;background:transparent}
    .products-main{flex:1;width: 100%;}

    .page-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
    .page-head h1{margin:0;font-size:22px}
    .filter-toggle{display:none;padding:8px 12px;border-radius:8px;border:1px solid #e4e7ec;background:var(--card);cursor:pointer}

    .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px}
    .product-card{background:var(--card);border-radius:14px;padding:0;display:flex;flex-direction:column;gap:10px;box-shadow:0 10px 28px rgba(20,20,30,0.05);transition:transform .16s ease,box-shadow .16s ease;overflow:hidden}
    .product-card:hover{transform:translateY(-8px);box-shadow:0 28px 60px rgba(16,24,40,0.12)}
    .product-media{position:relative;background:linear-gradient(180deg, #fbfdff, var(--surface));height:220px;display:flex;align-items:center;justify-content:center}
    .product-media img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .28s ease}
    .product-card:hover .product-media img{transform:scale(1.04)}
    .product-body{padding:12px 14px 16px}
    .product-title{font-weight:700;color:#0f172a;font-size:15px;margin-bottom:6px}
    .product-meta{color:var(--muted);font-size:13px;margin-bottom:8px}
    .product-footer{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:6px}
    .price{font-weight:800;color:var(--accent-2);font-size:16px}
    .btn{padding:8px 10px;border-radius:8px;border:0;cursor:pointer;font-weight:600}
    .btn-primary{background:var(--accent-2);color:#fff}
    .btn-outline{background:transparent;border:1px solid #e6e9ef;color:#111}

    /* upgraded card extras */
    .price-badge{position:absolute;right:10px;top:10px;background:var(--accent-2);color:#fff;padding:6px 8px;border-radius:10px;font-weight:700;box-shadow:0 6px 12px rgba(10,20,40,0.08)}
    .quick-actions{position:absolute;left:10px;bottom:10px;display:flex;gap:8px;opacity:0;transform:translateY(6px);transition:all .18s ease}
    .product-card:hover .quick-actions{opacity:1;transform:translateY(0)}
    .quick-actions .btn{padding:8px 10px;border-radius:10px;background:rgba(255,255,255,0.92);color:#0f172a;border:1px solid rgba(10,10,12,0.04)}
    .star{color:#fbbf24;font-size:13px}

    .price-badge{position:absolute;right:10px;top:10px;background:linear-gradient(180deg,var(--accent),#0563b8);color:#fff;padding:6px 8px;border-radius:10px;font-weight:700;box-shadow:0 6px 12px rgba(10,20,40,0.08)}

    .pagination{display:flex;gap:10px;align-items:center}

    @media (max-width:900px){
        .filters-sidebar{width:260px}
        .products-grid{grid-template-columns:repeat(auto-fill,minmax(180px,1fr));}
    }
    /* treat "big mobile" (<=600px) as the point to hide filters and use slide-in */
    @media (max-width:600px){
        .products-layout{flex-direction:column}
        .filters-sidebar{width:100%;order:2}
        .filters-card{position:static}
        .filter-toggle{display:inline-block}
        /* mobile: hide sidebar by default and use slide-in when opened */
        .filters-sidebar{display:none}
        .filters-sidebar.mobile-open{display:block;position:fixed;left:0;top:0;bottom:0;width:85%;max-width:360px;padding:20px;z-index:1200;transform:translateX(0);transition:transform .22s ease;background:var(--card);box-shadow:0 20px 60px rgba(2,6,23,0.2)}
        .filters-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1100}
        .product-media{height:180px}
    }
</style>

<div class="container">
    <div class="products-layout">
        <aside class="filters-sidebar">
            <div class="filters-card" style="position:sticky;top:88px;">
                <h3>Filters</h3>
                <form method="GET" class="filters" style="display:block;">
                    <div class="form-row">
                        <label for="q">Search</label>
                        <input id="q" type="text" name="q" placeholder="Search products" value="<?php echo htmlspecialchars($q); ?>" />
                    </div>

                    <div class="form-row">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All categories</option>
                            <?php foreach ($cats as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['slug']); ?>" <?php echo ($category === $c['slug']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label>Price</label>
                        <div style="display:flex;gap:8px;">
                            <input type="number" name="min_price" placeholder="Min" step="0.01" value="<?php echo htmlspecialchars($min_price); ?>" />
                            <input type="number" name="max_price" placeholder="Max" step="0.01" value="<?php echo htmlspecialchars($max_price); ?>" />
                        </div>
                    </div>

                    <div class="form-row">
                        <label for="sort">Sort</label>
                        <select id="sort" name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                            <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low → High</option>
                            <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High → Low</option>
                        </select>
                    </div>

                    <div style="display:flex;gap:8px;margin-top:8px;">
                        <button type="submit" style="flex:1;padding:8px 12px;border-radius:6px;border:0;background:#0a74da;color:#fff">Apply</button>
                        <a href="/product" style="display:inline-block;padding:8px 12px;border-radius:6px;border:1px solid #ddd;background:#fff;color:#333;text-decoration:none">Reset</a>
                    </div>
                </form>
            </div>
        </aside>

        <main class="products-main">
            <div class="page-head">
                <h1>Products</h1>
                <div style="display:flex;gap:12px;align-items:center">
                    <div style="color:var(--muted);font-size:14px">Showing <?php echo count($products); ?> of <?php echo $total; ?> results</div>
                    <button id="filterToggle" class="filter-toggle" aria-expanded="false">Filters</button>
                </div>
            </div>

            <div class="products-grid">
                <?php if (empty($products)): ?>
                    <div>No products found.</div>
                <?php endif; ?>
                <?php foreach ($products as $p): ?>
                    <?php $img = $p['images_arr'][0] ?? '/public/images/products/placeholder.png'; ?>
                    <div class="product-card upgraded">
                        <div class="product-media">
                            <a class="media-link" href="<?php echo url('/product?slug=' . htmlspecialchars($p['slug'])); ?>" aria-label="View <?php echo htmlspecialchars($p['title']); ?>">
                                <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" />
                            </a>
                            <div class="price-badge">৳ <?php echo number_format((float)$p['price'], 2); ?></div>
                            <div class="quick-actions">
                                <button type="button" class="btn add-mini add-to-cart" data-endpoint="<?php echo url('/product/add_cart.php'); ?>" data-product-id="<?php echo (int)$p['id']; ?>">Add</button>
                                <a class="btn" href="<?php echo url('/product?slug=' . htmlspecialchars($p['slug'])); ?>">View</a>
                            </div>
                        </div>
                        <div class="product-body">
                            <div class="product-title"><a href="<?php echo url('/product?slug=' . htmlspecialchars($p['slug'])); ?>"><?php echo htmlspecialchars($p['title']); ?></a></div>
                            <div class="product-meta"><?php echo htmlspecialchars($p['category_name'] ?? ''); ?></div>
                            <div class="product-footer">
                                <div class="price">৳ <?php echo number_format((float)$p['price'], 2); ?></div>
                                <div class="rating"><span class="star">★ ★ ★ ★ ☆</span></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pagination" style="margin-top:18px;display:flex;gap:8px;align-items:center;">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Prev</a>
                <?php endif; ?>
                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
    (function(){
        var toggle = document.getElementById('filterToggle');
        var sidebar = document.querySelector('.filters-sidebar');
        var backdrop = null;
        function closeSidebar(){
            if(!sidebar) return;
            sidebar.classList.remove('mobile-open');
            document.body.style.overflow = '';
            if(backdrop){ backdrop.remove(); backdrop = null; }
            if(toggle) toggle.setAttribute('aria-expanded','false');
        }
        function openSidebar(){
            if(!sidebar) return;
            sidebar.classList.add('mobile-open');
            document.body.style.overflow = 'hidden';
            backdrop = document.createElement('div');
            backdrop.className = 'filters-backdrop';
            backdrop.addEventListener('click', closeSidebar);
            document.body.appendChild(backdrop);
            if(toggle) toggle.setAttribute('aria-expanded','true');
        }

        if(toggle && sidebar){
            toggle.addEventListener('click', function(){
                var isOpen = sidebar.classList.contains('mobile-open');
                if(isOpen) closeSidebar(); else openSidebar();
            });
        }

        // cleanup on resize (if switching to desktop)
        window.addEventListener('resize', function(){
            if(window.innerWidth > 600){
                // ensure sidebar/backdrop are removed
                if(sidebar) sidebar.classList.remove('mobile-open');
                if(backdrop){ backdrop.remove(); backdrop = null; }
                document.body.style.overflow = '';
                if(toggle) toggle.setAttribute('aria-expanded','false');
            }
        });
    })();
</script>

<script>
// Bind add-to-cart buttons on products listing page to centralized handler
(function(){
    function safeBind(btn){
        btn.addEventListener('click', function(e){
            var pid = btn.getAttribute('data-product-id');
            var endpoint = btn.getAttribute('data-endpoint') || '/product/add_cart.php';
            if (typeof window.addToCart === 'function') {
                window.addToCart(pid, 1, {endpoint: endpoint, btn: btn}).catch(function(){});
            } else {
                btn.disabled = true;
                fetch(endpoint, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body: new URLSearchParams({product_id: pid, qty:1})})
                    .then(function(r){ return r.text(); }).then(function(){ btn.disabled = false; location.reload(); }).catch(function(){ btn.disabled = false; alert('Network error'); });
            }
        });
    }
    document.querySelectorAll('.add-to-cart').forEach(safeBind);

    // optionally make product-card clickable (navigate when clicking outside controls)
    document.querySelectorAll('.product-card').forEach(function(card){
        card.addEventListener('click', function(e){
            if (e.target.closest('a') || e.target.closest('button') || e.target.closest('form')) return;
            var link = card.querySelector('.product-title a');
            if (link) window.location.href = link.href;
        });
    });
})();
</script>

<?php
endif;
pageFooter();
?>