<?php
session_start();
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }
require '../includes/db.php'; // পাথ ঠিক আছে কিনা দেখে নিবেন

$logged_in_user_id = $_SESSION['agent_id']; // এটি users টেবিলের ID (যেমন: 29)

// ১. ইউজারের নাম বের করা (User Table থেকে)
$user_info = $conn->query("SELECT username FROM users WHERE id=$logged_in_user_id")->fetch_assoc();
$username = $user_info['username'];

// ২. এজেন্টের আসল প্রোফাইল বের করা (Agents Table থেকে)
// এখান থেকেই আমরা ব্যালেন্স এবং রেফার কোড পাবো
$agent_sql = $conn->query("SELECT * FROM agents WHERE user_id=$logged_in_user_id");

if ($agent_sql->num_rows == 0) {
    die("Agent profile not found. Please contact admin.");
}

$agent_data = $agent_sql->fetch_assoc();
$real_agent_id = $agent_data['id'];        // এজেন্টের আসল ID
$agent_balance = $agent_data['balance'];   // এজেন্টের আসল ব্যালেন্স
$ref_code = $agent_data['referral_code'];  // রেফারেল কোড

// ৩. প্লেয়ার পরিসংখ্যান বের করা
// প্লেয়ার টেবিলের agent_id কলামে real_agent_id থাকে
$total_players = $conn->query("SELECT COUNT(*) FROM users WHERE agent_id=$real_agent_id")->fetch_row()[0];
$total_player_bal = $conn->query("SELECT SUM(balance) FROM users WHERE agent_id=$real_agent_id")->fetch_row()[0];

// ৪. রেফারেল লিংক জেনারেট
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$ref_link = "$protocol://$domain/player/signup.php?ref=$ref_code";

?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>.glass { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); }</style>
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">

    <?php include '../includes/sidebar_agent.php'; ?>

    <div class="flex-1 flex flex-col h-full relative w-full">
        
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center z-30 shadow-md">
            <h1 class="text-lg font-bold text-yellow-500">এজেন্ট ড্যাশবোর্ড</h1>
            <button onclick="toggleSidebar()" class="text-white focus:outline-none">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            
            <div class="mb-6 flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold">স্বাগতম, <span class="text-yellow-500"><?php echo htmlspecialchars($username); ?></span></h1>
                    <p class="text-gray-400 text-sm mt-1 flex items-center gap-2">
                        স্ট্যাটাস: 
                        <span class="bg-green-900 text-green-400 px-2 py-0.5 rounded text-xs font-bold uppercase animate-pulse">Active</span>
                    </p>
                </div>
                
                <div class="flex gap-4 self-start md:self-center flex-wrap">
                    <div class="glass px-5 py-3 rounded-xl border border-yellow-600/30 shadow-lg bg-yellow-900/10">
                        <span class="text-gray-300 text-xs uppercase font-bold block mb-1">মেইন ব্যালেন্স</span>
                        <span class="text-2xl font-bold text-yellow-400">৳ <?php echo number_format($agent_balance, 2); ?></span>
                    </div>
                </div>
            </div>

            <div class="mb-8 bg-gradient-to-r from-[#002b2b] to-[#001f1f] border border-gray-700 rounded-xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fas fa-share-alt text-8xl"></i></div>
                
                <div class="relative z-10">
                    <h3 class="text-lg font-bold text-white mb-4 border-b border-gray-700 pb-2 inline-block">মার্কেটিং টুলস</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="text-gray-400 text-xs uppercase font-bold mb-1 block">আপনার রেফারেল কোড</label>
                            <div class="flex items-center gap-2">
                                <div class="bg-black/40 border border-gray-600 rounded px-4 py-2 text-xl font-mono text-yellow-500 font-bold tracking-widest">
                                    <?php echo $ref_code; ?>
                                </div>
                                <button onclick="copyText('<?php echo $ref_code; ?>')" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-3 rounded border border-gray-600 transition">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="text-gray-400 text-xs uppercase font-bold mb-1 block">রেজিস্ট্রেশন লিংক</label>
                            <div class="flex items-center gap-2">
                                <input type="text" value="<?php echo $ref_link; ?>" readonly class="w-full bg-black/40 border border-gray-600 rounded px-4 py-2.5 text-sm text-gray-300 focus:outline-none">
                                <button onclick="copyText('<?php echo $ref_link; ?>')" class="bg-yellow-600 hover:bg-yellow-700 text-black px-4 py-2.5 rounded font-bold transition whitespace-nowrap">
                                    <i class="fas fa-link mr-1"></i> কপি লিংক
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-8">
                
                <div class="bg-[#002b2b] p-6 rounded-xl border-l-4 border-blue-500 shadow-lg relative overflow-hidden group hover:bg-[#003333] transition">
                    <div class="relative z-10">
                        <p class="text-gray-400 text-sm font-medium uppercase">মোট প্লেয়ার</p>
                        <h3 class="text-3xl font-bold text-white mt-2"><?php echo $total_players; ?></h3>
                        <p class="text-xs text-gray-500 mt-1">আপনার অধীনে নিবন্ধিত</p>
                    </div>
                    <i class="fas fa-users absolute right-4 bottom-4 text-5xl text-blue-500/10 group-hover:scale-110 transition"></i>
                </div>
                
                <div class="bg-[#002b2b] p-6 rounded-xl border-l-4 border-green-500 shadow-lg relative overflow-hidden group hover:bg-[#003333] transition">
                    <div class="relative z-10">
                        <p class="text-gray-400 text-sm font-medium uppercase">প্লেয়ারদের অ্যাসেট</p>
                        <h3 class="text-3xl font-bold text-green-400 mt-2">৳ <?php echo number_format($total_player_bal ?? 0, 2); ?></h3>
                        <p class="text-xs text-gray-500 mt-1">প্লেয়ারদের বর্তমান ব্যালেন্স</p>
                    </div>
                    <i class="fas fa-chart-line absolute right-4 bottom-4 text-5xl text-green-500/10 group-hover:scale-110 transition"></i>
                </div>
                
                <div class="bg-gradient-to-br from-gray-800 to-[#002b2b] p-6 rounded-xl border border-gray-700 shadow-lg flex flex-col justify-center items-center text-center">
                    <div class="w-12 h-12 bg-yellow-500/20 text-yellow-500 rounded-full flex items-center justify-center text-2xl mb-2">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="text-white font-bold">কুইক অ্যাকশন</h3>
                    <div class="flex gap-2 mt-3 w-full">
                        <a href="requests.php" class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-black text-xs font-bold py-2 rounded">ডিপোজিট</a>
                        <a href="withdraw_money.php" class="flex-1 bg-gray-600 hover:bg-gray-500 text-white text-xs font-bold py-2 rounded">উইথড্র</a>
                    </div>
                </div>

            </div>

            <h3 class="text-lg font-bold mb-4 border-b border-gray-700 pb-2 text-gray-300">মেনু শর্টকাট</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="add_player.php" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition border border-gray-700 group">
                    <div class="w-12 h-12 bg-blue-600/20 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-600 group-hover:text-white transition">
                        <i class="fas fa-user-plus text-xl"></i>
                    </div>
                    <span class="text-sm font-medium">নতুন আইডি</span>
                </a>
                <a href="transfer.php" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition border border-gray-700 group">
                    <div class="w-12 h-12 bg-green-600/20 text-green-500 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-green-600 group-hover:text-white transition">
                        <i class="fas fa-paper-plane text-xl"></i>
                    </div>
                    <span class="text-sm font-medium">মানি সেন্ড</span>
                </a>
                <a href="players.php" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition border border-gray-700 group">
                    <div class="w-12 h-12 bg-indigo-600/20 text-indigo-500 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-indigo-600 group-hover:text-white transition">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <span class="text-sm font-medium">প্লেয়ার লিস্ট</span>
                </a>
                 <a href="agent_referral.php" class="bg-gray-800 hover:bg-gray-700 p-4 rounded-lg text-center transition border border-gray-700 group">
                    <div class="w-12 h-12 bg-pink-600/20 text-pink-500 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:bg-pink-600 group-hover:text-white transition">
                        <i class="fas fa-qrcode text-xl"></i>
                    </div>
                    <span class="text-sm font-medium">রেফার টুলস</span>
                </a>
            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar(){
            const sb = document.getElementById('agentSidebar');
            const ov = document.getElementById('sidebarOverlay');
            if(sb) sb.classList.toggle('-translate-x-full');
            if(ov) ov.classList.toggle('hidden');
        }

        function copyText(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert("Copied: " + text);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        }
    </script>
</body>
</html>