<?php
require_once __DIR__ . '/../../_imports.php';
pageHead("Categories - Supershop", ["admin_dashboard.css",]);
$admin = GetAdmin();
component('admin/nav.php', $admin);
?>
<div class="admin-container">

    <div class="admin-entity-header">
        <h1>Categories</h1>
        <div>
            <!-- form will be inline below -->
        </div>
    </div>

    <?php
    // DB connection and fetch all categories (flat)
    $conn = DB\getConnection();
    $errors = [];
    $old = ['name' => '', 'parent_id' => '', 'is_active' => 1];

    // If this request is a creation POST, handle it
    if (Req\isPost()) {
        [$inputs, $errors] = Req\validate([
            'name' => 'required|trim|min:3',
            'parent_id' => '',
            'is_active' => 'bool'
        ]);
        print_r([$inputs, $_POST]);
        // repopulate old values for the form
        if (is_array($inputs)) {
            $old['name'] = $inputs['name'] ?? '';
            $old['parent_id'] = ($inputs['parent_id'] ?? '') !== '' ? (int)$inputs['parent_id'] : '';
            $old['is_active'] = !empty($inputs['is_active']) ? 1 : 0;
        }

        if (empty($errors)) {
            // normalize/generate slug
            $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($inputs['name'] ?? ''));

            // ensure unique slug using prepared statements
            $candidate = $base;
            $i = 1;
            $exists = true;
            while ($exists) {
                $stmt = $conn->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
                if (!$stmt) {
                    $errors[] = 'Failed to prepare slug check: ' . $conn->error;
                    break;
                }
                $stmt->bind_param('s', $candidate);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows === 0) {
                    $exists = false;
                } else {
                    $candidate = $base . '-' . $i++;
                }
                $stmt->close();
            }

            if (empty($errors)) {
                // insert category (handle nullable parent_id)
                if ($old['parent_id'] === '') {
                    $ins = $conn->prepare('INSERT INTO categories (name, slug, parent_id, is_active) VALUES (?, ?, NULL, ?)');
                    if ($ins) $ins->bind_param('ssi', $old['name'], $candidate, $old['is_active']);
                } else {
                    $ins = $conn->prepare('INSERT INTO categories (name, slug, parent_id, is_active) VALUES (?, ?, ?, ?)');
                    if ($ins) $ins->bind_param('ssii', $old['name'], $candidate, $old['parent_id'], $old['is_active']);
                }

                if (!$ins) {
                    $errors[] = 'Failed to prepare insert statement: ' . $conn->error;
                } else {
                    if ($ins->execute()) {
                        // success, redirect to avoid resubmission
                        $ins->close();
                        header('Location: ' . $_SERVER['REQUEST_URI']);
                        exit;
                    } else {
                        $errors[] = 'Failed to insert category: ' . $ins->error;
                    }
                    $ins->close();
                }
            }
        }
    }


    $sql = "SELECT id, name, slug, parent_id, is_active, created_at FROM categories ORDER BY name ASC";
    $res = $conn->query($sql);


    if (!$res) {
        echo '<div class="alert alert-error">Failed to load categories: ' . htmlspecialchars($conn->error) . '</div>';
    } else {
        $cats = [];
        while ($row = $res->fetch_assoc()) {
            $row['children'] = [];
            $row['parent_id'] = $row['parent_id'] ? (int)$row['parent_id'] : null;
            $cats[(int)$row['id']] = $row;
        }

        // build tree for both select and display
        $tree = [];
        foreach ($cats as $id => &$c) {
            if ($c['parent_id'] && isset($cats[$c['parent_id']])) {
                $cats[$c['parent_id']]['children'][] = &$c;
            } else {
                $tree[] = &$c;
            }
        }
        unset($c);

        // helper: render parent <option> with indentation
        function render_parent_options(array $nodes, $depth = 0, $oldParent = '')
        {
            foreach ($nodes as $n) {
                $prefix = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
                $sel = ((string)$n['id'] === (string)$oldParent) ? ' selected' : '';
                printf('<option value="%d"%s>%s%s</option>', $n['id'], $sel, $prefix, htmlspecialchars($n['name']));
                if (!empty($n['children'])) {
                    render_parent_options($n['children'], $depth + 1, $oldParent);
                }
            }
        }

        // render inline form
    ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>

        <div class="card form-card" style="margin-bottom:18px;">
            <form method="post" class="category-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input class="form-input" name="name" value="<?php echo htmlspecialchars($old['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Parent</label>
                        <select class="form-select" name="parent_id">
                            <option value="">— none —</option>
                            <?php render_parent_options($tree, 0, $old['parent_id']); ?>
                        </select>
                    </div>
                    <div class="form-group form-group--small">
                        <label class="form-label">Active</label>
                        <div class="form-check">
                            <input id="is_active" type="checkbox" name="is_active" <?php echo $old['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" class="muted">visible</label>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <input type="hidden" name="create_category" value="1">
                    <button class="btn btn-primary" type="submit">Create category</button>
                </div>
            </form>
        </div>

        <?php
        // render categories as a table with indented name column
        echo '<div class="card">';
        echo '<table class="category-table">';
        echo '<thead><tr><th>Name</th><th>Slug</th><th>Status</th><th>Created</th><th style="width:130px">Actions</th></tr></thead>';
        echo '<tbody>';

        function render_category_rows(array $nodes, $depth = 0)
        {
            foreach ($nodes as $n) {
                $name = htmlspecialchars($n['name']);
                $slug = htmlspecialchars($n['slug']);
                $active = $n['is_active'] ? 'Active' : 'Inactive';
                $date = htmlspecialchars($n['created_at']);
                $pad = 16 * $depth;

                echo '<tr>';
                echo '<td class="name-cell">';
                echo '<div style="padding-left:' . $pad . 'px; display:flex; align-items:center; gap:10px;">';
                echo '<span class="name-title">' . $name . '</span>';
                echo '<span class="muted">/ ' . $slug . '</span>';
                echo '</div>';
                echo '</td>';
                echo '<td>' . $slug . '</td>';
                echo '<td>' . $active . '</td>';
                echo '<td>' . $date . '</td>';
                echo '<td class="actions">';
                echo '<a class="link-edit" href="/admin/category/edit.php?id=' . (int)$n['id'] . '">Edit</a> ';
                echo '<a class="link-delete" href="/admin/category/delete.php?id=' . (int)$n['id'] . '" onclick="return confirm(\'Are you sure?\')">Delete</a>';
                echo '</td>';
                echo '</tr>';

                if (!empty($n['children'])) {
                    render_category_rows($n['children'], $depth + 1);
                }
            }
        }

        render_category_rows($tree, 0);

        echo '</tbody></table>';
        echo '</div>';
        ?>

    <?php

    }
    ?>

</div> 

<?php
pageFooter();
?>