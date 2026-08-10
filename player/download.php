<?php
// Maintenance Mode public access guard
$__maintenance_db = __DIR__ . '/../includes/db.php';
if (file_exists($__maintenance_db)) { require_once $__maintenance_db; }

// Compatibility route for sidebar APP Download link inside /player.
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ../download.php' . $query, true, 302);
exit();
?>
