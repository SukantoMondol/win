<?php
// Legacy ProPay return endpoint disabled. LG Pay is the only active/default gateway.
header('Location: lgpay_deposit_return.php' . (!empty($_SERVER['QUERY_STRING']) ? ('?' . $_SERVER['QUERY_STRING']) : ''));
exit;
