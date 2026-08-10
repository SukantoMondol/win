<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }

require '../includes/db.php'; 

$logged_in_user_id = $_SESSION['agent_id']; 

// ১. এজেন্ট ডাটা এবং সেটিংস আনা
$sys = $conn->query("SELECT deposit_comm_percent FROM system_settings LIMIT 1")->fetch_assoc();
$default_comm = $sys['deposit_comm_percent'];

$agent_sql = $conn->query("SELECT * FROM agents WHERE user_id = $logged_in_user_id");
if($agent_sql->num_rows == 0) die("Agent Error! Contact Admin.");
$agent = $agent_sql->fetch_assoc();

$real_agent_id = $agent['id'];
$agent_bal = $agent['balance'];
$commission_rate = ($agent['custom_deposit_rate'] > 0) ? $agent['custom_deposit_rate'] : $default_comm;

// ২. এজেন্টের সকল প্লেয়ার ফেচ করা (ড্রপডাউনের জন্য)
$players_sql = $conn->query("SELECT id, username, phone, balance FROM users WHERE agent_id = $real_agent_id ORDER BY username ASC");

$msg = "";

// ৩. টাকা পাঠানোর লজিক
if (isset($_POST['transfer'])) {
    $uid = intval($_POST['user_id']);
    $amount = floatval($_POST['amount']);
    
    // ভ্যালিডেশন
    if ($amount <= 0) {
        $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4 border border-red-600 text-sm'>টাকার পরিমাণ সঠিক নয়!</div>";
    } 
    elseif ($agent_bal < $amount) {
        $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4 border border-red-600 text-sm'>আপনার পর্যাপ্ত ব্যালেন্স নেই! (আছে: ৳$agent_bal)</div>";
    } 
    else {
        // প্লেয়ার ভেরিফিকেশন
        $player_chk = $conn->query("SELECT username, phone FROM users WHERE id=$uid AND agent_id=$real_agent_id");
        
        if ($player_chk->num_rows > 0) {
            $player_data = $player_chk->fetch_assoc();
            $commission_amount = ($amount * $commission_rate) / 100;
            $trx_id = "AGT" . strtoupper(uniqid()); // ইউনিক ট্রানজেকশন আইডি

            // --- ট্রানজেকশন শুরু ---
            $conn->begin_transaction();
            try {
                // ১. প্লেয়ারের ব্যালেন্স আপডেট (+)
                $conn->query("UPDATE users SET balance = balance + $amount WHERE id=$uid");
                
                // ২. এজেন্টের ব্যালেন্স আপডেট (ব্যালেন্স -, বোনাস +)
                $conn->query("UPDATE agents SET balance = balance - $amount, bonus_balance = bonus_balance + $commission_amount WHERE id=$real_agent_id");
                
                // ৩. প্লেয়ারের হিস্ট্রি আপডেট (Transactions Fake Table - Approved)
                // এটি প্লেয়ারের ড্যাশবোর্ডে ডিপোজিট হিসেবে দেখাবে
                $sql_player_log = "INSERT INTO transactions_fake (user_id, agent_id, type, amount, method, transaction_id, status, created_at) 
                                   VALUES ($uid, $real_agent_id, 'deposit', $amount, 'agent_transfer', '$trx_id', 'approved', NOW())";
                $conn->query($sql_player_log);

                // ৪. এজেন্টের লেজার আপডেট (হিসাব রাখার জন্য)
                $desc = "Transfer to Player: " . $player_data['username'];
                $conn->query("INSERT INTO agent_transactions (agent_id, type, amount, description) VALUES ($real_agent_id, 'sell', $amount, '$desc')");

                // সব ঠিক থাকলে কমিট
                $conn->commit();
                
                $msg = "<div class='bg-green-600/20 text-green-400 p-4 rounded mb-4 border border-green-600'>
                            <h4 class='font-bold text-lg'><i class='fas fa-check-circle'></i> সফল!</h4>
                            <p class='text-sm mt-1'>৳{$amount} সফলভাবে পাঠানো হয়েছে।</p>
                            <div class='mt-2 pt-2 border-t border-green-600/30 text-xs text-yellow-300'>
                                কমিশন অর্জিত: ৳{$commission_amount} ({$commission_rate}%)
                            </div>
                        </div>";
                
                // লাইভ আপডেট (পেজ রিফ্রেশ না করে ভ্যালু আপডেট দেখানোর জন্য)
                $agent_bal -= $amount;

            } catch (Exception $e) {
                $conn->rollback();
                $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4'>ডাটাবেস এরর: " . $e->getMessage() . "</div>";
            }
        } else {
             $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4'>প্লেয়ার খুঁজে পাওয়া যায়নি!</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Money - Agent</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* Select2 Dark Mode Customization */
        .select2-container--default .select2-selection--single {
            background-color: #001f1f;
            border: 1px solid #4b5563;
            color: white;
            height: 50px;
            display: flex;
            align-items: center;
            border-radius: 0.5rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: white;
            padding-left: 10px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 48px;
        }
        .select2-dropdown {
            background-color: #002b2b;
            border: 1px solid #4b5563;
            color: white;
        }
        .select2-results__option--highlighted[aria-selected] {
            background-color: #ca8a04; /* Yellow-600 */
        }
        .select2-search__field {
            background-color: #001515 !important;
            color: white !important;
        }
    </style>
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">
    
    <?php include '../includes/sidebar_agent.php'; ?>
    
    <div class="flex-1 flex flex-col h-full relative w-full">
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center shadow-lg">
            <h1 class="text-lg font-bold text-yellow-500">মানি সেন্ড</h1>
            <button onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 flex items-center justify-center relative">
            
            <div class="bg-[#002b2b] p-6 md:p-8 rounded-2xl border border-gray-700 w-full max-w-lg shadow-2xl relative z-10">
                
                <div class="flex justify-between items-start mb-6 border-b border-gray-700 pb-4">
                    <div>
                        <h2 class="text-2xl font-bold text-white">টাকা পাঠান</h2>
                        <p class="text-gray-400 text-xs mt-1">প্লেয়ারের একাউন্টে সরাসরি টাকা জমা হবে</p>
                    </div>
                    <div class="text-right">
                        <span class="block text-xs text-gray-400">আমার ব্যালেন্স</span>
                        <span class="text-xl font-bold text-yellow-500">৳ <?php echo number_format($agent_bal, 2); ?></span>
                    </div>
                </div>

                <?php echo $msg; ?>

                <form method="POST" class="space-y-6">
                    
                    <div>
                        <label class="block text-gray-400 mb-2 text-sm font-bold">প্লেয়ার সিলেক্ট করুন</label>
                        <select name="user_id" id="playerSelect" class="w-full" required>
                            <option value="">-- প্লেয়ার খুঁজুন (নাম বা নাম্বার) --</option>
                            <?php 
                            if ($players_sql->num_rows > 0) {
                                while($p = $players_sql->fetch_assoc()) {
                                    echo "<option value='{$p['id']}'>{$p['username']} ({$p['phone']}) - Bal: ৳{$p['balance']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-400 mb-2 text-sm font-bold">পরিমাণ (BDT)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-money-bill-wave"></i></span>
                            <input type="number" name="amount" id="amountInput" oninput="calcComm()" placeholder="Min: 10" class="w-full pl-10 p-3 bg-[#001f1f] rounded-lg border border-gray-600 focus:border-yellow-500 focus:outline-none text-white transition" required>
                        </div>
                        
                        <div id="commPreview" class="mt-3 bg-gray-800/50 p-2 rounded border border-gray-600 hidden flex justify-between items-center text-xs">
                            <span class="text-gray-400">এজেন্ট কমিশন (<?php echo $commission_rate; ?>%):</span>
                            <span class="text-green-400 font-bold">+ ৳<span id="commAmount">0.00</span></span>
                        </div>
                    </div>

                    <button type="submit" name="transfer" onclick="return confirm('আপনি কি নিশ্চিত যে আপনি টাকা পাঠাতে চান?')" class="w-full bg-gradient-to-r from-yellow-600 to-yellow-500 hover:from-yellow-500 hover:to-yellow-400 text-black font-bold py-3.5 rounded-lg shadow-lg transform transition hover:scale-[1.01] flex justify-center items-center gap-2">
                        <i class="fas fa-paper-plane"></i> টাকা পাঠান
                    </button>

                </form>
            </div>
        </main>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Sidebar Toggle
        function toggleSidebar() { 
            const sb = document.getElementById('agentSidebar');
            const ov = document.getElementById('sidebarOverlay');
            sb.classList.toggle('-translate-x-full'); 
            ov.classList.toggle('hidden'); 
        }

        // Initialize Select2 (Searchable Dropdown)
        $(document).ready(function() {
            $('#playerSelect').select2({
                placeholder: "-- প্লেয়ার খুঁজুন --",
                allowClear: true,
                width: '100%' // Fix for Tailwind
            });
        });

        // Live Commission Calculation
        function calcComm() {
            const amount = document.getElementById('amountInput').value;
            const rate = <?php echo $commission_rate; ?>;
            const preview = document.getElementById('commPreview');
            const commSpan = document.getElementById('commAmount');

            if(amount > 0) {
                const commission = (amount * rate) / 100;
                commSpan.innerText = commission.toFixed(2);
                preview.classList.remove('hidden');
            } else {
                preview.classList.add('hidden');
            }
        }
    </script>
</body>
</html>