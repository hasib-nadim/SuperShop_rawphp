<header>
    <style>
        /* small user menu styles specific to header */
        .user-menu {
            position: relative;
            display: inline-block;
        }

        .user-btn {
            background: none;
            border: none;
            color: inherit;
            font: inherit;
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 6px;
        }

        .user-btn:focus {
            outline: 2px solid rgba(0, 0, 0, 0.12);
        }

        .user-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            min-width: 160px;
            border-radius: 8px;
            z-index: 1200;
        }

        .user-dropdown a {
            display: block;
            padding: 10px 12px;
            color: #222;
            text-decoration: none;
            font-size: 14px;
        }

        .user-dropdown a:hover {
            background: #f5f5f5;
        }

        .user-dropdown .sep {
            height: 1px;
            background: #eee;
            margin: 6px 0;
        }

        @media (max-width:600px) {
            .user-dropdown {
                right: -10px;
            }
        }
    </style>
    <div class="header-main">
        <a class="logo" href="/">🛒 SuperShop</a>
        <form class="search-bar" method="GET" action="<?php echo url('/product'); ?>"  >
            <input id="globalSearch" name="q" type="text" placeholder="Search for products..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" >
            <button type="submit">Search</button>
        </form>
        <div class="header-icons">
            <?php if ($user): ?>
                <div class="user-menu">
                    <button id="userMenuBtn" class="user-btn" aria-haspopup="true" aria-expanded="false">
                        Welcome, <?php echo htmlspecialchars($user['username'] ?? " "); ?> ▾
                    </button>
                    <div id="userDropdown" class="user-dropdown" role="menu" aria-hidden="true" hidden>
                        <a href="/auth/profile" role="menuitem">Profile</a>
                        <a href="/orders" role="menuitem">My Orders</a>
                        <div class="sep"></div>
                        <a href="/auth/logout.php" role="menuitem">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a class="icon-btn login-btn" href="/auth/login">
                    <span class="icon">🔐</span>
                    <span class="label">Login</span>
                </a>
            <?php endif; ?>
            <a href="/cart" id="headerCartBtn" class="icon-btn">
                🛒
                <span class="badge cart-count">0</span>
            </a>
        </div>
    </div>
</header>
<?php
if (!session_id()) session_start();
$cart_data = ['count' => 0, 'subtotal' => 0, 'items' => []];
// if user is logged in, hydrate from carts table; otherwise from session
if (!empty($user) && !empty($user['id'])) {
    try {
        $conn = DB\getConnection();
        $stmt = $conn->prepare("SELECT product_id, quantity, unit_price FROM carts WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $pid = (int) $r['product_id'];
                $qty = (int) $r['quantity'];
                $unit = isset($r['unit_price']) ? (float) $r['unit_price'] : 0;
                $cart_data['items'][] = ['product_id' => $pid, 'qty' => $qty, 'price' => $unit];
                $cart_data['count'] += $qty;
                $cart_data['subtotal'] += $qty * $unit;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // fallback to empty cart on error
    }
} elseif (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $k => $it) {
        $pid = is_numeric($k) ? (int) $k : (int) ($it['product_id'] ?? 0);
        $qty = isset($it['qty']) ? (int) $it['qty'] : (isset($it['quantity']) ? (int) $it['quantity'] : 1);
        $price = isset($it['price']) ? (float) $it['price'] : (isset($it['unit_price']) ? (float) $it['unit_price'] : 0);
        $cart_data['items'][] = ['product_id' => $pid, 'qty' => $qty, 'price' => $price, 'title' => ($it['title'] ?? null), 'image' => ($it['image'] ?? null)];
        $cart_data['count'] += $qty;
        $cart_data['subtotal'] += $qty * $price;
    }
}
?>
<script>
window.cart = <?php echo json_encode($cart_data, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
</script>
<script defer src="<?php echo url('/public/js/cart.js'); ?>"></script>
<script>
    // User menu dropdown
    (function() {
        const btn = document.getElementById('userMenuBtn');
        const menu = document.getElementById('userDropdown');
        if (!btn || !menu) return;

        function openMenu() {
            menu.hidden = false;
            menu.setAttribute('aria-hidden', 'false');
            btn.setAttribute('aria-expanded', 'true');
        }

        function closeMenu() {
            menu.hidden = true;
            menu.setAttribute('aria-hidden', 'true');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (menu.hidden) openMenu();
            else closeMenu();
        });

        // close on outside click
        document.addEventListener('click', function(e) {
            if (!menu.contains(e.target) && e.target !== btn) closeMenu();
        });

        // keyboard support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeMenu();
        });
    })();
</script>
<script>
    // Autofocus / active state for header search when on product page
    (function(){
        try {
            var input = document.getElementById('globalSearch');
            if(!input) return;
            var path = window.location.pathname || '';
            // treat any path that starts with /product as product page
            if(path.indexOf('/product') === 0) {
                // focus and move caret to end for convenience
                input.focus();
                var val = input.value || '';
                input.setSelectionRange(val.length, val.length);
                // add a subtle active class for visual cue
                input.classList.add('search-active');
                // remove active class after user interaction optionally
                input.addEventListener('blur', function(){
                    input.classList.remove('search-active');
                });
            }
        } catch (e){/* no-op */}
    })();
</script>