<?php
require_once __DIR__ . '/../../_imports.php';
pageHead('Edit Product - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header('Location: /admin/product/index.php');
    exit;
}

$errors = [];

// load categories
$cats = [];
$catsRes = $conn->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC");
if ($catsRes) {
    while ($r = $catsRes->fetch_assoc()) {
        $r['parent_id'] = (isset($r['parent_id']) && $r['parent_id'] !== '0' && $r['parent_id'] !== 0) ? (int)$r['parent_id'] : null;
        $cats[] = $r;
    }
}

// fetch product
$stmt = $conn->prepare('SELECT id, sku, title, slug, description, long_description, is_featured, price, stock, images, primary_category_id, is_active FROM products WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$cur = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cur) {
    header('Location: /admin/product/index.php');
    exit;
}

$old = [
    'sku' => $cur['sku'],
    'title' => $cur['title'],
    'slug' => $cur['slug'],
    'description' => $cur['description'],
    'long_description' => $cur['long_description'] ?? '',
    'price' => $cur['price'],
    'stock' => $cur['stock'],
    'images' => [],
    'category_id' => $cur['primary_category_id'] ?? '',
    'is_active' => $cur['is_active'] ? 1 : 0,
    'is_featured' => !empty($cur['is_featured']) ? 1 : 0,
];

// decode existing images
$existing_images = [];
if (!empty($cur['images'])) {
    $decoded = json_decode($cur['images'], true);
    if (is_array($decoded)) $existing_images = $decoded;
}

if (Req\isPost()) {
    [$inputs, $errors] = Req\validate([
        'sku' => 'trim',
        'title' => 'required|trim|min:3',
        'slug' => 'trim',
        'description' => 'sanitize_html',
        'long_description' => 'trim',
        'is_featured' => 'bool',
        'price' => 'numeric',
        'stock' => 'int',
        'category_id' => 'int',
        'is_active' => 'bool'
    ]);

    if (is_array($inputs)) {
        $old['sku'] = $inputs['sku'] ?? $old['sku'];
        $old['title'] = $inputs['title'] ?? $old['title'];
        $old['slug'] = $inputs['slug'] ?? $old['slug'];
        $old['description'] = $inputs['description'] ?? $old['description'];
        $old['long_description'] = $inputs['long_description'] ?? $old['long_description'];
        $old['price'] = ($inputs['price'] ?? $old['price']) + 0;
        $old['stock'] = $inputs['stock'] ?? $old['stock'];
        $old['category_id'] = isset($inputs['category_id']) ? (int)$inputs['category_id'] : '';
        if (empty($old['category_id'])) $errors[] = 'Please select a category for the product.';
        $old['is_active'] = !empty($inputs['is_active']) ? 1 : 0;
        $old['is_featured'] = !empty($inputs['is_featured']) ? 1 : 0;
    }

    if (empty($errors)) {
        // build slug
        if (!empty($old['slug'])) {
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($old['slug']));
        } else {
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($old['title']));
        }
        $base = trim($base, '-');
        if ($base === '') $base = 'product';

        // ensure unique slug excluding current id
        $candidate = $base;
        $i = 1;
        while (true) {
            $s = $conn->prepare('SELECT id FROM products WHERE slug = ? AND id != ? LIMIT 1');
            if (!$s) { $errors[] = 'Failed preparing slug check: ' . $conn->error; break; }
            $s->bind_param('si', $candidate, $id);
            $s->execute();
            $s->store_result();
            if ($s->num_rows === 0) { $s->close(); break; }
            $candidate = $base . '-' . $i++;
            $s->close();
        }
    }

    // handle uploaded images: if provided, they replace existing images; otherwise keep
    $images_json = null;
    $uploaded_paths = [];
    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $uploadDir = __DIR__ . '/../../public/images/products';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $countFiles = count($_FILES['images']['tmp_name']);
        for ($i = 0; $i < $countFiles; $i++) {
            $tmp = $_FILES['images']['tmp_name'][$i];
            if (empty($tmp) || !is_uploaded_file($tmp)) continue;
            $err = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_OK;
            if ($err !== UPLOAD_ERR_OK) { $errors[] = 'Image #' . ($i + 1) . ' upload error.'; continue; }
            $mime = finfo_file($finfo, $tmp);
            if (!in_array($mime, $allowed, true)) { $errors[] = 'Unsupported image type for image #' . ($i + 1); continue; }
            $origName = $_FILES['images']['name'][$i] ?? 'image';
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $safe = preg_replace('/[^a-z0-9._-]+/i', '', basename($origName));
            $filename = time() . '-' . bin2hex(random_bytes(6)) . '-' . $safe;
            if ($ext) $filename .= '.' . $ext;
            $target = $uploadDir . '/' . $filename;
            if (move_uploaded_file($tmp, $target)) {
                $uploaded_paths[] = '/public/images/products/' . $filename;
            } else { $errors[] = 'Failed to move uploaded image #' . ($i + 1) . '.'; }
        }
        finfo_close($finfo);
        if (!empty($uploaded_paths)) {
            $images_json = json_encode($uploaded_paths);
        }
    }

    if (empty($errors)) {
        // decide images value: new uploaded replace existing, else keep existing
        $images_q = $images_json === null ? ($cur['images'] !== null ? "'" . $conn->real_escape_string($cur['images']) . "'" : 'NULL') : "'" . $conn->real_escape_string($images_json) . "'";

        $sku_q = $conn->real_escape_string($old['sku']);
        $title_q = $conn->real_escape_string($old['title']);
        $slug_q = $conn->real_escape_string($candidate);
        $desc_q = $conn->real_escape_string($old['description']);
        $long_desc_q = $conn->real_escape_string($old['long_description'] ?? '');
        $price_q = number_format((float)$old['price'], 2, '.', '');
        $stock_q = (int)$old['stock'];
        $is_active_q = (int)$old['is_active'];
        $is_featured_q = !empty($old['is_featured']) ? 1 : 0;
        $primary_cat = isset($old['category_id']) ? (int)$old['category_id'] : 0;

        $sql = "UPDATE products SET sku = '{$sku_q}', title = '{$title_q}', slug = '{$slug_q}', description = '{$desc_q}', long_description = '{$long_desc_q}', price = {$price_q}, stock = {$stock_q}, images = {$images_q}, is_featured = {$is_featured_q}, is_active = {$is_active_q}, primary_category_id = {$primary_cat} WHERE id = " . (int)$id;
        if ($conn->query($sql)) {
            header('Location: /admin/product/index.php');
            exit;
        } else {
            $errors[] = 'Failed to update product: ' . $conn->error;
        }
    }
}

// helper to render parent-style options for categories (flat->nested)
function render_parent_options_from_cats_multi_edit($catsList, $parentId = null, $depth = 0, $selected = 0)
{
    foreach ($catsList as $n) {
        $nParent = array_key_exists('parent_id', $n) ? $n['parent_id'] : null;
        if ($nParent == $parentId) {
            $prefix = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
            $sel = ((string)$n['id'] === (string)$selected) ? ' selected' : '';
            printf('<option value="%d"%s>%s%s</option>', $n['id'], $sel, $prefix, htmlspecialchars($n['name'] ?? ''));
            render_parent_options_from_cats_multi_edit($catsList, $n['id'], $depth + 1, $selected);
        }
    }
}

?>
<div class="admin-container">

    <div class="admin-entity-header">
        <h1>Edit Product</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Title</label>
                    <input class="form-input" name="title" value="<?php echo htmlspecialchars($old['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">SKU</label>
                    <input class="form-input" name="sku" value="<?php echo htmlspecialchars($old['sku']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-input" name="slug" value="<?php echo htmlspecialchars($old['slug']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Price</label>
                    <input class="form-input" name="price" value="<?php echo htmlspecialchars($old['price']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Stock</label>
                    <input class="form-input" name="stock" value="<?php echo htmlspecialchars($old['stock']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id" required>
                        <option value="">— select category —</option>
                        <?php render_parent_options_from_cats_multi_edit($cats, null, 0, (isset($old['category_id']) ? $old['category_id'] : 0)); ?>
                    </select>
                </div>
                <div class="form-group form-group--small">
                    <label class="form-label">Active</label>
                    <div class="form-check">
                        <input id="is_active" type="checkbox" name="is_active" <?php echo $old['is_active'] ? 'checked' : ''; ?> />
                        <label for="is_active" class="muted">visible</label>
                    </div>
                </div>

                <div style="grid-column:1 / -1;">
                    <label class="form-label">Description</label>
                    <textarea class="form-input" name="description" rows="6"><?php echo htmlspecialchars($old['description']); ?></textarea>
                </div>

                <div style="grid-column:1 / -1;">
                    <label class="form-label">Long Description (Markdown)</label>
                    <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:8px">
                        <button type="button" id="previewLongBtn" class="btn">Preview</button>
                        <small class="muted">Use Markdown formatting. Preview opens in a modal.</small>
                    </div>
                    <textarea class="form-input" name="long_description" id="longDescription" rows="8"><?php echo htmlspecialchars($old['long_description']); ?></textarea>
                </div>

                <div style="grid-column:1 / -1;display:flex;gap:12px;align-items:center;">
                    <div class="form-group form-group--small" style="margin:0;">
                        <label class="form-label">Featured</label>
                        <div class="form-check">
                            <input id="is_featured" type="checkbox" name="is_featured" <?php echo !empty($old['is_featured']) ? 'checked' : ''; ?> />
                            <label for="is_featured" class="muted">Show as featured product</label>
                        </div>
                    </div>
                </div>

                <div style="grid-column:1 / -1;">
                    <label class="form-label">Images (upload — multiple allowed). Leave empty to keep existing images.</label>
                    <div id="uploadPreview" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px"></div>
                    <input id="imagesInput" class="form-input" type="file" name="images[]" accept="image/*" multiple>
                </div>

                <div style="grid-column:1 / -1;">
                    <label class="form-label">Existing images</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <?php if (empty($existing_images)): ?>
                            <div class="muted">No images</div>
                        <?php else: ?>
                                <?php foreach ($existing_images as $img): ?>
                                    <div class="existing-image" style="width:90px;text-align:center;position:relative" data-img="<?php echo htmlspecialchars($img); ?>">
                                        <img src="<?php echo htmlspecialchars($img); ?>" style="width:90px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #eee"><br>
                                        <button type="button" class="btn btn-danger remove-image" style="position:absolute;top:4px;right:4px;padding:4px 6px;font-size:12px">Remove</button>
                                    </div>
                                <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save changes</button>
                <a class="btn" href="/admin/product/index.php">Cancel</a>
            </div>
        </form>
    </div>

</div>

<?php
pageFooter();
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.remove-image').forEach(function(btn){
        btn.addEventListener('click', function(){
            if (!confirm('Remove this image?')) return;
            var container = btn.closest('.existing-image');
            var img = container ? container.getAttribute('data-img') : null;
            if (!img) return alert('Image not found');
            btn.disabled = true;
            btn.textContent = 'Removing...';
            fetch('<?php echo url('/admin/product/remove_image.php'); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: <?php echo (int)$id; ?>, image: img })
            }).then(function(res){ return res.json(); }).then(function(data){
                if (data && data.ok) {
                    // remove from DOM
                    container.parentNode.removeChild(container);
                } else {
                    alert('Failed: ' + (data && data.error ? data.error : 'Unknown error'));
                    btn.disabled = false;
                    btn.textContent = 'Remove';
                }
            }).catch(function(err){
                alert('Request failed');
                btn.disabled = false;
                btn.textContent = 'Remove';
            });
        });
    });
});
</script>

<style>
    /* Tighter admin form visuals for edit page */
    .existing-image{ width:110px; position:relative; border-radius:8px; overflow:hidden; box-shadow:0 6px 18px rgba(15,23,42,0.06); border:1px solid #eef3f8 }
    .existing-image img{ width:100%; height:80px; object-fit:cover }
    .existing-image .remove-image{ position:absolute; right:6px; top:6px; background:rgba(0,0,0,0.6); color:#fff; border:0; padding:4px 6px; border-radius:6px; cursor:pointer; font-size:12px }
    .remove-image.btn-danger{ background:#ef4444; color:#fff; border-radius:6px; padding:6px 8px }
    #uploadPreview .thumb{ width:110px; border-radius:8px; overflow:hidden; box-shadow:0 6px 18px rgba(15,23,42,0.06); border:1px solid #eef3f8 }
    #uploadPreview .thumb img{ width:100%; height:80px; object-fit:cover }
    #uploadPreview .thumb .remove-temp{ position:absolute; right:6px; top:6px; background:rgba(0,0,0,0.6); color:#fff; border:0; padding:4px 6px; border-radius:6px; cursor:pointer }
    
        /* modern inputs for edit page */
        .form-input,
        .form-select,
        textarea.form-input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #e6eef8;
            background: linear-gradient(180deg,#fff,#fbfdff);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.6), 0 2px 8px rgba(16,24,40,0.03);
            transition: box-shadow .15s ease, transform .08s ease, border-color .12s ease;
            font-size: 1rem;
        }

        .form-input::placeholder, textarea.form-input::placeholder { color: #9ca3af; }

        .form-input:focus,
        .form-select:focus,
        textarea.form-input:focus {
            outline: none;
            box-shadow: 0 10px 30px rgba(37,99,235,0.12);
            border-color: #3b82f6;
            transform: translateY(-1px);
        }
</style>

<script>
// preview selected images before upload (edit page)
(function(){
    var input = document.getElementById('imagesInput');
    var preview = document.getElementById('uploadPreview');
    if (!input || !preview) return;
    function clearPreview(){ preview.innerHTML = ''; }
    function makeThumb(src){
        var d = document.createElement('div'); d.className = 'thumb'; d.style.position='relative';
        var img = document.createElement('img'); img.src = src; d.appendChild(img);
        var btn = document.createElement('button'); btn.type = 'button'; btn.className='remove-temp'; btn.textContent='✕';
        btn.style.position='absolute'; btn.style.right='6px'; btn.style.top='6px'; btn.addEventListener('click', function(){ d.remove(); });
        d.appendChild(btn);
        preview.appendChild(d);
    }
    input.addEventListener('change', function(){
        clearPreview();
        var files = Array.from(input.files || []);
        files.slice(0,8).forEach(function(f){
            var reader = new FileReader();
            reader.onload = function(e){ makeThumb(e.target.result); };
            reader.readAsDataURL(f);
        });
    });
})();
</script>

<!-- Markdown preview modal -->
<div id="mdPreviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2500;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:10px;padding:18px;max-width:900px;width:95%;max-height:85vh;overflow:auto;box-shadow:0 20px 60px rgba(2,6,23,0.3);position:relative">
        <button id="mdPreviewClose" style="position:absolute;right:12px;top:12px;padding:6px 8px;border-radius:6px;border:0;cursor:pointer">Close</button>
        <div id="mdPreviewContent"></div>
    </div>
</div>

<script>
// reuse same simple markdown renderer as in create page
function mdToHtml(md){
    function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    const lines = md.split(/\r?\n/);
    let out = '';
    let inList = false;
    let inCode = false;
    let codeBuf = [];
    for (let i=0;i<lines.length;i++){
        let line = lines[i];
        if (line.startsWith('```')){
            if (!inCode){ inCode = true; codeBuf = []; continue; }
            else { out += '<pre><code>' + esc(codeBuf.join('\n')) + '</code></pre>'; inCode = false; continue; }
        }
        if (inCode){ codeBuf.push(line); continue; }
        if (/^#{1,6} /.test(line)){
            const lvl = line.match(/^#{1,6}/)[0].length;
            out += '<h' + lvl + '>' + esc(line.replace(/^#{1,6}\s+/, '')) + '</h' + lvl + '>';
            continue;
        }
        if (/^\s*[-*+]\s+/.test(line)){
            if (!inList){ inList = true; out += '<ul>'; }
            out += '<li>' + esc(line.replace(/^\s*[-*+]\s+/, '')) + '</li>';
            const next = lines[i+1] || '';
            if (!/^\s*[-*+]\s+/.test(next)){ out += '</ul>'; inList = false; }
            continue;
        }
        if (line.trim() === ''){ out += '<p></p>'; continue; }
        let html = esc(line)
            .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
            .replace(/\*(.*?)\*/g,'<em>$1</em>')
            .replace(/\[(.*?)\]\((.*?)\)/g,'<a href="$2" target="_blank" rel="noopener">$1</a>');
        out += '<p>' + html + '</p>';
    }
    return out;
}

document.getElementById('previewLongBtn') && document.getElementById('previewLongBtn').addEventListener('click', function(){
    var ta = document.getElementById('longDescription');
    if(!ta) return alert('No long description textarea found');
    var html = mdToHtml(ta.value || '');
    document.getElementById('mdPreviewContent').innerHTML = html || '<div class="muted">(empty)</div>';
    var modal = document.getElementById('mdPreviewModal');
    modal.style.display = 'flex';
});
document.getElementById('mdPreviewClose') && document.getElementById('mdPreviewClose').addEventListener('click', function(){
    document.getElementById('mdPreviewModal').style.display = 'none';
});
</script>
