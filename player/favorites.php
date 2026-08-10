<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    die('Database connection file not found.');
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$settings = [];
if (isset($conn) && !$conn->connect_error) {
    $settings_q = $conn->query("SELECT * FROM settings WHERE id=1");
    if ($settings_q && $settings_q->num_rows > 0) $settings = $settings_q->fetch_assoc();
}
$site_name = $settings['site_name'] ?? 'RedJili';

$games = [];
if (isset($conn) && !$conn->connect_error) {
    $res = $conn->query("SELECT game_uid, provider_id, name, image, category, status FROM games WHERE status='active' ORDER BY name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $games[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Favorites - <?php echo htmlspecialchars($site_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-dark:#071f18; --header-green:#0e3d2c; --accent:#1de9b6; --card:#0a2d1f; --gold:#f5c518; }
        html, body { max-width:100vw; overflow-x:hidden; }
        body { margin:0; background:var(--bg-dark); color:#fff; font-family:'Segoe UI', Roboto, Arial, sans-serif; padding-bottom:95px; }
        .header { position:sticky; top:0; z-index:50; background:linear-gradient(135deg,#0f3b2d,#071f18); border-bottom:1px solid rgba(29,233,182,.18); height:60px; display:flex; align-items:center; justify-content:space-between; padding:0 15px; }
        .page-title { font-weight:900; font-size:14px; text-transform:uppercase; letter-spacing:.8px; color:#fff; }
        .fav-count { font-size:11px; color:var(--accent); font-weight:800; background:rgba(29,233,182,.08); border:1px solid rgba(29,233,182,.18); padding:6px 10px; border-radius:999px; }
        .game-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; padding:14px; }
        @media (min-width: 700px) { .game-grid { grid-template-columns:repeat(5,1fr); max-width:900px; margin:0 auto; } }
        .game-card { position:relative; display:block; text-decoration:none; color:#fff; background:var(--card); border:1px solid rgba(29,233,182,.14); border-radius:14px; overflow:hidden; box-shadow:0 8px 18px rgba(0,0,0,.25); }
        .game-card img { width:100%; aspect-ratio:1/1; object-fit:cover; display:block; background:#0d3d2c; }
        .game-name { font-size:11px; font-weight:800; line-height:1.2; padding:8px 7px 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .provider { font-size:9px; color:#87c7ad; padding:0 7px 9px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .remove-fav { position:absolute; top:7px; right:7px; width:29px; height:29px; border-radius:50%; border:none; background:rgba(0,0,0,.58); color:var(--gold); display:flex; align-items:center; justify-content:center; cursor:pointer; }
        .empty { min-height:58vh; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; color:#8ab6a3; padding:30px; }
        .empty-icon { width:86px; height:86px; border-radius:50%; background:rgba(29,233,182,.08); border:1px solid rgba(29,233,182,.16); display:flex; align-items:center; justify-content:center; color:#315a4b; font-size:34px; margin-bottom:16px; }
        .btn-primary { display:inline-flex; align-items:center; gap:8px; margin-top:18px; background:var(--accent); color:#041a14; text-decoration:none; font-weight:900; font-size:12px; padding:11px 18px; border-radius:999px; text-transform:uppercase; }
    </style>
</head>
<body>
    <header class="header">
        <a href="dashboard.php" class="text-white text-xl p-1"><i class="fas fa-chevron-left"></i></a>
        <div class="page-title"><i class="fas fa-heart text-[#f5c518]"></i> Favorites</div>
        <div class="fav-count" id="favCount">0</div>
    </header>

    <main id="favoritesContainer" class="game-grid"></main>

    <div id="emptyState" class="empty hidden">
        <div class="empty-icon"><i class="far fa-heart"></i></div>
        <h2 class="text-white font-black text-lg mb-1">No Favorite Games</h2>
        <p class="text-xs max-w-[280px] leading-relaxed">Dashboard থেকে game card-এর heart icon চাপলে এখানে আপনার পছন্দের game দেখা যাবে।</p>
        <a href="dashboard.php" class="btn-primary"><i class="fas fa-gamepad"></i> Browse Games</a>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        const FAV_STORAGE_KEY = 'redjili_favorites';
        const allGames = <?php echo json_encode($games, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function getFavoriteGames() {
            try { return JSON.parse(localStorage.getItem(FAV_STORAGE_KEY) || '[]'); }
            catch (e) { return []; }
        }
        function setFavoriteGames(list) {
            localStorage.setItem(FAV_STORAGE_KEY, JSON.stringify([...new Set(list)]));
        }
        function imgFallback(el) {
            el.src = 'https://placehold.co/200x200/0d3d2c/ffd84d?text=GAME';
        }
        function removeFavorite(uid, event) {
            event.preventDefault();
            event.stopPropagation();
            setFavoriteGames(getFavoriteGames().filter(id => id !== uid));
            renderFavorites();
        }
        function renderFavorites() {
            const favIds = getFavoriteGames();
            const favSet = new Set(favIds);
            const favGames = allGames.filter(g => favSet.has(String(g.game_uid)));
            const container = document.getElementById('favoritesContainer');
            const empty = document.getElementById('emptyState');
            document.getElementById('favCount').innerText = favGames.length + ' Saved';

            if (!favGames.length) {
                container.innerHTML = '';
                empty.classList.remove('hidden');
                return;
            }
            empty.classList.add('hidden');
            container.innerHTML = favGames.map(g => {
                const uid = String(g.game_uid || '');
                const name = String(g.name || 'Casino Game');
                const provider = String(g.provider_id || 'Unknown');
                const image = String(g.image || '');
                return `
                    <a class="game-card" href="launch.php?game_id=${encodeURIComponent(uid)}">
                        <img src="${image.replace(/"/g, '&quot;')}" alt="${name.replace(/"/g, '&quot;')}" onerror="imgFallback(this)">
                        <button class="remove-fav" onclick="removeFavorite('${uid.replace(/'/g, "\\'")}', event)" title="Remove favorite"><i class="fas fa-heart"></i></button>
                        <div class="game-name">${name}</div>
                        <div class="provider">${provider}</div>
                    </a>`;
            }).join('');
        }
        document.addEventListener('DOMContentLoaded', renderFavorites);
    </script>
</body>
</html>
