<?php
session_start();

// If logged in, send each role to its correct admin/support screen.
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin' && !empty($_SESSION['admin_id'])) {
        header("Location: dashboard.php");
        exit();
    }
    if ($_SESSION['role'] === 'support' && !empty($_SESSION['user_id'])) {
        header("Location: support.php");
        exit();
    }
}

// Not logged in? Go to the login page in this same folder.
// This keeps working after the admin folder is renamed.
header("Location: login.php");
exit();
?>
