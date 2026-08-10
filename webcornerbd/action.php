<?php
// Legacy alternate login endpoint disabled. Administrator authentication is
// available only through login.php and the dedicated `admin` table.
header('Location: login.php', true, 302);
exit();
