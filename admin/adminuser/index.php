<?php
require_once __DIR__ . '/../../_imports.php';
pageHead('Admin Users - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

$total = 0;
$cnt = $conn->query("SELECT COUNT(*) AS cnt FROM adminuser");
if ($cnt) { $r = $cnt->fetch_assoc(); $total = (int)($r['cnt'] ?? 0); }

$users = [];
$stmt = $conn->prepare('SELECT id, username, email, role, is_super, is_active, last_login, created_at FROM adminuser ORDER BY created_at DESC LIMIT ?, ?');
if ($stmt) {
    $stmt->bind_param('ii', $offset, $perPage);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) while ($row = $res->fetch_assoc()) $users[] = $row;
    $stmt->close();
}

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
?>

<div class="admin-container">
    <div class="admin-entity-header">
        <h1>Admin Users</h1>
        <div>
            <a class="btn" href="<?php echo url('/admin/adminuser/new'); ?>">Add New</a>
        </div>
    </div>

    <div class="card">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px">ID</th>
                        <th style="text-align:left;padding:8px">Username</th>
                        <th style="text-align:left;padding:8px">Email</th>
                        <th style="text-align:left;padding:8px">Role</th>
                        <th style="text-align:left;padding:8px">Super</th>
                        <th style="text-align:left;padding:8px">Active</th>
                        <th style="text-align:left;padding:8px">Last login</th>
                        <th style="text-align:left;padding:8px">Created</th>
                        <th style="text-align:left;padding:8px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="9" style="padding:12px">No admin users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td style="padding:8px"><?php echo (int)$u['id']; ?></td>
                                <td style="padding:8px"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td style="padding:8px"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td style="padding:8px"><?php echo htmlspecialchars($u['role']); ?></td>
                                <td style="padding:8px"><?php echo $u['is_super'] ? 'Yes' : 'No'; ?></td>
                                <td style="padding:8px"><?php echo $u['is_active'] ? 'Yes' : 'No'; ?></td>
                                <td style="padding:8px"><?php echo htmlspecialchars($u['last_login']); ?></td>
                                <td style="padding:8px"><?php echo htmlspecialchars($u['created_at']); ?></td>
                                <td style="padding:8px">
                                    <a class="btn" href="<?php echo url('/admin/adminuser/edit.php') . '?id=' . (int)$u['id']; ?>">Edit</a>
                                    <a class="btn" href="<?php echo url('/admin/adminuser/delete.php') . '?id=' . (int)$u['id']; ?>" onclick="return confirm('Delete this admin user?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <?php if ($page > 1): ?><a class="btn" href="?page=<?php echo $page - 1; ?>">« Prev</a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p === $page): ?>
                    <strong style="padding:6px 10px;border-radius:6px;background:#eef2ff"><?php echo $p; ?></strong>
                <?php else: ?>
                    <a class="btn" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a class="btn" href="?page=<?php echo $page + 1; ?>">Next »</a><?php endif; ?>
            <div style="margin-left:8px;color:#64748b">Page <?php echo $page; ?> of <?php echo $totalPages; ?> — <?php echo $total; ?> users</div>
        </div>
    <?php endif; ?>

</div>

<?php pageFooter(); ?>
