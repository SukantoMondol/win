<?php
session_start();
// ১. ডাটাবেস কানেকশন (সরাসরি ইনক্লুড)
require __DIR__ . '/../includes/db.php'; 

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit(); 
}

$uid = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$site_name = $settings['site_name'] ?? 'SHA75';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rebate</title> <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* 1xBet White-Blue Theme */
        body { background-color: #f0f3f8; color: #333; font-family: 'Roboto', sans-serif; padding-bottom: 90px; }
        .header-premium { background: linear-gradient(90deg, #1a5c92 0%, #20b1ff 100%); border-bottom: 2px solid #154b77; }
        
        /* ট্যাব ডিজাইন */
        .tab-btn {
            flex: 1; text-align: center; padding: 14px 0;
            font-size: 13px; font-weight: bold; color: #1a5c92;
            background: #fff; border-bottom: 2px solid transparent; transition: 0.3s;
        }
        .tab-btn.active { color: #20b1ff; border-bottom-color: #20b1ff; background: #f8fafc; }

        /* টেবিল কার্ড ডিজাইন */
        .rebate-card { background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .table-header { background: #e0f2fe; color: #1a5c92; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        
        .btn-claim { 
            background: linear-gradient(180deg, #1a5c92 0%, #154b77 100%); 
            color: #fff; font-weight: bold; box-shadow: 0 4px 10px rgba(26, 92, 146, 0.2); 
        }
        .btn-claim:disabled { background: #e2e8f0; color: #94a3b8; box-shadow: none; }

    </style>
</head>
<body>

    <div class="header-premium p-4 flex items-center text-white sticky top-0 z-50 shadow-md">
        <a href="account.php" class="mr-4 active:scale-90 transition"><i class="fas fa-chevron-left text-xl"></i></a>
        <h1 class="text-sm font-black uppercase tracking-widest text-white">Rebate Records</h1>
    </div>

    <div class="flex bg-white shadow-sm sticky top-[58px] z-40">
        <a href="#" class="tab-btn">Manual Rebate</a>
        <a href="#" class="tab-btn active">Rebate History</a>
    </div>

    <div class="p-4">
        <div class="rebate-card">
            <div class="flex justify-between px-5 py-3 table-header">
                <span>Date</span>
                <span>Amount</span>
            </div>

            <div class="divide-y divide-gray-100">
                <div class="flex justify-between px-5 py-4 text-sm hover:bg-gray-50 transition">
                    <span class="text-gray-500 font-medium"><?php echo date('Y-m-d'); ?></span>
                    <span class="font-bold text-[#1a5c92]">৳ 0.0000</span>
                </div>
                <div class="flex justify-between px-5 py-4 text-sm hover:bg-gray-50 transition">
                    <span class="text-gray-500 font-medium"><?php echo date('Y-m-d', strtotime('-1 day')); ?></span>
                    <span class="font-bold text-[#1a5c92]">৳ 0.0000</span>
                </div>
            </div>

            <div class="p-5 bg-gray-50 border-t border-gray-100">
                <p class="text-[10px] text-gray-400 mb-3 text-center uppercase font-bold tracking-tighter italic">Minimum claim amount: ৳ 1.00</p>
                <button class="w-full btn-claim py-3.5 rounded-xl text-sm uppercase font-black tracking-widest active:scale-95 transition" disabled>
                    Claim Rebate Now
                </button>
            </div>
        </div>

        <div class="mt-6 bg-[#e0f2fe]/50 border border-[#1a5c92]/10 p-5 rounded-xl shadow-sm">
            <div class="flex items-center gap-2 mb-3">
                <i class="fas fa-info-circle text-[#1a5c92]"></i>
                <h4 class="text-xs font-black text-[#1a5c92] uppercase tracking-tighter">Rebate Instructions</h4>
            </div>
            <ul class="text-[11px] text-gray-500 leading-relaxed list-disc ml-4 font-medium space-y-1">
                <li>Rebate is calculated automatically every day based on your activity.</li>
                <li>The rebate amount is based on your total valid turnover on the platform.</li>
                <li>Please contact our 24/7 Live Support if you have any questions regarding your rebate.</li>
            </ul>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>

</body>
</html>