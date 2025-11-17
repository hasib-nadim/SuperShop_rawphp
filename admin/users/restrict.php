<?php
require_once __DIR__ . '/../../_imports.php';
$admin = GetAdmin();
$conn = DB\getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/users/index.php'); exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($id <= 0 || !in_array($action, ['restrict','unrestrict'])) { header('Location: /admin/users/index.php'); exit; }

$colRes = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'");
if (!($colRes && $colRes->num_rows)) {
    // cannot proceed
    $_SESSION['flash_error'] = 'is_active column not present on users table. Cannot toggle restrict.';
    header('Location: /admin/users/show.php?id=' . $id);
    exit;
}

$new = $action === 'restrict' ? 0 : 1;
$stmt = $conn->prepare('UPDATE users SET is_active = ? WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('ii', $new, $id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = $action === 'restrict' ? 'User restricted.' : 'User unrestricted.';
    } else {
        $_SESSION['flash_error'] = 'Failed to update user: ' . $conn->error;
    }
    $stmt->close();
} else {
    $_SESSION['flash_error'] = 'Failed to prepare statement: ' . $conn->error;
}

header('Location: /admin/users/show.php?id=' . $id);
exit;
