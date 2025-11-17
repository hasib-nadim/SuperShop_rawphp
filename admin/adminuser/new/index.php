<?php
require_once __DIR__ . '/../../../_imports.php';
pageHead('Create Admin User - Supershop', ['admin_dashboard.css']);
$admin = GetAdmin();
component('admin/nav.php', $admin);

$conn = DB\getConnection();

$errors = [];
$old = ['username'=>'','email'=>'','role'=>'admin','is_super'=>0,'is_active'=>1];

if (Req\isPost()) {
    [$inputs, $errors] = Req\validate([
        'username' => 'required|trim|min:3',
        'email' => 'trim',
        'password' => 'required|min:6',
        'role' => 'trim',
        'is_super' => 'bool',
        'is_active' => 'bool'
    ]);

    if (is_array($inputs)) {
        $old['username'] = $inputs['username'] ?? '';
        $old['email'] = $inputs['email'] ?? '';
        $old['role'] = $inputs['role'] ?? 'admin';
        $old['is_super'] = !empty($inputs['is_super']) ? 1 : 0;
        $old['is_active'] = !empty($inputs['is_active']) ? 1 : 0;
    }

    if (empty($errors)) {
        // ensure unique username
        $s = $conn->prepare('SELECT id FROM adminuser WHERE username = ? LIMIT 1');
        if ($s) {
            $s->bind_param('s', $old['username']); $s->execute(); $s->store_result();
            if ($s->num_rows > 0) $errors[] = 'Username already taken';
            $s->close();
        }
    }

    if (empty($errors)) {
        $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $ins = $conn->prepare('INSERT INTO adminuser (username, password_hash, email, role, is_super, is_active) VALUES (?, ?, ?, ?, ?, ?)');
        if ($ins) {
            $ins->bind_param('ssssii', $old['username'], $pw, $old['email'], $old['role'], $old['is_super'], $old['is_active']);
            if ($ins->execute()) {
                header('Location: /admin/adminuser/index.php'); exit;
            } else { $errors[] = 'Failed to create admin user: ' . $ins->error; }
            $ins->close();
        } else {
            $errors[] = 'Failed to prepare insert: ' . $conn->error;
        }
    }
}

?>
<div class="admin-container">
    <div class="admin-entity-header"><h1>Create Admin User</h1></div>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input class="form-input" name="username" value="<?php echo htmlspecialchars($old['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-input" name="email" value="<?php echo htmlspecialchars($old['email']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input class="form-input" name="password" type="password" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input class="form-input" name="role" value="<?php echo htmlspecialchars($old['role']); ?>">
                </div>
                <div class="form-group form-group--small">
                    <label class="form-label">Super admin</label>
                    <div class="form-check"><input type="checkbox" name="is_super" <?php echo $old['is_super'] ? 'checked' : ''; ?> /></div>
                </div>
                <div class="form-group form-group--small">
                    <label class="form-label">Active</label>
                    <div class="form-check"><input type="checkbox" name="is_active" <?php echo $old['is_active'] ? 'checked' : ''; ?> /></div>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Create</button>
                <a class="btn" href="/admin/adminuser/index.php">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php pageFooter(); ?>
