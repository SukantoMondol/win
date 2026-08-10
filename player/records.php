<?php
session_start();
require '../includes/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$uid = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#034C44';

// ডাটা আনা (Fake Transaction Table থেকে)
$history = $conn->query("SELECT * FROM transactions_fake WHERE user_id=$uid ORDER BY created_at DESC LIMIT 20");
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অ্যাকাউন্ট রেকর্ড</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">

    <div class="p-4 flex items-center text-white sticky top-0 z-50" style="background-color: <?php echo $primary; ?>;">
        <a href="account.php" class="mr-4"><i class="fas fa-chevron-left text-xl"></i></a>
        <h1 class="text-lg font-bold">অ্যাকাউন্ট রেকর্ড</h1>
    </div>

    <div class="flex justify-between p-3 bg-white shadow-sm mb-2">
        <button class="bg-gray-100 px-4 py-1 rounded text-sm flex items-center gap-2"><i class="far fa-calendar-alt"></i> আজ</button>
        <button class="bg-gray-100 px-4 py-1 rounded text-sm flex items-center gap-2"><i class="fas fa-filter"></i> সব টাইপ</button>
    </div>

    <div class="bg-white">
        <?php while($row = $history->fetch_assoc()): ?>
        <div class="flex justify-between items-center p-4 border-b border-gray-100 hover:bg-gray-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-700">
                        <?php echo ucfirst($row['type']); ?> 
                        <span class="text-xs font-normal text-gray-400 block"><?php echo $row['transaction_id']; ?></span>
                    </h4>
                    <p class="text-[10px] text-gray-400 mt-0.5"><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></p>
                </div>
            </div>
            <div class="text-right">
                <span class="block font-bold <?php echo $row['type']=='deposit'?'text-green-600':'text-red-500'; ?>">
                    <?php echo $row['type']=='deposit' ? '+' : '-'; ?> 
                    <?php echo number_format($row['amount'], 2); ?>
                </span>
                <span class="text-[10px] px-2 py-0.5 rounded bg-gray-100 text-gray-500"><?php echo $row['status']; ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

</body>
</html>