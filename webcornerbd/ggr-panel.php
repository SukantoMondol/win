<?php
// ১. ইরর রিপোর্ট অন করা
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start();
require '../includes/auth_session.php';

/**
 * ১. ডাটাবেস কানেকশন (সরাসরি মেইন ফাইল রিকোয়ার করা হয়েছে)
 */
require '../includes/db.php'; 

$REMOTE_API_URL = "https://masterggr.sha75.com/api/api.php"; 
$my_domain = $_SERVER['HTTP_HOST'];

/**
 * ডিবাগিং লগ ফাংশন
 */
define('GGR_LOG', __DIR__ . '/ggr_debug_log.txt');
function write_ggr_log($msg, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $msg" . ($data ? " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE) : "") . PHP_EOL;
    file_put_contents(GGR_LOG, $log_entry, FILE_APPEND);
}


/**
 * DB compatibility fix:
 * Older database backups may not have the is_synced column in game_bet_history.
 * Without this column the GGR panel throws: Unknown column 'is_synced' in 'WHERE'.
 */
function ggr_safe_identifier(string $name): string {
    return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
}

function ggr_column_exists(mysqli $conn, string $table, string $column): bool {
    try {
        $table = $conn->real_escape_string(ggr_safe_identifier($table));
        $column = $conn->real_escape_string(ggr_safe_identifier($column));
        $dbResult = $conn->query("SELECT DATABASE() AS db_name");
        $dbRow = $dbResult ? $dbResult->fetch_assoc() : null;
        $dbName = $conn->real_escape_string((string)($dbRow['db_name'] ?? ''));

        if ($dbName === '') {
            return false;
        }

        $sql = "SELECT COUNT(*) AS total
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = '$dbName'
                  AND TABLE_NAME = '$table'
                  AND COLUMN_NAME = '$column'";
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        return ((int)($row['total'] ?? 0)) > 0;
    } catch (Throwable $e) {
        write_ggr_log('Column check failed', $e->getMessage());
        return false;
    }
}

function ggr_ensure_column(mysqli $conn, string $table, string $column, string $definition): bool {
    $tableSafe = ggr_safe_identifier($table);
    $columnSafe = ggr_safe_identifier($column);

    if ($tableSafe === '' || $columnSafe === '') {
        return false;
    }

    if (ggr_column_exists($conn, $tableSafe, $columnSafe)) {
        return true;
    }

    try {
        $conn->query("ALTER TABLE `$tableSafe` ADD COLUMN `$columnSafe` $definition");
        write_ggr_log("Missing DB column created", [
            'table' => $tableSafe,
            'column' => $columnSafe,
            'definition' => $definition,
        ]);
        return true;
    } catch (Throwable $e) {
        // If another request created it at the same time, continue safely.
        if (stripos($e->getMessage(), 'Duplicate column') !== false) {
            return true;
        }
        write_ggr_log("Unable to create missing DB column", [
            'table' => $tableSafe,
            'column' => $columnSafe,
            'error' => $e->getMessage(),
        ]);
        return false;
    }
}

$ggr_has_is_synced_column = ggr_ensure_column($conn, 'game_bet_history', 'is_synced', 'TINYINT(1) NOT NULL DEFAULT 0');

// =================================================================
// 1. AUTO SYNC: PAYMENTS & GAME LOGS
// =================================================================
$pending_sql = "SELECT transaction_id FROM payment_requests WHERE type='ggr_payment' AND status='pending'";
$pending_query = $conn->query($pending_sql);
$refresh_needed = false;

if ($pending_query && $pending_query->num_rows > 0) {
    $pending_hashes = [];
    while($row = $pending_query->fetch_assoc()) { $pending_hashes[] = $row['transaction_id']; }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $REMOTE_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['action' => 'check_payment_status', 'hashes' => json_encode($pending_hashes), 'domain' => $my_domain]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $sync_res = curl_exec($ch);
    curl_close($ch);
    $sync_data = json_decode($sync_res, true);
    if (isset($sync_data['updates'])) {
        foreach ($sync_data['updates'] as $tx_id => $new_status) {
            if ($new_status === 'approved' || $new_status === 'rejected') {
                $tx_id = $conn->real_escape_string($tx_id);
                $new_status = $conn->real_escape_string($new_status);
                $conn->query("UPDATE payment_requests SET status='$new_status' WHERE transaction_id='$tx_id'");
                $refresh_needed = true;
            }
        }
    }
}

// 1. batch sync game logs
$unsynced_query = false;
if ($ggr_has_is_synced_column) {
    try {
        $unsynced_query = $conn->query("SELECT * FROM game_bet_history WHERE is_synced = 0 LIMIT 100");
    } catch (Throwable $e) {
        write_ggr_log('Game log sync query failed', $e->getMessage());
    }
} else {
    write_ggr_log('Game log sync skipped', 'Missing is_synced column and automatic ALTER failed. Run sql/add_is_synced_to_game_bet_history.sql manually from phpMyAdmin.');
}
if ($unsynced_query && $unsynced_query->num_rows > 0) {
    $batch_logs = []; $log_ids = [];
    while($row = $unsynced_query->fetch_assoc()) { $batch_logs[] = $row; $log_ids[] = (int)$row['id']; }
    $ch_logs = curl_init();
    curl_setopt($ch_logs, CURLOPT_URL, $REMOTE_API_URL);
    curl_setopt($ch_logs, CURLOPT_POST, 1);
    curl_setopt($ch_logs, CURLOPT_POSTFIELDS, http_build_query(['action' => 'sync_game_logs', 'domain' => $my_domain, 'logs' => json_encode($batch_logs)]));
    curl_setopt($ch_logs, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_logs, CURLOPT_SSL_VERIFYPEER, false);
    $log_res = curl_exec($ch_logs);
    curl_close($ch_logs);
    if (isset(json_decode($log_res, true)['status']) && json_decode($log_res, true)['status'] === 'success') {
        if (!empty($log_ids)) { $conn->query("UPDATE game_bet_history SET is_synced = 1 WHERE id IN (".implode(',', $log_ids).")"); }
        $refresh_needed = true; 
    }
}

// =================================================================
// 1.2 HANDLE NEW DEPOSIT REQUEST (Updated to match Master API)
// =================================================================
$submit_msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_payment'])) {
    $amount = floatval($_POST['amount']);
    $hash = mysqli_real_escape_string($conn, trim($_POST['hash']));

    // মাস্টার সার্ভারের api.php এর সাথে মিল রেখে কী (Key) নাম পরিবর্তন করা হয়েছে
    $post_data = [
        'action' => 'submit_payment', // Changed from submit_deposit
        'amount' => $amount, 
        'hash'   => $hash,           // Changed from transaction_hash
        'domain' => $my_domain
    ];

    write_ggr_log("Deposit Submission Attempt", $post_data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $REMOTE_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response_raw = curl_exec($ch);
    $response = json_decode($response_raw, true);
    curl_close($ch);

    write_ggr_log("Deposit Submission Raw Response", $response_raw);

    if (isset($response['status']) && $response['status'] === 'success') {
        $ins_sql = "INSERT INTO payment_requests (amount, transaction_id, status, type, created_at) 
                    VALUES ('$amount', '$hash', 'pending', 'ggr_payment', NOW())";
        if ($conn->query($ins_sql)) {
            $submit_msg = "success";
            $refresh_needed = true;
        } else {
            write_ggr_log("Local DB Insert Error", $conn->error);
        }
    } else {
        $submit_msg = "error: " . ($response['msg'] ?? 'Master server response invalid.');
        write_ggr_log("Submission Failed Message", $submit_msg);
    }
}

if ($refresh_needed) { header("Location: " . $_SERVER['PHP_SELF'] . ($submit_msg ? "?msg=$submit_msg" : "")); exit(); }

// =================================================================
// 2. FETCH CONFIGURATION FROM MASTER SERVER
// =================================================================
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $REMOTE_API_URL);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['action' => 'get_config', 'domain' => $my_domain]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$raw_config = curl_exec($ch);
$remote = json_decode($raw_config, true);
curl_close($ch);

write_ggr_log("Config Fetch Result", $remote);

if (!isset($remote['status']) || $remote['status'] !== 'success') {
    die('<body style="background:#000;color:red;text-align:center;padding-top:100px;"><h1>Domain Not Authorized</h1></body>');
}

$master_balance = floatval($remote['account_balance'] ?? 0); 
if($master_balance < 0) { $master_balance = 0; } 

$master_ggr      = $remote['target_ggr_percent'] ?? 10;
$master_address = $remote['deposit_address'] ?? 'N/A';
$master_network = $remote['network_name'] ?? 'TRC20';

/**
 * লগ অনুযায়ী মাস্টার সার্ভার থেকে আসা কী-এর নাম 'min_deposit'
 */
$master_min_dep = isset($remote['min_deposit']) ? floatval($remote['min_deposit']) : 10.00; 

$master_games   = $remote['active_games'] ?? [];
$master_notice  = $remote['notice'] ?? '';
$whitelisted_domain = $remote['allowed_domain'] ?? $my_domain;

// =================================================================
// 3. STATS CALCULATION
// =================================================================
$filter = $_GET['filter'] ?? 'lifetime';
$date_query = "";
if ($filter == 'month') {
    $start = date('Y-m-01 00:00:00'); $end = date('Y-m-t 23:59:59');
    $date_query = " WHERE created_at BETWEEN '$start' AND '$end'";
}

$stats_q = $conn->query("SELECT SUM(bet_amount) as t_bet, SUM(win_amount) as t_win FROM game_bet_history $date_query");
$stats = $stats_q ? $stats_q->fetch_assoc() : ['t_bet' => 0, 't_win' => 0];
$total_bet = $stats['t_bet'] ?? 0;
$total_win = $stats['t_win'] ?? 0;

// =================================================================
// 4. PAGINATION SETUP
// =================================================================
$limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_rows_q = $conn->query("SELECT COUNT(*) as count FROM game_bet_history");
$total_rows = $total_rows_q ? $total_rows_q->fetch_assoc()['count'] : 0;
$total_pages = ceil($total_rows / $limit);

$history_sql = "SELECT h.*, u.username as user_real_name, g.name as game_real_name 
                FROM game_bet_history h 
                LEFT JOIN users u ON h.user_id = u.id 
                LEFT JOIN games g ON h.game_uid = g.game_uid 
                ORDER BY h.created_at DESC LIMIT $offset, $limit";
$history_res = $conn->query($history_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GGR Management Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Exo 2', sans-serif; background-color: #050505; color: #e4e4e7; background-image: radial-gradient(circle at top, #1a1a1a 0%, #000000 100%); }
        .font-num { font-family: 'Rajdhani', sans-serif; }
        .card-glass { background: rgba(20, 20, 20, 0.6); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37); }
        .btn-gradient { background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); color: black; font-weight: 700; transition: all 0.3s; }
        .btn-gradient:hover { filter: brightness(1.1); transform: scale(1.02); box-shadow: 0 0 15px rgba(251, 191, 36, 0.4); }
        .pagination-btn { padding: 8px 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; transition: 0.3s; color: #999; }
        .pagination-btn.active { background: #fbbf24; color: black; border-color: #fbbf24; }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }
        .animate-enter { animation: slideUp 0.6s ease-out forwards; opacity: 0; transform: translateY(20px); }
    </style>
</head>
<body class="min-h-screen pb-20">

    <nav class="sticky top-0 z-50 card-glass border-b border-white/5">
        <div class="container mx-auto px-4 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="bg-white/5 p-2 rounded-full text-white transition border border-white/5"><i class="fas fa-arrow-left"></i></a>
                <div>
                    <h1 class="text-lg md:text-2xl font-bold tracking-widest text-white">GGR <span class="text-yellow-500">CONTROL</span></h1>
                </div>
            </div>
            <div class="hidden sm:flex items-center gap-3 bg-black/40 px-4 py-2 rounded-full border border-green-500/20">
                <span class="relative flex h-3 w-3"><span class="animate-ping absolute h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative h-3 w-3 bg-green-500 rounded-full"></span></span>
                <span class="text-xs font-bold text-white font-mono"><?php echo $whitelisted_domain; ?></span>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-6 md:py-10">
        
        <!-- সাকসেস বা এরর নোটিফিকেশন -->
        <?php if(isset($_GET['msg'])): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo strpos($_GET['msg'], 'success') !== false ? 'bg-green-500/10 border border-green-500/30 text-green-500' : 'bg-red-500/10 border border-red-500/30 text-red-500'; ?> animate-enter">
                <i class="fas <?php echo strpos($_GET['msg'], 'success') !== false ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-2"></i>
                <?php echo strpos($_GET['msg'], 'success') !== false ? 'Your Payment Request is submitted, please wait 5-15 min to cretited' : 'পেমেন্ট রিকোয়েস্ট সাবমিট করতে সমস্যা হয়েছে: ' . htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-col md:flex-row justify-between items-center mb-10 gap-4 animate-enter">
            <h2 class="text-2xl md:text-3xl font-bold text-white text-center md:text-left"><span class="text-gray-600">Dashboard /</span> Overview</h2>
            <div class="bg-[#151515] p-1 rounded-xl border border-white/5 flex gap-1 w-full md:w-auto">
                <a href="?filter=lifetime" class="flex-1 text-center px-4 md:px-6 py-2 text-[10px] md:text-xs font-bold rounded-lg transition-all <?php echo ($filter == 'lifetime') ? 'bg-zinc-800 text-white shadow-md' : 'text-zinc-500'; ?>">LIFETIME</a>
                <a href="?filter=month" class="flex-1 text-center px-4 md:px-6 py-2 text-[10px] md:text-xs font-bold rounded-lg transition-all <?php echo ($filter == 'month') ? 'bg-zinc-800 text-white shadow-md' : 'text-zinc-500'; ?>">MONTHLY</a>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-10">
            <div class="card-glass rounded-2xl p-6 relative animate-enter">
                <p class="text-gray-500 text-xs font-bold uppercase mb-2">Commission Rate</p>
                <div class="text-4xl font-num font-bold text-white"><?php echo $master_ggr; ?><span class="text-xl text-yellow-500">%</span></div>
            </div>
            
            <div class="card-glass rounded-2xl p-6 relative border-yellow-500/40 animate-enter">
                <p class="text-yellow-500 text-xs font-bold uppercase mb-2">Prepaid Balance</p>
                <div class="text-4xl font-num font-bold text-white">$<?php echo number_format($master_balance, 2); ?></div>
            </div>

            <div class="card-glass rounded-2xl p-6 relative animate-enter">
                <p class="text-gray-500 text-xs font-bold uppercase mb-2">Total Bet Volume</p>
                <div class="text-4xl font-num font-bold text-blue-400">৳<?php echo number_format($total_bet); ?></div>
            </div>

            <div class="card-glass rounded-2xl p-6 relative animate-enter">
                <p class="text-gray-500 text-xs font-bold uppercase mb-2">Total Win Payout</p>
                <div class="text-4xl font-num font-bold text-orange-400">৳<?php echo number_format($total_win); ?></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div class="lg:col-span-8 space-y-8 animate-enter">
                
                <div class="card-glass rounded-2xl overflow-hidden">
                    <div class="bg-gradient-to-r from-yellow-900/30 to-transparent p-5 border-b border-white/5 flex flex-col md:flex-row justify-between items-center gap-4 text-center md:text-left">
                        <h3 class="text-lg font-bold text-white uppercase tracking-wide">Add Funds</h3>
                        <span class="bg-black/50 text-gray-300 text-[10px] font-bold px-4 py-2 rounded-lg border border-white/10 font-mono italic">USDT (<?php echo $master_network; ?>)</span>
                    </div>

                    <div class="p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
                        <div class="flex flex-col items-center">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo $master_address; ?>" class="rounded-lg mb-2 w-32 md:w-full">
                            <span class="text-[9px] text-gray-400 uppercase font-black tracking-widest">Scan QR Code</span>
                        </div>

                        <div class="md:col-span-2 space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-bold text-gray-500 uppercase block tracking-tighter">Wallet Address</label>
                                <div class="flex gap-2">
                                    <code class="block flex-1 bg-black/50 border border-white/10 rounded-lg px-4 py-3 text-xs md:text-sm font-mono text-gray-300 truncate" id="walletAddr"><?php echo htmlspecialchars($master_address); ?></code>
                                    <button onclick="copyAddr()" class="bg-white/5 hover:bg-white/10 px-4 rounded-lg text-white border border-white/10 transition active:scale-95"><i class="fas fa-copy"></i></button>
                                </div>
                            </div>

                            <form method="POST" class="space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-center px-1">
                                            <label class="text-[10px] font-bold text-gray-500 uppercase">Amount ($)</label>
                                            <span class="text-[9px] font-bold text-yellow-500 uppercase">Min Limit: $<?php echo number_format($master_min_dep, 2); ?></span>
                                        </div>
                                        <input type="number" name="amount" step="0.01" min="<?php echo $master_min_dep; ?>" placeholder="Min $<?php echo $master_min_dep; ?>" required class="w-full bg-[#0a0a0a] border border-white/10 rounded-xl py-3 px-4 text-white focus:border-yellow-500 outline-none font-mono transition-all">
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-gray-500 uppercase px-1">TxID Hash</label>
                                        <input type="text" name="hash" placeholder="Enter Transaction Hash" required class="w-full bg-[#0a0a0a] border border-white/10 rounded-xl py-3 px-4 text-white focus:border-yellow-500 outline-none transition-all">
                                    </div>
                                </div>
                                <button type="submit" name="submit_payment" class="w-full btn-gradient py-4 rounded-xl shadow-xl uppercase text-xs tracking-widest transition transform hover:-translate-y-1">Submit Request</button>
                            </form>
                            <p class="text-[10px] text-gray-600 text-center uppercase tracking-widest font-black italic">Check ggr_debug_log.txt for api status</p>
                        </div>
                    </div>
                </div>

                <div class="card-glass rounded-2xl overflow-hidden">
                    <div class="p-5 border-b border-white/5 bg-white/5 flex justify-between items-center">
                        <h3 class="font-bold text-white text-xs md:text-sm uppercase tracking-wide"><i class="fas fa-list mr-2 text-blue-500"></i> Recent Gameplay</h3>
                        <span class="text-[9px] font-black bg-blue-500/10 text-blue-400 px-2 py-1 rounded-full border border-blue-500/20">LIVE FEED</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-[11px] md:text-sm text-gray-400 whitespace-nowrap">
                            <thead class="bg-black/30 uppercase font-black text-gray-500">
                                <tr>
                                    <th class="p-4">Time</th>
                                    <th class="p-4">User</th>
                                    <th class="p-4">Game</th>
                                    <th class="p-4 text-right">Bet</th>
                                    <th class="p-4 text-right">Win</th>
                                    <th class="p-4 text-center">Result</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php if($history_res && $history_res->num_rows > 0): 
                                    while($row = $history_res->fetch_assoc()):
                                        $is_win = $row['win_amount'] > $row['bet_amount'];
                                ?>
                                <tr class="hover:bg-white/5 transition">
                                    <td class="p-4 text-[9px] font-mono text-gray-500"><?php echo date('d M, H:i', strtotime($row['created_at'])); ?></td>
                                    <td class="p-4 font-bold text-white"><?php echo htmlspecialchars($row['user_real_name'] ?? 'User #'.$row['user_id']); ?></td>
                                    <td class="p-4 text-blue-400"><?php echo htmlspecialchars($row['game_real_name'] ?? 'UID: '.$row['game_uid']); ?></td>
                                    <td class="p-4 text-right font-num">৳<?php echo number_format($row['bet_amount']); ?></td>
                                    <td class="p-4 text-right font-num <?php echo $is_win ? 'text-yellow-500' : 'text-gray-600'; ?>">৳<?php echo number_format($row['win_amount']); ?></td>
                                    <td class="p-4 text-center">
                                        <span class="text-[8px] md:text-[10px] font-black px-2 py-0.5 rounded-full border <?php echo $is_win ? 'bg-green-500/10 text-green-400 border-green-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20'; ?>">
                                            <?php echo $is_win ? 'WIN' : 'LOSS'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="6" class="p-10 text-center text-gray-600">No activity records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if($total_pages > 1): ?>
                <div class="flex flex-wrap justify-center items-center gap-2 mt-6">
                    <?php if($page > 1): ?>
                        <a href="?page=<?php echo ($page-1); ?>&filter=<?php echo $filter; ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php 
                    $start_p = max(1, $page - 1); $end_p = min($total_pages, $page + 1);
                    for($i = $start_p; $i <= $end_p; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>" class="pagination-btn <?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page+1); ?>&filter=<?php echo $filter; ?>" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-4 animate-enter">
                <div class="card-glass rounded-2xl overflow-hidden h-full sticky top-24">
                    <div class="p-5 border-b border-white/5 bg-gradient-to-r from-green-900/20 to-transparent">
                        <h3 class="font-bold text-white text-xs md:text-sm uppercase tracking-wide"><i class="fas fa-gamepad mr-2 text-green-500"></i> Active Games</h3>
                    </div>
                    <div class="p-4 space-y-3 max-h-[500px] overflow-y-auto">
                        <?php if(!empty($master_games)): foreach($master_games as $game): ?>
                        <div class="flex items-center justify-between p-4 rounded-xl bg-white/5 border border-white/5 hover:border-yellow-500/50 transition">
                            <span class="text-xs font-bold text-gray-300"><?php echo htmlspecialchars($game); ?></span>
                            <div class="h-2 w-2 rounded-full bg-green-500 animate-pulse shadow-[0_0_8px_#22c55e]"></div>
                        </div>
                        <?php endforeach; else: ?>
                        <div class="py-10 text-center opacity-20"><i class="fas fa-gamepad text-5xl mb-3"></i><p class="text-[10px] font-black uppercase">No Active Games</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyAddr() {
            var copyText = document.getElementById("walletAddr").innerText.trim();
            navigator.clipboard.writeText(copyText).then(() => {
                const btn = document.querySelector('button[onclick="copyAddr()"]');
                btn.innerHTML = '<i class="fas fa-check text-green-500"></i>';
                setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
            });
        }
    </script>
</body>
</html>