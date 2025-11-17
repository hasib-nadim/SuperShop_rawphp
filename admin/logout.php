<?php
// admin/logout.php
// Log out the current admin by clearing server-side session and redirecting to login.
require_once __DIR__ . '/../_imports.php';

// Use the helper from app/helpers/Session.php which removes DB session row and destroys PHP session.
if (function_exists('LogoutAdmin')) {
    LogoutAdmin();
} else {
    // Fallback: clear PHP session and redirect to login
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /admin/login');
    exit();
}
