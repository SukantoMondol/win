<?php
session_start();
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/game_api_helper.php';
require_once __DIR__ . '/../includes/game_api_evolution_patch.php';
if (isset($conn) && !$conn->connect_error && function_exists('game_api_seed_jili_mappings')) {
    @game_api_seed_jili_mappings($conn);
}
if (isset($conn) && !$conn->connect_error && function_exists('game_api_evolution_ensure_patch')) {
    game_api_evolution_ensure_patch($conn);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ১. পেজিনেশন সেটআপ
$limit = 50; // প্রতি পেজে ৫০টি গেম
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$current_cat = $_GET['cat'] ?? 'all';
$current_provider = $_GET['provider'] ?? '';

// ২. প্রোভাইডার লিস্ট
$brands = [];
$brand_res = $conn->query("SELECT provider_id, name FROM game_providers WHERE status='active' ORDER BY name ASC");
while($b = $brand_res->fetch_assoc()) { $brands[] = $b; }

// ৩. অ্যাডভান্সড কুয়েরি লজিক
// প্রথমত front_category_games থেকে গেম আনবে, তারপর বাকি active গেমগুলো সিরিয়াল করবে
$where = "WHERE g.status = 'active'";
if ($current_cat !== 'all') {
    $where .= " AND fcg.category_id = " . intval($current_cat);
}
if (!empty($current_provider)) {
    $where .= " AND g.provider_id = '" . $conn->real_escape_string($current_provider) . "'";
}

// লজিক: প্রথমে অ্যাডমিন সাজানো গেম (Priority 1), তারপর বাকি গেম (Priority 2)
$sql = "SELECT DISTINCT g.*, 
        CASE WHEN fcg.game_uid IS NOT NULL THEN 1 ELSE 2 END as priority
        FROM games g 
        LEFT JOIN front_category_games fcg ON g.game_uid = fcg.game_uid 
        $where 
        ORDER BY priority ASC, g.id DESC 
        LIMIT $limit OFFSET $offset";

$games_res = $conn->query($sql);
$games = $games_res ? $games_res->fetch_all(MYSQLI_ASSOC) : [];
if (empty($current_provider) && function_exists('game_api_jili_prepare_display_rows')) {
    $games = game_api_jili_prepare_display_rows($conn, $games, 0);
}

// মোট গেম সংখ্যা বের করা (পেজিনেশনের জন্য)
$count_sql = "SELECT COUNT(DISTINCT g.id) as total FROM games g LEFT JOIN front_category_games fcg ON g.game_uid = fcg.game_uid $where";
$total_games = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_games / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Game Lobby</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0a1e29; color: #fff; font-family: 'Roboto', sans-serif; }
        .header-premium { background: linear-gradient(90deg, #102b3f 0%, #1a5c92 100%); border-bottom: 2px solid #20b1ff; }
        
        /* মারকিউ ড্র্যাগ এবং স্লো অ্যানিমেশন */
        .marquee-wrapper { background: #07151e; padding: 12px 0; overflow: hidden; border-bottom: 1px solid #1a3a4d; cursor: grab; }
        .marquee-content { display: flex; gap: 15px; width: max-content; animation: scroll-ultra-slow 120s linear infinite; }
        .marquee-wrapper:active .marquee-content { animation-play-state: paused; cursor: grabbing; }
        @keyframes scroll-ultra-slow { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        /* প্যানিগেশন স্টাইল */
        .page-link { background: #102b3f; border: 1px solid #1a3a4d; padding: 8px 15px; border-radius: 8px; font-size: 12px; }
        .page-link.active { background: #1a5c92; border-color: #ffdf1b; color: #ffdf1b; font-weight: bold; }

        /* মডাল সেটিংস */
        #providerModal { backdrop-filter: blur(8px); }
        .modal-header { position: sticky; top: 0; background: #102b3f; z-index: 50; border-bottom: 1px solid #1a3a4d; }
    </style>
</head>
<body>

    <header class="header-premium flex justify-between items-center px-4 h-[58px] sticky top-0 z-50">
        <a href="index.php" class="text-white"><i class="fas fa-arrow-left text-lg"></i></a>
        <h1 class="font-bold uppercase text-[12px]">Premium Lobby</h1>
        <button onclick="document.getElementById('providerModal').classList.remove('hidden')" class="text-[#ffdf1b] text-[10px] font-bold border border-[#ffdf1b] px-3 py-1 rounded-full uppercase">
            Providers <i class="fas fa-th-large ml-1"></i>
        </button>
    </header>

    <div class="marquee-wrapper">
        <div class="marquee-content">
            <?php for($i=0; $i<3; $i++): foreach($brands as $b): ?>
                <a href="?cat=<?php echo $current_cat; ?>&provider=<?php echo urlencode($b['provider_id']); ?>" class="bg-[#102b3f] border border-[#1a3a4d] px-4 py-2 rounded-lg text-[10px] font-bold uppercase whitespace-nowrap">
                    <?php echo $b['name']; ?>
                </a>
            <?php endforeach; endfor; ?>
        </div>
    </div>

    <div class="p-3">
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
            <?php foreach($games as $game): 
                $img = (strpos($game['image'], 'http') === 0) ? $game['image'] : "../" . $game['image'];
            ?>
            <a href="launch.php?game_id=<?php echo !empty($game['jili_launch_uid']) ? $game['jili_launch_uid'] : $game['game_uid']; ?>" class="block bg-[#102b3f] rounded-xl border border-[#1a3a4d] overflow-hidden active:scale-95 transition">
                <img src="<?php echo $img; ?>" loading="lazy" class="aspect-square w-full object-cover">
                <div class="p-1.5 border-t border-[#1a3a4d]">
                    <p class="text-[9px] font-bold truncate uppercase text-white"><?php echo $game['name']; ?></p>
                    <p class="text-[7px] text-blue-400 font-medium truncate uppercase"><?php echo $game['provider_id']; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="p-4 flex justify-center items-center gap-2 overflow-x-auto no-scrollbar pb-10">
        <?php if($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&cat=<?php echo $current_cat; ?>&provider=<?php echo $current_provider; ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>

        <?php 
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for($i=$start; $i<=$end; $i++): ?>
            <a href="?page=<?php echo $i; ?>&cat=<?php echo $current_cat; ?>&provider=<?php echo $current_provider; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&cat=<?php echo $current_cat; ?>&provider=<?php echo $current_provider; ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div id="providerModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-[#102b3f] w-full max-w-sm rounded-3xl overflow-hidden border border-[#1a3a4d] flex flex-col max-h-[80vh]">
            <div class="modal-header p-4 flex justify-between items-center">
                <span class="text-[#ffdf1b] font-black uppercase text-xs">All Providers</span>
                <button onclick="document.getElementById('providerModal').classList.add('hidden')" class="bg-[#1a5c92] text-white w-8 h-8 rounded-full flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="overflow-y-auto p-4 grid grid-cols-2 gap-3">
                <a href="?cat=all" class="bg-[#1a5c92] p-3 rounded-xl text-center text-[11px] font-bold uppercase">All Games</a>
                <?php foreach($brands as $b): ?>
                    <a href="?provider=<?php echo urlencode($b['provider_id']); ?>" class="bg-[#07151e] border border-[#1a3a4d] p-3 rounded-xl text-center text-[10px] font-bold uppercase hover:border-[#ffdf1b]">
                        <?php echo $b['name']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</body>
</html>