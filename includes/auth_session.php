<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protected panel pages must never be restored from browser cache after logout.
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

require_once __DIR__ . '/admin_path_helper.php';

$current_path = $_SERVER['PHP_SELF'] ?? '';
$role = isset($_SESSION['role']) ? (string)$_SESSION['role'] : '';
$isAdminSession = ($role === 'admin' && !empty($_SESSION['admin_id']));
$isUserSession = ($role !== 'admin' && !empty($_SESSION['user_id']));

// A legacy admin session is intentionally not accepted because admin login data
// must no longer be sourced from the users table.
if (!$isAdminSession && !$isUserSession) {
    if (admin_panel_is_request($current_path)) {
        admin_panel_redirect('login.php');
    }
    header('Location: login.php');
    exit();
}

// --- SECURITY FOR DYNAMIC ADMIN FOLDER ---
if (admin_panel_is_request($current_path)) {
    if ($role === 'support') {
        if (basename($current_path) !== 'support.php') {
            admin_panel_redirect('support.php');
        }
        return;
    }

    if ($role === 'agent') {
        header('Location: ../agent/index.php');
        exit();
    }

    if ($role === 'player') {
        header('Location: ../index.php');
        exit();
    }

    if ($isAdminSession) {
        return;
    }

    admin_panel_redirect('login.php');
}

// --- SECURITY FOR AGENT FOLDER ---
if (strpos($current_path, '/agent/') !== false) {
    if ($role !== 'agent') {
        if ($isAdminSession) {
            admin_panel_redirect('dashboard.php');
        }
        header('Location: ../index.php');
        exit();
    }
}
?>
