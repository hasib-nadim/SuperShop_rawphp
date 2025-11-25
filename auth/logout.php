<?php
// User logout: remove server-side session row and clear PHP session
require_once __DIR__ . '/../_imports.php';

$sid = $_SESSION['session_id'] ?? null;

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
        // ignore DB errors during logout
    }
}

// Clear PHP session and cookie
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

@session_destroy();

// redirect to home
redirect('/');
exit();
