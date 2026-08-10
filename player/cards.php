<?php
session_start();
require '../includes/db.php';

// ১. লগইন চেক
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$uid = $_SESSION['user_id'];
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$primary = $settings['theme_primary'] ?? '#034C44';

$msg = "";

// ২. কার্ড বা ওয়ালেট যুক্ত করার লজিক
if (isset($_POST['add_card'])) {
    $method = $_POST['method'];
    $number = $_POST['wallet_number']; // আপনার ডাটাবেজের কলাম নাম

    // ডুপ্লিকেট চেক
    $check = $conn->query("SELECT id FROM player_wallets WHERE user_id=$uid AND wallet_number='$number'");
    
    if ($check->num_rows > 0) {
        $msg = "<div class='bg-red-100 text-red-600 p-2 rounded text-xs mb-3 text-center'>এই নম্বরটি ইতিমধ্যে যুক্ত আছে!</div>";
    } else {
        // ডাটাবেজ টেবিল: player_wallets
        $stmt = $conn->prepare("INSERT INTO player_wallets (user_id, method, wallet_number) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $uid, $method, $number);
        
        if ($stmt->execute()) {
            $msg = "<div class='bg-green-100 text-green-600 p-2 rounded text-xs mb-3 text-center'>ওয়ালেট সফলভাবে যুক্ত হয়েছে!</div>";
        }
    }
}

// ৩. কার্ড ডিলিট লজিক
if (isset($_GET['delete_id'])) {
    $did = intval($_GET['delete_id']);
    $conn->query("DELETE FROM player_wallets WHERE id=$did AND user_id=$uid");
    header("Location: cards.php");
    exit();
}

// ৪. সেভ করা কার্ড লিস্ট আনা
$cards = $conn->query("SELECT * FROM player_wallets WHERE user_id=$uid ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>কার্ড ব্যবস্থাপনা</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f3f4f6; }
        /* কার্ডের কালার গ্রেডিয়েন্ট */
        .card-bkash { background: linear-gradient(135deg, #e2136e 0%, #b0004d 100%); }
        .card-nagad { background: linear-gradient(135deg, #f68e1f 0%, #d86400 100%); }
        .card-rocket { background: linear-gradient(135deg, #8c3494 0%, #5d1863 100%); }
        .card-bank { background: linear-gradient(135deg, #1f2937 0%, #000000 100%); }
    </style>
</head>
<body class="pb-20">

    <div class="p-4 flex items-center text-white sticky top-0 z-50 shadow-md" style="background-color: <?php echo $primary; ?>;">
        <a href="security.php" class="mr-4 text-xl"><i class="fas fa-chevron-left"></i></a>
        <h1 class="text-lg font-bold">কার্ড ব্যবস্থাপনা</h1>
    </div>

    <div class="p-4">
        
        <?php echo $msg; ?>

        <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                class="w-full border-2 border-dashed border-gray-300 rounded-xl p-4 flex flex-col items-center justify-center text-gray-400 hover:border-<?php echo $primary; ?> hover:text-<?php echo $primary; ?> transition mb-6 bg-white">
            <i class="fas fa-plus-circle text-3xl mb-1"></i>
            <span class="text-sm font-bold">নতুন ই-ওয়ালেট যুক্ত করুন</span>
        </button>

        <div class="space-y-4">
            <?php if($cards->num_rows > 0): ?>
                <?php while($row = $cards->fetch_assoc()): ?>
                    <?php 
                        // কার্ডের টাইপ অনুযায়ী ক্লাস নির্ধারণ
                        $bgClass = 'card-bank';
                        if(strtolower($row['method']) == 'bkash') $bgClass = 'card-bkash';
                        if(strtolower($row['method']) == 'nagad') $bgClass = 'card-nagad';
                        if(strtolower($row['method']) == 'rocket') $bgClass = 'card-rocket';
                    ?>
                    
                    <div class="<?php echo $bgClass; ?> rounded-xl p-5 text-white shadow-lg relative overflow-hidden group">
                        <a href="?delete_id=<?php echo $row['id']; ?>" onclick="return confirm('মুছে ফেলতে চান?')" class="absolute top-3 right-3 bg-white/20 hover:bg-white/40 p-1.5 rounded-full backdrop-blur-sm transition">
                            <i class="fas fa-trash text-xs"></i>
                        </a>

                        <div class="flex items-center gap-3 mb-6">
                            <div class="bg-white/20 p-2 rounded-full backdrop-blur-sm">
                                <i class="fas fa-wallet text-xl"></i>
                            </div>
                            <span class="font-bold uppercase tracking-wider text-sm"><?php echo $row['method']; ?> Wallet</span>
                        </div>

                        <div class="text-lg font-mono tracking-widest mb-1">
                            <?php echo substr($row['wallet_number'], 0, 3) . ' **** ' . substr($row['wallet_number'], -3); ?>
                        </div>
                        
                        <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center text-gray-400 text-sm py-4">কোনো কার্ড যুক্ত করা নেই</p>
            <?php endif; ?>
        </div>

    </div>

    <div id="addModal" class="hidden fixed inset-0 bg-black/60 z-[60] flex items-end sm:items-center justify-center backdrop-blur-sm">
        <div class="bg-white w-full sm:w-96 rounded-t-2xl sm:rounded-2xl p-6 animate-slide-up">
            
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-700">ওয়ালেট যুক্ত করুন</h3>
                <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">ওয়ালেট টাইপ</label>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="method" value="bkash" class="peer hidden" checked>
                            <div class="border rounded-lg p-2 text-center peer-checked:border-pink-500 peer-checked:bg-pink-50 peer-checked:text-pink-600 transition">
                                <span class="text-xs font-bold">Bkash</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="method" value="nagad" class="peer hidden">
                            <div class="border rounded-lg p-2 text-center peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 transition">
                                <span class="text-xs font-bold">Nagad</span>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="method" value="rocket" class="peer hidden">
                            <div class="border rounded-lg p-2 text-center peer-checked:border-purple-500 peer-checked:bg-purple-50 peer-checked:text-purple-600 transition">
                                <span class="text-xs font-bold">Rocket</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">অ্যাকাউন্ট নম্বর</label>
                    <input type="number" name="wallet_number" required placeholder="017xxxxxxxx" class="w-full p-3 rounded-lg border border-gray-200 focus:outline-none focus:border-<?php echo $primary; ?> bg-gray-50">
                </div>

                <button type="submit" name="add_card" class="w-full text-white font-bold py-3.5 rounded-lg shadow-lg hover:opacity-90 transition mt-2" style="background-color: <?php echo $primary; ?>;">
                    নিশ্চিত করুন
                </button>

            </form>
        </div>
    </div>

</body>
</html>