<?php
session_start();
require __DIR__ . '/../includes/db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];
// ইউজারের এজেন্ট আইডি এবং বর্তমান ব্যালেন্স চেক
$user_data = $conn->query("SELECT agent_id, username FROM users WHERE id=$uid")->fetch_assoc();
$agent_id = $user_data['agent_id'];

// যদি এজেন্ট না থাকে তবে ফিরে যাও
if ($agent_id <= 0) {
    header("Location: deposit.php");
    exit();
}

// ওই নির্দিষ্ট এজেন্টের তথ্য নিয়ে আসা
$agent_query = $conn->prepare("SELECT name, bkash_no, nagad_no FROM agents WHERE id = ?");
$agent_query->bind_param("i", $agent_id);
$agent_query->execute();
$agent = $agent_query->get_result()->fetch_assoc();

// ডিপোজিট পেজ থেকে আসা ডাটা
$amount = $_POST['amount'] ?? 0;
$method = $_POST['method'] ?? 'bKash';
$wallet = $_POST['wallet_number'] ?? '';
$promo_id = $_POST['promo_id'] ?? 0;

$target_number = ($method == 'Nagad') ? $agent['nagad_no'] : $agent['bkash_no'];

$settings = $conn->query("SELECT site_name FROM settings WHERE id=1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Agent Deposit - <?php echo $settings['site_name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7fa; font-family: 'Roboto', sans-serif; }
        .lg-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .lg-header { background: #008169; color: white; padding: 15px; text-align: center; }
        .method-badge { background: #d80073; color: white; padding: 5px 15px; border-radius: 5px; font-weight: bold; }
        .btn-confirm { background: white; border: 1px solid #333; color: #333; font-weight: bold; padding: 12px; border-radius: 8px; width: 100%; transition: 0.3s; }
        .btn-confirm:active { background: #eee; }
        .copy-btn { color: #008169; cursor: pointer; transition: 0.2s; }
        .copy-btn:active { transform: scale(0.9); }
        .warning-box { background: #fffbe6; border: 1px solid #ffe58f; padding: 10px; border-radius: 8px; font-size: 11px; color: #856404; }
    </style>
</head>
<body class="p-4 flex justify-center items-start min-h-screen">

    <div class="w-full max-w-md lg-card mt-5">
        <div class="lg-header">
            <div class="flex justify-between items-center mb-1">
                <span class="text-xl font-black uppercase">BDT <?php echo number_format($amount, 2); ?></span>
                <div class="flex gap-2">
                    <span class="bg-white/20 px-2 py-0.5 rounded text-[10px] font-bold">PAY</span>
                    <span class="bg-white/20 px-2 py-0.5 rounded text-[10px] font-bold">SERVICE</span>
                </div>
            </div>
            <p class="text-[11px] font-bold opacity-90">কম বা বেশি ক্যাশআউট করবেন না</p>
        </div>

        <div class="p-6 space-y-5">
            <p class="text-red-500 text-xs font-bold leading-relaxed text-center italic">
                আপনি যদি টাকার পরিমাণ পরিবর্তন করেন (BDT <?php echo $amount; ?>), আপনি ক্রেডিট পেতে সক্ষম হবেন না।
            </p>

            <div class="flex justify-between items-start border-b pb-4">
                <div class="flex-1">
                    <label class="text-[10px] font-bold text-gray-400 uppercase">Wallet No *</label>
                    <p class="text-[11px] text-blue-900 font-bold mb-2 uppercase">এই <?php echo strtoupper($method); ?> নাম্বারে শুধুমাত্র ক্যাশআউট গ্রহণ করা হয়</p>
                    <div class="flex items-center gap-3">
                        <span id="targetNo" class="text-2xl font-black text-gray-800 tracking-tighter"><?php echo $target_number; ?></span>
                        <i class="far fa-copy copy-btn text-xl" onclick="copyText('<?php echo $target_number; ?>')"></i>
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-[10px] font-bold text-gray-400 mb-1 uppercase">Provider</p>
                    <div class="method-badge text-xs uppercase"><?php echo $method; ?> Deposit</div>
                </div>
            </div>

            <form action="manual_process.php" method="POST" class="space-y-4">
                <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                <input type="hidden" name="method" value="<?php echo $method; ?>">
                <input type="hidden" name="agent_id" value="<?php echo $agent_id; ?>">
                <input type="hidden" name="promo_id" value="<?php echo $promo_id; ?>">

                <div>
                    <label class="text-[11px] font-bold text-gray-500">Transaction ID * <span class="text-red-400">(required)</span></label>
                    <input type="text" name="transaction_id" placeholder="Transaction ID" class="w-full border-2 border-red-200 rounded-lg p-3 mt-1 text-lg font-bold outline-none focus:border-red-400 transition" required>
                    <a href="#" class="text-[10px] text-blue-500 font-bold mt-1 inline-block">ট্রানজেকশন আইডি কীভাবে পাবেন?</a>
                </div>

                <button type="submit" class="btn-confirm mt-4 uppercase italic">নিশ্চিত</button>
            </form>

            <div class="space-y-3 pt-2">
                <h4 class="text-sm font-black text-gray-800 border-b pb-1 uppercase">সতর্কতা</h4>
                <p class="text-[10px] text-red-600 font-bold">লেনদেন আইডি সঠিকভাবে পূরণ করতে হবে, অন্যথায় স্কোর ব্যর্থ হবে!!</p>
                <div class="warning-box">
                    অনুগ্রহ করে নিশ্চিত হয়ে নিন যে আপনি <?php echo strtoupper($method); ?> ওয়ালেট নাম্বারে ক্যাশ আউট করছেন। এই নাম্বারের অন্য কোন ওয়ালেট থেকে ক্যাশ আউট করলে সেই টাকা পাওয়ার কোন সম্ভাবনা নাই
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text);
            alert("নাম্বার কপি করা হয়েছে: " + text);
        }
    </script>
</body>
</html>