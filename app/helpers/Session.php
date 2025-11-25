<?php

/**
 * Verify current request has a valid admin session.
 * If not valid, redirect to admin login and exit.
 */
function GetAdmin()
{
    // prefer session_id stored in session (set at login), else use PHP session id
    $sid = $_SESSION['session_id'];

    if (empty($sid)) {
        $_SESSION['flash_error'] = 'You must be logged in as Admin.';
        header("Location: /admin/login");
        exit();
    }

    // Attempt to verify session row in DB
    $conn = \DB\getConnection();

    $sql = "SELECT `admin_user_id`, `expires_at` FROM `sessions` WHERE `id` = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // fallback
        $_SESSION['flash_error'] = 'Failed to find Admin session.';
        header("Location: /admin/login");
        exit();
    }

    $stmt->bind_param('s', $sid);
    $stmt->execute();
    $adminId = null;
    $expiresAt = null;
    $stmt->bind_result($adminId, $expiresAt);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        // no server-side session -> redirect
        $_SESSION['flash_error'] = 'Failed to find Admin session.';
        header("Location: /admin/login");
        exit();
    }

    // check expiry if set
    if (!is_null($expiresAt) && $expiresAt !== '') {
        $now = date('Y-m-d H:i:s');
        if ($expiresAt < $now) {
            // expired: remove session row and redirect
            $del = $conn->prepare("DELETE FROM `sessions` WHERE `id` = ? LIMIT 1");
            if ($del) {
                $del->bind_param('s', $sid);
                $del->execute();
                $del->close();
            }

            $_SESSION['flash_error'] = 'Admin session expried.';
            header("Location: /admin/login");
            exit();
        }
    }
    // refresh last_activity
    $upd = $conn->prepare("UPDATE `sessions` SET `last_activity` = NOW() WHERE `id` = ? LIMIT 1");
    if ($upd) {
        $upd->bind_param('s', $sid);
        $upd->execute();
        $upd->close();
    }

    // fetch full admin entity
    $admin = null;
    $aSql = "SELECT `id`,`username`,`password_hash`,`email`,`role`,`is_super`,`is_active`,`last_login`,`created_at` FROM `adminuser` WHERE `id` = ? LIMIT 1";
    $ast = $conn->prepare($aSql);
    if ($ast) {
        $ast->bind_param('i', $adminId);
        $ast->execute();
        $aid = $ausername = $apassword_hash = $aemail = $arole = $ais_super = $ais_active = $alast_login = $acreated_at = null;
        $ast->bind_result($aid, $ausername, $apassword_hash, $aemail, $arole, $ais_super, $ais_active, $alast_login, $acreated_at);
        if ($ast->fetch()) {
            $admin = [
                'id' => $aid,
                'username' => $ausername,
                'email' => $aemail,
                'role' => $arole,
                'is_super' => (int)$ais_super,
                'is_active' => (int)$ais_active,
                'last_login' => $alast_login,
                'created_at' => $acreated_at,
            ];
        }
        $ast->close();
    }

    if (!$admin) {
        $_SESSION['flash_error'] = 'Failed to load Admin details.';
        header("Location: /admin/login");
        exit();
    }

    return $admin;
}

/**
 * Log out admin: remove session row and clear PHP session
 */
function LogoutAdmin()
{
    $sid = $_SESSION['session_id'];
    // delete DB session if possible
    if ($sid) {
        try {
            $conn = \DB\getConnection();
            $del = $conn->prepare("DELETE FROM `sessions` WHERE `id` = ? LIMIT 1");
            if ($del) {
                $del->bind_param('s', $sid);
                $del->execute();
                $del->close();
            }
        } catch (\Throwable $e) {
            // ignore DB errors
        }
    }

    // clear PHP session
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: /admin/login');
    exit();
}

function GetUser($required = false)
{
    // prefer session_id stored in session (set at login), else use PHP session id
    $sid = $_SESSION['session_id'];

    if (empty($sid) && $required) {
        $_SESSION['flash_error'] = 'You must be logged.';
        redirect("/auth/login");
        exit();
    }
    if (!empty($sid)) {
        $conn = \DB\getConnection();
        $sql = "SELECT `user_id`, `expires_at` FROM `sessions` WHERE `id` = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt && $required) {
            // fallback
            $_SESSION['flash_error'] = 'Failed to find User session.';
            redirect("/auth/login");
            exit();
        }

        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $userId = null;
        $expiresAt = null;
        $stmt->bind_result($userId, $expiresAt);
        $found = $stmt->fetch();
        $stmt->close();

        if (!$found && $required) {
            // no server-side session -> redirect
            $_SESSION['flash_error'] = 'Failed to find User session.';
            redirect("/auth/login");
        }


        // check expiry if set
        if (!is_null($expiresAt) && $expiresAt !== '') {
            $now = date('Y-m-d H:i:s');
            if ($expiresAt < $now) {
                // expired: remove session row and redirect
                $del = $conn->prepare("DELETE FROM `sessions` WHERE `id` = ? LIMIT 1");
                if ($del) {
                    $del->bind_param('s', $sid);
                    $del->execute();
                    $del->close();
                }

                $_SESSION['flash_error'] = 'User session expried.';
                redirect("/auth/login");
            }
        }
        // refresh last_activity
        $upd = $conn->prepare("UPDATE `sessions` SET `last_activity` = NOW() WHERE `id` = ? LIMIT 1");
        if ($upd) {
            $upd->bind_param('s', $sid);
            $upd->execute();
            $upd->close();
        }

        // fetch full user entity (users table stores first_name/last_name)
        $user = null;
        $uSql = "SELECT `id`,`first_name`,`last_name`,`email`,`created_at` FROM `users` WHERE `id` = ? LIMIT 1";
        $ust = $conn->prepare($uSql);
        if ($ust) {
            $ust->bind_param('i', $userId);
            $ust->execute();
            $uid = $ufirst = $ulast = $uemail = $ucreated_at = null;
            $ust->bind_result($uid, $ufirst, $ulast, $uemail, $ucreated_at);
            if ($ust->fetch()) {
                $display = trim(($ufirst ?? '') . ' ' . ($ulast ?? '')) ?: $uemail;
                $user = [
                    'id' => $uid,
                    'username' => $display,
                    'email' => $uemail,
                    'created_at' => $ucreated_at,
                ];
            }
            $ust->close();
        }
        if ($user) {
            return $user;
        } elseif ($required) {
            $_SESSION['flash_error'] = 'Failed to load User details.';
            redirect("/auth/login");
        }
    }
    return null;
}
