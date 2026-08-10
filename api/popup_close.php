<?php
// Stores global popup close state for the current browser/login session.
// This keeps the announcement popup from opening again on every page click/load.

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');

$key = '';
if (isset($_POST['popup_key'])) {
    $key = trim((string)$_POST['popup_key']);
} else {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['popup_key'])) {
            $key = trim((string)$json['popup_key']);
        }
    }
}

if ($key === '' || !preg_match('/^rj_promo_popup_[A-Za-z0-9_]+_[a-f0-9]{16}$/', $key)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Invalid popup key'));
    exit;
}

if (!isset($_SESSION['rj_promo_popup_closed']) || !is_array($_SESSION['rj_promo_popup_closed'])) {
    $_SESSION['rj_promo_popup_closed'] = array();
}
$_SESSION['rj_promo_popup_closed'][$key] = time();

// Session cookie only: it is cleared by the browser/session, so popup can show again on a new visit/login session.
@setcookie($key, '1', 0, '/', '', false, true);

echo json_encode(array('success' => true));
