<?php
// Maintenance Mode public access guard
$__maintenance_db = __DIR__ . '/../includes/db.php';
if (file_exists($__maintenance_db)) { require_once $__maintenance_db; }

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Check if user is logged in
if (isset($_SESSION['user_id'])) {
    
    // If logged in, go straight to Player Dashboard
    header("Location: dashboard.php");
    exit();

} else {
    
    // Not logged in? Go to Login Page
    header("Location: ../login.php"); 
    exit();
    
}
?>