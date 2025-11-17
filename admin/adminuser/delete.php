<?php
require_once __DIR__ . '/../../_imports.php';

$admin = GetAdmin();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /admin/adminuser/index.php'); exit; }

$conn = DB\getConnection();

// prevent deleting last super admin
$chk = $conn->prepare('SELECT is_super FROM adminuser WHERE id = ? LIMIT 1');
if ($chk) {
    $chk->bind_param('i', $id);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
} else {
    $row = null;
}

if (!$row) { header('Location: /admin/adminuser/index.php'); exit; }

if ($row['is_super']) {
    $res = $conn->query('SELECT COUNT(*) AS cnt FROM adminuser WHERE is_super = 1');
    $cnt = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
    if ($cnt <= 1) {
        // cannot delete last super admin
        require_once __DIR__ . '/../../_imports.php';
        pageHead('Cannot delete', ['admin_dashboard.css']);
        $admin = GetAdmin(); component('admin/nav.php', $admin);
        ?>
        <div class="admin-container"><div class="card"><h2>Cannot delete user</h2><p>This user is the last super admin and cannot be deleted.</p><p><a class="btn" href="/admin/adminuser/index.php">Back</a></p></div></div>
        <?php
        pageFooter();
        exit;
    }
}

$del = $conn->prepare('DELETE FROM adminuser WHERE id = ?');
if ($del) { $del->bind_param('i', $id); $del->execute(); $del->close(); }

header('Location: /admin/adminuser/index.php'); exit;
