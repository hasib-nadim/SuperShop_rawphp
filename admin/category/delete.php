<?php
require_once __DIR__ . '/../../_imports.php';

$admin = GetAdmin();
// simple deletion via id param
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /admin/category/index.php');
    exit;
}

$conn = DB\getConnection();

// Prevent delete if category has children
$chk = $conn->prepare('SELECT id, name FROM categories WHERE parent_id = ? LIMIT 100');
if ($chk) {
        $chk->bind_param('i', $id);
        $chk->execute();
        $res = $chk->get_result();
        $children = $res->fetch_all(MYSQLI_ASSOC);
        $chk->close();
} else {
        $children = [];
}

if (!empty($children)) {
        // show a simple error page listing the children and a back link
        require_once __DIR__ . '/../../_imports.php';
        pageHead('Cannot delete - Category has children', ['admin_dashboard.css']);
        $admin = GetAdmin();
        component('admin/nav.php', $admin);
        ?>
        <div class="admin-container">
            <div class="card">
                <h2>Cannot delete category</h2>
                <p>This category has child categories. Please remove or reassign the following child categories before deleting:</p>
                <ul>
                <?php foreach ($children as $ch): ?>
                    <li><?php echo htmlspecialchars($ch['name']) . ' (id: ' . (int)$ch['id'] . ')'; ?></li>
                <?php endforeach; ?>
                </ul>
                <p><a class="btn" href="/admin/category/index.php">Back to categories</a></p>
            </div>
        </div>
        <?php
        pageFooter();
        exit;
}

// delete the category; cascade/parent behaviors handled by FK (if configured)
$stmt = $conn->prepare('DELETE FROM categories WHERE id = ?');
if ($stmt) {
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
}

header('Location: /admin/category/index.php');
exit;
