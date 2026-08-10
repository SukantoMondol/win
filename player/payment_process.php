<?php
session_start();
require '../includes/db.php';

// লগিন চেক
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// ১. ইউজারের ফোন এবং এজেন্ট আইডি বের করা
$user_q = $conn->query("SELECT phone, agent_id FROM users WHERE id=$uid");
$user_data = $user_q->fetch_assoc();
$user_phone = $user_data['phone'] ?? '01XXXXXXXXX';
$my_agent_id = isset($user_data['agent_id']) ? intval($user_data['agent_id']) : 0;

// ---------------------------------------------------
// 2. GET INPUTS
// ---------------------------------------------------
$amount = isset($_REQUEST['amount']) ? floatval($_REQUEST['amount']) : 0;
$method = isset($_REQUEST['method']) ? strtolower($_REQUEST['method']) : '';
$channel = isset($_REQUEST['channel']) ? $_REQUEST['channel'] : 'Fast';
$promo_id = isset($_REQUEST['promo_id']) && !empty($_REQUEST['promo_id']) ? intval($_REQUEST['promo_id']) : 0;

if ($amount <= 0 || empty($method)) {
    echo "<script>alert('Invalid Request'); window.location='deposit.php';</script>";
    exit();
}

// ---------------------------------------------------
// 3. FIND PAYMENT NUMBER (LOGIC MODIFIED)
// ---------------------------------------------------

// টার্গেট সেট করা: প্লেয়ারের এজেন্ট থাকলে এজেন্ট, না থাকলে কোম্পানি (0)
$target_agent_id = ($my_agent_id > 0) ? $my_agent_id : 0;

// ডাটাবেস থেকে নাম্বার খোঁজা (এজেন্ট বা এডমিন)
$sql = "SELECT * FROM payment_accounts 
        WHERE agent_id = $target_agent_id 
        AND method = '$method' 
        AND is_active = 1 
        ORDER BY RAND() LIMIT 1";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // ডাটা সেট করা
    $target_number = $row['number'];
    $wallet_type = strtolower($row['type']); // personal / agent
    $min_limit = $row['limit_min'];
    $max_limit = $row['limit_max'];
    $db_instruction = $row['instructions'];

    // ভ্যালিডেশন: এমাউন্ট লিমিটের মধ্যে আছে কি না
    if ($amount < $min_limit || $amount > $max_limit) {
        echo "<script>alert('এই নাম্বারে ডিপোজিট লিমিট: $min_limit - $max_limit টাকা।'); window.history.back();</script>";
        exit();
    }

    // UI Text Logic (Personal vs Agent)
    if ($wallet_type == 'personal') {
        $action_head = "Send Money";
        $action_msg = "নিচের নাম্বারে <b>Send Money</b> করুন।";
        $badge_color = "bg-pink-600";
    } else {
        $action_head = "Cash Out";
        $action_msg = "এই নাম্বারে শুধুমাত্র <b>Cash Out</b> করুন।";
        $badge_color = "bg-green-600";
    }

} else {
    // যদি এজেন্টের নাম্বার না পাওয়া যায়
    echo "<script>alert('বর্তমানে এই মেথডটি (".$method.") এই এজেন্টের জন্য উপলব্ধ নয়। অন্য মেথড চেষ্টা করুন।'); window.location='deposit.php';</script>";
    exit();
}

// ---------------------------------------------------
// 4. UI CONFIG
// ---------------------------------------------------
$ui_config = [
    'bkash' => ['color' => '#E2136E', 'name' => 'bKash', 'logo' => 'https://freelogopng.com/images/all_img/1656234745bkash-app-logo-png.png'],
    'nagad' => ['color' => '#F7941D', 'name' => 'Nagad', 'logo' => 'https://freelogopng.com/images/all_img/1679248828Nagad-Logo-PNG.png'],
    'rocket'=> ['color' => '#8C3494', 'name' => 'Rocket', 'logo' => 'https://seeklogo.com/images/D/dutch-bangla-rocket-logo-B4D1CC458D-seeklogo.com.png'],
    'upay'  => ['color' => '#ED1C24', 'name' => 'UPay',   'logo' => 'https://images.seeklogo.com/logo-png/64/1/upay-logo-png_seeklogo-642533.png'],
];
$current_ui = $ui_config[$method] ?? $ui_config['bkash']; // ডিফল্ট বা অন্য মেথড

// ---------------------------------------------------
// 5. HANDLE FORM SUBMISSION
// ---------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $trx_id = mysqli_real_escape_string($conn, $_POST['trx_id']);
    $final_promo_id = isset($_POST['promo_id']) && !empty($_POST['promo_id']) ? intval($_POST['promo_id']) : NULL;
    
    // ডুপ্লিকেট চেক
    $dup_check = $conn->query("SELECT id FROM transactions_fake WHERE transaction_id='$trx_id'");
    if ($dup_check->num_rows > 0) {
        echo "<script>alert('এই ট্রানজেকশন আইডি ইতিমধ্যে ব্যবহার করা হয়েছে!'); window.history.back();</script>";
        exit();
    }
    
    // ইনসার্ট (এজেন্ট আইডি সহ)
    $stmt = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, created_at, wallet_number, transaction_id, agent_id, promo_id) VALUES (?, 'deposit', ?, ?, 'pending', NOW(), ?, ?, ?, ?)");
    
    $stmt->bind_param("idsssii", $uid, $amount, $method, $target_number, $trx_id, $target_agent_id, $final_promo_id);
    
    if ($stmt->execute()) {
        echo '
        <!DOCTYPE html>
        <html lang="bn">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Success</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-[#005c4b] flex flex-col items-center justify-center min-h-screen text-white text-center p-4">
            <div class="w-24 h-24 rounded-full border-4 border-white flex items-center justify-center mb-6"><i class="fas fa-check text-5xl"></i></div>
            <h2 class="text-2xl font-bold mb-2">রিকোয়েস্ট সফল!</h2>
            <p class="text-sm opacity-90 mb-6 leading-relaxed">আপনার ডিপোজিট রিকোয়েস্ট<br>জমা হয়েছে।<br>অনুগ্রহ করে অপেক্ষা করুন।</p>
            <button onclick="window.location.href=\'deposit.php\'" class="bg-white text-[#005c4b] w-full max-w-xs py-3 rounded-lg font-bold">ফিরে যান</button>
        </body>
        </html>';
        exit();
    } else {
        die("Error: " . $stmt->error);
    }
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Process - <?php echo $current_ui['name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Roboto', sans-serif; background-color: #f3f4f6; }
        .header-green { background-color: #005c4b; color: white; }
        .logo-ring { width: 80px; height: 80px; border-radius: 50%; border: 3px solid #005c4b; padding: 3px; background: white; margin: 0 auto; display: flex; justify-content: center; align-items: center; }
        .logo-inner { width: 90%; height: 90%; object-fit: contain; }
    </style>
</head>
<body>

    <div class="header-green p-4 text-center pb-12 rounded-b-[2.5rem] shadow-lg relative z-10">
        <h2 class="text-xl font-bold tracking-wide"><?php echo $action_head; ?></h2>
        <div class="text-3xl font-mono font-bold mt-2" id="timer">05:00</div>
        <p class="text-xs opacity-70">সময় বাকি</p>
    </div>

    <div class="max-w-md mx-auto px-4 -mt-10 relative z-20">
        <div class="bg-white rounded-xl shadow-xl p-6 border-t-4 border-[#005c4b]">
            
            <div class="mb-4 text-center -mt-14">
                <div class="logo-ring shadow-md">
                    <img src="<?php echo $current_ui['logo']; ?>" class="logo-inner">
                </div>
            </div>

            <div class="text-center mb-6">
                <span class="<?php echo $badge_color; ?> text-white text-[10px] font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                    <?php echo ucfirst($wallet_type); ?> Wallet
                </span>
                <p class="text-sm text-gray-600 mt-3 leading-relaxed">
                    <?php echo $action_msg; ?>
                </p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 space-y-3 mb-6">
                <div class="flex justify-between items-center text-sm border-b border-gray-200 pb-2">
                    <span class="text-gray-500">পরিমাণ</span>
                    <span class="font-bold text-[#005c4b] text-lg">৳<?php echo number_format($amount, 2); ?></span>
                </div>

                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500">একাউন্ট নাম্বার</span>
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-bold text-gray-800 text-lg tracking-wider" id="agentNum"><?php echo $target_number; ?></span>
                        <button onclick="copyNumber()" class="text-[#005c4b] hover:bg-green-50 p-1 rounded transition">
                            <i class="far fa-copy text-lg"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if(!empty($db_instruction)): ?>
                <div class="bg-yellow-50 text-yellow-800 text-xs p-3 rounded mb-6 border border-yellow-200 flex items-start gap-2">
                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                    <span><?php echo $db_instruction; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                <input type="hidden" name="method" value="<?php echo $method; ?>">
                <input type="hidden" name="promo_id" value="<?php echo $promo_id; ?>">
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 mb-1 uppercase">যে নাম্বার থেকে পাঠাচ্ছেন</label>
                    <input type="text" value="<?php echo $user_phone; ?>" readonly class="w-full bg-gray-100 border border-gray-300 rounded p-3 text-sm text-gray-500 focus:outline-none cursor-not-allowed">
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-600 mb-1 uppercase">ট্রানজেকশন আইডি (TrxID)</label>
                    <input type="text" name="trx_id" required placeholder="Ex: 9G7H6F5D" class="w-full border border-gray-300 rounded p-3 text-sm focus:outline-none focus:border-[#005c4b] focus:ring-1 focus:ring-[#005c4b] uppercase font-mono tracking-wide">
                </div>

                <button type="submit" class="w-full bg-[#005c4b] text-white font-bold py-3.5 rounded-lg shadow-lg hover:bg-[#004a3c] transition transform hover:scale-[1.01] flex items-center justify-center gap-2">
                    <span>কনফার্ম করুন</span> <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <div class="mt-6 text-center">
                <div class="flex items-center justify-center gap-1 text-gray-400 font-bold text-[10px] uppercase tracking-widest">
                    <i class="fas fa-shield-alt"></i> Secure Payment
                </div>
            </div>

        </div>
    </div>

    <script>
        // Timer Logic
        let time = 5 * 60; 
        const timerElement = document.getElementById('timer');
        
        const countdown = setInterval(() => { 
            const minutes = Math.floor(time / 60); 
            let seconds = time % 60; 
            seconds = seconds < 10 ? '0' + seconds : seconds; 
            timerElement.innerText = `0${minutes}:${seconds}`; 
            
            if (time <= 0) {
                clearInterval(countdown);
                alert("সময় শেষ! আবার চেষ্টা করুন।");
                window.location.href = "deposit.php";
            }
            time--; 
        }, 1000);

        // Copy Function
        function copyNumber() { 
            const num = document.getElementById('agentNum').innerText; 
            navigator.clipboard.writeText(num).then(() => { 
                // টোস্ট বা সিম্পল ফিডব্যাক
                const btn = document.querySelector('.fa-copy');
                btn.classList.remove('fa-copy');
                btn.classList.add('fa-check');
                setTimeout(() => {
                    btn.classList.remove('fa-check');
                    btn.classList.add('fa-copy');
                }, 2000);
            }); 
        }
    </script>
</body>
</html>