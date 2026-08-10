<?php
// এরর রিপোর্টিং চালু
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../includes/db.php'; 

// ইউজার লগইন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $uid = $_SESSION['user_id'];
    
    // ডাটা গ্রহণ
    $amount = $_POST['amount'] ?? 0;
    $method = $_POST['method'] ?? '';
    $promo_id = $_POST['promo_id'] ?? 0;
    $wallet_number = $_POST['sender_wallet'] ?? ''; 
    $transaction_id = $_POST['transaction_id'] ?? '';
    
    $type = 'deposit';
    $status = 'pending';
    $agent_id = 0; 
    
    if (empty($transaction_id)) {
        die("Transaction ID is required!");
    }

    // ডাটাবেজ ইনসার্ট
    $sql = "INSERT INTO transactions_fake 
            (user_id, type, amount, method, promo_id, status, wallet_number, transaction_id, agent_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdsisssi", $uid, $type, $amount, $method, $promo_id, $status, $wallet_number, $transaction_id, $agent_id);

    if ($stmt->execute()) {
        // সফল হলে এই নিচের HTML ডিজাইনটি দেখাবে
        ?>
        <!DOCTYPE html>
        <html lang="bn">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Success | Deposit Request</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                @keyframes scaleIn {
                    0% { transform: scale(0); opacity: 0; }
                    100% { transform: scale(1); opacity: 1; }
                }
                .animate-success { animation: scaleIn 0.5s cubic-bezier(0.165, 0.84, 0.44, 1) forwards; }
            </style>
        </head>
        <body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
            <div class="max-w-md w-full bg-white rounded-3xl p-8 shadow-2xl text-center animate-success">
                <div class="w-20 h-20 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-check-circle text-5xl"></i>
                </div>
                
                <h1 class="text-2xl font-black text-gray-800 mb-2 uppercase italic">ধন্যবাদ!</h1>
                <p class="text-gray-600 font-medium mb-6">
                    আপনার ডিপোজিট রিকোয়েস্টটি সফলভাবে গ্রহণ করা হয়েছে। আমাদের টিম ভেরিফাই করে আপনার ব্যালেন্স যোগ করে দিবে।
                </p>

                <div class="bg-blue-50 rounded-2xl p-4 mb-8">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">পরিমাণ:</span>
                        <span class="font-bold text-blue-700">৳ <?php echo number_format($amount, 2); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">মেথড:</span>
                        <span class="font-bold text-blue-700 uppercase"><?php echo $method; ?></span>
                    </div>
                </div>

                <a href="dashboard.php" class="inline-block w-full bg-[#1a5c92] text-white font-bold py-4 rounded-xl shadow-lg hover:bg-blue-800 transition-all mb-4 uppercase italic tracking-wider">
                    ড্যাশবোর্ডে ফিরে যান
                </a>
                
                <p class="text-[10px] text-gray-400">
                    আপনাকে <span id="countdown" class="font-bold text-blue-500">5</span> সেকেন্ডের মধ্যে অটোমেটিক রিডাইরেক্ট করা হবে।
                </p>
            </div>

            <script>
                // ৫ সেকেন্ডের কাউন্টডাউন এবং রিডাইরেক্ট লজিক
                let seconds = 5;
                const countdownEl = document.getElementById('countdown');
                
                const timer = setInterval(() => {
                    seconds--;
                    countdownEl.innerText = seconds;
                    if (seconds <= 0) {
                        clearInterval(timer);
                        window.location.href = '/player/dashboard.php';
                    }
                }, 1000);
            </script>
        </body>
        </html>
        <?php
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: deposit.php");
    exit();
}