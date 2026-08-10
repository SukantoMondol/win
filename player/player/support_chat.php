<?php
// Compatibility redirect for old/wrong link: /player/player/support_chat.php
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: ../support_chat.php' . $query, true, 302);
exit();
?>
