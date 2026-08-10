<?php
// includes/db.php

$host   = getenv('DB_HOST')   ?: 'localhost';
$user   = getenv('DB_USER')   ?: 'root';      
$pass   = getenv('DB_PASS')   ?: '';         
$dbname = getenv('DB_NAME')   ?: 'bating';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    http_response_code(503);
    die("Service temporarily unavailable.");
}

// Set Charset to handle special characters (e.g. Currency symbols)
$conn->set_charset("utf8mb4");


// Auto-fix 9Wicket local game icon if the SQL backup has an empty/placeholder image.
$nine_wicket_icon_patch = __DIR__ . '/game_api_9wicket_icon_patch.php';
if (file_exists($nine_wicket_icon_patch)) {
    require_once $nine_wicket_icon_patch;
    if (function_exists('apply_9wicket_icon_patch')) {
        @apply_9wicket_icon_patch($conn);
    }
}

// Auto-setup Gamblly API credentials and seed mappings
$gamblly_setup_file = __DIR__ . '/gamblly_auto_setup.php';
if (file_exists($gamblly_setup_file)) {
    require_once $gamblly_setup_file;
    if (function_exists('gamblly_auto_setup_run')) {
        @gamblly_auto_setup_run($conn);
    }
}

// Enforce Maintenance Mode on all public/user-facing pages that load the database.
$maintenance_guard = __DIR__ . '/maintenance_guard.php';
if (!defined('GAME_API_SKIP_MAINTENANCE') && file_exists($maintenance_guard)) {
    require_once $maintenance_guard;
    maintenance_enforce($conn);
}
?>