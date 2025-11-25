// Centralized cart helpers: window.cart, window.updateCart, window.showToast
(function(){
    if (typeof window === 'undefined') return;
    window.cart = window.cart || {count:0, subtotal:0, items:[]};

    window.showToast = window.showToast || function(msg, timeout){
        timeout = timeout || 2200;
        try{
            var t = document.createElement('div');
            t.className = 'cart-toast';
            t.style = 'position:fixed;right:20px;bottom:20px;background:#111;color:#fff;padding:10px 14px;border-radius:8px;z-index:99999;box-shadow:0 6px 20px rgba(2,6,23,0.2);font-size:14px';
            t.textContent = msg || '';
            document.body.appendChild(t);
            setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 240); }, timeout);
        }catch(e){ console.warn('toast error', e); }
    };

    window.updateCart = window.updateCart || function(source){
        source = source || window.cart || {count:0};
        var selectors = ['#cart-count', '.cart-count', '.header-cart-count', '.cart-badge', '.badge.cart-count'];
        selectors.forEach(function(sel){
            document.querySelectorAll(sel).forEach(function(el){
                try{
                    el.textContent = source.count || 0;
                    if ((source.count || 0) > 0) el.classList.add('has-items'); else el.classList.remove('has-items');
                }catch(e){/* ignore */}
            });
        });
        try{ window.dispatchEvent(new CustomEvent('cart.updated', {detail: source})); }catch(e){}
    };

    // Run once on load to sync any present values
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ window.updateCart(); });
    else window.updateCart();
    
    // Reusable addToCart API
    window.addToCart = window.addToCart || function(productId, qty, options){
        options = options || {};
        var endpoint = options.endpoint || (typeof options === 'string' ? options : '/product/add_cart.php');
        var btn = options.btn || null;
        qty = Math.max(1, parseInt(qty || 1));
        if (btn){ btn.disabled = true; btn.classList.add('loading'); }

        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({product_id: productId, qty: qty})
        })
        .then(function(r){ return r.text(); })
        .then(function(text){
            var json = null;
            try{ json = JSON.parse(text); }catch(e){ json = null; }
            if (btn){ btn.disabled = false; btn.classList.remove('loading'); }
            if (!json) { window.showToast ? window.showToast('Invalid server response') : console.warn('Invalid server response'); return Promise.resolve(null); }
            // unify shapes
            if (json.cart) window.cart = json.cart;
            else if (typeof json.count !== 'undefined' || typeof json.cart_count !== 'undefined'){
                window.cart.count = json.count || json.cart_count || window.cart.count || 0;
                if (typeof json.subtotal !== 'undefined') window.cart.subtotal = json.subtotal;
            } else if (json.ok && json.method === 'session' && typeof json.cart_count !== 'undefined'){
                window.cart.count = json.cart_count;
            } else {
                window.cart.count = (window.cart.count||0) + qty;
            }
            if (window.updateCart) window.updateCart(window.cart);
            if (json.message) window.showToast && window.showToast(json.message);
            return json;
        })
        .catch(function(err){ if (btn){ btn.disabled = false; btn.classList.remove('loading'); } console.error(err); window.showToast ? window.showToast('Network error adding to cart') : null; return Promise.reject(err); });
    };
})();
