<?php
session_start();
require '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$uid = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

// কালার সেটআপ
$primary = $settings['theme_primary'] ?? '#034C44'; 
$text_col = $settings['theme_text'] ?? '#FFFFFF';
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>প্রোফাইল</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #111; color: <?php echo $text_col; ?>; font-family: 'Segoe UI', sans-serif; }
        .grid-item { background: #1a1a1a; border-radius: 12px; transition: 0.2s; }
        .grid-item:active { transform: scale(0.95); background: #252525; }
        .icon-circle { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
    </style>
</head>
<body class="pb-20">

    <?php include '../includes/sidebar_player.php'; ?>

    <div class="p-4 flex justify-between items-center bg-[#1a1a1a] shadow-md sticky top-0 z-40">
        <h2 class="text-lg font-bold text-yellow-500">মেম্বার সেন্টার</h2>
        <div class="flex gap-3">
            <span class="text-sm">ID: <?php echo $user['username']; ?></span>
            <i class="fas fa-bell text-yellow-500"></i>
        </div>
    </div>

    <div class="m-4 p-4 rounded-xl bg-gradient-to-r from-teal-800 to-teal-600 text-white shadow-lg">
        <p class="text-xs opacity-80">মোট ব্যালেন্স</p>
        <h1 class="text-3xl font-bold mt-1">৳ <?php echo number_format($user['balance'], 2); ?></h1>
    </div>

    <div class="grid grid-cols-3 gap-3 p-4">
        
        <a href="missions.php" class="grid-item flex flex-col items-center justify-center p-4">
            <div class="icon-circle bg-red-900/20 text-red-500"><i class="fas fa-gift"></i></div>
            <span class="text-xs text-gray-300">মিশন</span>
        </a>

        <a href="records.php" class="grid-item flex flex-col items-center justify-center p-4">
            <div class="icon-circle bg-blue-900/20 text-blue-500"><i class="fas fa-file-invoice"></i></div>
            <span class="text-xs text-gray-300">বেটিং রেকর্ড</span>
        </a>

        <a href="rebate.php" class="grid-item flex flex-col items-center justify-center p-4">
            <div class="icon-circle bg-yellow-900/20 text-yellow-500"><i class="fas fa-coins"></i></div>
            <span class="text-xs text-gray-300">রিবেট</span>
        </a>

        <a href="my_account.php" class="grid-item flex flex-col items-center justify-center p-4">
            <div class="icon-circle bg-green-900/20 text-green-500"><i class="fas fa-user"></i></div>
            <span class="text-xs text-gray-300">আমার অ্যাকাউন্ট</span>
        </a>

        <a href="security.php" class="grid-item flex flex-col items-center justify-center p-4">
            <div class="icon-circle bg-purple-900/20 text-purple-500"><i class="fas fa-shield-alt"></i></div>
            <span class="text-xs text-gray-300">সুরক্ষা কেন্দ্র</span>
        </a>

        <a href="../logout.php" class="grid-item flex flex-col items-center justify-center p-4">
            <div class="icon-circle bg-gray-700 text-gray-300"><i class="fas fa-sign-out-alt"></i></div>
            <span class="text-xs text-gray-300">লগআউট</span>
        </a>

    </div>

</body>
</html>