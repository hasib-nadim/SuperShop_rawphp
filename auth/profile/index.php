<?php
require_once __DIR__ . '/../../_imports.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user = GetUser(true);
$conn = DB\getConnection();

pageHead('Profile - Supershop', ['home.css']);
component('header.php', ['user' => $user]);

// Handle POST (update profile)
if (Req\isPost()) {
    [$inputs, $errors] = Req\validate([
        'first_name' => 'required|trim|min:2',
        'last_name' => 'trim',
        'email' => 'required|trim|email',
        'phone' => 'trim',
    ]);

    if (!empty($errors)) {
        $_SESSION['flash_error'] = 'Please correct the highlighted errors.';
        $_SESSION['old_profile'] = $inputs;
        redirect('/auth/profile');
    }

    // update users table
    $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?');
    if ($stmt) {
        $fn = $inputs['first_name'];
        $ln = $inputs['last_name'] ?? '';
        $em = $inputs['email'];
        $ph = $inputs['phone'] ?? '';
        $stmt->bind_param('ssssi', $fn, $ln, $em, $ph, $user['id']);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = 'Profile updated.';
            // refresh user in session by redirecting to profile (GetUser reads sessions table), keep it simple
            redirect('/auth/profile');
        } else {
            $_SESSION['flash_error'] = 'Failed to update profile.';
            $_SESSION['old_profile'] = $inputs;
            redirect('/auth/profile');
        }
        $stmt->close();
    } else {
        $_SESSION['flash_error'] = 'Server error.';
        redirect('/auth/profile');
    }
}

// load current user details from DB (fresh)
$profile = null;
$q = $conn->prepare('SELECT id, first_name, last_name, email, phone, created_at FROM users WHERE id = ? LIMIT 1');
if ($q) { $q->bind_param('i', $user['id']); $q->execute(); $res = $q->get_result(); $profile = $res->fetch_assoc(); $q->close(); }

// if old_profile exists (from validation), use that to prefill
$old = $_SESSION['old_profile'] ?? null; if ($old) { $profile = array_merge($profile ?? [], $old); unset($_SESSION['old_profile']); }

?>

<div style="max-width:900px;margin:28px auto;padding:20px">
    <h2 style="margin:0 0 12px">My profile</h2>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div style="padding:12px;margin-bottom:12px;background:#fee2e2;color:#b91c1c;border-radius:8px;border:1px solid #fca5a5">
            <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div style="padding:12px;margin-bottom:12px;background:#ecfdf5;color:#065f46;border-radius:8px;border:1px solid #bbf7d0">
            <?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <div style="display:flex;gap:20px;align-items:flex-start">
        <div style="flex:1;background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
            <form method="POST">
                <div style="display:flex;gap:12px;margin-bottom:12px">
                    <input name="first_name" placeholder="First name" required value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" style="flex:1;padding:10px;border:1px solid #e6edf3;border-radius:8px">
                    <input name="last_name" placeholder="Last name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" style="flex:1;padding:10px;border:1px solid #e6edf3;border-radius:8px">
                </div>
                <div style="margin-bottom:12px">
                    <input name="email" placeholder="Email" required type="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" style="width:100%;padding:10px;border:1px solid #e6edf3;border-radius:8px">
                </div>
                <div style="margin-bottom:12px">
                    <input name="phone" placeholder="Phone" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" style="width:320px;padding:10px;border:1px solid #e6edf3;border-radius:8px">
                </div>
                <div style="margin-top:8px">
                    <button type="submit" style="padding:10px 14px;border-radius:8px;background:#0b8457;color:#fff;border:0;cursor:pointer">Save changes</button>
                </div>
            </form>
        </div>

        <aside style="width:300px">
            <div style="background:#fff;padding:16px;border-radius:10px;border:1px solid #eef2f6">
                <h3 style="margin:0 0 8px">Account</h3>
                <div style="color:#374151;padding:6px 0">Member since: <?php echo htmlspecialchars($profile['created_at'] ?? ''); ?></div>
                <div style="color:#64748b;padding:6px 0">User ID: <?php echo (int)($profile['id'] ?? 0); ?></div>
            </div>
        </aside>
    </div>
</div>

<?php pageFooter();
