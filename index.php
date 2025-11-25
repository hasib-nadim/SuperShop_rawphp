<?php
require_once __DIR__ . '/_imports.php';
pageHead("Home - Supershop", ["home.css"]);
$user = GetUser();
component('header.php', ['user' => $user]);
component('nav.php');
?>


<!-- Hero Section -->
<section class="hero">
    <h1>Welcome to SuperShop</h1>
    <p>Your One-Stop Shopping Destination for Everything You Need</p>
    <a href="/product" class="btn">Shop Now</a>
</section>

<!-- Categories -->
<section class="categories">
    <h2 class="section-title">Shop by Category</h2>
    <style>
        .category-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px}
        .category-item{display:block;padding:16px;border-radius:10px;border:1px solid #e8eef5;background:#fff;color:inherit;text-decoration:none;transition:transform .12s ease,box-shadow .12s ease}
        .category-item:hover{transform:translateY(-6px);box-shadow:0 12px 30px rgba(12,18,31,0.06)}
        .category-item ul{margin:0;padding-left:18px}
        .category-item li{font-weight:700;font-size:16px;color:#0f172a}
        .category-item .children{margin-top:8px;color:#6b7280;font-size:13px}
    </style>

    <?php
    $conn = DB\getConnection();
    $parents = [];
    $cres = $conn->query("SELECT id,name,slug FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY name ASC");
    if ($cres) {
        while ($crow = $cres->fetch_assoc()) $parents[] = $crow;
    }
    ?>

    <div class="category-grid">
        <?php if (empty($parents)): ?>
            <div style="color:#6b7280">No categories available.</div>
        <?php endif; ?>

        <?php foreach ($parents as $p): ?>
            <?php
            $children = [];
            try {
                if (!empty($p['id'])) {
                    $cstmt = $conn->prepare("SELECT name,slug FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY name ASC LIMIT 2");
                    if ($cstmt) {
                        $cstmt->bind_param('i', $p['id']);
                        $cstmt->execute();
                        $cres2 = $cstmt->get_result();
                        while ($r = $cres2->fetch_assoc()) $children[] = $r;
                        $cstmt->close();
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
            ?>

            <a class="category-item" href="<?php echo url('/product?category=' . rawurlencode($p['slug'])); ?>">
                <div>
                    <ul>
                        <li><?php echo htmlspecialchars($p['name']); ?></li>
                    </ul>
                    <div class="children">
                        <?php if (!empty($children)): ?>
                            <?php echo htmlspecialchars($children[0]['name']); ?><?php if (count($children) > 1) echo ', ' . htmlspecialchars($children[1]['name']); ?>
                        <?php else: ?>
                            Explore products
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Featured Products -->
<section class="products">
    <div class="products-container">
        <h2 class="section-title">Featured Products</h2>
        <?php
        // fetch featured products
        $featured = [];
        try {
            $fstmt = $conn->prepare("SELECT p.id,p.title,p.slug,p.price,p.images,p.stock,p.created_at, c.name as category_name FROM products p LEFT JOIN categories c ON c.id = p.primary_category_id WHERE p.is_featured = 1 AND p.is_active = 1 ORDER BY p.created_at DESC LIMIT 8");
            if ($fstmt) {
                $fstmt->execute();
                $fres = $fstmt->get_result();
                while ($prow = $fres->fetch_assoc()) {
                    // normalize images
                    $imgs = [];
                    if (!empty($prow['images'])) {
                        $raw = $prow['images'];
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) $imgs = $decoded;
                        else $imgs = array_filter(array_map('trim', explode(',', $raw)));
                    }
                    $prow['images_arr'] = $imgs;
                    $featured[] = $prow;
                }
                $fstmt->close();
            }
        } catch (\Throwable $e) {
            // ignore
        }
        ?>

        <div class="product-grid">
            <style>
                .product-info .btn{padding:8px 12px;border-radius:8px;border:0;cursor:pointer;font-weight:600}
                .product-info .add-to-cart{background:#0b8457;color:#fff}
                .product-info .add-to-cart.loading{opacity:.7}
                .product-info .btn-outline{background:transparent;border:1px solid #e6edf3;color:#0f172a}
                .product-info .btn:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(12,18,31,0.05)}
                .product-info .btn:active{transform:translateY(0)}
            </style>
            <?php if (empty($featured)): ?>
                <div class="muted">No featured products right now.</div>
            <?php endif; ?>

            <?php foreach ($featured as $p): ?>
                <?php $img = $p['images_arr'][0] ?? '/public/images/products/placeholder.png'; ?>
                <div class="product-card" data-url="<?php echo url('/product?slug=' . htmlspecialchars($p['slug'])); ?>" style="cursor:pointer">
                    <div class="product-img" style="background:#f7fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;border-radius:10px;height:160px">
                        <a href="<?php echo url('/product?slug=' . htmlspecialchars($p['slug'])); ?>" style="display:block;width:100%;height:100%">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                        </a>
                    </div>
                    <div class="product-info" style="padding-top:8px">
                        <h3><a href="<?php echo url('/product?slug=' . htmlspecialchars($p['slug'])); ?>" style="color:inherit;text-decoration:none"><?php echo htmlspecialchars($p['title']); ?></a></h3>
                        <div class="price">৳ <?php echo number_format((float)$p['price'], 2); ?></div>
                        <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
                            <button type="button" class="btn add-to-cart" data-endpoint="<?php echo url('/product/add_cart.php'); ?>" data-product-id="<?php echo (int)$p['id']; ?>">Add</button>
                            <a class="btn btn-outline" href="<?php echo url('/product?slug=' . htmlspecialchars($p['slug'])); ?>">View</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>About SuperShop</h3>
            <p>Your trusted online shopping destination offering quality products at competitive prices.</p>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Contact</a></li>
                <li><a href="#">FAQs</a></li>
                <li><a href="#">Shipping Info</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Customer Service</h3>
            <ul>
                <li><a href="#">My Account</a></li>
                <li><a href="#">Order History</a></li>
                <li><a href="#">Returns</a></li>
                <li><a href="#">Privacy Policy</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Newsletter</h3>
            <p>Subscribe for exclusive deals and updates</p>
            <input type="email" placeholder="Your email" style="width: 100%; padding: 10px; margin-top: 10px; border-radius: 5px; border: none;">
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 SuperShop. All rights reserved.</p>
    </div>
</footer>

<script>
// Wire add-to-cart buttons to the centralized `window.addToCart` function (defined in /public/js/cart.js)
(function(){
    function safeAdd(btn){
        btn.addEventListener('click', function(e){
            var pid = btn.getAttribute('data-product-id');
            var endpoint = btn.getAttribute('data-endpoint') || '/product/add_cart.php';
            if (typeof window.addToCart === 'function') {
                window.addToCart(pid, 1, {endpoint: endpoint, btn: btn}).catch(function(){});
            } else {
                // fallback: perform a simple POST
                btn.disabled = true;
                fetch(endpoint, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body: new URLSearchParams({product_id: pid, qty:1})})
                    .then(function(r){ return r.text(); }).then(function(){ btn.disabled = false; location.reload(); }).catch(function(){ btn.disabled = false; alert('Network error'); });
            }
        });
    }

    document.querySelectorAll('.add-to-cart').forEach(safeAdd);

    // Category card interactions (keep existing behavior if present)
    document.querySelectorAll('.category-card').forEach(function(card){
        card.addEventListener('click', function(){
            var h = card.querySelector('h3'); if (!h) return; alert('Browsing ' + h.textContent);
        });
    });

    // Make product-card clickable except when clicking buttons/links inside
    document.querySelectorAll('.product-card').forEach(function(card){
        card.addEventListener('click', function(e){
            // do nothing if clicked element is a link or inside a link
            if (e.target.closest('a')) return;
            // do nothing if clicking the add button or a control
            if (e.target.closest('.add-to-cart') || e.target.closest('button') || e.target.closest('form')) return;
            var url = card.getAttribute('data-url');
            if (url) window.location.href = url;
        });
    });
})();
</script>
<?php
pageFooter();
?>