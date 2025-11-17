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
$stmt = $conn->prepare('SELECT id, sku, title, slug, description, price, stock, images, primary_category_id, is_active FROM products WHERE id = ? LIMIT 1');
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
    'price' => $cur['price'],
    'stock' => $cur['stock'],
    'images' => [],
    'category_id' => $cur['primary_category_id'] ?? '',
    'is_active' => $cur['is_active'] ? 1 : 0,
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
        $old['price'] = ($inputs['price'] ?? $old['price']) + 0;
        $old['stock'] = $inputs['stock'] ?? $old['stock'];
        $old['category_id'] = isset($inputs['category_id']) ? (int)$inputs['category_id'] : '';
        if (empty($old['category_id'])) $errors[] = 'Please select a category for the product.';
        $old['is_active'] = !empty($inputs['is_active']) ? 1 : 0;
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
        $price_q = number_format((float)$old['price'], 2, '.', '');
        $stock_q = (int)$old['stock'];
        $is_active_q = (int)$old['is_active'];
        $primary_cat = isset($old['category_id']) ? (int)$old['category_id'] : 0;

        $sql = "UPDATE products SET sku = '{$sku_q}', title = '{$title_q}', slug = '{$slug_q}', description = '{$desc_q}', price = {$price_q}, stock = {$stock_q}, images = {$images_q}, is_active = {$is_active_q}, primary_category_id = {$primary_cat} WHERE id = " . (int)$id;
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
                    <label class="form-label">Images (upload — multiple allowed). Leave empty to keep existing images.</label>
                    <input class="form-input" type="file" name="images[]" accept="image/*" multiple>
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
