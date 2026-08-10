<?php
// agent/db.php
$db_path = file_exists('../includes/db.php') ? '../includes/db.php' : '../../includes/db.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("Database file not found!");
}
?>