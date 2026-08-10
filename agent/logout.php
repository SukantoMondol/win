<?php
// Maintenance Mode public access guard
$__maintenance_db = __DIR__ . '/../includes/db.php';
if (file_exists($__maintenance_db)) { require_once $__maintenance_db; }

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ১. সেশন ডেস্ট্রয় বা ধ্বংস করা
session_unset();
session_destroy();

// ২. লগইন পেজে পাঠিয়ে দেওয়া
header("Location: login.php");
exit();
?>