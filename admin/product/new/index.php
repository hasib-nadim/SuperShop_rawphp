<?php
require_once __DIR__ . '/../../../_imports.php';
pageHead('Create Product - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$errors = [];
$old = [
    'sku' => '',
    'title' => '',
    'slug' => '',
    'description' => '',
    'price' => '0.00',
    'stock' => 0,
    'images' => [],
    'category_id' => '',
    'is_active' => 1
];

// fetch categories for multi-select
$catsRes = $conn->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC");
$cats = [];
if ($catsRes) {
    while ($r = $catsRes->fetch_assoc()) {
        // normalize parent_id: treat 0/empty as null
        $r['parent_id'] = (isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '0' && $r['parent_id'] !== 0) ? (int)$r['parent_id'] : null;
        $cats[] = $r;
    }
    // sort categories so top-level parents appear before children (helps rendering order)
    usort($cats, function($a, $b){
        $pa = $a['parent_id'] === null ? 0 : 1;
        $pb = $b['parent_id'] === null ? 0 : 1;
        if ($pa !== $pb) return $pa - $pb;
        return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
    });
} else {
    // surface DB error for easier debugging when categories fail to load
    $errors[] = 'Failed loading categories: ' . $conn->error;
}

if (Req\isPost()) {
    [$inputs, $errors] = Req\validate([
        'sku' => 'trim',
        'title' => 'required|trim|min:3',
        'slug' => 'trim',
        'description' => 'sanitize_html',
        'price' => 'numeric',
        'stock' => 'int',
        'images' => '',
        'category_id' => 'int',
        'is_active' => 'bool'
    ]);

    if (is_array($inputs)) {
        $old['sku'] = $inputs['sku'] ?? '';
        $old['title'] = $inputs['title'] ?? '';
        $old['slug'] = $inputs['slug'] ?? '';
        $old['description'] = $inputs['description'] ?? '';
        $old['price'] = ($inputs['price'] ?? 0) + 0;
        $old['stock'] = $inputs['stock'] ?? 0;
    // files come via $_FILES; keep placeholder for old values if needed
    $old['images'] = $inputs['images'] ?? [];
        $old['category_id'] = isset($inputs['category_id']) ? (int)$inputs['category_id'] : '';
        // ensure category selection is present
        if (empty($old['category_id'])) {
            $errors[] = 'Please select a category for the product.';
        }
        $old['is_active'] = !empty($inputs['is_active']) ? 1 : 0;
    }

    if (empty($errors)) {
        // slug base
        if (!empty($old['slug'])) {
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($old['slug']));
        } else {
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($old['title']));
        }
        $base = trim($base, '-');
        if ($base === '') $base = 'product';

        // ensure unique slug
        $candidate = $base;
        $i = 1;
        while (true) {
            $stmt = $conn->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
            if (!$stmt) {
                $errors[] = 'Failed preparing slug check: ' . $conn->error;
                break;
            }
            $stmt->bind_param('s', $candidate);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                break;
            }
            $candidate = $base . '-' . $i++;
            $stmt->close();
        }
    }

    if (empty($errors)) {
        // handle uploaded image files (multiple images)
        $images_json = null;
        $uploaded_paths = [];
        if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $uploadDir = __DIR__ . '/../../../public/images/products';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $countFiles = count($_FILES['images']['tmp_name']);
            for ($i = 0; $i < $countFiles; $i++) {
                $tmp = $_FILES['images']['tmp_name'][$i];
                if (empty($tmp) || !is_uploaded_file($tmp)) continue;
                $err = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_OK;
                if ($err !== UPLOAD_ERR_OK) {
                    $errors[] = 'Image #' . ($i + 1) . ' upload error.';
                    continue;
                }
                $mime = finfo_file($finfo, $tmp);
                if (!in_array($mime, $allowed, true)) {
                    $errors[] = 'Unsupported image type for image #' . ($i + 1) . ". Allowed: jpg,png,webp,gif.";
                    continue;
                }
                $origName = $_FILES['images']['name'][$i] ?? 'image';
                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                $safe = preg_replace('/[^a-z0-9._-]+/i', '', basename($origName));
                $filename = time() . '-' . bin2hex(random_bytes(6)) . '-' . $safe;
                if ($ext) $filename .= '.' . $ext;
                $target = $uploadDir . '/' . $filename;
                if (move_uploaded_file($tmp, $target)) {
                    $uploaded_paths[] = '/public/images/products/' . $filename;
                } else {
                    $errors[] = 'Failed to move uploaded image #' . ($i + 1) . '.';
                }
            }
            finfo_close($finfo);
            if (!empty($uploaded_paths)) {
                $images_json = json_encode($uploaded_paths);
            }
        }

        // perform insertion using escaped values (prepared statement was causing portability issues in some environments)
        $price = (float)$old['price'];
        $stock = (int)$old['stock'];
        $is_active = (int)$old['is_active'];

        $sku_q = $conn->real_escape_string($old['sku']);
        $title_q = $conn->real_escape_string($old['title']);
        $slug_q = $conn->real_escape_string($candidate);
        $desc_q = $conn->real_escape_string($old['description']);
        $price_q = number_format($price, 2, '.', '');
        $stock_q = (int)$stock;
        $images_q = $images_json === null ? 'NULL' : "'" . $conn->real_escape_string($images_json) . "'";
        $is_active_q = (int)$is_active;

        // include primary_category_id column (single required category)
        $primary_cat = isset($old['category_id']) ? (int)$old['category_id'] : 0;
        $sql = "INSERT INTO products (sku, title, slug, description, price, stock, images, is_active, primary_category_id) VALUES ('{$sku_q}', '{$title_q}', '{$slug_q}', '{$desc_q}', {$price_q}, {$stock_q}, {$images_q}, {$is_active_q}, {$primary_cat})";
        if ($conn->query($sql)) {
            // product inserted using primary_category_id; no legacy product_category table expected
            header('Location: /admin/product/index.php');
            exit;
        } else {
            $errors[] = 'Failed to insert product: ' . $conn->error;
        }
    }
}

// helper to render parent-style options for categories (flat->nested)
function render_parent_options_from_cats_multi($catsList, $parentId = null, $depth = 0, $selected = 0)
{
    foreach ($catsList as $n) {
        $nParent = array_key_exists('parent_id', $n) ? $n['parent_id'] : null;
        // use loose comparison to tolerate int/string/null differences
        if ($nParent == $parentId) {
            $prefix = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
            $sel = ((string)$n['id'] === (string)$selected) ? ' selected' : '';
            printf('<option value="%d"%s>%s%s</option>', $n['id'], $sel, $prefix, htmlspecialchars($n['name'] ?? ''));
            render_parent_options_from_cats_multi($catsList, $n['id'], $depth + 1, $selected);
        }
    }
}

?>
<div class="admin-container">

    <div class="admin-entity-header">
        <h1>Create Product</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="post" enctype="multipart/form-data">
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
                        <?php render_parent_options_from_cats_multi($cats, null, 0, (isset($old['category_id']) ? $old['category_id'] : 0)); ?>
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
                    <label class="form-label">Images (upload — multiple allowed)</label>
                    <input class="form-input" type="file" name="images[]" accept="image/*" multiple>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Create product</button>
                <a class="btn" href="/admin/product">Cancel</a>
            </div>
        </form>
    </div>

</div>

<?php
pageFooter();
?>

<style>
    /* Modern product form tweaks (local to this page) */
    .admin-container {
        max-width: 1100px;
        margin: 24px auto;
        padding: 0 12px;
    }

    .form-card {
        box-shadow: 0 6px 18px rgba(16, 24, 40, 0.06);
        border-radius: 12px;
        padding: 18px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        align-items: start;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-label {
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 6px;
    }

    .form-input,
    .form-select,
    textarea.form-input {
        width: 100%;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid #e6eef8;
        background: #fbfdff;
        font-size: 0.95rem;
    }

    .form-input:focus,
    .form-select:focus,
    textarea.form-input:focus {
        outline: none;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
        border-color: #7aa8ff;
    }

    .form-actions {
        margin-top: 16px;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .btn-primary {
        background: linear-gradient(180deg, #2563eb, #1e40af);
        color: #fff;
        padding: 10px 14px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
    }

    .btn {
        background: #f3f4f6;
        color: #0f172a;
        padding: 8px 12px;
        border-radius: 10px;
        text-decoration: none;
        display: inline-block;
    }

    .form-select[multiple] {
        min-height: 160px;
    }

    .muted {
        color: #64748b;
    }

    @media (max-width:900px) {
        .form-grid {
            grid-template-columns: 1fr;
        }

        .form-select[multiple] {
            min-height: 120px;
        }
    }
</style>