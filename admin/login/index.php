<?php
require_once __DIR__ . '/../../_imports.php';
pageHead("Admin Login - Supershop",["admin_login.css"]);

use function Req\hasError;

// prepare containers
$inputs = [];
$errors = null;

// Handle POST: validate and then auth if valid
if (Req\isPost()) {
    [$inputs, $errors] = Req\validate([
        'username' => 'required|trim',
        'password' => 'required',
    ]);

    if ($errors === null) {
        // placeholder authentication - replace with DB check
        $username = $inputs['username'] ?? '';
        $password = $inputs['password'] ?? '';
        $conn = DB\getConnection();
        // find adminuser by username
        $sql = "SELECT `id`, `password_hash` FROM `adminuser` WHERE `username` = ? or `email` = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute(); 
            $stmt->bind_result($adminId, $password_hash);
            if ($stmt->fetch()) {
                // got a row
                $stmt->close();
                if (password_verify($password, $password_hash??"")) { 
                    // authentication success
                    // create a session and insert in table and set in request session
                    // regenerate PHP session id for this login
                    if (!session_id()) { @session_start(); }
                    session_regenerate_id(true);
                    $sid = session_id();

                    // prepare session payload (store minimal info)
                    $payload = json_encode(['admin_id' => $adminId, 'username' => $username]);
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);
                    $lifetime = (int) (function_exists('env') ? env('session_lifetime', 120) : 120);
                    $expires_at = date('Y-m-d H:i:s', time() + ($lifetime * 60));

                    // insert or update sessions table
                    $insSql = "INSERT INTO `sessions` (`id`,`admin_user_id`,`payload`,`ip_address`,`user_agent`,`last_activity`,`expires_at`) VALUES (?,?,?,?,?,NOW(),?);";
                    $ins = $conn->prepare($insSql);
                    if ($ins) {
                        $ins->bind_param('sissss', $sid, $adminId, $payload, $ip, $ua, $expires_at);
                        $ins->execute();
                        $ins->close();
                    }

                    // set session vars for request 
                    $_SESSION['session_id'] = $sid;

                    // update last_login for admin
                    $upd = $conn->prepare("UPDATE `adminuser` SET `last_login` = NOW() WHERE `id` = ? LIMIT 1");
                    if ($upd) {
                        $upd->bind_param('i', $adminId);
                        $upd->execute();
                        $upd->close();
                    }

                    $_SESSION['flash_success'] = 'Login successful.';
                    redirect('/admin');
                    exit();
                } else {
                    $_SESSION['flash_error'] = 'Invalid username or password.';
                }
            } else {
                $stmt->close();
                $_SESSION['flash_error'] = 'Invalid username or password.';
            }
        } else {
            $_SESSION['flash_error'] = 'Server error: could not prepare statement.';
        }
    }
}

pageHead("Admin Login - Supershop", ["admin_login.css"]);
?>

<main class="admin-login-page">
    <div class="login-card">
        <h1 class="brand">Supershop Admin</h1>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['flash_error']);
                                            unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']);
                                                unset($_SESSION['flash_success']); ?></div>
        <?php endif; ?>


        <form method="post" class="login-form" action="">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" placeholder="admin" required autofocus
                value="<?php echo htmlspecialchars($inputs['username'] ?? ''); ?>"
                class="<?php echo hasError('username') ? 'input-error' : ''; ?>" />
            <?php if (hasError('username')): ?>
                <div class="field-error"><?php echo htmlspecialchars(Req\error('username')); ?></div>
            <?php endif; ?>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" placeholder="••••••••" required
                class="<?php echo hasError('password') ? 'input-error' : ''; ?>" />
            <?php if (hasError('password')): ?>
                <div class="field-error"><?php echo htmlspecialchars(Req\error('password')); ?></div>
            <?php endif; ?>

            <div class="form-row">
                <button type="submit" class="btn">Sign in</button>
            </div>
        </form>

    </div>
</main>

<?php
pageFooter();
?>