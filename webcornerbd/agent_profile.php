<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// 1. Validate Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$agent_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($agent_id === 0) die("Invalid Agent ID");

$msg = "";
$msg_type = "";

// ---------------------------------------------------------
// 1. HANDLE POST ACTIONS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- [NEW] MANAGE FUNDS (ADD/DEDUCT BALANCE) ---
    if (isset($_POST['adjust_balance'])) {
        $amount = floatval($_POST['amount']);
        $type = $_POST['type']; // 'add' or 'deduct'
        $remarks = $conn->real_escape_string($_POST['remarks']);
        
        if ($amount > 0) {
            // ব্যালেন্স লজিক
            if ($type == 'add') {
                // টাকা যোগ করা হচ্ছে
                $sql = "UPDATE agents SET balance = balance + $amount WHERE id = $agent_id";
                $desc = "Admin Added: " . $remarks;
                $trans_type = 'deposit'; // লেজারের জন্য টাইপ
            } else {
                // টাকা কাটা হচ্ছে (চেক করা হবে ব্যালেন্স আছে কিনা)
                $check = $conn->query("SELECT balance FROM agents WHERE id=$agent_id")->fetch_assoc();
                if ($check['balance'] >= $amount) {
                    $sql = "UPDATE agents SET balance = balance - $amount WHERE id = $agent_id";
                    $desc = "Admin Deducted: " . $remarks;
                    $trans_type = 'withdraw';
                } else {
                    $msg = "Error: Agent has insufficient balance to deduct!";
                    $msg_type = "error";
                }
            }

            // যদি এরর না থাকে তবে এক্সিকিউট করো
            if (empty($msg)) {
                if ($conn->query($sql)) {
                    // ২. লেজারে এন্ট্রি (যাতে হিসেব থাকে)
                    $conn->query("INSERT INTO agent_transactions (agent_id, type, amount, description, created_at) VALUES ($agent_id, '$trans_type', $amount, '$desc', NOW())");
                    
                    $msg = "Balance updated successfully!";
                    $msg_type = "success";
                } else {
                    error_log('Agent wallet update failed: ' . $conn->error);
                    $msg = "Unable to update the agent right now.";
                    $msg_type = "error";
                }
            }
        } else {
            $msg = "Invalid Amount!";
            $msg_type = "error";
        }
    }

    // --- RESET PASSWORD ---
    if (isset($_POST['reset_password'])) {
        $new_pass_plain = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8); 
        $hashed_pass = password_hash($new_pass_plain, PASSWORD_DEFAULT);
        
        $u_res = $conn->query("SELECT user_id FROM agents WHERE id=$agent_id");
        if($u_res->num_rows > 0){
            $u_row = $u_res->fetch_assoc();
            $uid = $u_row['user_id'];
            
            if($conn->query("UPDATE users SET password='$hashed_pass' WHERE id=$uid")){
                $msg = "Password reset successfully! New Password: <b>$new_pass_plain</b> <button onclick=\"navigator.clipboard.writeText('$new_pass_plain'); alert('Copied!');\" class='ml-2 bg-gray-200 px-2 py-1 rounded text-xs hover:bg-gray-300'>Copy</button>";
                $msg_type = "success";
            } else {
                $msg = "Error updating password.";
                $msg_type = "error";
            }
        }
    }

    // --- ADD NEW NUMBER ---
    if (isset($_POST['add_number'])) {
        $method = $conn->real_escape_string($_POST['method']);
        $type = $conn->real_escape_string($_POST['type']);
        $number = $conn->real_escape_string($_POST['number']);
        
        $limit_min = !empty($_POST['limit_min']) ? floatval($_POST['limit_min']) : 100.00;
        $limit_max = !empty($_POST['limit_max']) ? floatval($_POST['limit_max']) : 25000.00;
        $limit_daily = !empty($_POST['limit_daily']) ? floatval($_POST['limit_daily']) : 500000.00;
        $instructions = $conn->real_escape_string($_POST['instructions']);
        
        $sql = "INSERT INTO payment_accounts (agent_id, method, type, number, limit_min, limit_max, limit_daily, instructions, is_active) 
                VALUES ($agent_id, '$method', '$type', '$number', $limit_min, $limit_max, $limit_daily, '$instructions', 1)";
        
        if($conn->query($sql)) {
            $msg = "New payment method added successfully!";
            $msg_type = "success";
        } else {
            error_log('Agent payment number insert failed: ' . $conn->error);
            $msg = "Unable to add the number right now.";
            $msg_type = "error";
        }
    }

    // --- DELETE NUMBER ---
    if (isset($_POST['delete_number'])) {
        $pid = intval($_POST['pid']);
        $conn->query("DELETE FROM payment_accounts WHERE id=$pid AND agent_id=$agent_id");
        $msg = "Number deleted."; $msg_type = "success";
    }

    // --- TOGGLE NUMBER STATUS ---
    if (isset($_POST['toggle_number'])) {
        $pid = intval($_POST['pid']);
        $current = intval($_POST['current_status']);
        $new = $current ? 0 : 1;
        $conn->query("UPDATE payment_accounts SET is_active=$new WHERE id=$pid AND agent_id=$agent_id");
        $msg = "Number status updated."; $msg_type = "success";
    }

    // --- UPDATE CONFIGURATION ---
    if (isset($_POST['update_config'])) {
        $agent_type = $conn->real_escape_string($_POST['agent_type']); 
        $start = $_POST['active_start'];
        $end = $_POST['active_end'];
        
        $c_dp = !empty($_POST['custom_dp']) ? floatval($_POST['custom_dp']) : "NULL";
        $c_wd = !empty($_POST['custom_wd']) ? floatval($_POST['custom_wd']) : "NULL";

        $sql = "UPDATE agents SET 
                type='$agent_type',
                active_start_time='$start', 
                active_end_time='$end', 
                custom_deposit_rate=$c_dp,
                custom_withdraw_rate=$c_wd
                WHERE id=$agent_id";
        
        if ($conn->query($sql)) {
            $msg = "Configuration saved successfully.";
            $msg_type = "success";
        } else {
            error_log('Agent configuration update failed: ' . $conn->error);
            $msg = "Unable to update configuration right now.";
            $msg_type = "error";
        }
    }

    // --- TOGGLE WITHDRAW LOCK ---
    if (isset($_POST['toggle_lock'])) {
        $current = intval($_POST['current_lock']);
        $new = $current ? 0 : 1;
        $conn->query("UPDATE agents SET is_withdraw_locked=$new WHERE id=$agent_id");
        $msg = $new ? "Withdrawals LOCKED." : "Withdrawals UNLOCKED.";
        $msg_type = $new ? "warning" : "success";
    }
}

// ---------------------------------------------------------
// 2. FETCH DATA
// ---------------------------------------------------------
$sys = $conn->query("SELECT * FROM system_settings LIMIT 1")->fetch_assoc();
$agent = $conn->query("SELECT * FROM agents WHERE id = $agent_id")->fetch_assoc();

if (!$agent) die("Agent not found.");

$user_id = $agent['user_id'];
$numbers = $conn->query("SELECT * FROM payment_accounts WHERE agent_id = $agent_id ORDER BY id DESC");
$kyc = $conn->query("SELECT * FROM kyc_documents WHERE user_id = $user_id ORDER BY submitted_at DESC LIMIT 1")->fetch_assoc();

$dp_rate = $agent['custom_deposit_rate'] !== null ? $agent['custom_deposit_rate'] : $sys['deposit_comm_percent'];
$wd_rate = $agent['custom_withdraw_rate'] !== null ? $agent['custom_withdraw_rate'] : $sys['withdraw_comm_percent'];

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$date_sql = "";
if ($filter == 'today') $date_sql = "AND DATE(created_at) = CURDATE()";
elseif ($filter == 'week') $date_sql = "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";

$stats_query = "SELECT 
    COUNT(*) as count,
    SUM(CASE WHEN type = 'deposit' AND status = 'approved' THEN amount ELSE 0 END) as dp_vol,
    SUM(CASE WHEN type = 'withdraw' AND status = 'approved' THEN amount ELSE 0 END) as wd_vol
    FROM transactions_fake 
    WHERE agent_id = $agent_id $date_sql";
$stats = $conn->query($stats_query)->fetch_assoc();

$est_earnings = ($stats['dp_vol'] * $dp_rate / 100) + ($stats['wd_vol'] * $wd_rate / 100);

$current_time = date('H:i:s');
$is_online = ($current_time >= $agent['active_start_time'] && $current_time <= $agent['active_end_time']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent: <?php echo htmlspecialchars($agent['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script>
        function openTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('border-indigo-600', 'text-indigo-600'));
            document.getElementById(tabName).classList.remove('hidden');
            document.getElementById('btn-' + tabName).classList.add('border-indigo-600', 'text-indigo-600');
        }
        function showImage(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('imgModal').classList.remove('hidden');
        }
    </script>
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-100 font-sans text-slate-800">

<?php include '../includes/sidebar_admin.php'; ?>

<main class="lg:ml-64 p-4 lg:p-6 min-h-screen pb-20 transition-all duration-300">

    <?php if($msg): ?>
        <div class="mb-6 p-4 rounded-lg flex items-center gap-3 shadow-sm bg-white border-l-4 <?php echo $msg_type == 'success' ? 'border-green-500 text-green-700' : 'border-red-500 text-red-700'; ?>">
            <i class="fas <?php echo $msg_type == 'success' ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
            <span class="text-sm font-medium"><?php echo $msg; ?></span>
        </div>
    <?php endif; ?>

    <div id="imgModal" class="fixed inset-0 bg-black bg-opacity-80 z-50 hidden flex items-center justify-center p-4" onclick="this.classList.add('hidden')">
        <img id="modalImg" src="" class="max-w-full max-h-full rounded shadow-lg">
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fas fa-id-badge text-8xl lg:text-9xl"></i></div>
        <div class="flex flex-col md:flex-row items-center md:items-start gap-4 lg:gap-6 relative z-10">
            <div class="w-16 h-16 lg:w-20 lg:h-20 rounded-full flex items-center justify-center text-3xl text-white shadow-lg shrink-0 <?php echo $agent['type'] == 'ewallet' ? 'bg-rose-600' : 'bg-indigo-600'; ?>">
                <i class="fas <?php echo $agent['type'] == 'ewallet' ? 'fa-wallet' : 'fa-user-tie'; ?>"></i>
            </div>
            <div class="flex-1 text-center md:text-left">
                <div class="flex flex-col md:flex-row items-center gap-2 mb-1">
                    <h1 class="text-xl lg:text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($agent['name']); ?></h1>
                    <?php if($is_online): ?>
                        <span class="bg-green-100 text-green-700 text-[10px] lg:text-xs font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
                            <span class="w-2 h-2 bg-green-600 rounded-full animate-pulse"></span> Online
                        </span>
                    <?php else: ?>
                        <span class="bg-gray-100 text-gray-500 text-[10px] lg:text-xs font-bold px-2 py-0.5 rounded-full">Offline</span>
                    <?php endif; ?>
                </div>
                <p class="text-xs lg:text-sm text-gray-500">
                    <strong class="uppercase"><?php echo $agent['type']; ?></strong> 
                    | Active: <?php echo date('h:i A', strtotime($agent['active_start_time'])); ?> - <?php echo date('h:i A', strtotime($agent['active_end_time'])); ?>
                </p>
                <form method="POST" class="mt-2" onsubmit="return confirm('Are you sure you want to reset the password?');">
                    <button type="submit" name="reset_password" class="text-xs bg-red-100 text-red-600 px-3 py-1 rounded hover:bg-red-200 transition font-bold flex items-center gap-1">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                </form>
            </div>
            <form method="POST" class="w-full md:w-auto flex justify-center">
                <input type="hidden" name="current_lock" value="<?php echo $agent['is_withdraw_locked']; ?>">
                <button type="submit" name="toggle_lock" class="flex flex-col items-center justify-center w-full md:w-20 py-2 md:py-0 md:h-20 rounded-xl border-2 transition <?php echo $agent['is_withdraw_locked'] ? 'border-red-500 bg-red-50 text-red-600' : 'border-green-500 bg-green-50 text-green-600'; ?>">
                    <i class="fas <?php echo $agent['is_withdraw_locked'] ? 'fa-lock' : 'fa-lock-open'; ?> text-xl mb-1"></i>
                    <span class="text-[10px] font-bold uppercase"><?php echo $agent['is_withdraw_locked'] ? 'LOCKED' : 'ACTIVE'; ?></span>
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-gradient-to-br from-purple-600 to-indigo-700 text-white p-5 rounded-xl shadow-lg">
            <p class="text-xs text-purple-200 uppercase font-bold mb-1">Wallet Balance</p>
            <h2 class="text-2xl font-bold">৳<?php echo number_format($agent['balance'], 2); ?></h2>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-center mb-2">
                <p class="text-xs text-gray-500 uppercase font-bold">Filtered Earnings</p>
                <select onchange="window.location.href='?id=<?php echo $agent_id; ?>&filter='+this.value" class="text-[10px] border rounded bg-gray-50 p-1">
                    <option value="all" <?php if($filter=='all') echo 'selected'; ?>>All Time</option>
                    <option value="today" <?php if($filter=='today') echo 'selected'; ?>>Today</option>
                    <option value="week" <?php if($filter=='week') echo 'selected'; ?>>Week</option>
                </select>
            </div>
            <h2 class="text-2xl font-bold text-emerald-600">৳<?php echo number_format($est_earnings, 2); ?></h2>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
            <p class="text-xs text-gray-500 uppercase font-bold mb-1">Total Processed</p>
            <h2 class="text-xl font-bold text-gray-800"><?php echo $stats['count']; ?> <span class="text-xs text-gray-400 font-normal">Transactions</span></h2>
        </div>
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-center items-center text-center">
            <?php if($kyc && $kyc['status'] == 'approved'): ?>
                <div class="text-green-600 text-2xl mb-1"><i class="fas fa-shield-check"></i></div>
                <span class="text-[10px] font-bold text-gray-600 uppercase">Verified</span>
                <?php if(!empty($kyc['document_image'])): ?><button onclick="showImage('../uploads/kyc/<?php echo $kyc['document_image']; ?>')" class="mt-2 text-[10px] text-blue-600 hover:underline">View Proof</button><?php endif; ?>
            <?php else: ?>
                <div class="text-orange-400 text-2xl mb-1"><i class="fas fa-user-clock"></i></div>
                <span class="text-[10px] font-bold text-gray-600 uppercase">KYC Pending</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-1 space-y-6">
            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-bold text-gray-800 mb-4 text-xs uppercase flex items-center gap-2">
                    <i class="fas fa-coins text-yellow-600"></i> Manage Funds
                </h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">Select Action</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="add" checked class="peer hidden">
                                <div class="text-center py-2 border rounded bg-gray-50 text-gray-500 text-xs font-bold peer-checked:bg-green-600 peer-checked:text-white peer-checked:border-green-600 transition">
                                    <i class="fas fa-plus-circle"></i> Add
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="deduct" class="peer hidden">
                                <div class="text-center py-2 border rounded bg-gray-50 text-gray-500 text-xs font-bold peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600 transition">
                                    <i class="fas fa-minus-circle"></i> Deduct
                                </div>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">Amount</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-400 font-bold">৳</span>
                            <input type="number" step="0.01" name="amount" class="w-full pl-8 pr-3 py-2 text-sm border rounded focus:border-indigo-500 outline-none" placeholder="0.00" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-500 mb-1 uppercase">Remarks / Note</label>
                        <input type="text" name="remarks" class="w-full p-2 text-sm border rounded focus:border-indigo-500 outline-none" placeholder="e.g. Bank Deposit / Cash Payment" required>
                    </div>
                    <button type="submit" name="adjust_balance" onclick="return confirm('Are you sure you want to update this balance?')" class="w-full bg-gray-800 hover:bg-gray-900 text-white py-2 rounded text-xs font-bold transition">
                        Update Balance
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-gray-800 text-xs uppercase flex items-center gap-2">
                        <i class="fas fa-sim-card text-blue-600"></i> Deposit Numbers
                    </h3>
                    <span class="text-[10px] text-gray-400">Publicly visible numbers</span>
                </div>
                
                <div class="max-h-80 overflow-y-auto">
                    <?php if($numbers->num_rows > 0): ?>
                        <table class="w-full text-left text-xs">
                            <tbody class="divide-y divide-gray-100">
                                <?php while($num = $numbers->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 group">
                                    <td class="p-3">
                                        <div class="font-bold text-gray-700 capitalize"><?php echo $num['method']; ?> <span class="text-[10px] text-gray-400 font-normal">(<?php echo $num['type']; ?>)</span></div>
                                        <div class="text-[10px] text-gray-500 mt-1">
                                            Limits: ৳<?php echo number_format($num['limit_min']); ?> - ৳<?php echo number_format($num['limit_max']); ?>
                                        </div>
                                        <?php if($num['instructions']): ?>
                                            <div class="text-[9px] text-blue-500 italic mt-0.5"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($num['instructions']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="font-mono text-sm font-bold text-gray-800"><?php echo $num['number']; ?></div>
                                        <div class="flex justify-end gap-2 mt-2 opacity-60 group-hover:opacity-100 transition">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="pid" value="<?php echo $num['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $num['is_active']; ?>">
                                                <button type="submit" name="toggle_number" class="text-<?php echo $num['is_active']?'green':'gray'; ?>-500 hover:text-green-700">
                                                    <i class="fas fa-toggle-<?php echo $num['is_active']?'on':'off'; ?> text-lg"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this number?');">
                                                <input type="hidden" name="pid" value="<?php echo $num['id']; ?>">
                                                <button type="submit" name="delete_number" class="text-red-400 hover:text-red-600">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="p-4 text-center text-xs text-gray-400">No active numbers found.</div>
                    <?php endif; ?>
                </div>

                <div class="p-4 bg-gray-50 border-t border-gray-200">
                    <p class="text-[10px] text-gray-500 mb-2 font-bold uppercase">Add New Wallet</p>
                    <form method="POST" class="space-y-2">
                        <div class="flex gap-2">
                            <select name="method" class="w-1/2 text-xs border rounded p-2 bg-white">
                                <option value="bkash">bKash</option>
                                <option value="nagad">Nagad</option>
                                <option value="rocket">Rocket</option>
                            </select>
                            <select name="type" class="w-1/2 text-xs border rounded p-2 bg-white">
                                <option value="personal">Personal</option>
                                <option value="agent">Agent</option>
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" name="number" placeholder="Phone Number (017...)" class="w-2/3 text-xs border rounded p-2" required>
                            <input type="number" name="limit_daily" placeholder="Daily Limit" value="25000" class="w-1/3 text-xs border rounded p-2">
                        </div>
                        <div class="flex gap-2">
                            <div class="w-1/2">
                                <input type="number" name="limit_min" placeholder="Min Deposit" value="100" class="w-full text-xs border rounded p-2">
                            </div>
                            <div class="w-1/2">
                                <input type="number" name="limit_max" placeholder="Max Deposit" value="25000" class="w-full text-xs border rounded p-2">
                            </div>
                        </div>
                        <input type="text" name="instructions" placeholder="Note (e.g. Cash Out Only)" class="w-full text-xs border rounded p-2">
                        <button type="submit" name="add_number" class="w-full bg-blue-600 text-white px-3 py-2 rounded text-xs font-bold hover:bg-blue-700 shadow-sm mt-1">
                            <i class="fas fa-plus mr-1"></i> Add Number
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-bold text-gray-800 mb-4 text-xs uppercase flex items-center gap-2">
                    <i class="fas fa-sliders-h text-gray-400"></i> Agent Settings
                </h3>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Agent Type</label>
                        <select name="agent_type" class="w-full text-xs border rounded p-2 bg-gray-50 outline-none focus:border-indigo-500">
                            <option value="local" <?php echo $agent['type']=='local'?'selected':''; ?>>Local (Referral Based)</option>
                            <option value="ewallet" <?php echo $agent['type']=='ewallet'?'selected':''; ?>>E-Wallet (Payment Processor)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Active Hours (24h Format)</label>
                        <div class="flex items-center gap-2">
                            <input type="time" name="active_start" value="<?php echo $agent['active_start_time']; ?>" class="w-full text-xs border rounded p-2">
                            <span class="text-gray-400">-</span>
                            <input type="time" name="active_end" value="<?php echo $agent['active_end_time']; ?>" class="w-full text-xs border rounded p-2">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1">Commission Override (%)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-[10px] text-gray-400 block">Deposit</span>
                                <input type="number" step="0.01" name="custom_dp" placeholder="Global: <?php echo $sys['deposit_comm_percent']; ?>%" value="<?php echo $agent['custom_deposit_rate']; ?>" class="w-full text-xs border rounded p-2">
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-400 block">Withdraw</span>
                                <input type="number" step="0.01" name="custom_wd" placeholder="Global: <?php echo $sys['withdraw_comm_percent']; ?>%" value="<?php echo $agent['custom_withdraw_rate']; ?>" class="w-full text-xs border rounded p-2">
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="update_config" class="w-full bg-slate-800 text-white py-2 rounded-lg text-xs font-bold hover:bg-slate-900 transition">
                        Save Configuration
                    </button>
                </form>
            </div>

        </div>

        <div class="lg:col-span-2">
            <div class="flex border-b border-gray-200 mb-4 overflow-x-auto">
                <button id="btn-player-trans" onclick="openTab('player-trans')" class="tab-btn px-4 py-2 text-sm font-bold text-indigo-600 border-b-2 border-indigo-600 whitespace-nowrap">Player Transactions</button>
                <button id="btn-agent-ledger" onclick="openTab('agent-ledger')" class="tab-btn px-4 py-2 text-sm font-bold text-gray-500 hover:text-gray-700 whitespace-nowrap">Agent Ledger</button>
            </div>

            <div id="player-trans" class="tab-content bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs lg:text-sm">
                        <thead class="bg-gray-50 text-gray-400 font-semibold">
                            <tr>
                                <th class="p-3 lg:p-4">User</th>
                                <th class="p-3 lg:p-4">Type</th>
                                <th class="p-3 lg:p-4 text-right">Amount</th>
                                <th class="p-3 lg:p-4 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php 
                            $pt_sql = "SELECT t.*, u.username FROM transactions_fake t JOIN users u ON t.user_id = u.id WHERE t.agent_id = $agent_id ORDER BY t.created_at DESC LIMIT 20";
                            $pt_res = $conn->query($pt_sql);
                            if($pt_res->num_rows > 0): while($row = $pt_res->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 lg:p-4 font-bold text-gray-700"><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="p-3 lg:p-4 capitalize">
                                    <span class="px-2 py-0.5 rounded text-[10px] <?php echo $row['type']=='deposit'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>"><?php echo $row['type']; ?></span>
                                </td>
                                <td class="p-3 lg:p-4 text-right font-mono">৳<?php echo number_format($row['amount']); ?></td>
                                <td class="p-3 lg:p-4 text-right text-gray-400"><?php echo date('d M H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="p-6 text-center text-gray-400">No activity found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="agent-ledger" class="tab-content hidden bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs lg:text-sm">
                        <thead class="bg-gray-50 text-gray-400 font-semibold">
                            <tr>
                                <th class="p-3 lg:p-4">Type</th>
                                <th class="p-3 lg:p-4">Amount</th>
                                <th class="p-3 lg:p-4">Description</th>
                                <th class="p-3 lg:p-4 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php 
                            $al_sql = "SELECT * FROM agent_transactions WHERE agent_id = $agent_id ORDER BY created_at DESC LIMIT 20";
                            $al_res = $conn->query($al_sql);
                            if($al_res->num_rows > 0): while($row = $al_res->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-3 lg:p-4 capitalize font-bold"><?php echo $row['type']; ?></td>
                                <td class="p-3 lg:p-4 font-mono <?php echo $row['type']=='withdraw'?'text-red-600':'text-green-600'; ?>">
                                    <?php echo $row['type']=='withdraw'?'-':'+'; ?>৳<?php echo number_format($row['amount'], 2); ?>
                                </td>
                                <td class="p-3 lg:p-4 text-gray-500"><?php echo $row['description']; ?></td>
                                <td class="p-3 lg:p-4 text-right text-gray-400"><?php echo date('d M H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="p-6 text-center text-gray-400">No ledger history.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</main>
</body>
</html>