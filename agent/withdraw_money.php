<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }

require '../includes/db.php'; 

$logged_in_user_id = $_SESSION['agent_id']; 

// ১. এজেন্টের রিয়েল ডাটা এবং ব্যালেন্স আনা
$agent_sql = $conn->query("SELECT * FROM agents WHERE user_id = $logged_in_user_id");
if($agent_sql->num_rows == 0) die("Profile Error!");
$agent = $agent_sql->fetch_assoc();

$real_agent_id = $agent['id'];
$main_balance = $agent['balance'];
$comm_balance = $agent['bonus_balance']; // শুধু এই টাকা উইথড্র করা যাবে

$msg = "";

// ২. উইথড্র রিকোয়েস্ট হ্যান্ডলিং
if (isset($_POST['request_withdraw'])) {
    $amount = floatval($_POST['amount']);
    $method = $conn->real_escape_string($_POST['method']);
    $details = $conn->real_escape_string($_POST['details']);

    // ভ্যালিডেশন: এমাউন্ট অবশ্যই কমিশন ব্যালেন্সের সমান বা কম হতে হবে
    if ($amount <= 0) {
        $msg = "<p class='bg-red-600/20 text-red-500 p-3 rounded mb-4 border border-red-600'>টাকার পরিমাণ সঠিক নয়!</p>";
    } 
    elseif ($amount > $comm_balance) {
        $msg = "<p class='bg-red-600/20 text-red-500 p-3 rounded mb-4 border border-red-600'>আপনার পর্যাপ্ত কমিশন ব্যালেন্স নেই! (আছে: ৳$comm_balance)</p>";
    } 
    else {
        // ট্রানজেকশন শুরু
        $conn->begin_transaction();
        try {
            // ১. কমিশন ব্যালেন্স থেকে টাকা কেটে নেওয়া (Lock Funds)
            $conn->query("UPDATE agents SET bonus_balance = bonus_balance - $amount WHERE id=$real_agent_id");

            // ২. উইথড্র টেবিলে ইনসার্ট
            $sql = "INSERT INTO agent_withdrawals (agent_id, amount, method, details, status) VALUES ($real_agent_id, $amount, '$method', '$details', 'pending')";
            $conn->query($sql);

            $conn->commit();
            $msg = "<p class='bg-green-600/20 text-green-500 p-3 rounded mb-4 border border-green-600'>উইথড্র রিকোয়েস্ট সফলভাবে পাঠানো হয়েছে!</p>";
            
            // লাইভ আপডেট
            $comm_balance -= $amount;

        } catch (Exception $e) {
            $conn->rollback();
            $msg = "<p class='bg-red-600/20 text-red-500 p-3 rounded mb-4'>কোথাও সমস্যা হয়েছে, আবার চেষ্টা করুন।</p>";
        }
    }
}

// ৩. উইথড্র হিস্ট্রি ফেচ করা
$withdraw_history = $conn->query("SELECT * FROM agent_withdrawals WHERE agent_id=$real_agent_id ORDER BY created_at DESC LIMIT 10");

// ৪. প্লেয়ার ট্রানজেকশন হিস্ট্রি ফেচ করা (Sent Money Log)
// transactions_fake টেবিল থেকে ডাটা আনা হচ্ছে যেখানে agent_id মিলছে
$player_trx_history = $conn->query("
    SELECT t.*, u.username, u.phone 
    FROM transactions_fake t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.agent_id = $real_agent_id 
    ORDER BY t.created_at DESC LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw & History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Custom Scrollbar for tables */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .glass-panel { background: rgba(0, 43, 43, 0.8); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">
    
    <?php include '../includes/sidebar_agent.php'; ?>
    
    <div class="flex-1 flex flex-col h-full relative w-full">
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center shadow-lg z-20">
            <h1 class="text-lg font-bold text-yellow-500">উইথড্র ও হিস্ট্রি</h1>
            <button onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-gradient-to-r from-purple-900 to-[#002b2b] p-6 rounded-xl border border-purple-500/50 shadow-xl relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-purple-300 text-sm font-bold uppercase tracking-wider">উইথড্রযোগ্য কমিশন</p>
                        <h2 class="text-4xl font-bold text-white mt-2">৳ <?php echo number_format($comm_balance, 2); ?></h2>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-info-circle"></i> শুধুমাত্র এই টাকা উইথড্র করা যাবে</p>
                    </div>
                    <i class="fas fa-coins absolute right-4 bottom-4 text-6xl text-purple-500/20"></i>
                </div>

                <div class="bg-[#002b2b] p-6 rounded-xl border border-gray-700 shadow-xl relative overflow-hidden opacity-75">
                    <div class="relative z-10">
                        <p class="text-gray-400 text-sm font-bold uppercase tracking-wider">মেইন ব্যালেন্স (Asset)</p>
                        <h2 class="text-3xl font-bold text-yellow-500 mt-2">৳ <?php echo number_format($main_balance, 2); ?></h2>
                        <p class="text-xs text-gray-500 mt-2">এটি প্লেয়ারদের পাঠানোর জন্য</p>
                    </div>
                    <i class="fas fa-wallet absolute right-4 bottom-4 text-6xl text-gray-600/20"></i>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-1">
                    <div class="bg-[#002b2b] p-6 rounded-xl border border-gray-700 shadow-2xl">
                        <h3 class="text-xl font-bold mb-4 text-white border-b border-gray-700 pb-2"><i class="fas fa-hand-holding-usd mr-2"></i> কমিশন উইথড্র</h3>
                        
                        <?php echo $msg; ?>
                        
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-gray-400 text-xs font-bold mb-1 uppercase">টাকার পরিমাণ</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-500">৳</span>
                                    <input type="number" name="amount" placeholder="Min 500" class="w-full pl-8 p-3 bg-[#001f1f] rounded border border-gray-600 focus:border-purple-500 focus:outline-none text-white transition" required>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-400 text-xs font-bold mb-1 uppercase">মেথড</label>
                                <select name="method" class="w-full p-3 bg-[#001f1f] rounded border border-gray-600 focus:border-purple-500 text-white transition">
                                    <option value="bkash">বিকাশ</option>
                                    <option value="nagad">নগদ</option>
                                    <option value="rocket">রকেট</option>
                                    <option value="bank">ব্যাংক</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-400 text-xs font-bold mb-1 uppercase">অ্যাকাউন্ট নম্বর</label>
                                <textarea name="details" class="w-full p-3 bg-[#001f1f] rounded border border-gray-600 focus:border-purple-500 focus:outline-none h-24 text-sm" placeholder="নম্বর এবং একাউন্ট টাইপ (Personal/Agent)..." required></textarea>
                            </div>
                            
                            <button type="submit" name="request_withdraw" onclick="return confirm('আপনি কি নিশ্চিত?')" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg shadow-lg transition transform hover:scale-[1.02]">
                                রিকোয়েস্ট পাঠান
                            </button>
                        </form>
                    </div>

                    <div class="mt-8">
                        <h3 class="text-sm font-bold text-gray-400 mb-3 uppercase">আমার উইথড্র রিকোয়েস্ট</h3>
                        <div class="bg-[#002b2b] rounded-xl border border-gray-700 overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-[#001f1f] text-gray-400">
                                    <tr>
                                        <th class="p-3">তারিখ</th>
                                        <th class="p-3">পরিমাণ</th>
                                        <th class="p-3 text-right">অবস্থা</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <?php if($withdraw_history->num_rows > 0): ?>
                                        <?php while($row = $withdraw_history->fetch_assoc()): ?>
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="p-3 text-gray-400"><?php echo date('d M', strtotime($row['created_at'])); ?></td>
                                            <td class="p-3 font-bold text-white">৳<?php echo number_format($row['amount']); ?></td>
                                            <td class="p-3 text-right">
                                                <?php 
                                                    $status_color = match($row['status']) {
                                                        'approved' => 'text-green-400',
                                                        'rejected' => 'text-red-400',
                                                        default => 'text-yellow-400'
                                                    };
                                                ?>
                                                <span class="font-bold <?php echo $status_color; ?> uppercase text-[10px]"><?php echo $row['status']; ?></span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="p-4 text-center text-gray-500">কোনো হিস্ট্রি নেই</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="bg-[#002b2b] rounded-xl border border-gray-700 shadow-2xl overflow-hidden flex flex-col h-full">
                        <div class="p-5 border-b border-gray-700 bg-[#002222] flex justify-between items-center">
                            <h3 class="text-lg font-bold text-white"><i class="fas fa-history mr-2 text-yellow-500"></i> প্লেয়ার লেনদেন হিস্ট্রি</h3>
                            <span class="text-xs text-gray-400 bg-black/30 px-2 py-1 rounded">Last 20 Txn</span>
                        </div>
                        
                        <div class="overflow-x-auto flex-1">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-[#001515] text-gray-400 text-xs uppercase font-bold">
                                    <tr>
                                        <th class="p-4">প্লেয়ার</th>
                                        <th class="p-4">TrxID</th>
                                        <th class="p-4">ধরন</th>
                                        <th class="p-4 text-right">পরিমাণ</th>
                                        <th class="p-4 text-right">সময়</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    <?php if($player_trx_history->num_rows > 0): ?>
                                        <?php while($trx = $player_trx_history->fetch_assoc()): ?>
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="p-4">
                                                <div class="font-bold text-white"><?php echo $trx['username']; ?></div>
                                                <div class="text-xs text-gray-500"><?php echo $trx['phone'] ?? 'N/A'; ?></div>
                                            </td>
                                            <td class="p-4 font-mono text-xs text-yellow-500/80"><?php echo $trx['transaction_id']; ?></td>
                                            <td class="p-4">
                                                <span class="px-2 py-1 rounded text-xs font-bold bg-green-900/50 text-green-400 border border-green-700">
                                                    DEPOSIT
                                                </span>
                                            </td>
                                            <td class="p-4 text-right font-bold text-green-400 font-mono">
                                                + ৳<?php echo number_format($trx['amount']); ?>
                                            </td>
                                            <td class="p-4 text-right text-xs text-gray-400">
                                                <?php echo date('d M h:i A', strtotime($trx['created_at'])); ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="p-10 text-center text-gray-500 flex flex-col items-center">
                                                <i class="fas fa-ghost text-4xl mb-3 opacity-50"></i>
                                                <span>এখনও কোনো লেনদেন হয়নি</span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() { 
            const sb = document.getElementById('agentSidebar');
            const ov = document.getElementById('sidebarOverlay');
            sb.classList.toggle('-translate-x-full'); 
            ov.classList.toggle('hidden'); 
        }
    </script>
</body>
</html>