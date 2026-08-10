<?php
require_once 'api_config.php';

function callBetApi($action, $data) {
    $url = API_BASE . "?action=" . $action;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-API-KEY: " . API_KEY]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// ১. ইউজার রেজিস্টার করা (যদি না থাকে)
function registerExchangeUser($username) {
    return callBetApi('create_user', ['username' => $username]);
}

// ২. গেম লিংক পাওয়া
function getExchangeUrl($username) {
    return callBetApi('get_game_url', ['username' => $username]);
}

// ৩. টাকা পাঠানো (Main -> Exchange)
function depositToExchange($username, $amount) {
    $ref = "DEP_" . time() . "_" . rand(100,999);
    return callBetApi('deposit', [
        'username' => $username,
        'amount' => $amount,
        'ref' => $ref
    ]);
}

// ৪. টাকা তোলা (Exchange -> Main)
function withdrawFromExchange($username, $amount) {
    $ref = "WID_" . time() . "_" . rand(100,999);
    return callBetApi('withdraw', [
        'username' => $username,
        'amount' => $amount,
        'ref' => $ref
    ]);
}

// ৫. ব্যালেন্স চেক
function checkExchangeBalance($username) {
    return callBetApi('balance', ['username' => $username]);
}
?>