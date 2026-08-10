<?php
// Maintenance Mode public access guard
$__maintenance_db = __DIR__ . '/../includes/db.php';
if (file_exists($__maintenance_db)) { require_once $__maintenance_db; }

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Check if user is logged in AND is an 'admin'
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'agent') {
    
    // Logged in? Go to Dashboard
    header("Location: dashboard.php");
    exit();

} else {
    
    // Not logged in? Go to Login Page
    // Assuming your login page is in the main folder (one level up)
    header("Location: ../agent/login.php"); 
    exit();
    
}
?>