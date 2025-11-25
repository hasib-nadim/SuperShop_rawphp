<?php
require_once __DIR__ . '/_imports.php';
pageHead("Home - Supershop", ["home.css"]);
$user = GetUser();
component('header.php', ['user' => $user]);
component('nav.php');

// Prepare hero images: select up to 6 random featured products (use first product image)
$conn = DB\getConnection();
$hero_images = [];
try {
    $hstmt = $conn->prepare("SELECT p.images FROM products p WHERE p.is_featured = 1 AND p.is_active = 1 ORDER BY RAND() LIMIT 6");
    if ($hstmt) {
        $hstmt->execute();
        $hres = $hstmt->get_result();
        while ($row = $hres->fetch_assoc()) {
            $imgs = [];
            if (!empty($row['images'])) {
                $raw = $row['images'];
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $imgs = $decoded;
                else $imgs = array_filter(array_map('trim', explode(',', $raw)));
            }
            $hero_images[] = $imgs[0] ?? '/public/images/products/placeholder.png';
        }
        $hstmt->close();
    }
} catch (\Throwable $e) {
    // ignore, fallback to placeholder below
}
// ensure we have at least one image
if (empty($hero_images)) $hero_images = ['/public/images/products/placeholder.png'];
?>


<!-- Hero Section -->
<section class="hero" aria-labelledby="hero-heading">
    <style>
        .hero{display:flex;align-items:center;justify-content:center;padding:48px 16px;border-radius:12px;background:linear-gradient(135deg,#06b6d4 0%,#0b8457 100%);color:#fff;margin:18px 0}
        .hero-inner{max-width:1100px;display:flex;align-items:center;gap:32px}
        .hero-copy{flex:1}
        .hero h1{font-size:clamp(28px,4.5vw,44px);margin:0 0 12px;line-height:1.05}
        .hero p{margin:0 0 18px;color:rgba(255,255,255,0.95);font-size:16px}
        .hero .cta{display:flex;gap:12px;align-items:center}
        .btn-cta{padding:12px 18px;border-radius:10px;border:0;font-weight:700;cursor:pointer}
        .btn-cta.primary{background:#fff;color:#0b8457}
        .btn-cta.secondary{background:transparent;border:1px solid rgba(255,255,255,0.18);color:#fff}
        .hero-visual{width:320px;flex:0 0 320px;border-radius:10px;overflow:hidden;box-shadow:0 12px 30px rgba(2,6,23,0.18)}
        .hero-visual img{width:100%;height:100%;object-fit:cover;display:block}
        @media (max-width:920px){.hero-inner{flex-direction:column;text-align:center}.hero-visual{width:100%;max-width:420px}}
    </style>

    <div class="hero-inner">
        <div class="hero-copy">
            <h1 id="hero-heading">Welcome to SuperShop</h1>
            <p>Your one-stop shopping destination — curated products, secure checkout, and fast delivery. Discover great deals and new arrivals every day.</p>
            <div class="cta">
                <a class="btn-cta primary" href="<?php echo url('/product'); ?>">Shop Now</a> 
            </div>
        </div>

        <div class="hero-visual" aria-hidden="true">
            <canvas id="hero-canvas" width="960" height="640" style="display:none;width:100%;height:auto"></canvas>
            <img id="hero-composite" src="" alt="Featured products" style="width:100%;height:auto;display:block">
        </div>
    </div>
</section>
<script>
// Build a single composite image from up to 6 hero images
;(function(){
    var imageUrls = <?php echo json_encode(array_values($hero_images), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    if (!imageUrls || !imageUrls.length) return;
    var canvas = document.getElementById('hero-canvas');
    var outImg = document.getElementById('hero-composite');
    if (!canvas || !outImg) return;
    var ctx = canvas.getContext('2d');
    var cols = 3, rows = 2;
    var cellW = Math.floor(canvas.width / cols);
    var cellH = Math.floor(canvas.height / rows);

    function loadImage(url){
        return new Promise(function(resolve, reject){
            var img = new Image();
            img.onload = function(){ resolve(img); };
            img.onerror = function(){ resolve(null); };
            img.src = url;
        });
    }

    Promise.all(
        // ensure exactly cols*rows images by repeating placeholder if needed
        (function(){
            var list = imageUrls.slice(0, cols*rows);
            while (list.length < cols*rows) list.push('/public/images/products/placeholder.png');
            return list;
        })().map(loadImage)
    ).then(function(imgs){
        // draw background
        ctx.fillStyle = '#fff'; ctx.fillRect(0,0,canvas.width,canvas.height);
        for (var i=0;i<imgs.length;i++){
            var img = imgs[i];
            var cx = i % cols;
            var cy = Math.floor(i/cols);
            var dx = cx * cellW;
            var dy = cy * cellH;
            if (!img) {
                // draw placeholder rect
                ctx.fillStyle = '#f3f4f6'; ctx.fillRect(dx,dy,cellW,cellH);
                continue;
            }
            var iw = img.width, ih = img.height;
            var scale = Math.max(cellW / iw, cellH / ih);
            var sw = Math.round(cellW / scale);
            var sh = Math.round(cellH / scale);
            var sx = Math.max(0, Math.floor((iw - sw) / 2));
            var sy = Math.max(0, Math.floor((ih - sh) / 2));
            try {
                ctx.drawImage(img, sx, sy, sw, sh, dx, dy, cellW, cellH);
            } catch(e) {
                // fallback: draw image stretched
                ctx.drawImage(img, dx, dy, cellW, cellH);
            }
        }
        // export and set image
        try {
            var data = canvas.toDataURL('image/jpeg', 0.85);
            outImg.src = data;
            // hide canvas now
            canvas.style.display = 'none';
        } catch(e){
            // if toDataURL fails, fallback: show first image
            outImg.src = imageUrls[0];
        }
    }).catch(function(){
        document.getElementById('hero-composite').src = imageUrls[0];
    });
})();
</script>

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