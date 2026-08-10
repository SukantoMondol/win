<?php
session_start();
// ১. ডাটাবেজ কানেকশন (ম্যানুয়াল কানেকশন রিমুভড)
require __DIR__ . '/../includes/db.php';

function promo_player_safe_query($conn, $sql) {
    try {
        return $conn->query($sql);
    } catch (Throwable $e) {
        return false;
    }
}

function promo_player_ensure_column($conn, $column, $definition) {
    if (!$conn || $conn->connect_error) return;
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeColumn === '') return;
    $escapedColumn = $conn->real_escape_string($safeColumn);
    $check = promo_player_safe_query($conn, "SHOW COLUMNS FROM `promotions` LIKE '$escapedColumn'");
    if ($check && $check->num_rows > 0) return;
    promo_player_safe_query($conn, "ALTER TABLE `promotions` ADD COLUMN `$safeColumn` $definition");
}

if (isset($conn) && !$conn->connect_error) {
    promo_player_ensure_column($conn, 'subtitle', "varchar(255) DEFAULT NULL");
    promo_player_safe_query($conn, "ALTER TABLE `promotions` MODIFY `category` varchar(50) DEFAULT 'all'");
    promo_player_safe_query($conn, "ALTER TABLE `promotions` MODIFY `status` varchar(20) DEFAULT 'active'");
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['user_id'];

// ২. ইউজার টেবিল থেকে ref_code সংগ্রহ
$user_query = $conn->query("SELECT ref_code FROM users WHERE id=$uid");
$user_data = $user_query->fetch_assoc();
$ref_code = $user_data['ref_code'] ?? 'default';

/** * ৩. অটোমেটিক ডোমেইন ডিটেকশন 
 * এটি অটোমেটিক http/https এবং বর্তমান ডোমেইন নাম খুঁজে নিবে
 */
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$ref_link = $protocol . "://" . $host . "/player/signup.php?ref=" . $ref_code;

$settings_query = $conn->query("SELECT * FROM settings WHERE id=1");
$settings = $settings_query ? $settings_query->fetch_assoc() : [];
$site_name = $settings['site_name'] ?? 'SHA75';

$cat = $_GET['cat'] ?? 'all';
$sql = "SELECT * FROM promotions WHERE status='active'";
if ($cat != 'all') {
    $cat_safe = $conn->real_escape_string($cat);
    $sql .= " AND category='$cat_safe'";
}
$sql .= " ORDER BY is_new DESC, id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Promotions - <?php echo $site_name; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/mobile-scroll-fix.css?v=2">
    <style>
        body { background-color: #f0f3f8; color: #333; font-family: 'Roboto', sans-serif; padding-bottom: 90px; }
        .header-premium { background: linear-gradient(90deg, #1a5c92 0%, #20b1ff 100%); border-bottom: 2px solid #154b77; }
        
        .tab-btn {
            background-color: #ffffff; color: #1a5c92; padding: 8px 20px;
            font-size: 11px; font-weight: bold; border-radius: 50px;
            white-space: nowrap; margin-right: 10px; border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .tab-btn.active { background-color: #1a5c92; color: #fff; border-color: #1a5c92; }
        
        .promo-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .btn-details { background: #1a5c92; color: #fff; font-weight: bold; border-radius: 6px; }
        
        /* ড্যাশ বর্ডার রেফারেল বক্স */
        .ref-box { background: #e0f2fe; border: 2px dashed #1a5c92; border-radius: 12px; }
        
        /* Custom scroll hide */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body>

    <header class="header-premium flex items-center justify-between px-4 h-[60px] sticky top-0 z-50 shadow-lg">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="text-white"><i class="fas fa-arrow-left"></i></a>
            <h1 class="text-xs font-bold uppercase tracking-widest text-white">Promotions</h1>
        </div>
        <a href="support_chat.php" class="text-white"><i class="fas fa-headset"></i></a>
    </header>

    <div class="px-4 py-4 overflow-x-auto flex no-scrollbar bg-white shadow-sm mb-2">
        <a href="?cat=all" class="tab-btn <?php echo $cat=='all'?'active':''; ?>">ALL</a>
        <a href="?cat=welcome" class="tab-btn <?php echo $cat=='welcome'?'active':''; ?>">WELCOME</a>
        <a href="?cat=slots" class="tab-btn <?php echo $cat=='slots'?'active':''; ?>">SLOTS</a>
    </div>

    <div class="m-4 p-4 ref-box">
        <p class="text-[10px] text-[#1a5c92] uppercase font-black mb-2 tracking-tighter">Your Referral Link (Earn Bonus)</p>
        <div class="flex gap-2">
            <input type="text" id="refLink" value="<?php echo $ref_link; ?>" readonly class="bg-white text-[11px] text-[#1a5c92] px-3 py-2.5 rounded-lg flex-1 outline-none border border-[#1a5c92]/20">
            <button onclick="copyRef()" class="bg-[#1a5c92] text-white px-4 py-2 rounded-lg text-xs font-bold active:scale-95 transition">Copy</button>
        </div>
    </div>

    <div class="px-4">
        <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <div class="promo-card">
                <img src="../<?php echo htmlspecialchars(ltrim($row['image_path'] ?? '', '/'), ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" decoding="async" class="w-full h-40 object-cover" onerror="this.src='https://placehold.co/600x250/1a5c92/FFF?text=PROMO'" alt="Promotion Banner">
                <div class="p-4">
                    <h3 class="text-sm font-black text-[#1a5c92] uppercase"><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-[10px] text-gray-500 mt-1"><?php echo htmlspecialchars($row['subtitle'] ?? ($row['bonus_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-100">
                        <span class="text-[9px] text-gray-400"><i class="far fa-clock mr-1"></i> Ends: <?php echo date('d/m/Y', strtotime($row['end_date'])); ?></span>
                        <button onclick="toggleDetails(<?php echo $row['id']; ?>)" class="text-[#1a5c92] text-[10px] font-bold underline">Show Details</button>
                    </div>
                    <div id="details-<?php echo $row['id']; ?>" class="hidden mt-3 text-[11px] text-gray-600 leading-relaxed bg-[#f8fafc] p-3 rounded-lg border border-gray-100">
                        <?php echo nl2br(htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center py-10">
                <i class="fas fa-gift text-gray-300 text-5xl mb-3"></i>
                <p class="text-gray-400 text-xs">No active promotions found.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        function toggleDetails(id) {
            document.getElementById('details-' + id).classList.toggle('hidden');
        }
        function copyRef() {
            const copyText = document.getElementById("refLink");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile
            navigator.clipboard.writeText(copyText.value).then(() => {
                alert("Referral link copied!");
            });
        }
    </script>
</body>
</html>