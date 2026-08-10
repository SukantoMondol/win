<?php
session_start();
require __DIR__ . '/../includes/db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// ডিপোজিট পেজ থেকে আসা ডাটা
$amount = $_POST['amount'] ?? 0;
$method = $_POST['method'] ?? 'bKash';
$wallet = $_POST['wallet_number'] ?? '';
$promo_id = $_POST['promo_id'] ?? 0;

// যদি সরাসরি কেউ এই পেজে আসে তবে তাকে ফেরত পাঠানো
if ($amount <= 0) {
    header("Location: deposit.php");
    exit();
}

// payment_accounts টেবিল থেকে অ্যাডমিন/সিস্টেম নাম্বার নিয়ে আসা (agent_id = 0)
// এখানে 'status' এর বদলে 'is_active' ব্যবহার করা হয়েছে আপনার আগের এরর অনুযায়ী
$stmt = $conn->prepare("SELECT number, type FROM payment_accounts WHERE method = ? AND agent_id = 0 AND is_active = 1 LIMIT 1");
$stmt->bind_param("s", $method);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();

// যদি কোনো নাম্বার সেট করা না থাকে
$target_number = $account['number'] ?? 'No Number Set';
$acc_type = $account['type'] ?? 'personal';

// টাইপ অনুযায়ী বাংলার নির্দেশনা
$instruction_text = (strtolower($acc_type) == 'agent') ? 'ক্যাশআউট' : 'সেন্ড মানি';

$settings = $conn->query("SELECT site_name FROM settings WHERE id=1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manual Deposit - <?php echo $settings['site_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7fa; font-family: 'Roboto', sans-serif; }
        .lg-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .lg-header { background: #1a5c92; color: white; padding: 15px; text-align: center; }
        .method-badge { background: #d80073; color: white; padding: 5px 15px; border-radius: 5px; font-weight: bold; }
        .btn-confirm { background: #1a5c92; color: white; font-weight: bold; padding: 15px; border-radius: 12px; width: 100%; transition: 0.3s; text-transform: uppercase; }
        .btn-confirm:active { transform: scale(0.98); opacity: 0.9; }
        .copy-btn { color: #1a5c92; cursor: pointer; transition: 0.2s; }
        .warning-box { background: #fffbe6; border: 1px solid #ffe58f; padding: 10px; border-radius: 8px; font-size: 11px; color: #856404; }
    </style>
</head>
<body class="p-4 flex justify-center items-start min-h-screen">

    <div class="w-full max-w-md lg-card mt-5">
        <div class="lg-header">
            <div class="flex justify-between items-center mb-1">
                <span class="text-xl font-black uppercase">BDT <?php echo number_format($amount, 2); ?></span>
                <div class="flex gap-2">
                    <span class="bg-white/20 px-2 py-0.5 rounded text-[10px] font-bold">DEPOSIT</span>
                    <span class="bg-white/20 px-2 py-0.5 rounded text-[10px] font-bold">MANUAL</span>
                </div>
            </div>
            <p class="text-[11px] font-bold opacity-90">সঠিক ভাবে <?php echo $instruction_text; ?> সম্পন্ন করুন</p>
        </div>

        <div class="p-6 space-y-5">
            <p class="text-red-500 text-xs font-bold leading-relaxed text-center italic">
                আপনি যদি টাকার পরিমাণ পরিবর্তন করেন (BDT <?php echo $amount; ?>), আপনার ব্যালেন্স আপডেট হতে দেরি হতে পারে।
            </p>

            <div class="flex justify-between items-start border-b pb-4">
                <div class="flex-1">
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Wallet No (<?php echo ucfirst($acc_type); ?>) *</label>
                    <p class="text-[11px] text-blue-900 font-bold mb-2 uppercase italic">এই নাম্বারে শুধুমাত্র <?php echo $instruction_text; ?> করুন</p>
                    <div class="flex items-center gap-3">
                        <span id="targetNo" class="text-2xl font-black text-gray-800 tracking-tighter"><?php echo $target_number; ?></span>
                        <i class="far fa-copy copy-btn text-xl" onclick="copyText('<?php echo $target_number; ?>')"></i>
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-[10px] font-bold text-gray-400 mb-1 uppercase">Method</p>
                    <div class="method-badge text-xs uppercase"><?php echo $method; ?></div>
                </div>
            </div>

            <form action="manual_process.php" method="POST" class="space-y-4">
                <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                <input type="hidden" name="method" value="<?php echo $method; ?>">
                <input type="hidden" name="promo_id" value="<?php echo $promo_id; ?>">
                <input type="hidden" name="sender_wallet" value="<?php echo $wallet; ?>">

                <div>
                    <label class="text-[11px] font-bold text-gray-500 italic">Transaction ID * <span class="text-red-400">(প্রয়োজনীয়)</span></label>
                    <input type="text" name="transaction_id" placeholder="TrxID দিন এখানে" class="w-full border-2 border-gray-200 rounded-lg p-3 mt-1 text-lg font-bold outline-none focus:border-blue-400 transition" required>
                </div>

                <button type="submit" class="btn-confirm mt-4 italic">Confirm Deposit</button>
            </form>

            <div class="space-y-3 pt-2">
                <h4 class="text-sm font-black text-gray-800 border-b pb-1 uppercase">নির্দেশনা</h4>
                <div class="warning-box">
                    ১. প্রথমে উপরের নাম্বারটি কপি করুন।<br>
                    ২. আপনার অ্যাপ থেকে সঠিক পরিমাণে <b><?php echo $instruction_text; ?></b> করুন।<br>
                    ৩. সফল ট্রানজেকশন আইডিটি কপি করে উপরে বসান।<br>
                    ৪. মনে রাখবেন, <b><?php echo ucfirst($acc_type); ?></b> নাম্বারে শুধুমাত্র <b><?php echo $instruction_text; ?></b> গ্রহণযোগ্য।
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyText(text) {
            if (text === 'No Number Set') return;
            navigator.clipboard.writeText(text);
            alert("নাম্বার কপি করা হয়েছে: " + text);
        }
    </script>
</body>
</html>