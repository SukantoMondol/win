<?php
/**
 * OXEN_TECH wallet callback handler.
 * Supports: getBalance, deductBalance, addToBalance.
 */
define('GAME_API_SKIP_MAINTENANCE', true);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Token, X-Secret-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(array('status' => 200));
    exit;
}

require_once '../includes/db.php';
require_once '../includes/game_api_helper.php';
if (file_exists(__DIR__ . '/Encryption.php')) { require_once __DIR__ . '/Encryption.php'; }
game_api_start_error_logging();
game_api_ensure_schema($conn, false);

function oxen_callback_log($event, $context = array()) {
    game_api_debug_log('callback_' . $event, $context);
    $legacyLog = __DIR__ . '/callback_logs.log';
    @file_put_contents($legacyLog, '[' . date('Y-m-d H:i:s') . '] ' . $event . ' ' . json_encode(game_api_mask_sensitive($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function oxen_pick($data, $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '') { return $data[$key]; }
    }
    return $default;
}


function oxen_format_balance_value($balance) {
    return (float)number_format((float)$balance, 2, '.', '');
}

function oxen_balance_payload($balance) {
    $value = oxen_format_balance_value($balance);
    $text2 = number_format((float)$balance, 2, '.', '');
    $text3 = number_format((float)$balance, 3, '.', '');

    $flat = array(
        'balance' => $value,
        'userBalance' => $value,
        'UserBalance' => $value,
        'user_balance' => $value,
        'currentBalance' => $value,
        'current_balance' => $value,
        'availableBalance' => $value,
        'available_balance' => $value,
        'cashBalance' => $value,
        'cash_balance' => $value,
        'memberBalance' => $value,
        'member_balance' => $value,
        'walletBalance' => $value,
        'wallet_balance' => $value,
        'newBalance' => $value,
        'new_balance' => $value,
        'score' => $value,
        'cash' => $value,
        'credit' => $value,
        'amount' => $text2,
        'balanceText' => $text3,
        'balance_text' => $text3,
        'balanceString' => $text3,
        'balance_string' => $text3,
        'userBalanceText' => $text3,
        'formattedBalance' => $text3,
        'formatted_balance' => $text3
    );

    return $flat + array(
        'data' => $flat,
        'payload' => $flat
    );
}

function oxen_wallet_response($status, $message = '', $balance = null, $extra = array()) {
    $success = ((int)$status === 200);
    $response = array(
        'status' => (int)$status,
        'code' => $success ? 0 : (int)$status,
        'errorCode' => $success ? 0 : (int)$status,
        'msgCode' => $success ? 12 : (int)$status,
        'success' => $success,
        'msg' => $message !== '' ? $message : ($success ? 'Success' : 'Error')
    );

    if ($balance !== null) {
        $response = array_merge($response, oxen_balance_payload($balance));
    }

    foreach ($extra as $key => $value) {
        if (($key === 'data' || $key === 'payload') && isset($response[$key]) && is_array($response[$key]) && is_array($value)) {
            $response[$key] = array_merge($response[$key], $value);
        } else {
            $response[$key] = $value;
        }
    }

    oxen_callback_log('response_sent', array('status' => $status, 'msg' => $response['msg'], 'balance' => $balance, 'extra_keys' => array_keys($extra)));
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function oxen_current_user_balance($conn, $playerId) {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id=? LIMIT 1");
    if (!$stmt) { return null; }
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $balance = ($res && $res->num_rows > 0) ? (float)$res->fetch_assoc()['balance'] : null;
    $stmt->close();
    return $balance;
}

function oxen_make_tx_id($type, $playerId, $apiGameId, $roundId, $amount, $data) {
    $given = oxen_pick($data, array('TransactionId','transactionId','transaction_id','TxnId','txnId','txn_id','txid','TxId','transferId','transfer_id','transferCode','transfer_code','id','BetId','betId','bet_id','WagerId','wagerId','wager_id','OrderId','order_id','RoundId','round_id','roundNo','round_no','round','game_round','serial_number','serialNumber','mtcode'), '');
    if ($given !== '') { return $type . '_' . $given; }
    return $type . '_' . sha1($playerId . '|' . $apiGameId . '|' . $roundId . '|' . $amount . '|' . json_encode($data));
}

function oxen_record_transaction($conn, $txId, $playerId, $type, $localGameUid, $apiGameId, $roundId, $amount, $before, $after, $raw) {
    $stmt = $conn->prepare("INSERT INTO game_api_callback_transactions (external_transaction_id, player_id, callback_type, local_game_uid, api_game_id, round_id, amount, balance_before, balance_after, raw_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) { return false; }
    $rawJson = is_string($raw) ? $raw : json_encode(game_api_mask_sensitive($raw), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->bind_param('sissssddds', $txId, $playerId, $type, $localGameUid, $apiGameId, $roundId, $amount, $before, $after, $rawJson);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function oxen_adjust_balance($conn, $data, $type) {
    $rawPlayerId = oxen_pick($data, array('PlayerId','player_id','playerId','userId','user_id','UserId','uid','member_id','memberId','full_user_id','member_account','memberAccount','account','account_no','accountNo','Account','username','userName','login','loginName','member','memberName','userAccount','player','playerName','player_name'), 0);
    $playerId = game_api_normalize_player_id($conn, $rawPlayerId);
    $amount = (float)oxen_pick($data, array('amount','Amount','bet_amount','win_amount','betAmount','winAmount','BetAmount','WinAmount','bet','win','debit_amount','debitAmount','credit_amount','creditAmount','stake','stake_amount','bet_money','win_money','payout','payout_amount','total_win','totalWin','prize','money','validBet','valid_bet','profit','settleAmount','settle_amount'), 0);
    $apiGameId = (string)oxen_pick($data, array('GameId','gameId','game_id','game_uid','GameUID','gameUID','gameCode','gamecode','GameCode','game_code','providerGameCode','provider_game_code','uidGameCode','code','Code','gamehall','gameHall','game_name','gameName','gamename','gameNameEn'), '');
    $roundId = (string)oxen_pick($data, array('RoundId','roundId','round_id','game_round','GameRound','roundid','roundID','round_no','roundNo','mtcode','serial_number','serialNumber'), '');
    $currency = game_api_get_setting($conn, 'currency_code', 'BDT');
    $localGameUid = game_api_local_uid_by_api_id($conn, $apiGameId);
    if ($localGameUid === '') { $localGameUid = $apiGameId; }
    if ($roundId === '') { $roundId = date('YmdHis') . '_' . substr(sha1(json_encode($data)), 0, 10); }

    if ($playerId <= 0) {
        oxen_callback_log('invalid_player_id', array('raw_player_id' => $rawPlayerId, 'type' => $type, 'data' => $data));
        game_api_json_response(array('status' => 400, 'msg' => 'Invalid PlayerId'));
    }
    if ($amount < 0) {
        // Some providers send signed wallet deltas. Normalize negative single-operation amounts by reversing the action.
        $originalType = $type;
        $originalAmount = $amount;
        $amount = abs($amount);
        if ($type === 'deductBalance') { $type = 'addToBalance'; }
        elseif ($type === 'addToBalance') { $type = 'deductBalance'; }
        oxen_callback_log('signed_amount_normalized', array(
            'player_id' => $playerId,
            'original_type' => $originalType,
            'normalized_type' => $type,
            'original_amount' => $originalAmount,
            'normalized_amount' => $amount,
            'data' => $data
        ));
    }

    $txId = oxen_make_tx_id($type, $playerId, $apiGameId, $roundId, $amount, $data);
    $stmtChk = $conn->prepare("SELECT balance_after FROM game_api_callback_transactions WHERE external_transaction_id=? LIMIT 1");
    if ($stmtChk) {
        $stmtChk->bind_param('s', $txId);
        $stmtChk->execute();
        $resChk = $stmtChk->get_result();
        if ($resChk && $resChk->num_rows > 0) {
            $rowChk = $resChk->fetch_assoc();
            $stmtChk->close();
            oxen_callback_log('duplicate_transaction', array('transaction_id' => $txId, 'player_id' => $playerId, 'balance_after' => $rowChk['balance_after']));
            oxen_wallet_response(200, 'Duplicate', round((float)$rowChk['balance_after'], 2), array('duplicate' => true));
        }
        $stmtChk->close();
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE id=? FOR UPDATE");
        if (!$stmt) { throw new Exception('User lookup failed'); }
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { throw new Exception('User not found'); }
        $user = $res->fetch_assoc();
        $stmt->close();

        $before = round((float)$user['balance'], 2);
        if ($type === 'deductBalance') {
            if ($amount > $before) { throw new Exception('Insufficient balance'); }
            $after = round($before - $amount, 2);
            $historyType = 'bet';
        } else {
            $after = round($before + $amount, 2);
            $historyType = 'win';
        }

        $stmtUp = $conn->prepare("UPDATE users SET balance=? WHERE id=?");
        if (!$stmtUp) { throw new Exception('Balance update prepare failed'); }
        $stmtUp->bind_param('di', $after, $playerId);
        if (!$stmtUp->execute()) { throw new Exception('Balance update failed'); }
        $stmtUp->close();

        if (!oxen_record_transaction($conn, $txId, $playerId, $type, $localGameUid, $apiGameId, $roundId, $amount, $before, $after, $data)) {
            throw new Exception('Duplicate or invalid callback transaction');
        }

        game_api_insert_bet_history($conn, array(
            'user_id' => $playerId,
            'username' => $user['username'],
            'vendor_code' => 'OXEN_TECH',
            'game_uid' => $localGameUid,
            'round_id' => $roundId,
            'transaction_id' => $txId,
            'type' => $historyType,
            'amount' => $amount,
            'balance_after' => $after,
            'currency' => $currency
        ));

        $conn->commit();
        $response = array('status' => 200, 'balance' => round($after, 2));
        game_api_log($conn, array(
            'user_id' => $playerId,
            'local_game_uid' => $localGameUid,
            'api_game_id' => $apiGameId,
            'request_data' => game_api_mask_sensitive($data),
            'response_data' => $response,
            'status' => $type,
            'message' => 'Wallet callback processed'
        ));
        oxen_callback_log('processed', array('type' => $type, 'player_id' => $playerId, 'transaction_id' => $txId, 'before' => $before, 'after' => $after, 'amount' => $amount));
        oxen_wallet_response(200, 'Success', $after, array('action' => $type, 'transactionId' => $txId, 'balance_after' => round($after, 2)));
    } catch (Exception $e) {
        $conn->rollback();
        oxen_callback_log('error', array('error' => $e->getMessage(), 'type' => $type, 'player_id' => $playerId, 'raw_player_id' => $rawPlayerId, 'data' => $data));
        game_api_log($conn, array(
            'user_id' => $playerId,
            'local_game_uid' => $localGameUid,
            'api_game_id' => $apiGameId,
            'request_data' => game_api_mask_sensitive($data),
            'status' => 'callback_error',
            'message' => $e->getMessage()
        ));
        game_api_json_response(array('status' => 400, 'msg' => $e->getMessage()));
    }
}

function oxen_callback_tx_row($conn, $txId) {
    $stmt = $conn->prepare("SELECT balance_after FROM game_api_callback_transactions WHERE external_transaction_id=? LIMIT 1");
    if (!$stmt) { return null; }
    $stmt->bind_param('s', $txId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

function oxen_base_transaction_id($data, $playerId, $apiGameId, $roundId) {
    $base = oxen_pick($data, array('serial_number','SerialNumber','serialNumber','transaction_id','TransactionId','txn_id','TxnId','txid','TxId','id','mtcode','order_id','OrderId','game_round','GameRound','round_no','roundNo','round_id','RoundId','roundid','operation_id','operationId','spin_id','spinId'), '');
    if ($base !== '') { return preg_replace('/[^a-zA-Z0-9_\-:.]/', '_', (string)$base); }
    return sha1($playerId . '|' . $apiGameId . '|' . $roundId . '|' . json_encode($data));
}

function oxen_process_combined_bet_win($conn, $data) {
    $rawPlayerId = oxen_pick($data, array('PlayerId','player_id','playerId','userId','user_id','UserId','uid','member_id','memberId','full_user_id','member_account','memberAccount','account','account_no','accountNo','Account','username','userName','login','loginName','member','memberName','userAccount','player','playerName','player_name'), 0);
    $playerId = game_api_normalize_player_id($conn, $rawPlayerId);
    $bet = (float)oxen_pick($data, array('bet_amount','betAmount','BetAmount','bet','debit_amount','debitAmount','stake','stake_amount','bet_money','valid_bet','validBet','bet_money','betMoney'), 0);
    $win = (float)oxen_pick($data, array('win_amount','winAmount','WinAmount','win','credit_amount','creditAmount','win_money','payout','payout_amount','total_win','totalWin','prize','win_money','winMoney'), 0);
    $apiGameId = (string)oxen_pick($data, array('GameId','gameId','game_id','game_uid','GameUID','gameUID','gameCode','gamecode','GameCode','game_code','providerGameCode','provider_game_code','uidGameCode','code','Code','gamehall','gameHall','game_name','gameName','gamename','gameNameEn'), '');
    $roundId = (string)oxen_pick($data, array('RoundId','roundId','round_id','game_round','GameRound','roundid','roundID','round_no','roundNo','mtcode','serial_number','serialNumber'), '');
    $currency = (string)oxen_pick($data, array('currency_code','Currency','currency'), game_api_get_setting($conn, 'currency_code', 'BDT'));
    $localGameUid = game_api_local_uid_by_api_id($conn, $apiGameId);
    if ($localGameUid === '') { $localGameUid = $apiGameId; }
    if ($roundId === '') { $roundId = date('YmdHis') . '_' . substr(sha1(json_encode($data)), 0, 10); }

    if ($playerId <= 0) {
        oxen_callback_log('combined_invalid_player_id', array('raw_player_id' => $rawPlayerId, 'data' => $data));
        game_api_json_response(array('status' => 400, 'msg' => 'Invalid PlayerId'));
    }
    // Signed settlement support:
    // bet_amount > 0  = deduct stake; bet_amount < 0  = refund/reversal of stake.
    // win_amount > 0  = credit win;   win_amount < 0  = reverse/deduct prior win.
    $betSigned = $bet;
    $winSigned = $win;
    $betAbs = abs($betSigned);
    $winAbs = abs($winSigned);
    $netSignedAmount = round((0 - $betSigned) + $winSigned, 2);

    $baseTx = oxen_base_transaction_id($data, $playerId, $apiGameId, $roundId);
    $betTxId = ($betSigned >= 0 ? 'deductBalance_' : 'refundBet_') . $baseTx;
    $winTxId = ($winSigned >= 0 ? 'addToBalance_' : 'reverseWin_') . $baseTx;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id, username, balance FROM users WHERE id=? FOR UPDATE");
        if (!$stmt) { throw new Exception('User lookup failed'); }
        $stmt->bind_param('i', $playerId);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows === 0) { throw new Exception('User not found'); }
        $user = $res->fetch_assoc();
        $stmt->close();

        $openingBalance = round((float)$user['balance'], 2);
        $runningBalance = $openingBalance;
        $applied = array();
        $duplicates = array();

        if ($betSigned != 0) {
            $existingBet = oxen_callback_tx_row($conn, $betTxId);
            $betAction = $betSigned > 0 ? 'bet' : 'bet_refund';
            if ($existingBet) {
                $duplicates[] = $betAction;
            } else {
                $before = $runningBalance;
                if ($betSigned > 0) {
                    if ($betAbs > $runningBalance) { throw new Exception('Insufficient balance'); }
                    $runningBalance = round($runningBalance - $betAbs, 2);
                    $callbackType = 'deductBalance';
                    $historyType = 'bet';
                } else {
                    $runningBalance = round($runningBalance + $betAbs, 2);
                    $callbackType = 'refundBet';
                    $historyType = 'cancel';
                }
                if (!oxen_record_transaction($conn, $betTxId, $playerId, $callbackType, $localGameUid, $apiGameId, $roundId, $betAbs, $before, $runningBalance, $data)) {
                    throw new Exception('Duplicate or invalid bet transaction');
                }
                game_api_insert_bet_history($conn, array(
                    'user_id' => $playerId,
                    'username' => $user['username'],
                    'vendor_code' => 'OXEN_TECH',
                    'game_uid' => $localGameUid,
                    'round_id' => $roundId,
                    'transaction_id' => $betTxId,
                    'type' => $historyType,
                    'amount' => $betAbs,
                    'balance_after' => $runningBalance,
                    'currency' => $currency
                ));
                $applied[] = $betAction;
            }
        }

        if ($winSigned != 0) {
            $existingWin = oxen_callback_tx_row($conn, $winTxId);
            $winAction = $winSigned > 0 ? 'win' : 'win_reversal';
            if ($existingWin) {
                $duplicates[] = $winAction;
            } else {
                $before = $runningBalance;
                if ($winSigned > 0) {
                    $runningBalance = round($runningBalance + $winAbs, 2);
                    $callbackType = 'addToBalance';
                    $historyType = 'win';
                } else {
                    if ($winAbs > $runningBalance) { throw new Exception('Insufficient balance'); }
                    $runningBalance = round($runningBalance - $winAbs, 2);
                    $callbackType = 'reverseWin';
                    $historyType = 'cancel';
                }
                if (!oxen_record_transaction($conn, $winTxId, $playerId, $callbackType, $localGameUid, $apiGameId, $roundId, $winAbs, $before, $runningBalance, $data)) {
                    throw new Exception('Duplicate or invalid win transaction');
                }
                game_api_insert_bet_history($conn, array(
                    'user_id' => $playerId,
                    'username' => $user['username'],
                    'vendor_code' => 'OXEN_TECH',
                    'game_uid' => $localGameUid,
                    'round_id' => $roundId,
                    'transaction_id' => $winTxId,
                    'type' => $historyType,
                    'amount' => $winAbs,
                    'balance_after' => $runningBalance,
                    'currency' => $currency
                ));
                $applied[] = $winAction;
            }
        }

        if ($runningBalance !== $openingBalance || !empty($applied)) {
            $stmtUp = $conn->prepare("UPDATE users SET balance=? WHERE id=?");
            if (!$stmtUp) { throw new Exception('Balance update prepare failed'); }
            $stmtUp->bind_param('di', $runningBalance, $playerId);
            if (!$stmtUp->execute()) { throw new Exception('Balance update failed'); }
            $stmtUp->close();
        }

        $conn->commit();
        $response = array(
            'status' => 200,
            'balance' => round($runningBalance, 2),
            'bet_amount' => round($betSigned, 2),
            'win_amount' => round($winSigned, 2),
            'bet_abs' => round($betAbs, 2),
            'win_abs' => round($winAbs, 2),
            'net_amount' => $netSignedAmount,
            'applied' => $applied,
            'duplicates' => $duplicates
        );
        game_api_log($conn, array(
            'user_id' => $playerId,
            'local_game_uid' => $localGameUid,
            'api_game_id' => $apiGameId,
            'request_data' => game_api_mask_sensitive($data),
            'response_data' => $response,
            'status' => 'callback_settlement',
            'message' => 'Combined bet/win callback processed'
        ));
        oxen_callback_log('combined_processed', array(
            'player_id' => $playerId,
            'round_id' => $roundId,
            'bet_transaction_id' => $betTxId,
            'win_transaction_id' => $winTxId,
            'before' => $openingBalance,
            'after' => $runningBalance,
            'bet_amount' => $betSigned,
            'win_amount' => $winSigned,
            'bet_abs' => $betAbs,
            'win_abs' => $winAbs,
            'net_amount' => $netSignedAmount,
            'applied' => $applied,
            'duplicates' => $duplicates
        ));
        oxen_wallet_response(200, 'Success', $runningBalance, array(
            'action' => 'settlement',
            'bet_amount' => round($betSigned, 2),
            'win_amount' => round($winSigned, 2),
            'bet_abs' => round($betAbs, 2),
            'win_abs' => round($winAbs, 2),
            'net_amount' => $netSignedAmount,
            'applied' => $applied,
            'duplicates' => $duplicates,
            'transactionId' => $baseTx,
            'serial_number' => $baseTx,
            'game_round' => $roundId
        ));
    } catch (Exception $e) {
        $conn->rollback();
        oxen_callback_log('combined_error', array('error' => $e->getMessage(), 'player_id' => $playerId, 'raw_player_id' => $rawPlayerId, 'data' => $data));
        game_api_log($conn, array(
            'user_id' => $playerId,
            'local_game_uid' => $localGameUid,
            'api_game_id' => $apiGameId,
            'request_data' => game_api_mask_sensitive($data),
            'status' => 'callback_error',
            'message' => $e->getMessage()
        ));
        game_api_json_response(array('status' => 400, 'msg' => $e->getMessage()));
    }
}


$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);
$jsonError = json_last_error_msg();
if (!is_array($data) || empty($data)) { $data = $_POST; }
if (!is_array($data)) { $data = array(); }

oxen_callback_log('received', array(
    'raw_input' => $rawData,
    'json_error' => $jsonError,
    'post_data' => $_POST,
    'parsed_data' => $data,
    'headers' => function_exists('getallheaders') ? getallheaders() : array()
));

// Legacy encrypted callback support, if old provider still posts encrypted_payload.
if (isset($data['encrypted_payload']) && $data['encrypted_payload'] !== '' && class_exists('Encryption')) {
    $secret = game_api_get_setting($conn, 'secret_key', '');
    try {
        $decrypted = Encryption::decryptPayloadECB($data['encrypted_payload'], $secret);
        if (is_array($decrypted)) { $data = $decrypted; }
    } catch (Exception $e) {
        oxen_callback_log('encrypted_payload_decrypt_failed', array('error' => $e->getMessage()));
        game_api_json_response(array('status' => 400, 'msg' => 'Invalid encrypted payload'));
    }
}

$type = (string)oxen_pick($data, array('Type','type','action','Action','event','Event','method','Method'), '');
$typeLower = strtolower(trim($type));
if (in_array($typeLower, array('getbalance','get_balance','balance','querybalance','balancequery','getaccount','accountbalance'), true)) { $type = 'getBalance'; }
elseif (in_array($typeLower, array('deductbalance','deduct_balance','deduct','debit','bet','placebet','place_bet','betting','withdraw','withdrawal'), true)) { $type = 'deductBalance'; }
elseif (in_array($typeLower, array('addtobalance','add_balance','credit','win','settle','settlement','payout','result','betresult','deposit','cashout'), true)) { $type = 'addToBalance'; }
$rawPlayerId = oxen_pick($data, array('PlayerId','player_id','playerId','userId','user_id','UserId','uid','member_id','memberId','full_user_id','member_account','memberAccount','account','account_no','accountNo','Account','username','userName','login','loginName','member','memberName','userAccount','player','playerName','player_name'), 0);
$playerId = game_api_normalize_player_id($conn, $rawPlayerId);

// OXEN/JILI combined settlement callback support.
// Provider sends bet_amount and win_amount together for one round. Process both atomically.
if ((isset($data['bet_amount']) || isset($data['betAmount']) || isset($data['BetAmount']) || isset($data['bet']) || isset($data['debit_amount']) || isset($data['debitAmount']) || isset($data['stake']) || isset($data['stake_amount']) || isset($data['bet_money'])) &&
    (isset($data['win_amount']) || isset($data['winAmount']) || isset($data['WinAmount']) || isset($data['win']) || isset($data['credit_amount']) || isset($data['creditAmount']) || isset($data['win_money']) || isset($data['payout']) || isset($data['payout_amount']) || isset($data['prize']))) {
    oxen_process_combined_bet_win($conn, $data);
}

if ($type === 'getBalance') {
    if ($playerId <= 0) {
        oxen_callback_log('get_balance_invalid_player', array('raw_player_id' => $rawPlayerId, 'data' => $data));
        game_api_json_response(array('status' => 400, 'msg' => 'Invalid PlayerId'));
    }
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id=? LIMIT 1");
    if (!$stmt) {
        oxen_callback_log('get_balance_db_prepare_failed', array('player_id' => $playerId));
        game_api_json_response(array('status' => 400, 'msg' => 'Database error'));
    }
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) {
        $stmt->close();
        oxen_callback_log('get_balance_user_not_found', array('player_id' => $playerId, 'raw_player_id' => $rawPlayerId));
        game_api_json_response(array('status' => 400, 'msg' => 'User not found'));
    }
    $balance = (float)$res->fetch_assoc()['balance'];
    $stmt->close();
    $response = array('status' => 200, 'balance' => round($balance, 2));
    oxen_callback_log('get_balance_success', array('player_id' => $playerId, 'balance' => $balance));
    oxen_wallet_response(200, 'Success', round($balance, 2), array('action' => 'getBalance'));
}

if ($type === 'deductBalance' || $type === 'addToBalance') {
    oxen_adjust_balance($conn, $data, $type);
}

if ($playerId > 0) {
    $currentBalance = oxen_current_user_balance($conn, $playerId);
    if ($currentBalance !== null) {
        oxen_callback_log('balance_refresh_success', array('player_id' => $playerId, 'type' => $type, 'balance' => $currentBalance, 'data' => $data));
        oxen_wallet_response(200, 'Success', round($currentBalance, 2), array('action' => 'balance_refresh'));
    }
}

oxen_callback_log('unknown_type', array('type' => $type, 'data' => $data));
game_api_json_response(array('status' => 400, 'msg' => 'Unknown callback Type'));
?>
