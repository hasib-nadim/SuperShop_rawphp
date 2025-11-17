<?php
require_once __DIR__ . '/../../_imports.php';
pageHead('User - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: /admin/users/index.php'); exit; }

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$user = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $user = $res->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    echo "<div class=\"admin-container\"><div class=\"card\"><h2>User not found</h2><p><a class=\"btn\" href=\"/admin/users/index.php\">Back</a></p></div></div>";
    pageFooter();
    exit;
}
 
$canToggle = true;
 
?>

<div class="admin-container">
    <div class="admin-entity-header">
        <h1>User #<?php echo (int)$user['id']; ?></h1>
        <div>
            <a class="btn" href="/admin/users/index.php">Back to list</a>
        </div>
    </div>

    <div class="card">
        <table style="width:100%;border-collapse:collapse">
            <tbody>
                <?php foreach ($user as $k => $v): ?>
                    <tr>
                        <td style="padding:8px;width:260px;font-weight:600;text-transform:capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $k)); ?></td>
                        <td style="padding:8px"><?php echo htmlspecialchars((string)$v); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:12px">
            <?php if ($canToggle): ?>
                <?php if (!empty($user['is_active'])): ?>
                    <form method="POST" action="/admin/users/restrict.php" onsubmit="return confirm('Restrict this user? They will be prevented from logging in.');" style="display:inline">
                        <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>" />
                        <input type="hidden" name="action" value="restrict" />
                        <button class="btn" type="submit" style="background:#ef4444;color:#fff">Restrict User</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="/admin/users/restrict.php" onsubmit="return confirm('Unrestrict this user?');" style="display:inline">
                        <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>" />
                        <input type="hidden" name="action" value="unrestrict" />
                        <button class="btn" type="submit" style="background:#10b981;color:#fff">Unrestrict User</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div style="color:#b91c1c;margin-top:8px">This installation does not have an <code>is_active</code> column on the <code>users</code> table. Restrict/unrestrict is not available. You can add an <code>is_active TINYINT(1) DEFAULT 1</code> column to enable this feature.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php pageFooter(); ?>
