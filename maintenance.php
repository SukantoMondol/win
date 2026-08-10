<?php
session_start();
$db_path = __DIR__ . '/includes/db.php';
if (file_exists($db_path)) {
    require_once $db_path;
}
require_once __DIR__ . '/includes/maintenance_guard.php';
$settings = isset($conn) ? maintenance_fetch_settings($conn) : [];
maintenance_render_page($settings);
?>
