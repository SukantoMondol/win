<?php
// Legacy payment gateway endpoint disabled. LG Pay is the only active/default gateway.
http_response_code(410);
header('Content-Type: text/plain; charset=UTF-8');
echo 'disabled';
exit;
