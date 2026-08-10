<?php
session_start();
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }

require '../includes/db.php'; 
require_once '../includes/referral_system_helper.php';
wcb_referral_ensure_schema($conn);

$logged_in_user_id = $_SESSION['agent_id']; 

// ১. এজেন্টের ডাটা আনা
$agent_sql = $conn->query("SELECT * FROM agents WHERE user_id = $logged_in_user_id");
if($agent_sql->num_rows == 0) die("Agent Profile Error!");
$agent_data = $agent_sql->fetch_assoc();

$real_agent_id = $agent_data['id']; 
$agent_current_bal = $agent_data['balance'];

// কমিশন রেট সেট করা
$sys = $conn->query("SELECT deposit_comm_percent, withdraw_comm_percent FROM system_settings LIMIT 1")->fetch_assoc();
$dep_comm_rate = ($agent_data['custom_deposit_rate'] > 0) ? $agent_data['custom_deposit_rate'] : $sys['deposit_comm_percent'];
$wd_comm_rate = ($agent_data['custom_withdraw_rate'] > 0) ? $agent_data['custom_withdraw_rate'] : $sys['withdraw_comm_percent'];

$msg = "";

// ২. অ্যাকশন হ্যান্ডলিং (Approve/Reject)
if (isset($_POST['action'])) {
    $trx_id = intval($_POST['req_id']); 
    $action = $_POST['action'];
    
    // প্রুফ বা নোট
    $payment_proof = "";
    $agent_note = isset($_POST['agent_note']) ? $conn->real_escape_string($_POST['agent_note']) : "";

    // ফাইল আপলোড লজিক
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'mp4'];
        $filename = $_FILES['proof_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_name = "proof_" . time() . "." . $ext;
            move_uploaded_file($_FILES['proof_file']['tmp_name'], "../uploads/proofs/" . $new_name);
            $payment_proof = $new_name;
        }
    }

    $req_qry = $conn->query("SELECT * FROM transactions_fake WHERE id=$trx_id AND agent_id=$real_agent_id");
    
    if($req_qry->num_rows > 0){
        $req = $req_qry->fetch_assoc();
        $player_uid = $req['user_id'];
        $amount = $req['amount'];
        $type = $req['type']; 

        if ($req['status'] == 'pending') {
            
            if ($action == 'approve') {
                
                // --- DEPOSIT APPROVAL ---
                if ($type == 'deposit') {
                    if ($agent_current_bal < $amount) {
                        $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4'>আপনার পর্যাপ্ত ব্যালেন্স নেই!</div>";
                    } else {
                        $conn->begin_transaction();
                        try {
                            $commission = ($amount * $dep_comm_rate) / 100;

                            // [FIXED] ব্যালেন্সের সাথে Wager (turnover_target) ও যোগ করা হলো (1x)
                            // অর্থাৎ, প্লেয়ার ৫০০০ টাকা ডিপোজিট করলে তাকে ৫০০০ টাকার গেম খেলতে হবে উইথড্র করার জন্য
                            $conn->query("UPDATE users SET balance = balance + $amount, turnover_target = turnover_target + $amount WHERE id=$player_uid");
                            
                            // এজেন্টের ব্যালেন্স আপডেট
                            $conn->query("UPDATE agents SET balance = balance - $amount, bonus_balance = bonus_balance + $commission WHERE id=$real_agent_id");
                            
                            // ট্রানজেকশন আপডেট
                            $sql = "UPDATE transactions_fake SET status='approved', proof_file='$payment_proof', admin_note='$agent_note' WHERE id=$trx_id";
                            $conn->query($sql);
                            wcb_referral_award_for_deposit($conn, $player_uid, $trx_id, $amount);
                            
                            // এজেন্ট লেজার
                            $desc = "Deposit Approved for User #$player_uid";
                            $conn->query("INSERT INTO agent_transactions (agent_id, type, amount, description) VALUES ($real_agent_id, 'withdraw', $amount, '$desc')");

                            // নোটিফিকেশন
                            $n_msg = "Your deposit of ৳$amount has been approved.";
                            $conn->query("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES ($player_uid, 'Deposit Approved', '$n_msg', 'success', NOW())");

                            $conn->commit();
                            $msg = "<div class='bg-green-600/20 text-green-400 p-3 rounded mb-4'>ডিপোজিট সফল! কমিশন: ৳$commission</div>";
                            $agent_current_bal -= $amount; 
                        } catch(Exception $e) {
                            $conn->rollback();
                            $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4'>System Error: " . $e->getMessage() . "</div>";
                        }
                    }
                }
                
                // --- WITHDRAW APPROVAL ---
                elseif ($type == 'withdraw') {
                    $conn->begin_transaction();
                    try {
                        $commission = ($amount * $wd_comm_rate) / 100;

                        $conn->query("UPDATE agents SET balance = balance + $amount, bonus_balance = bonus_balance + $commission WHERE id=$real_agent_id");
                        
                        $sql = "UPDATE transactions_fake SET status='approved', proof_file='$payment_proof', admin_note='$agent_note' WHERE id=$trx_id";
                        $conn->query($sql);
                        
                        $desc = "Withdraw Approved (Agent Received Asset)";
                        $conn->query("INSERT INTO agent_transactions (agent_id, type, amount, description) VALUES ($real_agent_id, 'deposit', $amount, '$desc')");

                        // Notification
                        $n_msg = "Your withdraw request of ৳$amount has been processed.";
                        $conn->query("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES ($player_uid, 'Withdraw Successful', '$n_msg', 'success', NOW())");

                        $conn->commit();
                        $msg = "<div class='bg-green-600/20 text-green-400 p-3 rounded mb-4'>উইথড্র এপ্রুভ! ব্যালেন্স ও কমিশন যোগ হয়েছে।</div>";
                        $agent_current_bal += $amount; 
                    } catch(Exception $e) {
                        $conn->rollback();
                        $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4'>System Error: " . $e->getMessage() . "</div>";
                    }
                }

            } elseif ($action == 'reject') {
                
                // --- REJECTION LOGIC ---
                $conn->begin_transaction();
                try {
                    if ($type == 'withdraw') {
                        $conn->query("UPDATE users SET balance = balance + $amount WHERE id=$player_uid");
                    }

                    $conn->query("UPDATE transactions_fake SET status='rejected', admin_note='$agent_note' WHERE id=$trx_id");
                    
                    // Notification
                    $n_msg = "Your transaction request of ৳$amount was rejected.";
                    $conn->query("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES ($player_uid, 'Request Rejected', '$n_msg', 'error', NOW())");

                    $conn->commit();
                    $msg = "<div class='bg-yellow-600/20 text-yellow-400 p-3 rounded mb-4'>রিকোয়েস্ট বাতিল করা হয়েছে।</div>";
                } catch(Exception $e) {
                    $conn->rollback();
                    $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4'>Error: " . $e->getMessage() . "</div>";
                }
            }
        }
    }
}

// ৩. পেন্ডিং রিকোয়েস্ট ফেচ করা
$pending_reqs = $conn->query("SELECT tf.*, u.username, u.phone, u.balance as u_bal 
                              FROM transactions_fake tf 
                              JOIN users u ON tf.user_id = u.id 
                              WHERE tf.agent_id = $real_agent_id AND tf.status = 'pending' 
                              ORDER BY tf.created_at DESC");

// ৪. হিস্ট্রি ফেচ করা (পেন্ডিং সহ সব দেখাবে - User Request)
// [UPDATED] Removed 'AND tf.status != pending' condition
$history_reqs = $conn->query("SELECT tf.*, u.username 
                              FROM transactions_fake tf 
                              JOIN users u ON tf.user_id = u.id 
                              WHERE tf.agent_id = $real_agent_id 
                              ORDER BY tf.created_at DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        function openTab(name) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(name).classList.remove('hidden');
        }
        function toggleProof(id) {
            document.getElementById('proof-box-'+id).classList.toggle('hidden');
        }
    </script>
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">
    
    <?php include '../includes/sidebar_agent.php'; ?>
    
    <div class="flex-1 flex flex-col h-full relative w-full">
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center z-30">
            <h1 class="text-lg font-bold text-yellow-500">রিকোয়েস্ট ম্যানেজমেন্ট</h1>
            <button onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-5xl mx-auto">
                
                <div class="flex flex-col md:flex-row justify-between items-center mb-6 bg-[#002b2b] p-4 rounded-xl border border-gray-700">
                    <div>
                        <h2 class="text-xl font-bold text-gray-300">লেনদেন রিকোয়েস্ট</h2>
                        <div class="flex gap-4 mt-1 text-xs text-gray-400">
                            <span><i class="fas fa-arrow-down text-green-500"></i> Dep Comm: <?php echo $dep_comm_rate; ?>%</span>
                            <span><i class="fas fa-arrow-up text-red-500"></i> WD Comm: <?php echo $wd_comm_rate; ?>%</span>
                        </div>
                    </div>
                    <div class="text-right mt-2 md:mt-0">
                        <p class="text-xs text-gray-400">আপনার ব্যালেন্স</p>
                        <p class="text-xl font-bold text-yellow-500">৳ <?php echo number_format($agent_current_bal, 2); ?></p>
                    </div>
                </div>
                
                <?php echo $msg; ?>

                <div class="flex gap-2 mb-4 border-b border-gray-700 pb-1">
                    <button onclick="openTab('pending')" class="px-4 py-2 text-sm font-bold bg-yellow-600 text-black rounded-t-lg">পেন্ডিং (<?php echo $pending_reqs->num_rows; ?>)</button>
                    <button onclick="openTab('history')" class="px-4 py-2 text-sm font-bold bg-gray-800 text-gray-400 rounded-t-lg hover:text-white">হিস্ট্রি</button>
                </div>

                <div id="pending" class="tab-content space-y-4">
                    <?php if($pending_reqs && $pending_reqs->num_rows > 0): ?>
                        <?php while($row = $pending_reqs->fetch_assoc()): 
                            $rate = ($row['type'] == 'deposit') ? $dep_comm_rate : $wd_comm_rate;
                            $potential_comm = ($row['amount'] * $rate) / 100;
                        ?>
                        <div class="bg-[#002b2b] p-5 rounded-xl border border-gray-700 shadow-lg relative overflow-hidden group">
                            <div class="absolute left-0 top-0 bottom-0 w-1 <?php echo $row['type'] == 'deposit' ? 'bg-green-500' : 'bg-red-500'; ?>"></div>
                            
                            <div class="flex flex-col md:flex-row gap-4">
                                <div class="flex-1 pl-3">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider <?php echo $row['type'] == 'deposit' ? 'bg-green-900 text-green-400' : 'bg-red-900 text-red-400'; ?>">
                                            <?php echo $row['type']; ?>
                                        </span>
                                        <span class="text-xs text-gray-500">User Bal: ৳<?php echo $row['u_bal']; ?></span>
                                    </div>
                                    <h3 class="text-lg font-bold text-white">
                                        <?php echo $row['username']; ?> 
                                        <span class="text-xs text-gray-400 font-normal">(<?php echo $row['phone']; ?>)</span>
                                    </h3>
                                    <div class="text-xs text-gray-400 mt-1 flex flex-wrap gap-2">
                                        <span class="bg-gray-800 px-2 py-0.5 rounded border border-gray-600"><?php echo $row['method']; ?></span>
                                        <?php if($row['transaction_id']) echo "<span class='bg-gray-800 px-2 py-0.5 rounded border border-gray-600 font-mono text-yellow-500'>TRX: {$row['transaction_id']}</span>"; ?>
                                        <?php if($row['wallet_number']) echo "<span class='bg-gray-800 px-2 py-0.5 rounded border border-gray-600'>To: {$row['wallet_number']}</span>"; ?>
                                    </div>
                                </div>

                                <div class="text-left md:text-right pl-3 md:pl-0">
                                    <div class="text-2xl font-bold <?php echo $row['type'] == 'deposit' ? 'text-green-400' : 'text-red-400'; ?>">
                                        ৳ <?php echo number_format($row['amount']); ?>
                                    </div>
                                    <p class="text-xs text-yellow-300 mt-1 font-mono">
                                        <i class="fas fa-gift"></i> Comm: +৳<?php echo number_format($potential_comm, 2); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-gray-700/50 pl-3">
                                <?php if($row['type'] == 'withdraw'): ?>
                                    <button onclick="toggleProof(<?php echo $row['id']; ?>)" class="text-xs text-blue-400 hover:text-blue-300 underline mb-2 block">
                                        <i class="fas fa-paperclip"></i> প্রমাণ আপলোড করুন (Optional)
                                    </button>
                                <?php endif; ?>

                                <form method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row gap-3">
                                    <input type="hidden" name="req_id" value="<?php echo $row['id']; ?>">
                                    
                                    <div id="proof-box-<?php echo $row['id']; ?>" class="hidden w-full md:w-1/2 flex gap-2">
                                        <input type="text" name="agent_note" placeholder="TrxID / Note" class="w-1/2 bg-gray-900 border border-gray-600 rounded px-2 text-xs text-white">
                                        <input type="file" name="proof_file" class="w-1/2 text-xs text-gray-400 file:bg-gray-700 file:border-0 file:text-white file:px-2 file:py-1 file:rounded">
                                    </div>

                                    <div class="flex gap-2 w-full md:w-auto ml-auto">
                                        <button type="submit" name="action" value="approve" onclick="return confirm('লেনদেন নিশ্চিত করুন?')" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-bold transition shadow-lg text-sm">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" onclick="return confirm('বাতিল করলে টাকা রিফান্ড হবে। নিশ্চিত?')" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-bold transition shadow-lg text-sm">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-16 bg-[#002b2b] rounded-xl border border-gray-700 border-dashed opacity-50">
                            <i class="fas fa-coffee text-4xl mb-3"></i>
                            <p>কোনো পেন্ডিং রিকোয়েস্ট নেই</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="history" class="tab-content hidden">
                    <div class="bg-[#002b2b] rounded-xl border border-gray-700 overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-[#001f1f] text-gray-400 uppercase text-xs">
                                <tr>
                                    <th class="p-4">User</th>
                                    <th class="p-4">TrxID / Details</th> <th class="p-4 text-right">Amount</th>
                                    <th class="p-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php if($history_reqs && $history_reqs->num_rows > 0): ?>
                                    <?php while($h = $history_reqs->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-800/50 transition">
                                        <td class="p-4 font-bold text-white">
                                            <?php echo $h['username']; ?>
                                            <div class="text-[10px] text-gray-500 font-normal"><?php echo date('d M h:i A', strtotime($h['created_at'])); ?></div>
                                        </td>
                                        
                                        <td class="p-4">
                                            <div class="flex flex-col">
                                                <?php if(!empty($h['transaction_id'])): ?>
                                                    <span class="text-xs font-mono text-yellow-500 mb-1">
                                                        #<?php echo $h['transaction_id']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span class="text-xs text-gray-400"><?php echo $h['method']; ?></span>
                                                <span class="text-[10px] text-gray-500"><?php echo $h['wallet_number']; ?></span>
                                                <span class="text-[10px] uppercase font-bold mt-1 <?php echo $h['type']=='deposit'?'text-green-400':'text-red-400'; ?>">
                                                    <?php echo $h['type']; ?>
                                                </span>
                                            </div>
                                        </td>

                                        <td class="p-4 text-right font-mono text-gray-300">৳ <?php echo number_format($h['amount']); ?></td>
                                        
                                        <td class="p-4 text-right">
                                            <?php 
                                                $statusClass = 'bg-gray-700 text-gray-300';
                                                if($h['status'] == 'approved') $statusClass = 'bg-green-900 text-green-400';
                                                elseif($h['status'] == 'rejected') $statusClass = 'bg-red-900 text-red-400';
                                                elseif($h['status'] == 'pending') $statusClass = 'bg-yellow-900 text-yellow-400';
                                            ?>
                                            <span class="px-2 py-1 rounded text-[10px] font-bold uppercase <?php echo $statusClass; ?>">
                                                <?php echo $h['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="p-8 text-center text-gray-500">কোনো হিস্ট্রি পাওয়া যায়নি</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </main>
    </div>
    
    <script>function toggleSidebar(){document.getElementById('agentSidebar').classList.toggle('-translate-x-full');document.getElementById('sidebarOverlay').classList.toggle('hidden');}</script>
</body>
</html>