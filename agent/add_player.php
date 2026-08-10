<?php
session_start();
if (!isset($_SESSION['agent_id'])) { header("Location: login.php"); exit(); }

require '../includes/db.php'; 

// ১. লগইন করা ইউজারের ID থেকে 'agents' টেবিলের আসল ID বের করা
$logged_in_user_id = $_SESSION['agent_id'];
$agent_sql = $conn->query("SELECT id FROM agents WHERE user_id = $logged_in_user_id");

if($agent_sql->num_rows == 0) {
    die("Agent Profile Error! Please contact admin.");
}

$agent_data = $agent_sql->fetch_assoc();
$real_agent_id = $agent_data['id']; // এটি হলো আসল এজেন্ট আইডি (যেমন: 7)

$msg = "";

if (isset($_POST['create'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $raw_password = $_POST['password']; 
    
    // পাসওয়ার্ড এনক্রিপশন (নিরাপত্তার জন্য)
    $hashed_password = password_hash($raw_password, PASSWORD_DEFAULT);

    // ডুপ্লিকেট চেক
    $check = $conn->query("SELECT id FROM users WHERE username='$username' OR phone='$phone'");
    
    if ($check->num_rows > 0) {
        $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4 border border-red-600 text-sm'>ইউজারনেম বা মোবাইল নম্বর ইতিমধ্যে ব্যবহৃত হয়েছে!</div>";
    } else {
        // ২. ডাটাবেসে ইনসার্ট (সঠিক এজেন্ট আইডি এবং ফোন কলাম ব্যবহার করে)
        // referrer_id হিসেবেও আপনার ইউজার আইডি দেওয়া হলো, যাতে রেফারেল ট্র্যাকিং থাকে
        $sql = "INSERT INTO users (username, phone, password, role, balance, agent_id, referrer_id, created_at) 
                VALUES ('$username', '$phone', '$hashed_password', 'player', 0.00, $real_agent_id, $logged_in_user_id, NOW())";
        
        if ($conn->query($sql)) {
            $msg = "<div class='bg-green-600/20 text-green-400 p-3 rounded mb-4 border border-green-600 text-sm'>
                        <i class='fas fa-check-circle'></i> নতুন প্লেয়ার আইডি সফলভাবে তৈরি হয়েছে!
                    </div>";
        } else {
            $msg = "<div class='bg-red-600/20 text-red-400 p-3 rounded mb-4'>কোথাও ভুল হয়েছে: " . $conn->error . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Player</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-[#001515] text-white flex h-screen overflow-hidden font-sans">
    
    <?php include '../includes/sidebar_agent.php'; ?>
    
    <div class="flex-1 flex flex-col h-full relative w-full">
        <header class="md:hidden bg-[#002b2b] border-b border-gray-700 p-4 flex justify-between items-center shadow-lg">
            <h1 class="text-lg font-bold text-yellow-500">নতুন আইডি</h1>
            <button onclick="toggleSidebar()"><i class="fas fa-bars text-2xl"></i></button>
        </header>

        <main class="flex-1 overflow-y-auto p-4 flex items-center justify-center relative">
            
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
                <div class="absolute top-10 right-10 w-72 h-72 bg-yellow-600/10 rounded-full blur-3xl"></div>
                <div class="absolute bottom-10 left-10 w-72 h-72 bg-blue-600/10 rounded-full blur-3xl"></div>
            </div>

            <div class="bg-[#002b2b] p-6 md:p-8 rounded-2xl border border-gray-700 w-full max-w-md shadow-2xl relative z-10">
                
                <div class="mb-6 border-b border-gray-700 pb-4">
                    <h2 class="text-2xl font-bold text-yellow-500"><i class="fas fa-user-plus mr-2"></i> নতুন প্লেয়ার যুক্ত করুন</h2>
                    <p class="text-gray-400 text-xs mt-1">এই আইডিটি অটোমেটিক আপনার আন্ডারে চলে যাবে</p>
                </div>
                
                <?php echo $msg; ?>
                
                <form method="POST" class="space-y-5 mt-2">
                    <div>
                        <label class="block text-gray-400 mb-1 text-sm font-bold">ইউজারনেম</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" placeholder="ex: player123" class="w-full pl-10 p-3 bg-[#001f1f] rounded-lg border border-gray-600 focus:border-yellow-500 focus:outline-none focus:ring-1 focus:ring-yellow-500 text-white transition" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 mb-1 text-sm font-bold">মোবাইল নম্বর</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-mobile-alt"></i></span>
                            <input type="number" name="phone" placeholder="017xxxxxxxx" class="w-full pl-10 p-3 bg-[#001f1f] rounded-lg border border-gray-600 focus:border-yellow-500 focus:outline-none focus:ring-1 focus:ring-yellow-500 text-white transition" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-400 mb-1 text-sm font-bold">পাসওয়ার্ড</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500"><i class="fas fa-lock"></i></span>
                            <input type="text" name="password" placeholder="Password" class="w-full pl-10 p-3 bg-[#001f1f] rounded-lg border border-gray-600 focus:border-yellow-500 focus:outline-none focus:ring-1 focus:ring-yellow-500 text-white transition" required>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1">* প্লেয়ারকে এই পাসওয়ার্ডটি দিন</p>
                    </div>
                    
                    <button type="submit" name="create" class="w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold py-3 rounded-lg shadow-lg transform transition hover:scale-[1.02]">
                        একাউন্ট তৈরি করুন
                    </button>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() { 
            const sb = document.getElementById('agentSidebar');
            const ov = document.getElementById('sidebarOverlay');
            if(sb) sb.classList.toggle('-translate-x-full'); 
            if(ov) ov.classList.toggle('hidden'); 
        }
    </script>
</body>
</html>