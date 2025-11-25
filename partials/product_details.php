
<?php
// Product details component
// Usage: set $slug before including this file, e.g. component('product_details.php', ['slug' => $slug]);
// If $slug is not provided, tries $_GET['slug'], otherwise shows a not-found message.

// ensure DB helper is available
$conn = DB\getConnection();

if (empty($slug)) {
	$slug = trim($_GET['slug'] ?? '');
}

if (empty($slug)) {
	?>
	<div class="product-notfound" style="padding:28px;background:#fff;border-radius:10px;border:1px solid #eee;max-width:900px;margin:18px auto;text-align:center">
		<h2 style="margin:0 0 8px;font-size:20px;color:#111">Product not found</h2>
		<p style="margin:0 0 12px;color:#6b7280">We couldn't find the product you're looking for. It may have been removed or the link is incorrect.</p>
		<a href="<?php echo url('/product'); ?>" style="display:inline-block;padding:8px 12px;border-radius:8px;background:#0a74da;color:#fff;text-decoration:none">Browse products</a>
	</div>
	<?php
	return;
}

// fetch product by slug
$product = null;
$stmt = $conn->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON c.id = p.primary_category_id WHERE p.slug = ? AND p.is_active = 1 LIMIT 1");
if ($stmt) {
	$stmt->bind_param('s', $slug);
	$stmt->execute();
	$res = $stmt->get_result();
	$product = $res->fetch_assoc();
	$stmt->close();
}

if (empty($product)) {
	?>
	<div class="product-notfound" style="padding:28px;background:#fff;border-radius:10px;border:1px solid #eee;max-width:900px;margin:18px auto;text-align:center">
		<h2 style="margin:0 0 8px;font-size:20px;color:#111">Product not found</h2>
		<p style="margin:0 0 12px;color:#6b7280">No product matches the requested identifier.</p>
		<a href="<?php echo url('/product'); ?>" style="display:inline-block;padding:8px 12px;border-radius:8px;background:#0a74da;color:#fff;text-decoration:none">Browse products</a>
	</div>
	<?php
	return;
}

// normalize images (stored as JSON or comma list)
$images = [];
if (!empty($product['images'])) {
	$raw = $product['images'];
	$decoded = json_decode($raw, true);
	if (is_array($decoded)) $images = $decoded;
	else $images = array_filter(array_map('trim', explode(',', $raw)));
}

// provide defaults for missing fields (dummy data)
$title = trim($product['title'] ?? 'Untitled product');
$price = isset($product['price']) && is_numeric($product['price']) ? (float)$product['price'] : 0.00;
$old_price = isset($product['old_price']) && is_numeric($product['old_price']) ? (float)$product['old_price'] : null;
$stock = isset($product['stock']) ? (int)$product['stock'] : 0;
$sku = !empty($product['sku']) ? $product['sku'] : 'SKU-' . strtoupper(substr(md5($product['slug'] ?? rand()), 0, 8));
$brand = !empty($product['brand']) ? $product['brand'] : 'Unknown Brand';
$rating = isset($product['rating']) && is_numeric($product['rating']) ? (float)$product['rating'] : 4.2;
$rating_count = isset($product['rating_count']) ? (int)$product['rating_count'] : 24;
$description = trim($product['description'] ?? "This product currently has no detailed description. We'll add more information soon.");
$category_name = $product['category_name'] ?? '';
$category_slug = $product['category_slug'] ?? '';

// ensure images list has at least one placeholder
if (empty($images)) $images = ['/public/images/products/placeholder.png'];
$mainImg = $images[0];

// small helper to output stars
function renderStars($r){
	$out = '';
	$full = floor($r);
	$half = ($r - $full) >= 0.5;
	for ($i=0;$i<5;$i++){
		if ($i < $full) $out .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="#fbbf24" xmlns="http://www.w3.org/2000/svg"><path d="M12 .587l3.668 7.431L23.5 9.75l-5.75 5.602L19.335 24 12 20.201 4.665 24l1.585-8.648L.5 9.75l7.832-1.732L12 .587z"/></svg>';
		else if ($i === $full && $half) $out .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="#fbbf24" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g"><stop offset="50%" stop-color="#fbbf24"/><stop offset="50%" stop-color="#e5e7eb"/></linearGradient></defs><path d="M12 .587l3.668 7.431L23.5 9.75l-5.75 5.602L19.335 24 12 20.201 4.665 24l1.585-8.648L.5 9.75l7.832-1.732L12 .587z" fill="url(#g)"/></svg>';
		else $out .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="#e5e7eb" xmlns="http://www.w3.org/2000/svg"><path d="M12 .587l3.668 7.431L23.5 9.75l-5.75 5.602L19.335 24 12 20.201 4.665 24l1.585-8.648L.5 9.75l7.832-1.732L12 .587z"/></svg>';
	}
	return $out;
}

// render modern product details
?>
<?php
// Simple server-side Markdown -> HTML renderer (safe: escapes input before converting)
function md_to_html($md){
	$md = (string)$md;
	$lines = preg_split("/\r\n|\n|\r/", $md);
	$out = '';
	$inList = false;
	$inCode = false;
	$codeBuf = [];

	$process_inline = function($s){
		$s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		// code spans
		$s = preg_replace_callback('/`([^`]+)`/', function($m){ return '<code>'.htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</code>'; }, $s);
		// links
		$s = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s);
		// bold then italic
		$s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
		$s = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $s);
		return $s;
	};

	for ($i = 0; $i < count($lines); $i++) {
		$line = $lines[$i];
		if (preg_match('/^```/', $line)) {
			if (!$inCode) { $inCode = true; $codeBuf = []; continue; }
			else { $out .= '<pre><code>'.htmlspecialchars(implode("\n", $codeBuf), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</code></pre>'; $inCode = false; continue; }
		}
		if ($inCode) { $codeBuf[] = $line; continue; }

		if (preg_match('/^(#{1,6})\s*(.*)$/', $line, $m)) {
			$lvl = strlen($m[1]);
			$out .= '<h'. $lvl . '>' . $process_inline(trim($m[2])) . '</h'. $lvl . '>';
			continue;
		}

		if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m)) {
			if (!$inList) { $inList = true; $out .= '<ul>'; }
			$out .= '<li>' . $process_inline($m[1]) . '</li>';
			$next = $lines[$i+1] ?? '';
			if (!preg_match('/^\s*[-*+]\s+/', $next)) { $out .= '</ul>'; $inList = false; }
			continue;
		}

		if (trim($line) === '') { $out .= '<p></p>'; continue; }
		$out .= '<p>' . $process_inline($line) . '</p>';
	}

	// close any unclosed list
	if ($inList) $out .= '</ul>';
	if ($inCode) $out .= '<pre><code>'.htmlspecialchars(implode("\n", $codeBuf), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</code></pre>';
	return $out;
}
?>
<style>
:root{--card:#ffffff;--muted:#6b7280;--accent:#0a74da;--accent-2:#0b8457;--surface:#f8fafc}
.pd-wrap{max-width:1200px;margin:20px auto;padding:18px}
.pd-grid{display:grid;grid-template-columns:420px 1fr;gap:24px;align-items:start}
.pd-card{background:var(--card);border-radius:12px;padding:18px;border:1px solid #e6edf3;box-shadow:0 8px 30px rgba(12,18,31,0.04)}
.pd-gallery{display:flex;flex-direction:column;gap:12px}
.pd-main{border-radius:10px;overflow:hidden;height:420px;background:linear-gradient(180deg,#f8fafc,#fff);display:flex;align-items:center;justify-content:center}
.pd-main img{width:100%;height:100%;object-fit:cover}
.pd-thumb-row{display:flex;gap:10px;margin-top:8px}
.pd-thumb{width:72px;height:72px;border-radius:8px;overflow:hidden;border:2px solid transparent;cursor:pointer}
.pd-thumb img{width:100%;height:100%;object-fit:cover}
.pd-thumb.active{border-color:var(--accent)}
.pd-info{display:flex;flex-direction:column;gap:12px}
.pd-breadcrumbs{color:var(--muted);font-size:13px}
.pd-title{font-size:26px;margin:0;color:#0f172a}
.pd-meta{display:flex;gap:14px;align-items:center}
.pd-price{font-size:22px;font-weight:800;color:var(--accent-2)}
.pd-old{color:#9ca3af;text-decoration:line-through;margin-left:8px;font-size:14px}
.pd-stock{padding:6px 8px;border-radius:8px;font-weight:700}
.in-stock{background:#e6ffef;color:#065f46}
.out-stock{background:#fff5f5;color:#7f1d1d}
.pd-rating{display:flex;gap:8px;align-items:center}
.pd-desc{color:#374151}
.pd-actions{display:flex;gap:12px;align-items:center;margin-top:8px}
.qty{display:flex;align-items:center;border:1px solid #e6e9ef;border-radius:8px;overflow:hidden}
.qty button{background:#fff;border:0;padding:8px 12px;cursor:pointer}
.qty input{width:64px;text-align:center;border:0;padding:8px}
.btn-primary{padding:10px 14px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none}
.btn-buy{background:#0b8457}
.specs{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}
.spec{background:#f8fafc;padding:8px 12px;border-radius:8px;color:#0f172a;font-size:13px}
.pd-tabs{margin-top:18px;border-top:1px solid #eef2f6;padding-top:12px}
.pd-tabs h3{margin:0 0 8px}
@media (max-width:980px){.pd-grid{grid-template-columns:1fr;}.pd-main{height:360px}}
</style>

<div class="pd-wrap">
	<div class="pd-grid">
		<div class="pd-card pd-gallery">
			<div class="pd-main">
				<img id="pd-main-img" src="<?php echo htmlspecialchars($mainImg); ?>" alt="<?php echo htmlspecialchars($title); ?>">
			</div>
			<div class="pd-thumb-row">
				<?php foreach ($images as $i => $img): $active = ($i === 0) ? 'active' : ''; ?>
					<div class="pd-thumb <?php echo $active; ?>" data-src="<?php echo htmlspecialchars($img); ?>">
						<img src="<?php echo htmlspecialchars($img); ?>" alt="thumb">
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="pd-card pd-info">
			<div>
				<div class="pd-breadcrumbs"><a href="/" style="color:inherit;text-decoration:none">Home</a> › <a href="<?php echo url('/product?category=' . rawurlencode($category_slug)); ?>" style="color:inherit;text-decoration:none"><?php echo htmlspecialchars($category_name ?: 'All products'); ?></a></div>
				<h1 class="pd-title"><?php echo htmlspecialchars($title); ?></h1>
			</div>

			<div class="pd-meta">
				<div class="pd-price">৳ <?php echo number_format($price, 2); ?></div>
				<?php if ($old_price): ?><div class="pd-old">৳ <?php echo number_format($old_price, 2); ?></div><?php endif; ?>
				<div class="pd-stock <?php echo $stock > 0 ? 'in-stock' : 'out-stock'; ?>" style="margin-left:auto"><?php echo $stock > 0 ? 'In stock' : 'Out of stock'; ?></div>
			</div>

			<div class="pd-rating">
				<div class="stars"><?php echo renderStars($rating); ?></div>
				<div style="color:var(--muted);font-size:13px"><?php echo number_format($rating,1); ?> · <?php echo $rating_count; ?> reviews</div>
			</div>

			<div class="pd-desc"><?php echo nl2br(htmlspecialchars($description)); ?></div>

			<div class="specs">
				<div class="spec">SKU: <?php echo htmlspecialchars($sku); ?></div>
				<div class="spec">Brand: <?php echo htmlspecialchars($brand); ?></div>
				<div class="spec">Delivery: 2–5 days</div>
			</div>

			<div class="pd-actions">
				<div class="qty" role="group" aria-label="Quantity selector">
					<button type="button" id="decQty">−</button>
					<input id="qtyInput" type="text" value="1" />
					<button type="button" id="incQty">+</button>
				</div>

				<a id="addToCartBtn" class="btn-primary" href="javascript:void(0);" data-endpoint="<?php echo url('/product/add_cart.php'); ?>" data-product-id="<?php echo (int)$product['id']; ?>">Add to cart</a>
				<a class="btn-primary btn-buy" href="<?php echo url('/checkout?product_id=' . (int)$product['id']); ?>">Buy now</a>
			</div>

			<div class="pd-tabs">
				<h3>Details</h3>
				<div><?php echo md_to_html($product['long_description']); ?></div>
			</div>
		</div>
	</div>
</div>

<script>
	(function(){
		// gallery thumbnails
		document.querySelectorAll('.pd-thumb').forEach(function(t){
			t.addEventListener('click', function(){
				document.getElementById('pd-main-img').src = t.getAttribute('data-src');
				document.querySelectorAll('.pd-thumb').forEach(x=>x.classList.remove('active'));
				t.classList.add('active');
			});
		});

		// quantity controls
		var qtyInput = document.getElementById('qtyInput');
		var inc = document.getElementById('incQty');
		var dec = document.getElementById('decQty');
		inc && inc.addEventListener('click', function(){ qtyInput.value = Math.max(1, parseInt(qtyInput.value || '1') + 1); });
		dec && dec.addEventListener('click', function(){ qtyInput.value = Math.max(1, parseInt(qtyInput.value || '1') - 1); });

		// Ensure global helpers exist (centralized in /public/js/cart.js). Provide minimal fallbacks.
		window.cart = window.cart || {count:0, subtotal:0, items:[]};
		if (typeof window.updateCart !== 'function') {
			window.updateCart = function(source){
				source = source || window.cart || {count:0};
				var selectors = ['#cart-count', '.cart-count', '.header-cart-count', '.cart-badge', '.badge.cart-count'];
				selectors.forEach(function(sel){
					document.querySelectorAll(sel).forEach(function(el){
						el.textContent = source.count || 0;
						if ((source.count || 0) > 0) el.classList.add('has-items'); else el.classList.remove('has-items');
					});
				});
				try{ window.dispatchEvent(new CustomEvent('cart.updated', {detail: source})); }catch(e){}
			};
		}
		window.updateCart();

		if (typeof window.showToast !== 'function') {
			window.showToast = function(msg, timeout){
				timeout = timeout || 2200;
				var t = document.createElement('div');
				t.className = 'pd-toast';
				t.style = 'position:fixed;right:20px;bottom:20px;background:#111;color:#fff;padding:10px 14px;border-radius:8px;z-index:99999;box-shadow:0 6px 20px rgba(2,6,23,0.2);font-size:14px';
				t.textContent = msg || '';
				document.body.appendChild(t);
				setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 240); }, timeout);
			};
		}

		var addBtn = document.getElementById('addToCartBtn');
		if(addBtn){
			addBtn.addEventListener('click', function(e){
				e.preventDefault();
				var endpoint = addBtn.getAttribute('data-endpoint');
				var productId = addBtn.getAttribute('data-product-id');
				var qty = Math.max(1, parseInt(qtyInput.value || '1'));
				if (!endpoint || !productId) return;

				if (typeof window.addToCart === 'function') {
					window.addToCart(productId, qty, {endpoint: endpoint, btn: addBtn}).catch(function(){});
					return;
				}

				// fallback to inline fetch if centralized function missing
				addBtn.classList.add('disabled');
				addBtn.setAttribute('aria-disabled', 'true');
				fetch(endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {'X-Requested-With':'XMLHttpRequest'},
					body: new URLSearchParams({product_id: productId, qty: qty})
				})
				.then(function(res){ return res.text(); })
				.then(function(text){
					addBtn.classList.remove('disabled');
					addBtn.removeAttribute('aria-disabled');
					var json = null; try{ json = JSON.parse(text); }catch(e){ json = null; }
					if (!json) return showToast('Invalid server response');
					if (json.success === false || json.ok === false) return showToast(json.message || 'Could not add item to cart.');
					if (json.cart) window.cart = json.cart;
					else if (typeof json.count !== 'undefined') window.cart.count = json.count;
					else window.cart.count = (window.cart.count||0) + qty;
					window.updateCart();
					showToast(json.message || 'Added to cart');
				})
				.catch(function(err){
					addBtn.classList.remove('disabled');
					addBtn.removeAttribute('aria-disabled');
					showToast('Network error adding to cart');
					console.error(err);
				});
			});
		}
	})();
</script>

<?php
// end component
