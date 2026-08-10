<?php
session_start();
// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল কানেকশন রিমুভড)
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit(); 
}

$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#034C44';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Security Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding-bottom: 90px; }
    </style>
</head>
<body class="bg-gray-100">

    <div class="p-4 flex items-center text-white sticky top-0 z-50 shadow-md" style="background-color: <?php echo $primary; ?>;">
        <a href="account.php" class="mr-4 p-1"><i class="fas fa-chevron-left text-xl"></i></a>
        <h1 class="text-lg font-bold uppercase tracking-tight">Security Center</h1>
    </div>

    <div class="m-4 p-6 bg-white rounded-xl shadow-sm flex items-center gap-6 border border-gray-100">
        <div class="relative w-20 h-20 flex items-center justify-center">
            <div class="w-full h-full rounded-full border-4 border-blue-100 border-t-blue-500 animate-spin absolute" style="animation-duration: 5s;"></div>
            <span class="text-xl font-bold text-blue-600">66%</span>
        </div>
        <div>
            <h2 class="font-bold text-gray-800 flex items-center gap-2">Security: Medium <i class="fas fa-bolt text-yellow-400"></i></h2>
            <p class="text-[10px] text-gray-400 mt-1 font-medium">Last login: <?php echo date('Y-m-d H:i'); ?></p>
        </div>
    </div>

    <p class="text-center text-[11px] text-red-500 mb-5 font-bold px-4 uppercase tracking-tighter italic">Your account security level is medium, please improve your info.</p>

    <div class="bg-white mx-4 rounded-xl shadow-sm divide-y divide-gray-100 border border-gray-100 overflow-hidden">
        
        <a href="personal_info.php" class="flex items-center justify-between p-5 hover:bg-gray-50 active:bg-gray-100 transition">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                    <i class="far fa-user text-blue-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-700 flex items-center gap-2 text-sm">Personal Info <i class="fas fa-exclamation-circle text-red-500 text-[10px]"></i></h3>
                    <p class="text-[11px] text-gray-400 font-medium">Complete your personal details</p>
                </div>
            </div>
            <i class="fas fa-chevron-right text-gray-300 text-sm"></i>
        </a>

        <a href="withdraw.php" class="flex items-center justify-between p-5 hover:bg-gray-50 active:bg-gray-100 transition">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center">
                    <i class="fas fa-wallet text-green-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-700 flex items-center gap-2 text-sm">Bind Wallet <i class="fas fa-check-circle text-green-500 text-[10px]"></i></h3>
                    <p class="text-[11px] text-gray-400 font-medium">Add payment details for withdrawals</p>
                </div>
            </div>
            <i class="fas fa-chevron-right text-gray-300 text-sm"></i>
        </a>

        <a href="change_password.php" class="flex items-center justify-between p-5 hover:bg-gray-50 active:bg-gray-100 transition">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center">
                    <i class="fas fa-lock text-purple-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-700 flex items-center gap-2 text-sm">Change Password <i class="fas fa-check-circle text-green-500 text-[10px]"></i></h3>
                    <p class="text-[11px] text-gray-400 font-medium">Secure your login credentials</p>
                </div>
            </div>
            <i class="fas fa-chevron-right text-gray-300 text-sm"></i>
        </a>

        <a href="../logout.php" class="flex items-center justify-between p-5 hover:bg-red-50 active:bg-red-100 transition">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
                    <i class="fas fa-power-off text-red-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-700 text-sm">Logout</h3>
                    <p class="text-[11px] text-gray-400 font-medium">Securely exit your session</p>
                </div>
            </div>
            <i class="fas fa-chevron-right text-gray-300 text-sm"></i>
        </a>

    </div>

    <?php include 'bottom_nav.php'; ?>

</body>
</html>