<?php
session_start();
// সরাসরি ডাটাবেস কানেকশন (No Fallback)
require __DIR__ . '/../includes/db.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Data Fetch Logic (Placeholder)
$running_spins = []; 
$completed_spins = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Free Spin | SHA75</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        body { 
            background-color: #f0f3f8; 
            color: #333; 
            font-family: 'Roboto', sans-serif; 
            padding-bottom: 80px;
        }

        /* --- 1xBet Style Header --- */
        .header-bg { 
            background: linear-gradient(90deg, #1a5c92 0%, #20b1ff 100%); 
            padding: 15px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            position: relative;
            border-bottom: 2px solid #154b77;
        }
        .close-btn {
            position: absolute;
            right: 15px;
            font-size: 18px;
            color: #fff;
        }

        /* --- Tabs (White-Blue Style) --- */
        .tabs-container {
            display: flex;
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .tab-btn {
            flex: 1;
            text-align: center;
            padding: 14px 0;
            font-size: 13px;
            font-weight: bold;
            color: #1a5c92;
            cursor: pointer;
            position: relative;
            text-transform: uppercase;
        }
        .tab-btn.active {
            color: #20b1ff;
        }
        /* Blue Underline Indicator */
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #20b1ff;
        }

        /* --- No Data State --- */
        .no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 350px;
            opacity: 0.5;
        }
        .clipboard-icon {
            width: 80px;
            margin-bottom: 15px;
            filter: grayscale(1) opacity(0.5);
        }

        /* Content Logic */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Bottom Nav (White Style) */
        .bottom-nav { background-color: #ffffff; border-top: 1px solid #e2e8f0; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
        .nav-item { color: #94a3b8; } 
        .nav-item.active { color: #1a5c92; }
    </style>
</head>
<body>

    <div class="header-bg">
        <h1 class="text-white font-bold text-base tracking-wide uppercase">Free Spin</h1>
        <a href="account.php" class="close-btn active:scale-90 transition"><i class="fas fa-times"></i></a>
    </div>

    <div class="tabs-container sticky top-0 z-40">
        <div class="tab-btn active" onclick="switchTab('running', this)">Running</div>
        <div class="tab-btn" onclick="switchTab('completed', this)">Completed</div>
    </div>

    <div id="running" class="tab-content active p-4">
        <?php if (!empty($running_spins)): ?>
            <?php else: ?>
            <div class="no-data">
                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486747.png" class="clipboard-icon">
                <p class="text-xs text-[#1a5c92] font-bold uppercase tracking-tighter">No Active Spins Found</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="completed" class="tab-content p-4">
        <?php if (!empty($completed_spins)): ?>
            <?php else: ?>
            <div class="no-data">
                <img src="https://cdn-icons-png.flaticon.com/512/7486/7486747.png" class="clipboard-icon">
                <p class="text-xs text-[#1a5c92] font-bold uppercase tracking-tighter">History is Empty</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="fixed bottom-0 left-0 w-full h-[65px] bottom-nav z-50 flex justify-around items-center px-4">
        <a href="index.php" class="flex flex-col items-center justify-center nav-item">
            <i class="fas fa-home text-xl mb-1"></i>
            <span class="text-[9px] font-bold">Home</span>
        </a>
        <a href="promotions.php" class="flex flex-col items-center justify-center nav-item">
            <i class="fas fa-gift text-xl mb-1"></i>
            <span class="text-[9px] font-bold">Promo</span>
        </a>
        <a href="deposit.php" class="relative -top-4">
            <div class="w-14 h-14 bg-gradient-to-b from-[#1a5c92] to-[#154b77] rounded-full flex items-center justify-center text-white border-4 border-[#f0f3f8] shadow-lg active:scale-95 transition">
                <i class="fas fa-wallet text-xl"></i>
            </div>
        </a>
        <a href="account.php" class="flex flex-col items-center justify-center nav-item active">
            <i class="fas fa-user-circle text-xl mb-1"></i>
            <span class="text-[9px] font-bold">Account</span>
        </a>
    </div>

    <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>

</body>
</html>