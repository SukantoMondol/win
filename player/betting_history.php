<?php
session_start();
require '../includes/db.php';

// ১. লগইন চেক
if (!isset($_SESSION['user_id'])) { 
    header("Location: ../login.php"); 
    exit(); 
}

$uid = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#034C44'; // ডাটাবেজ থেকে কালার

// ২. বেটিং রেকর্ড ফেচ করা
// game_logs টেবিল থেকে ডাটা আনা হচ্ছে
$logs = $conn->query("SELECT * FROM game_logs WHERE user_id=$uid ORDER BY created_at DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>বেটিং রেকর্ড</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f3f4f6; }
        .win-text { color: #16a34a; } /* Green */
        .loss-text { color: #dc2626; } /* Red */
        .pending-text { color: #d97706; } /* Orange */
    </style>
</head>
<body class="pb-20">

    <div class="p-4 flex items-center text-white sticky top-0 z-50 shadow-md" style="background-color: <?php echo $primary; ?>;">
        <a href="account.php" class="mr-4 text-xl"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-lg font-bold">বেটিং ইতিহাস</h1>
    </div>

    <div class="flex justify-around bg-white p-3 shadow-sm mb-2 text-sm text-gray-600 font-medium">
        <button class="border-b-2 border-<?php echo $primary; ?> text-black pb-1">সব</button>
        <button class="hover:text-black">বিজয়ী</button>
        <button class="hover:text-black">পরাজিত</button>
    </div>

    <div class="bg-white divide-y divide-gray-100">
        <?php if($logs->num_rows > 0): ?>
            <?php while($row = $logs->fetch_assoc()): ?>
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex justify-between items-start mb-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg">
                            <i class="fas fa-gamepad"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-800 text-sm"><?php echo $row['game_name']; ?></h3>
                            <p class="text-xs text-gray-400 mt-0.5">ID: #<?php echo $row['id']; ?></p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <?php if($row['status'] == 'win'): ?>
                            <span class="block font-bold text-lg win-text">+৳<?php echo number_format($row['win_amount'], 2); ?></span>
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded font-bold">WIN</span>
                        <?php elseif($row['status'] == 'loss'): ?>
                            <span class="block font-bold text-lg loss-text">-৳<?php echo number_format($row['bet_amount'], 2); ?></span>
                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded font-bold">LOSS</span>
                        <?php else: ?>
                            <span class="block font-bold text-lg text-gray-700">৳<?php echo number_format($row['bet_amount'], 2); ?></span>
                            <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded font-bold">PENDING</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex justify-between items-center text-xs text-gray-400 mt-2 border-t border-dashed pt-2">
                    <span><i class="far fa-clock"></i> <?php echo date('d M, h:i A', strtotime($row['created_at'])); ?></span>
                    <span>বেট: ৳<?php echo number_format($row['bet_amount'], 2); ?></span>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center py-12 text-gray-400">
                <i class="fas fa-history text-4xl mb-3 opacity-30"></i>
                <p>কোনো বেটিং রেকর্ড পাওয়া যায়নি</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>