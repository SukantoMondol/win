<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/propay_gateway_helper.php';
propay_ensure_schema($conn);

// Default to ID provided or fallback
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id === 0) die("Invalid User ID");

// ---------------------------------------------------------
// 1. HANDLE POST & GET ACTIONS
// ---------------------------------------------------------
$msg = "";
$msg_type = "";

// --- GET ACTIONS (Risk Scan, Ban, etc.) ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action == 'deep_scan') {
        $new_score = calculateRiskScore($conn, $user_id);
        $conn->query("UPDATE player_profiles SET last_scan_at = NOW() WHERE user_id = $user_id");
        $msg = "Deep Scan Complete. Risk Score: $new_score%";
        $msg_type = "success";
    }
    elseif ($action == 'force_logout') {
        $conn->query("UPDATE users SET session_token = NULL, force_logout = 1 WHERE id = $user_id");
        $msg = "User forced out."; $msg_type = "success";
    }
    elseif ($action == 'toggle_withdraw') {
        // [FIXED] Withdraw Lock Database Update Logic
        $current = isset($_GET['current']) ? intval($_GET['current']) : 0;
        $new_lock = ($current == 1) ? 0 : 1;
        
        $upd = $conn->query("UPDATE player_profiles SET is_withdraw_locked=$new_lock WHERE user_id=$user_id");
        
        if($upd){
            $msg = "Withdraw lock updated successfully."; 
            $msg_type = "warning";
            // রিফ্রেশ যাতে লেটেস্ট ডাটা দেখায়
            header("Location: user_profile.php?id=$user_id&msg=lock_updated");
            exit();
        } else {
            error_log('User profile lock update failed: ' . $conn->error);
            $msg = "Unable to update the profile right now.";
            $msg_type = "error";
        }
    }
}

// মেসেজ হ্যান্ডলিং (রিডাইরেক্ট এর পর)
if (isset($_GET['msg']) && $_GET['msg'] == 'lock_updated') {
    $msg = "Withdraw lock status updated successfully.";
    $msg_type = "warning";
}

// --- POST ACTIONS (Balance & Bonus) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_id = $_SESSION['admin_id'] ?? 0;

    // A. Real Balance Manager (Admin Adjustment)
    if (isset($_POST['update_real_balance'])) {
        $amount = floatval($_POST['amount']);
        $type = $_POST['operation']; 
        
        if ($amount > 0) {
            if ($type == 'add') {
                // [MODIFIED] 1:1 Wager Logic Added
                // ব্যালেন্স বাড়ার সাথে সাথে turnover_target ও বাড়বে (1x)
                $conn->query("UPDATE users SET balance = balance + $amount, turnover_target = GREATEST(COALESCE(turnover_target,0), COALESCE(turnover_completed,0)) + $amount WHERE id = $user_id");
                
                $conn->query("INSERT INTO transactions_fake (user_id, type, amount, method, status, agent_id, created_at) VALUES ($user_id, 'deposit', $amount, 'Manual Adjustment (Admin) + 1x Wager', 'approved', $admin_id, NOW())");
                $msg = "Added ৳$amount to Real Balance with 1x Wager.";
            } else {
                // টাকা কাটার সময় ওয়েজার কমানোর দরকার নেই, শুধু ব্যালেন্স কাটবে
                $conn->query("UPDATE users SET balance = GREATEST(0, balance - $amount) WHERE id = $user_id");
                
                $conn->query("INSERT INTO transactions_fake (user_id, type, amount, method, status, agent_id, created_at) VALUES ($user_id, 'withdraw', $amount, 'Manual Deduction (Admin)', 'approved', $admin_id, NOW())");
                $msg = "Removed ৳$amount from Real Balance.";
            }
            $msg_type = "success";
        }
    }

    // B. Manual Bonus (Promotion)
    if (isset($_POST['give_bonus'])) {
        $amount = floatval($_POST['bonus_amount']);
        $reason = $conn->real_escape_string($_POST['reason']);
        
        // Add directly to Real Balance as requested
        $update_main = "UPDATE users SET balance = balance + $amount WHERE id = $user_id";
        
        if($conn->query($update_main)) {
            // Track in profile stats
            $conn->query("UPDATE player_profiles SET total_bonus_claimed = total_bonus_claimed + $amount WHERE user_id = $user_id");
            // Log as 'bonus' transaction
            $sql_log = "INSERT INTO transactions_fake (user_id, type, amount, method, status, created_at) VALUES ($user_id, 'bonus', $amount, '$reason', 'approved', NOW())";
            $conn->query($sql_log);
            
            $msg = "Bonus of ৳$amount sent successfully!";
            $msg_type = "success";
        } else {
            error_log('User profile balance update failed: ' . $conn->error);
            $msg = "Unable to complete the update right now.";
            $msg_type = "error";
        }
    }

    // C. Manual Turnover Control
    if (isset($_POST['update_turnover'])) {
        $turnover_amount = max(0, floatval($_POST['turnover_amount'] ?? 0));
        $turnover_operation = $_POST['turnover_operation'] ?? 'add';
        if ($turnover_operation === 'add') {
            $ok = $conn->query("UPDATE users SET turnover_target = GREATEST(COALESCE(turnover_target,0), COALESCE(turnover_completed,0)) + $turnover_amount WHERE id = $user_id");
            $msg = "Turnover requirement added: ৳" . number_format($turnover_amount, 2);
        } elseif ($turnover_operation === 'set') {
            $ok = $conn->query("UPDATE users SET turnover_target = COALESCE(turnover_completed,0) + $turnover_amount WHERE id = $user_id");
            $msg = "Turnover requirement set to remaining ৳" . number_format($turnover_amount, 2);
        } elseif ($turnover_operation === 'remove') {
            $ok = $conn->query("UPDATE users SET turnover_target = GREATEST(COALESCE(turnover_completed,0), COALESCE(turnover_target,0) - $turnover_amount) WHERE id = $user_id");
            $msg = "Turnover requirement removed: ৳" . number_format($turnover_amount, 2);
        } else {
            $ok = $conn->query("UPDATE users SET turnover_target = COALESCE(turnover_completed,0) WHERE id = $user_id");
            $msg = "Turnover requirement has been cleared to zero remaining.";
        }
        $msg_type = !empty($ok) ? "success" : "error";
        if (empty($ok)) { error_log('Turnover update failed: ' . $conn->error); $msg = "Unable to update turnover right now."; }
    }
}

// ---------------------------------------------------------
// 2. FETCH USER DATA (Updated SQL for Agent Info)
// ---------------------------------------------------------
// [FIXED] Added phone, agent_id selection and Agent Name Subquery
$sql = "SELECT u.id, u.username, u.email, u.phone, u.role, u.status, u.balance, u.turnover_target, u.turnover_completed, u.created_at, u.agent_id,
               p.kyc_status, p.kyc_front, p.kyc_back, p.risk_score, p.last_ip, p.last_device, 
               p.total_bonus_claimed, p.is_withdraw_locked, p.is_bonus_banned,
               s.risk_auto_lock_percent,
               (SELECT username FROM users WHERE id = (SELECT user_id FROM agents WHERE id = u.agent_id LIMIT 1)) as agent_name
        FROM users u 
        LEFT JOIN player_profiles p ON u.id = p.user_id 
        CROSS JOIN settings s WHERE s.id = 1 AND u.id = $user_id";

$result = $conn->query($sql);
if ($result->num_rows == 0) die("User not found");
$user = $result->fetch_assoc();
$profile_email = wcb_public_email($user['email'] ?? '');

$real_balance = floatval($user['balance']); 
$turnover_target = floatval($user['turnover_target'] ?? 0);
$turnover_completed = floatval($user['turnover_completed'] ?? 0);
$turnover_remaining = max(0, $turnover_target - $turnover_completed);

// ---------------------------------------------------------
// 3. LOGIC: PLAYER CATEGORY (Bonus Hunter)
// ---------------------------------------------------------
$dep_count_q = $conn->query("SELECT COUNT(*) FROM transactions_fake WHERE user_id=$user_id AND type='deposit' AND status='approved' AND method NOT LIKE '%Manual%'");
$deposit_count = $dep_count_q->fetch_row()[0] ?? 0;

$player_category = "Regular";
$cat_color = "bg-blue-100 text-blue-700 border-blue-200";

if ($deposit_count == 0 && $real_balance > 0) {
    $player_category = "Bonus Hunter";
    $cat_color = "bg-purple-100 text-purple-700 border-purple-200 animate-pulse";
} elseif ($deposit_count > 50) { 
    $player_category = "VIP";
    $cat_color = "bg-yellow-100 text-yellow-700 border-yellow-200";
} elseif ($deposit_count > 10) {
    $player_category = "Monthly";
    $cat_color = "bg-green-100 text-green-700 border-green-200";
}

// ---------------------------------------------------------
// 4. FETCH HISTORY & STATS (Updated Bet History Query)
// ---------------------------------------------------------
$dep_q = $conn->query("SELECT SUM(amount) FROM transactions_fake WHERE user_id=$user_id AND type='deposit' AND status='approved'");
$total_deposit = $dep_q->fetch_row()[0] ?? 0;
$with_q = $conn->query("SELECT SUM(amount) FROM transactions_fake WHERE user_id=$user_id AND type='withdraw' AND status='approved'");
$total_withdraw = $with_q->fetch_row()[0] ?? 0;

// [FIXED] JOIN with games table to get Game Name
$bet_sql = "SELECT gbh.*, g.name as game_title 
            FROM game_bet_history gbh 
            LEFT JOIN games g ON gbh.game_uid = g.game_uid 
            WHERE gbh.user_id=$user_id 
            ORDER BY gbh.created_at DESC LIMIT 50";
$bet_history = $conn->query($bet_sql);

$trans_history = $conn->query("SELECT * FROM transactions_fake WHERE user_id=$user_id ORDER BY created_at DESC LIMIT 50");

// Safe Vars
$kyc_front = $user['kyc_front'] ?? null;
$kyc_back = $user['kyc_back'] ?? null;
$kyc_status = $user['kyc_status'] ?? 'not_submitted';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?> - Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-gray-50 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-6 min-h-screen pb-24 lg:pb-6 transition-all duration-300">
        
        <?php if($msg): ?>
            <div class="mb-4 p-4 rounded-lg flex items-center gap-3 shadow-sm text-sm <?php echo $msg_type == 'success' ? 'bg-green-100 text-green-800' : ($msg_type == 'warning' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                <i class="fas <?php echo $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span class="font-medium"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6 relative">
            <div class="h-28 bg-gradient-to-r from-slate-800 to-slate-900"></div>
            <div class="px-6 pb-6">
                <div class="flex flex-col lg:flex-row items-center lg:items-end -mt-10 gap-6">
                    
                    <div class="relative z-10">
                        <div class="w-24 h-24 rounded-2xl bg-blue-600 flex items-center justify-center text-white text-3xl font-bold border-4 border-white shadow-md">
                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-6 h-6 border-4 border-white rounded-full <?php echo ($user['status'] == 'active') ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                    </div>

                    <div class="flex-1 text-center lg:text-left pt-2" style="margin-top:32px;">
                        <div class="flex flex-col lg:flex-row items-center gap-3 mb-1">
                            <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($user['username']); ?></h1>
                            
                            <span class="px-2 py-1 rounded bg-gray-100 text-gray-600 text-xs font-mono font-bold border border-gray-300">
                                ID: #<?php echo $user['id']; ?>
                            </span>

                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border <?php echo $cat_color; ?>">
                                <?php echo $player_category; ?>
                            </span>
                            
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?php echo ($user['status'] == 'active') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo $user['status']; ?>
                            </span>
                        </div>

                        <div class="text-xl font-bold text-slate-700 my-1 font-mono tracking-wide">
                            <i class="fas fa-phone-alt text-sm text-slate-400 mr-1"></i>
                            <?php echo !empty($user['phone']) ? $user['phone'] : 'No Mobile'; ?>
                        </div>

                        <?php if($profile_email !== ''): ?>
                            <p class="text-sm text-gray-500"><i class="fas fa-envelope mr-1"></i> <?php echo htmlspecialchars($profile_email, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        
                        <?php if(!empty($user['agent_name'])): ?>
                            <p class="mt-2 text-xs font-bold text-orange-600 bg-orange-50 inline-block px-2 py-1 rounded border border-orange-200">
                                <i class="fas fa-user-secret mr-1"></i> Agent: <?php echo htmlspecialchars($user['agent_name']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-2">
                        <a href="?id=<?php echo $user_id; ?>&action=toggle_withdraw&current=<?php echo $user['is_withdraw_locked']; ?>" 
                           class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold border transition <?php echo $user['is_withdraw_locked'] ? 'border-red-500 text-red-600 hover:bg-red-50' : 'border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">
                            <i class="fas <?php echo $user['is_withdraw_locked'] ? 'fa-lock' : 'fa-unlock'; ?>"></i>
                            <?php echo $user['is_withdraw_locked'] ? 'Withdraw Locked' : 'Lock Withdraw'; ?>
                        </a>
                        <a href="?id=<?php echo $user_id; ?>&action=force_logout" onclick="return confirm('Logout this user?')" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-500 hover:bg-red-50 hover:text-red-600 font-bold text-sm transition">
                            <i class="fas fa-power-off"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <div class="lg:col-span-4 space-y-6">
                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2"><i class="fas fa-shield-halved text-blue-600"></i> Risk Analysis</h3>
                        <a href="?id=<?php echo $user_id; ?>&action=deep_scan" class="text-xs font-bold text-indigo-600 bg-indigo-50 px-3 py-1 rounded hover:bg-indigo-100 transition">Scan Now</a>
                    </div>
                    <div class="flex justify-center">
                        <div class="w-40 h-40 relative">
                            <canvas id="riskGauge"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-3xl font-bold text-gray-800"><?php echo $user['risk_score']; ?></span>
                                <span class="text-[10px] text-gray-400 uppercase font-bold tracking-widest">Score</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <p class="text-xs text-gray-500">Auto-lock triggers at <?php echo $user['risk_auto_lock_percent']; ?>%</p>
                    </div>
                </div>

            </div>

            <div class="lg:col-span-8 space-y-6">

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="bg-blue-600 text-white p-6 rounded-2xl shadow-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-blue-200 text-xs font-bold uppercase mb-1">Real Balance</p>
                            <h2 class="text-3xl font-bold">৳ <?php echo number_format($real_balance, 2); ?></h2>
                        </div>
                        <i class="fas fa-wallet absolute right-[-10px] bottom-[-10px] text-8xl text-white opacity-10"></i>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <p class="text-gray-400 text-xs font-bold uppercase mb-2">Total Deposited</p>
                        <h2 class="text-2xl font-bold text-gray-800">৳ <?php echo number_format($total_deposit, 2); ?></h2>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                        <p class="text-gray-400 text-xs font-bold uppercase mb-2">Total Withdrawn</p>
                        <h2 class="text-2xl font-bold text-gray-800">৳ <?php echo number_format($total_withdraw, 2); ?></h2>
                    </div>
                    <div class="bg-emerald-600 text-white p-6 rounded-2xl shadow-lg relative overflow-hidden">
                        <div class="relative z-10">
                            <p class="text-emerald-100 text-xs font-bold uppercase mb-1">Turnover Remaining</p>
                            <h2 class="text-2xl font-bold">৳ <?php echo number_format($turnover_remaining, 2); ?></h2>
                            <p class="text-[10px] text-emerald-100 mt-1">Target ৳<?php echo number_format($turnover_target, 2); ?> / Played ৳<?php echo number_format($turnover_completed, 2); ?></p>
                        </div>
                        <i class="fas fa-sync-alt absolute right-[-10px] bottom-[-10px] text-7xl text-white opacity-10"></i>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-4 opacity-5"><i class="fas fa-coins text-6xl"></i></div>
                    <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i class="fas fa-sliders-h text-orange-600"></i> Real Balance Manager
                    </h3>
                    
                    <form method="POST" class="flex flex-col sm:flex-row gap-3 items-end">
                        <div class="flex-1 w-full">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Manual Adjustment</label>
                            <div class="flex gap-0">
                                <select name="operation" class="bg-gray-100 border border-gray-300 text-gray-700 text-sm rounded-l-lg p-3 outline-none font-bold">
                                    <option value="add">Add (+)</option>
                                    <option value="subtract">Cut (-)</option>
                                </select>
                                <input type="number" step="0.01" name="amount" placeholder="Amount (e.g. 500)" class="bg-white border border-l-0 border-gray-300 text-gray-900 text-sm rounded-r-lg p-3 w-full outline-none" required>
                            </div>
                        </div>
                        <button type="submit" name="update_real_balance" class="w-full sm:w-auto bg-orange-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-orange-700 transition">
                            Update
                        </button>
                    </form>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-sync-alt text-emerald-600"></i> Manual Turnover Control
                        </h3>
                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded">Remaining ৳<?php echo number_format($turnover_remaining, 2); ?></span>
                    </div>
                    <form method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                        <div class="sm:col-span-1">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Action</label>
                            <select name="turnover_operation" class="w-full border p-3 rounded-lg text-sm outline-none focus:border-emerald-500 font-bold">
                                <option value="add">Add Turnover</option>
                                <option value="set">Set Remaining</option>
                                <option value="remove">Remove Amount</option>
                                <option value="zero">Zero / Clear</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-bold text-gray-500 uppercase block mb-1">Turnover Amount</label>
                            <input type="number" step="0.01" min="0" name="turnover_amount" placeholder="Amount, e.g. 5000" class="w-full border p-3 rounded-lg text-sm outline-none focus:border-emerald-500">
                        </div>
                        <button type="submit" name="update_turnover" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-lg text-sm transition">
                            Update Turnover
                        </button>
                    </form>
                    <p class="text-[11px] text-gray-400 mt-3 font-medium">Zero করলে user withdraw করতে পারবে, যদি balance available থাকে।</p>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-gift text-pink-600"></i> Manual Bonus
                        </h3>
                    </div>
                    <form method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <input type="number" name="bonus_amount" placeholder="Amount (৳)" class="w-full border p-3 rounded-lg text-sm outline-none focus:border-pink-500" required>
                        <input type="text" name="reason" placeholder="Reason (e.g. Compensation)" class="w-full border p-3 rounded-lg text-sm outline-none focus:border-pink-500" required>
                        <button type="submit" name="give_bonus" class="w-full bg-pink-600 hover:bg-pink-700 text-white font-bold py-3 rounded-lg text-sm transition">
                            Send Bonus
                        </button>
                    </form>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="flex border-b border-gray-200">
                        <button onclick="openTab('bets')" id="btn-bets" class="flex-1 py-3 text-sm font-bold text-indigo-600 border-b-2 border-indigo-600 bg-indigo-50">Bet History</button>
                        <button onclick="openTab('trans')" id="btn-trans" class="flex-1 py-3 text-sm font-bold text-gray-500 hover:bg-gray-50">Transactions</button>
                    </div>

                    <div id="tab-bets" class="h-96 overflow-y-auto custom-scroll">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-gray-50 text-gray-500 font-bold sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 bg-gray-50">Game</th>
                                    <th class="px-4 py-3 bg-gray-50">Amount</th>
                                    <th class="px-4 py-3 bg-gray-50">Result</th>
                                    <th class="px-4 py-3 bg-gray-50 text-right">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($bet_history && $bet_history->num_rows > 0): ?>
                                    <?php while($bet = $bet_history->fetch_assoc()): 
                                        $profit = $bet['win_amount'] - $bet['bet_amount'];
                                        $resColor = $profit >= 0 ? 'text-green-600' : 'text-red-600';
                                        
                                        // [FIXED] Game Name logic with safe check
                                        $gameName = !empty($bet['game_title']) ? $bet['game_title'] : $bet['game_uid'];
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-medium text-gray-700"><?php echo htmlspecialchars($gameName); ?></td>
                                        <td class="px-4 py-2">৳<?php echo number_format($bet['bet_amount'], 2); ?></td>
                                        <td class="px-4 py-2 font-bold <?php echo $resColor; ?>">
                                            <?php echo ($profit >= 0 ? '+' : '') . number_format($profit, 2); ?>
                                        </td>
                                        <td class="px-4 py-2 text-right text-gray-400"><?php echo date('d M, h:i A', strtotime($bet['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-10 text-gray-400">No bets found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="tab-trans" class="h-96 overflow-y-auto custom-scroll hidden">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead class="bg-gray-50 text-gray-500 font-bold sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 bg-gray-50">Type</th>
                                    <th class="px-4 py-3 bg-gray-50">Amount</th>
                                    <th class="px-4 py-3 bg-gray-50">Method</th>
                                    <th class="px-4 py-3 bg-gray-50">Status</th>
                                    <th class="px-4 py-3 bg-gray-50 text-right">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($trans_history->num_rows > 0): ?>
                                    <?php while($tr = $trans_history->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 uppercase font-bold text-xs <?php echo $tr['type']=='deposit'?'text-green-600':($tr['type']=='bonus'?'text-pink-600':'text-red-600'); ?>">
                                            <?php echo $tr['type']; ?>
                                        </td>
                                        <td class="px-4 py-2 font-bold">৳<?php echo number_format($tr['amount'], 2); ?></td>
                                        <td class="px-4 py-2 text-gray-600"><?php echo htmlspecialchars($tr['method']); ?></td>
                                        <td class="px-4 py-2">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-green-100 text-green-700"><?php echo $tr['status']; ?></span>
                                        </td>
                                        <td class="px-4 py-2 text-right text-gray-400"><?php echo date('d M, h:i A', strtotime($tr['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-10 text-gray-400">No transactions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>



    <script>
        // Tab Logic
        function openTab(tabName) {
            document.getElementById('tab-bets').classList.add('hidden');
            document.getElementById('tab-trans').classList.add('hidden');
            document.getElementById('btn-bets').classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600', 'bg-indigo-50');
            document.getElementById('btn-trans').classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600', 'bg-indigo-50');
            document.getElementById('btn-bets').classList.add('text-gray-500');
            document.getElementById('btn-trans').classList.add('text-gray-500');

            document.getElementById('tab-' + tabName).classList.remove('hidden');
            document.getElementById('btn-' + tabName).classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600', 'bg-indigo-50');
            document.getElementById('btn-' + tabName).classList.remove('text-gray-500');
        }

        // Chart.js Logic for Risk Gauge
        const ctx = document.getElementById('riskGauge').getContext('2d');
        const score = <?php echo $user['risk_score']; ?>;
        let color = '#10B981'; // Green
        if (score >= <?php echo $user['risk_auto_lock_percent']; ?>) color = '#DC2626'; // Red
        else if (score >= 50) color = '#F59E0B'; // Orange

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Risk', 'Safe'],
                datasets: [{
                    data: [score, 100 - score],
                    backgroundColor: [color, '#F3F4F6'],
                    borderWidth: 0,
                    borderRadius: 20,
                    cutout: '85%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: { animateScale: true }
            }
        });
    </script>
</body>
</html>