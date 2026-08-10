<?php
// Category Games page - optimized first render + AJAX load more.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/category_games_helper.php';

if (file_exists(__DIR__ . '/../includes/game_api_helper.php')) {
    require_once __DIR__ . '/../includes/game_api_helper.php';
    if (isset($conn) && !$conn->connect_error) {
        if (function_exists('game_api_seed_jili_mappings')) {
            @game_api_seed_jili_mappings($conn);
        }
        if (function_exists('game_api_seed_evolutionlive_mappings')) {
            @game_api_seed_evolutionlive_mappings($conn);
        }
    }
}

$hot_cat_id = 1;
if (isset($conn) && !$conn->connect_error) {
    $cat_q = $conn->query("SELECT id FROM front_categories WHERE LOWER(name) LIKE '%hot%' OR LOWER(name) LIKE '%popular%' LIMIT 1");
    if ($cat_q && $cat_q->num_rows > 0) {
        $hot_cat_id = (int)$cat_q->fetch_assoc()['id'];
    }
}

$cat_id = isset($_GET['cat_id']) ? max(1, (int)$_GET['cat_id']) : $hot_cat_id;
$selected_provider = isset($_GET['provider']) ? trim((string)$_GET['provider']) : 'all';
if ($selected_provider === '') {
    $selected_provider = 'all';
}

$cat_stmt = $conn->prepare("SELECT name FROM front_categories WHERE id = ? LIMIT 1");
$cat_name = 'Games';
if ($cat_stmt) {
    $cat_stmt->bind_param('i', $cat_id);
    $cat_stmt->execute();
    $cat_stmt->bind_result($db_cat_name);
    if ($cat_stmt->fetch()) {
        $cat_name = $db_cat_name;
    }
    $cat_stmt->close();
}
if (strcasecmp($cat_name, 'Hot Game') === 0 || strcasecmp($cat_name, 'Hot') === 0) {
    $cat_name = 'Popular';
}

$providers_res = $conn->query("SELECT provider_id, name, image FROM game_providers WHERE status='active' ORDER BY name ASC");

$initial_limit = 50;
$initial_data = aj_fetch_category_games_fast($conn, $cat_id, $selected_provider, '', 0, $initial_limit);
$initial_rows = $initial_data['rows'];
$initial_html = aj_render_game_cards($initial_rows);
$initial_count = count($initial_rows);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo aj_html($cat_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <style>
        :root { --bg-dark: #071f18; --header-green: #0e3d2c; --accent-green: #1de9b6; --card-bg: #0a2d1f; }
        html { -webkit-text-size-adjust: 100%; }
        body { background-color: var(--bg-dark); color: white; font-family: 'Roboto', sans-serif; padding-bottom: 100px; overflow-x: hidden; }
        .header-fixed { background: var(--header-green); border-bottom: 1px solid #1a5c40; position: sticky; top: 0; z-index: 1000; height: 60px; display: flex; align-items: center; justify-content: space-between; padding: 0 15px; }
        .provider-chip { background: #1c5244; border: 1.5px solid #236353; transition: border-color .2s ease, background .2s ease; width: 75px; height: 45px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-radius: 8px; overflow: hidden; }
        .provider-chip.active { border-color: var(--accent-green); background: #2a6b5a; box-shadow: 0 0 10px rgba(29, 233, 182, 0.3); }
        .game-card { background: var(--card-bg); border: 1px solid #1a5c40; border-radius: 12px; overflow: hidden; content-visibility: auto; contain-intrinsic-size: 165px; }
        .game-card img { transform: translateZ(0); }
        .search-input { background: #0a2d1f; border: 1px solid #1a5c40; border-radius: 50px; padding: 10px 45px; width: 100%; color: white; outline: none; }
        #allProvModal { display: none; position: fixed; inset: 0; z-index: 2000; background: rgba(0,0,0,0.95); padding: 20px; overflow-y: auto; -webkit-overflow-scrolling: touch; }
        #gameDisplay { min-height: 220px; }
        .fast-loader { display:flex; align-items:center; justify-content:center; min-height:180px; color:#1de9b6; }
    </style>
</head>
<body>

    <header class="header-fixed">
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="text-white text-xl p-1"><i class="fas fa-chevron-left"></i></a>
            <h1 class="font-bold uppercase text-sm italic"><?php echo ($selected_provider == 'all') ? aj_html($cat_name) : 'Provider: ' . aj_html($selected_provider); ?></h1>
        </div>
    </header>

    <div class="p-4 bg-[#0a2d1f]/50">
        <div class="relative">
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-emerald-500"></i>
            <input type="text" id="gameSearch" class="search-input text-sm" placeholder="Search Game Name..." autocomplete="off">
        </div>
    </div>

    <div class="p-3 flex items-center gap-2 bg-[#0e3027] sticky top-[60px] z-40">
        <button onclick="toggleProvModal()" class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center text-black flex-shrink-0" type="button">
            <i class="fas fa-th"></i>
        </button>
        <div class="swiper provSlider overflow-hidden">
            <div class="swiper-wrapper">
                <div class="swiper-slide !w-auto">
                    <a href="?cat_id=<?php echo $cat_id; ?>&provider=all" class="provider-chip <?php echo ($selected_provider == 'all') ? 'active' : ''; ?>">
                        <span class="text-[10px] font-black uppercase">ALL</span>
                    </a>
                </div>
                <?php if($providers_res): while($p = $providers_res->fetch_assoc()): ?>
                <div class="swiper-slide !w-auto">
                    <a href="?cat_id=<?php echo $cat_id; ?>&provider=<?php echo urlencode($p['provider_id']); ?>" 
                       class="provider-chip <?php echo ($selected_provider == $p['provider_id']) ? 'active' : ''; ?>">
                        <img src="<?php echo aj_html($p['image']); ?>" class="w-full h-full object-contain p-1" loading="lazy" decoding="async" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <span class="text-[8px] font-bold hidden uppercase"><?php echo aj_html($p['name']); ?></span>
                    </a>
                </div>
                <?php endwhile; endif; ?>
            </div>
        </div>
    </div>

    <div class="p-3 grid grid-cols-3 gap-3" id="gameDisplay">
        <?php if ($initial_html !== ''): ?>
            <?php echo $initial_html; ?>
        <?php else: ?>
            <p class="col-span-full text-center py-10 text-gray-500 font-bold uppercase">No games found</p>
        <?php endif; ?>
    </div>

    <div class="p-6 text-center" id="loadMoreSection" style="display: <?php echo ($initial_count >= $initial_limit) ? 'block' : 'none'; ?>;">
        <button id="loadMoreBtn" class="bg-emerald-500 text-black px-8 py-2 rounded-full text-xs font-black uppercase italic shadow-lg active:scale-90 transition" type="button">
            Load More
        </button>
    </div>

    <div id="allProvModal">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-emerald-400 font-black italic uppercase">Select Provider</h2>
            <button onclick="toggleProvModal()" class="text-3xl" type="button">&times;</button>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <?php 
            if($providers_res) {
                $providers_res->data_seek(0);
                while($p = $providers_res->fetch_assoc()):
            ?>
            <a href="?cat_id=<?php echo $cat_id; ?>&provider=<?php echo urlencode($p['provider_id']); ?>" class="bg-[#1c5244] p-3 rounded-lg text-center border border-white/5">
                <img src="<?php echo aj_html($p['image']); ?>" class="h-8 mx-auto object-contain mb-1" loading="lazy" decoding="async" onerror="this.src='https://placehold.co/100x50/0a2d1f/fff?text=<?php echo aj_html($p['name']); ?>'">
                <p class="text-[8px] font-black truncate uppercase text-gray-300"><?php echo aj_html($p['name']); ?></p>
            </a>
            <?php endwhile; } ?>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <script>
        let offset = <?php echo (int)$initial_count; ?>;
        const limit = <?php echo (int)$initial_limit; ?>;
        const cat_id = <?php echo (int)$cat_id; ?>;
        const provider = <?php echo json_encode($selected_provider); ?>;
        let activeRequest = null;
        let searchTimer = null;

        document.addEventListener('DOMContentLoaded', function() {
            if (window.Swiper) {
                new Swiper('.provSlider', { slidesPerView: 'auto', spaceBetween: 8, freeMode: true });
            }
        });

        function toggleProvModal() {
            const modal = document.getElementById('allProvModal');
            if (!modal) return;
            modal.style.display = (modal.style.display === 'block') ? 'none' : 'block';
        }

        function setLoading() {
            document.getElementById('gameDisplay').innerHTML = '<div class="col-span-full fast-loader"><i class="fas fa-circle-notch fa-spin text-3xl"></i></div>';
        }

        function loadGames(reset = false) {
            const searchInput = document.getElementById('gameSearch');
            const gameDisplay = document.getElementById('gameDisplay');
            const loadMoreSection = document.getElementById('loadMoreSection');
            const search = searchInput ? searchInput.value : '';

            if (reset) {
                offset = 0;
                setLoading();
            }

            if (activeRequest) {
                activeRequest.abort();
            }
            activeRequest = new AbortController();

            const params = new URLSearchParams({
                cat_id: cat_id,
                provider: provider,
                search: search,
                offset: offset,
                limit: limit
            });

            fetch('ajax_fetch_games.php?' + params.toString(), {
                method: 'GET',
                cache: search ? 'no-store' : 'default',
                signal: activeRequest.signal,
                headers: { 'X-Requested-With': 'fetch' }
            })
            .then(function(response) { return response.text(); })
            .then(function(html) {
                activeRequest = null;
                html = html.trim();

                if (reset) {
                    gameDisplay.innerHTML = '';
                }

                if (html === '') {
                    if (reset) {
                        gameDisplay.innerHTML = '<p class="col-span-full text-center py-10 text-gray-500 font-bold uppercase">No games found</p>';
                    }
                    loadMoreSection.style.display = 'none';
                    return;
                }

                gameDisplay.insertAdjacentHTML('beforeend', html);
                const returnedItems = (html.match(/class="game-card"/g) || []).length;
                offset += returnedItems;
                loadMoreSection.style.display = returnedItems >= limit ? 'block' : 'none';
            })
            .catch(function(error) {
                if (error.name === 'AbortError') return;
                activeRequest = null;
                if (reset) {
                    gameDisplay.innerHTML = '<p class="col-span-full text-center py-10 text-gray-500 font-bold uppercase">Please try again</p>';
                }
            });
        }

        document.getElementById('gameSearch').addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() { loadGames(true); }, 250);
        });

        document.getElementById('loadMoreBtn').addEventListener('click', function() {
            loadGames(false);
        });
    </script>
</body>
</html>
