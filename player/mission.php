<?php
session_start();
// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল ফলব্যাক রিমুভড)
require __DIR__ . '/../includes/db.php'; 

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit(); 
}

$uid = $_SESSION['user_id'];
// ইউজারের লেটেস্ট ডাটা এবং সেটিংস সংগ্রহ
$user_data = $conn->query("SELECT balance, username FROM users WHERE id=$uid")->fetch_assoc();
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();

$site_name = $settings['site_name'] ?? 'SHA75';
// 1xBet Style Primary Blue
$primary = $settings['theme_primary'] ?? '#154b77'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Daily Missions</title> 
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #0a1e29; color: #fff; padding-bottom: 90px; }
        .header-premium { background: linear-gradient(90deg, #102b3f 0%, #1a5c92 100%); border-bottom: 2px solid #20b1ff; }
        .mission-card { background: #102b3f; border: 1px solid #1a3a4d; border-radius: 15px; position: relative; transition: 0.3s; }
        .mission-card:active { transform: scale(0.98); }
        .progress-bar-bg { background: #07151e; border-radius: 10px; overflow: hidden; }
        /* Progress Bar changed to Green Gradient to match Bottom Nav */
        .progress-fill { background: linear-gradient(90deg, #1de9b6 0%, #00897b 100%); transition: width 0.5s ease-in-out; }
        .tab-btn { font-size: 13px; font-weight: 700; color: #8292a2; position: relative; padding-bottom: 8px; }
        /* Active Tab indicator changed to Green */
        .tab-active { color: #1de9b6; }
        .tab-active::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: #1de9b6; border-radius: 5px; }
        .accent-text { color: #1de9b6; }
        .glass-effect { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); }
    </style>
</head>
<body>
    
    <div class="header-premium p-4 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center">
            <a href="account.php" class="mr-4 active:scale-90 transition"><i class="fas fa-chevron-left text-xl"></i></a>
            <h1 class="text-lg font-black uppercase italic tracking-widest">Missions</h1>
        </div>
        <i class="fas fa-info-circle text-gray-400"></i>
    </div>

    <div class="relative bg-gradient-to-br from-[#154b77] to-[#0a1e29] p-6 border-b border-[#1a3a4d] flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full border-2 border-[#1de9b6] p-0.5 shadow-lg shadow-[#1de9b6]/20">
                <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRk6A5nSOfzB7G1KWh-o_hM-iY9KWhV_Q5A-A&s" class="w-full h-full object-cover rounded-full">
            </div>
            <div>
                <p class="text-[10px] uppercase font-bold text-blue-300 tracking-tighter">Account Balance</p>
                <h2 class="text-2xl font-black accent-text">৳ <?php echo number_format($user_data['balance'] ?? 0, 2); ?></h2>
            </div>
        </div>
        <div class="text-right">
            <div class="bg-black/30 px-3 py-1 rounded-full border border-white/10 text-[10px] font-bold">
                <i class="fas fa-star accent-text mr-1"></i> VIP Level 1
            </div>
        </div>
    </div>

    <div class="flex justify-around bg-[#102b3f] p-4 border-b border-[#1a3a4d] sticky top-[60px] z-40">
        <button class="tab-btn tab-active uppercase"><i class="fas fa-rocket mr-1"></i> Active</button>
        <button class="tab-btn uppercase"><i class="far fa-clock mr-1"></i> Upcoming</button>
        <button class="tab-btn uppercase"><i class="fas fa-check-circle mr-1"></i> Finished</button>
    </div>

    <div class="p-4 space-y-5">
        
        <div class="mission-card p-5 shadow-2xl relative">
            <div class="flex justify-between items-start mb-4">
                <div class="flex gap-4">
                    <div class="w-14 h-14 bg-gradient-to-br from-[#1de9b6] to-[#00897b] rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-[#1de9b6]/10">
                        <i class="fas fa-medal"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-sm uppercase tracking-tight">Free Spin Master Mission</h3>
                        <p class="text-[10px] text-gray-500 mt-1 uppercase font-bold">Expires: <span class="text-gray-300">2026.12.31</span></p>
                        <div class="flex items-center gap-1 mt-1">
                           <i class="fas fa-hourglass-half text-[10px] accent-text"></i>
                           <span id="countdown" class="text-[10px] font-mono accent-text">19:53:27</span>
                        </div>
                    </div>
                </div>
                <button class="px-4 py-1.5 rounded-lg glass-effect border border-white/10 text-[10px] font-bold uppercase tracking-tighter">Rules</button>
            </div>

            <div class="mb-4 bg-black/20 rounded-lg p-2 flex items-center justify-between border border-white/5">
                <span class="text-[10px] font-bold text-gray-400 uppercase">Mission Reward:</span>
                <span class="text-xs font-black accent-text">৳ 500.00 BONUS</span>
            </div>

            <div class="mt-4">
                <div class="flex justify-between text-[11px] font-black uppercase mb-2">
                    <span class="text-blue-300">Progress: <span class="text-white">0 / 3 Games</span></span>
                    <span class="accent-text">0%</span>
                </div>
                <div class="w-full progress-bar-bg h-3 shadow-inner">
                    <div class="progress-fill h-full rounded-full" style="width: 5%"></div>
                </div>
            </div>

            <button class="w-full mt-5 bg-gradient-to-b from-[#1de9b6] to-[#00897b] text-white font-black text-xs py-3 rounded-xl uppercase tracking-widest shadow-lg active:scale-95 transition">
                Start Mission Now
            </button>
        </div>

        <div class="glass-effect rounded-2xl p-4 border border-white/10 mt-6">
            <div class="flex items-center gap-3 mb-2">
                <i class="fas fa-shield-alt accent-text"></i>
                <h4 class="text-xs font-bold uppercase">Mission Instructions</h4>
            </div>
            <p class="text-[10px] text-gray-400 leading-relaxed">
                Complete each mission to receive a bonus directly into your main balance. You must click the 'Start Mission' button before beginning. Please review the rules for each game before playing.
            </p>
        </div>

    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        // Simple Countdown Logic
        function startTimer(duration, display) {
            var timer = duration, hours, minutes, seconds;
            setInterval(function () {
                hours = parseInt(timer / 3600, 10);
                minutes = parseInt((timer % 3600) / 60, 10);
                seconds = parseInt(timer % 60, 10);

                hours = hours < 10 ? "0" + hours : hours;
                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = hours + ":" + minutes + ":" + seconds;

                if (--timer < 0) {
                    timer = duration;
                }
            }, 1000);
        }

        window.onload = function () {
            var display = document.querySelector('#countdown');
            startTimer(71607, display); 
        };
    </script>
</body>
</html>