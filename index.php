<?php
require_once __DIR__ . '/_imports.php';
pageHead("Home - Supershop", ["home.css"]);
?>

<!-- Top Bar -->
<div class="top-bar">
    <div class="container">
        <div>📞 Customer Service: +1-800-123-4567</div>
        <div>
            <!-- <?php echo env('APP_ENV'); ?> -->
            <a href="#">Track Order</a>
            <a href="#">Help</a>
        </div>
    </div>
</div>

<!-- Header -->
<header>
    <div class="header-main">
        <div class="logo">🛒 SuperShop</div>
        <div class="search-bar">
            <input type="text" placeholder="Search for products...">
            <button>Search</button>
        </div>
        <div class="header-icons">
            <a class="icon-btn" href="/auth/login/">
                👤
            </a>
            <button class="icon-btn">
                ❤️
                <span class="badge">3</span>
            </button>
            <button class="icon-btn">
                🛒
                <span class="badge">5</span>
            </button>
        </div>
    </div>
</header>

<!-- Navigation -->
<nav>
    <ul>
        <li><a href="#">Home</a></li>
        <li><a href="#">Electronics</a></li>
        <li><a href="#">Fashion</a></li>
        <li><a href="#">Home & Living</a></li>
        <li><a href="#">Beauty</a></li>
        <li><a href="#">Sports</a></li>
        <li><a href="#">Deals</a></li>
    </ul>
</nav>

<!-- Hero Section -->
<section class="hero">
    <h1>Welcome to SuperShop</h1>
    <p>Your One-Stop Shopping Destination for Everything You Need</p>
    <a href="#" class="btn">Shop Now</a>
</section>

<!-- Categories -->
<section class="categories">
    <h2 class="section-title">Shop by Category</h2>
    <div class="category-grid">
        <div class="category-card">
            <div class="category-img"></div>
            <div class="category-info">
                <h3>Electronics</h3>
                <p>Latest gadgets & devices</p>
            </div>
        </div>
        <div class="category-card">
            <div class="category-img" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);"></div>
            <div class="category-info">
                <h3>Fashion</h3>
                <p>Trendy clothing & accessories</p>
            </div>
        </div>
        <div class="category-card">
            <div class="category-img" style="background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);"></div>
            <div class="category-info">
                <h3>Home & Living</h3>
                <p>Furniture & decor</p>
            </div>
        </div>
        <div class="category-card">
            <div class="category-img" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);"></div>
            <div class="category-info">
                <h3>Beauty</h3>
                <p>Cosmetics & skincare</p>
            </div>
        </div>
    </div>
</section>

<!-- Featured Products -->
<section class="products">
    <div class="products-container">
        <h2 class="section-title">Featured Products</h2>
        <div class="product-grid">
            <div class="product-card">
                <div class="product-img"></div>
                <div class="product-info">
                    <h3>Wireless Headphones</h3>
                    <div class="price">$89.99 <span class="old-price">$129.99</span></div>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>
            <div class="product-card">
                <div class="product-img" style="background: linear-gradient(135deg, #ff9a56 0%, #ff6a88 100%);"></div>
                <div class="product-info">
                    <h3>Smart Watch Pro</h3>
                    <div class="price">$199.99 <span class="old-price">$249.99</span></div>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>
            <div class="product-card">
                <div class="product-img" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"></div>
                <div class="product-info">
                    <h3>Designer Handbag</h3>
                    <div class="price">$149.99 <span class="old-price">$199.99</span></div>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>
            <div class="product-card">
                <div class="product-img" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);"></div>
                <div class="product-info">
                    <h3>Running Shoes</h3>
                    <div class="price">$79.99 <span class="old-price">$99.99</span></div>
                    <button class="add-to-cart">Add to Cart</button>
                </div>
            </div>
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
    // Add to cart functionality
    const addToCartBtns = document.querySelectorAll('.add-to-cart');
    const cartBadge = document.querySelector('.icon-btn:last-child .badge');

    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            let currentCount = parseInt(cartBadge.textContent);
            cartBadge.textContent = currentCount + 1;

            // Visual feedback
            this.textContent = 'Added!';
            this.style.background = '#27ae60';

            setTimeout(() => {
                this.textContent = 'Add to Cart';
                this.style.background = '#e74c3c';
            }, 1000);
        });
    });

    // Search functionality
    const searchInput = document.querySelector('.search-bar input');
    const searchBtn = document.querySelector('.search-bar button');

    searchBtn.addEventListener('click', function() {
        if (searchInput.value.trim()) {
            alert('Searching for: ' + searchInput.value);
        }
    });

    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && this.value.trim()) {
            alert('Searching for: ' + this.value);
        }
    });

    // Category card interactions
    const categoryCards = document.querySelectorAll('.category-card');
    categoryCards.forEach(card => {
        card.addEventListener('click', function() {
            const categoryName = this.querySelector('h3').textContent;
            alert('Browsing ' + categoryName + ' category');
        });
    });
</script>
<?php
pageFooter();
?>