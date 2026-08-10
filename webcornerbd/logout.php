<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// The admin-panel logout endpoint never redirects to the public/user website.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

// Remove every value belonging to the current panel session.
$_SESSION = array();

// Remove the PHP session cookie using its original cookie settings.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        (bool)$params['secure'],
        (bool)$params['httponly']
    );
}

session_destroy();

// Always return to the login page inside the admin folder.
header('Location: login.php', true, 303);
exit();
?>
