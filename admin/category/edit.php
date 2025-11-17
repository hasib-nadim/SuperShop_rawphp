<?php
require_once __DIR__ . '/../../_imports.php';
pageHead('Edit Category - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    header('Location: /admin/category/index.php');
    exit;
}

$errors = [];

// fetch categories for parent select (exclude current id to avoid self-parent)
$res = $conn->query("SELECT id, name, parent_id FROM categories ORDER BY name ASC");
$cats = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $r['parent_id'] = $r['parent_id'] ? (int)$r['parent_id'] : null;
        $cats[(int)$r['id']] = $r;
    }
}

// fetch current category
$stmt = $conn->prepare('SELECT id, name, slug, parent_id, is_active FROM categories WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$cur = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cur) {
    header('Location: /admin/category/index.php');
    exit;
}

$old = [
    'name' => $cur['name'],
    'slug' => $cur['slug'],
    'parent_id' => $cur['parent_id'] ?? '',
    'is_active' => $cur['is_active'] ? 1 : 0,
];

if (Req\isPost()) {
    [$inputs, $errors] = Req\validate([
        'name' => 'required|trim|min:3',
        'parent_id' => '',
        'slug' => 'trim',
        'is_active' => 'bool'
    ]);

    if (is_array($inputs)) {
        $old['name'] = $inputs['name'] ?? $old['name'];
        $old['slug'] = $inputs['slug'] ?? $old['slug'];
        $old['parent_id'] = ($inputs['parent_id'] ?? '') !== '' ? (int)$inputs['parent_id'] : '';
        $old['is_active'] = !empty($inputs['is_active']) ? 1 : 0;
    }

    if (empty($errors)) {
        // prevent parenting to self
        if ($old['parent_id'] !== '' && $old['parent_id'] == $id) {
            $errors[] = 'A category cannot be its own parent.';
        }

        // normalize slug
        $base = $old['slug'] !== '' ? preg_replace('/[^a-z0-9]+/i', '-', strtolower($old['slug'])) : preg_replace('/[^a-z0-9]+/i', '-', strtolower($old['name']));
        $base = trim($base, '-');
        if ($base === '') $base = 'cat';

        // ensure unique slug excluding current id
        $candidate = $base;
        $i = 1;
        while (true) {
            $stmt = $conn->prepare('SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1');
            if (!$stmt) { $errors[] = 'Failed preparing slug check: ' . $conn->error; break; }
            $stmt->bind_param('si', $candidate, $id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) { $stmt->close(); break; }
            $candidate = $base . '-' . $i++;
            $stmt->close();
        }
    }

    if (empty($errors)) {
        if ($old['parent_id'] === '') {
            $upd = $conn->prepare('UPDATE categories SET name = ?, slug = ?, parent_id = NULL, is_active = ? WHERE id = ?');
            if ($upd) $upd->bind_param('ssii', $old['name'], $candidate, $old['is_active'], $id);
        } else {
            $upd = $conn->prepare('UPDATE categories SET name = ?, slug = ?, parent_id = ?, is_active = ? WHERE id = ?');
            if ($upd) $upd->bind_param('ssiii', $old['name'], $candidate, $old['parent_id'], $old['is_active'], $id);
        }

        if (!$upd) {
            $errors[] = 'Failed to prepare update: ' . $conn->error;
        } else {
            if ($upd->execute()) {
                $upd->close();
                header('Location: /admin/category/index.php');
                exit;
            } else {
                $errors[] = 'Failed to update category: ' . $upd->error;
            }
            $upd->close();
        }
    }
}

// build tree for parent select
$tree = [];
foreach ($cats as $cid => $c) {
    if ($c['parent_id'] && isset($cats[$c['parent_id']])) {
        $cats[$c['parent_id']]['children'][] = &$cats[$cid];
    }
}
foreach ($cats as $cid => $c) {
    if (empty($c['children'])) continue;
}

// render
?>
<div class="admin-container">
  <div class="admin-entity-header"><h1>Edit Category</h1></div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
  <?php endif; ?>

  <div class="card form-card">
    <form method="post">
      <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Name</label>
          <input class="form-input" name="name" value="<?php echo htmlspecialchars($old['name']); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Slug</label>
          <input class="form-input" name="slug" value="<?php echo htmlspecialchars($old['slug']); ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Parent</label>
          <select class="form-select" name="parent_id">
            <option value="">— none —</option>
            <?php
      // render options excluding current id by walking the flat $cats list (avoids building referenced trees)
      function render_parent_options_from_cats($catsList, $parentId = null, $depth = 0, $curId = 0, $oldParent = '') {
        foreach ($catsList as $n) {
          $nParent = isset($n['parent_id']) ? $n['parent_id'] : null;
          if ($nParent === $parentId) {
            if ($n['id'] == $curId) continue;
            $prefix = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
            $sel = ((string)$n['id'] === (string)$oldParent) ? ' selected' : '';
            printf('<option value="%d"%s>%s%s</option>', $n['id'], $sel, $prefix, htmlspecialchars($n['name']));
            // recurse into children
            render_parent_options_from_cats($catsList, $n['id'], $depth + 1, $curId, $oldParent);
          }
        }
      }
      // call renderer starting from top-level (parent_id NULL)
      render_parent_options_from_cats($cats, null, 0, $id, $old['parent_id']);
            ?>
          </select>
        </div>
        <div class="form-group form-group--small">
          <label class="form-label">Active</label>
          <div class="form-check">
            <input id="is_active" type="checkbox" name="is_active" <?php echo $old['is_active'] ? 'checked' : ''; ?> />
            <label for="is_active" class="muted">visible</label>
          </div>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save changes</button>
        <a class="btn" href="/admin/category/index.php">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php
pageFooter();
?>
