<!-- Navigation -->
<?php
// parent categories for nav
$conn = DB\getConnection();
$nav_categories = [];
$res = $conn->query("SELECT id, name, slug FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $nav_categories[] = $row;
    }
}
?>
<nav class="categories-nav" aria-label="Categories navigation">

    <style>
        .categories-nav {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px;
            background: #fff;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .categories-nav .home-link {
            flex: 0 0 auto;
        }

        .categories-nav .home-link a {
            display: inline-block;
            padding: 8px 10px;
            color: #111;
            text-decoration: none;
            border-radius: 6px;
        }

        .categories-nav .home-link a:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .categories-nav .parents {
            display: flex;
            gap: 10px;
            padding: 6px 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
            flex: 1 1 auto;
        }

        .categories-nav .parents::-webkit-scrollbar {
            height: 8px;
        }

        .categories-nav .parents::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.12);
            border-radius: 4px;
        }

        .categories-nav .parent-item {
            position: relative;
        }

        .categories-nav .parent-item a {
            display: inline-block;
            padding: 8px 10px;
            text-decoration: none;
            color: #222;
            border-radius: 6px;
        }

        .categories-nav .parent-item a:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        /* child dropdown */
        .categories-nav .child-menu {
            display: none;
            position: absolute;
            left: 0;
            top: calc(100% + 8px);
            min-width: 200px;
            background: #fff;
            border: 1px solid #eee;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            border-radius: 8px;
            z-index: 1200;
            max-height: 360px;
            overflow: auto;
        }

        .categories-nav .child-menu a {
            display: block;
            padding: 8px 12px;
            color: #222;
            text-decoration: none;
        }

        .categories-nav .child-menu a:hover {
            background: #f7f7f7;
        }

        /* show on hover (desktop)
           mobile: toggled by .open class on .child-menu */
        .categories-nav .parent-item:hover>.child-menu {
            display: block;
        }

        .categories-nav .child-menu.open {
            display: block;
            position: static;
            box-shadow: none;
            border: none;
        }

        /* ensure single-line without wrapping */
        .categories-nav .parents {
            flex-wrap: nowrap;
        }

        @media (max-width:600px) {
            .categories-nav {
                padding: 6px 8px;
            }
        }
    </style>

    <div class="home-link"><a href="/">Home</a></div>
    <div class="parents" role="menubar" aria-label="Parent categories">
        <?php foreach ($nav_categories as $cat): ?>
            <?php
            $children = [];
            try {
                if (!empty($cat['id'])) {
                    $cstmt = $conn->prepare("SELECT id,name,slug FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY name ASC");
                    if ($cstmt) {
                        $cstmt->bind_param('i', $cat['id']);
                        $cstmt->execute();
                        $cres = $cstmt->get_result();
                        while ($crow = $cres->fetch_assoc()) {
                            $children[] = $crow;
                        }
                        $cstmt->close();
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
            ?>
            <div class="parent-item" role="none">
                <a role="menuitem" href="<?php echo url('/product?category=' . rawurlencode($cat['slug'])); ?>"><?php echo htmlspecialchars($cat['name']); ?></a>
                <?php if (!empty($children)): ?>
                    <div class="child-menu" role="menu" aria-hidden="true">
                        <?php foreach ($children as $ch): ?>
                            <?php
                            // fetch grandchildren for this child
                            $grandchildren = [];
                            try {
                                if (!empty($ch['id'])) {
                                    $gstmt = $conn->prepare("SELECT id,name,slug FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY name ASC");
                                    if ($gstmt) {
                                        $gstmt->bind_param('i', $ch['id']);
                                        $gstmt->execute();
                                        $gres = $gstmt->get_result();
                                        while ($grow = $gres->fetch_assoc()) {
                                            $grandchildren[] = $grow;
                                        }
                                        $gstmt->close();
                                    }
                                }
                            } catch (\Throwable $e) {
                                // ignore
                            }
                            ?>
                            <a role="menuitem" href="<?php echo url('/product?category=' . rawurlencode($ch['slug'])); ?>"><?php echo htmlspecialchars($ch['name']); ?></a>
                            <?php if (!empty($grandchildren)): ?>
                                <div class="grandchildren">
                                    <?php foreach ($grandchildren as $g): ?>
                                        <a class="grandchild" role="menuitem" href="<?php echo url('/product?category=' . rawurlencode($g['slug'])); ?>"><?php echo htmlspecialchars($g['name']); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</nav>
<script>
    // Mobile/touch support for category child menus: tap to open first, tap again to navigate
    (function() {
        const parentItems = document.querySelectorAll('.categories-nav .parent-item');
        if (!parentItems.length) return;

        const isTouch = window.matchMedia && window.matchMedia('(hover: none)').matches || /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);

        function closeAll() {
            document.querySelectorAll('.categories-nav .child-menu.open').forEach(m => {
                m.classList.remove('open');
                m.setAttribute('aria-hidden', 'true');
            });
        }

        parentItems.forEach(item => {
            const link = item.querySelector('a[role="menuitem"]');
            const menu = item.querySelector('.child-menu');
            if (!link || !menu) return;

            // ensure aria
            menu.setAttribute('aria-hidden', 'true');

            link.addEventListener('click', function(e) {
                if (!isTouch) return; // desktop: hover handles it
                if (!menu.classList.contains('open')) {
                    e.preventDefault();
                    closeAll();
                    menu.classList.add('open');
                    menu.setAttribute('aria-hidden', 'false');
                } else {
                    // allow navigation on second tap
                    closeAll();
                }
            });
        });

        // close menus on outside click or Escape
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.categories-nav')) closeAll();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAll();
        });
    })();

    // Desktop: position child dropdowns using fixed positioning on hover to avoid clipping inside scroll containers
    (function() {
        const parentItems = document.querySelectorAll('.categories-nav .parent-item');
        if (!parentItems.length) return;

        const isTouch = window.matchMedia && window.matchMedia('(hover: none)').matches || /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
        if (isTouch) return; // only for pointer devices

        function positionMenu(menu, parent) {
            const rect = parent.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.left = Math.max(8, rect.left) + 'px';
            // ensure menu doesn't overflow viewport on right
            const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
            const maxW = Math.min(420, vw - 16);
            menu.style.minWidth = Math.min(Math.max(rect.width, 200), maxW) + 'px';
            // place below the parent
            menu.style.top = (rect.bottom + 8) + 'px';
            menu.style.zIndex = 2500;
        }

        parentItems.forEach(item => {
            const menu = item.querySelector('.child-menu');
            if (!menu) return;
            let menuHovered = false;

            // keep visible when hovering either parent or menu
            menu.addEventListener('mouseenter', function() {
                menuHovered = true;
            });
            menu.addEventListener('mouseleave', function() {
                menuHovered = false;
                if (!menu.classList.contains('open')) {
                    menu.style.display = '';
                    menu.style.position = '';
                    menu.setAttribute('aria-hidden', 'true');
                }
            });

            item.addEventListener('mouseenter', function() {
                menu.style.display = 'block';
                menu.setAttribute('aria-hidden', 'false');
                positionMenu(menu, item);
            });
            item.addEventListener('mouseleave', function() {
                // delay a bit to allow entering the menu
                setTimeout(function() {
                    if (!menuHovered && !menu.classList.contains('open')) {
                        menu.style.display = '';
                        menu.style.position = '';
                        menu.setAttribute('aria-hidden', 'true');
                    }
                }, 80);
            });
        });

        // hide menus on resize/scroll to avoid misplaced floats
        ['resize', 'scroll'].forEach(evt => window.addEventListener(evt, function() {
            document.querySelectorAll('.categories-nav .child-menu').forEach(m => {
                if (!m.classList.contains('open')) {
                    m.style.display = '';
                    m.style.position = '';
                    m.setAttribute('aria-hidden', 'true');
                } else {
                    // reposition open ones
                    const parent = m.closest('.parent-item');
                    if (parent) positionMenu(m, parent);
                }
            });
        }));
    })();
</script>