<?php
require_once __DIR__ . '/../..//_imports.php';
pageHead('Edit Admin User - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) { header('Location: /admin/adminuser/index.php'); exit; }

$stmt = $conn->prepare('SELECT id, username, email, role, is_super, is_active FROM adminuser WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$cur = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cur) { header('Location: /admin/adminuser/index.php'); exit; }

$errors = [];
$old = [
    'username' => $cur['username'],
    'email' => $cur['email'],
    'role' => $cur['role'],
    'is_super' => $cur['is_super'] ? 1 : 0,
    'is_active' => $cur['is_active'] ? 1 : 0
];

if (Req\isPost()) {
    [$inputs, $errors] = Req\validate([
        'username' => 'required|trim|min:3',
        'email' => 'trim',
        'password' => '',
        'role' => 'trim',
        'is_super' => 'bool',
        'is_active' => 'bool'
    ]);

    if (is_array($inputs)) {
        $old['username'] = $inputs['username'] ?? $old['username'];
        $old['email'] = $inputs['email'] ?? $old['email'];
        $old['role'] = $inputs['role'] ?? $old['role'];
        $old['is_super'] = !empty($inputs['is_super']) ? 1 : 0;
        $old['is_active'] = !empty($inputs['is_active']) ? 1 : 0;
    }

    if (empty($errors)) {
        // unique username excluding current
        $s = $conn->prepare('SELECT id FROM adminuser WHERE username = ? AND id != ? LIMIT 1');
        if ($s) { $s->bind_param('si', $old['username'], $id); $s->execute(); $s->store_result(); if ($s->num_rows > 0) $errors[] = 'Username already taken'; $s->close(); }
    }

    if (empty($errors)) {
        // build query
        $sets = [];
        $params = [];
        $types = '';
        $username_q = $conn->real_escape_string($old['username']);
        $email_q = $conn->real_escape_string($old['email']);
        $role_q = $conn->real_escape_string($old['role']);
        $is_super_q = (int)$old['is_super'];
        $is_active_q = (int)$old['is_active'];

        if (!empty($_POST['password'])) {
            $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pw_q = $conn->real_escape_string($pw);
            $sets[] = "password_hash = '{$pw_q}'";
        }
        $sets[] = "username = '{$username_q}'";
        $sets[] = "email = '{$email_q}'";
        $sets[] = "role = '{$role_q}'";
        $sets[] = "is_super = {$is_super_q}";
        $sets[] = "is_active = {$is_active_q}";

        $sql = 'UPDATE adminuser SET ' . implode(', ', $sets) . ' WHERE id = ' . (int)$id;
        if ($conn->query($sql)) { header('Location: /admin/adminuser/index.php'); exit; } else { $errors[] = 'Failed to update: ' . $conn->error; }
    }
}

?>
<div class="admin-container">
    <div class="admin-entity-header"><h1>Edit Admin User</h1></div>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div><?php endif; ?>
    <div class="card form-card">
        <form method="post">
            <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
            <div class="form-grid">
                <div class="form-group"><label class="form-label">Username</label><input class="form-input" name="username" value="<?php echo htmlspecialchars($old['username']); ?>" required></div>
                <div class="form-group"><label class="form-label">Email</label><input class="form-input" name="email" value="<?php echo htmlspecialchars($old['email']); ?>"></div>
                <div class="form-group"><label class="form-label">Password (leave empty to keep)</label><input class="form-input" name="password" type="password"></div>
                <div class="form-group"><label class="form-label">Role</label><input class="form-input" name="role" value="<?php echo htmlspecialchars($old['role']); ?>"></div>
                <div class="form-group form-group--small"><label class="form-label">Super admin</label><div class="form-check"><input type="checkbox" name="is_super" <?php echo $old['is_super'] ? 'checked' : ''; ?>></div></div>
                <div class="form-group form-group--small"><label class="form-label">Active</label><div class="form-check"><input type="checkbox" name="is_active" <?php echo $old['is_active'] ? 'checked' : ''; ?>></div></div>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Save</button><a class="btn" href="/admin/adminuser/index.php">Cancel</a></div>
        </form>
    </div>
</div>

<?php pageFooter(); ?>
