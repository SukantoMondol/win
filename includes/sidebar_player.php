<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_guest = !isset($_SESSION['user_id']);
$user_name = $_SESSION['username'] ?? 'Guest';
$user_balance = 0.00;

if (!$is_guest && isset($conn)) {
    $uid = $_SESSION['user_id'];
    $u_res = $conn->query("SELECT balance FROM users WHERE id=$uid");
    if($u_res && $u_res->num_rows > 0){
        $user_balance = $u_res->fetch_assoc()['balance'];
    }
}

if (!function_exists('getLink')) {
    function getLink($page) {
        global $is_guest;
        $in_player_folder = (basename(dirname($_SERVER['PHP_SELF'] ?? '')) === 'player');
        
        if ($is_guest) {
            return $in_player_folder ? "login.php" : "player/login.php";
        }
        
        if ($in_player_folder) {
            return $page;
        } else {
            return "player/" . ltrim($page, '/');
        }
    }
$theme_helper_path = __DIR__ . '/theme_helper.php';
if (file_exists($theme_helper_path)) { require_once $theme_helper_path; }
if (function_exists('get_site_theme_css')) { echo get_site_theme_css($settings ?? []); }
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ============================================
   SIDEBAR
   ============================================ */

/* Desktop: always visible as left sidebar */
@media (min-width: 900px) {
    #sidebarOverlay { display: none !important; }
    #sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        transform: translateX(0) !important;
        height: 100vh !important;
        z-index: 50 !important;
        box-shadow: 2px 0 16px rgba(0,0,0,0.4) !important;
    }
    body { padding-left: 280px; }
}

/* Mobile: hidden by default, slides in */
@media (max-width: 899px) {
    #sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    #sidebar.open {
        transform: translateX(0) !important;
    }
}

#sidebar {
    background-color: #062c23 !important;
    width: 280px !important;
    height: 100% !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 70 !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
}
#sidebar::-webkit-scrollbar { display: none !important; }
#sidebar { -ms-overflow-style: none !important; scrollbar-width: none !important; }

/* === TOP HEADER BAR === */
.sb-header {
    background-color: #062c23 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 14px 16px !important;
    border-bottom: 1px solid rgba(255,255,255,0.06) !important;
}
.sb-logo-text {
    color: #f5c518 !important;
    font-size: 20px !important;
    font-weight: 900 !important;
    font-style: italic !important;
    letter-spacing: 1px !important;
    font-family: Arial Black, sans-serif !important;
    text-shadow: 0 0 16px rgba(245,197,24,0.35) !important;
}
.sb-close-btn {
    background: transparent !important;
    border: none !important;
    color: #ffffff !important;
    width: 34px !important;
    height: 34px !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    font-size: 18px !important;
    transition: background 0.2s !important;
}
.sb-close-btn:hover { background: rgba(255,255,255,0.1) !important; }

/* Desktop: hide close button */
@media (min-width: 900px) {
    .sb-close-btn { display: none !important; }
}

/* === 2-COLUMN MENU GRID === */
.sb-menu-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 10px !important;
    padding: 14px !important;
}

/* ── Card design — exact match to screenshot ── */
.sb-menu-card {
    background: #093327 !important;
    border-radius: 14px !important;
    padding: 18px 8px 14px !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
    text-decoration: none !important;
    cursor: pointer !important;
    min-height: 100px !important;
    position: relative !important;

    /* 
      Border: top & sides = slightly lighter teal, bottom = darker (3D depth)
      This matches the screenshot exactly — top-left edges are brighter,
      bottom edge is the "shadow" side
    */
    border-top: 1.5px solid rgba(30, 180, 130, 0.35) !important;
    border-left: 1.5px solid rgba(30, 180, 130, 0.25) !important;
    border-right: 1.5px solid rgba(10, 60, 40, 0.8) !important;
    border-bottom: 3px solid #031913 !important;  /* thick dark bottom = 3D base */

    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.07),   /* inner top highlight */
        0 6px 0 #031510,                          /* hard 3D bottom drop */
        0 8px 16px rgba(0,0,0,0.45) !important;  /* soft outer shadow */

    transition: transform 0.12s ease, box-shadow 0.12s ease !important;
}

.sb-menu-card:active {
    transform: translateY(5px) !important;
    border-bottom-width: 1px !important;
    box-shadow:
        inset 0 1px 0 rgba(255,255,255,0.07),
        0 1px 0 #031510,
        0 2px 6px rgba(0,0,0,0.3) !important;
}

/* Icon wrapper */
.sb-card-icon {
    font-size: 34px !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

/* Card label */
.sb-card-label {
    color: #ffffff !important;
    font-size: 13.5px !important;
    font-weight: 700 !important;
    text-align: center !important;
    line-height: 1.2 !important;
    letter-spacing: 0.2px !important;
    font-family: Arial, sans-serif !important;
}

/* Icon Colors */
.ic-red  { color: #d76d5e !important; }
.ic-teal { color: #49b9ab !important; }
.ic-gold { color: #db9c3f !important; }

/* Language flag */
.lang-flag-circle {
    width: 34px !important;
    height: 34px !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 28px !important;
    line-height: 1 !important;
}

/* === LOGOUT BOTTOM === */
.sb-bottom-strip {
    padding: 0 16px 40px 16px !important;
}
.btn-logout {
    background: rgba(239,68,68,0.08) !important;
    border: 1px solid rgba(239,68,68,0.25) !important;
    border-bottom: 3px solid rgba(180,30,30,0.5) !important;
    color: #ef4444 !important;
    padding: 12px !important;
    border-radius: 10px !important;
    text-align: center !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    text-decoration: none !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    width: 100% !important;
    transition: all 0.15s !important;
    box-shadow: 0 4px 0 rgba(120,20,20,0.4) !important;
}
.btn-logout:active {
    transform: translateY(3px) !important;
    box-shadow: 0 1px 0 rgba(120,20,20,0.4) !important;
}

/* Overlay */
#sidebarOverlay {
    background-color: rgba(0,0,0,0.65) !important;
    backdrop-filter: blur(6px) !important;
    -webkit-backdrop-filter: blur(6px) !important;
    z-index: 60 !important;
    position: fixed !important;
    top: 0; left: 0; right: 0; bottom: 0;
}
.hidden { display: none !important; }
</style>

<div id="sidebarOverlay" onclick="toggleSidebar()" class="hidden"></div>

<div id="sidebar">

    <div class="sb-header">
        <span class="sb-logo-text"><?php echo !empty($settings['site_name']) ? $settings['site_name'] : 'SHA75'; ?></span>
        <button onclick="toggleSidebar()" class="sb-close-btn">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sb-menu-grid">

        <a href="<?php echo getLink('all_category_games.php?cat_id=1'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-fire"></i></div>
            <span class="sb-card-label">Hot Games</span>
        </a>

        <a href="<?php echo getLink('invite.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-teal"><i class="fa-solid fa-user-group"></i></div>
            <span class="sb-card-label">Invite friends</span>
        </a>

        <a href="<?php echo getLink('favorites.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-folder-heart"></i></div>
            <span class="sb-card-label">Favorites</span>
        </a>

        <a href="<?php echo getLink('promotions.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-gold"><i class="fa-solid fa-gift"></i></div>
            <span class="sb-card-label">Promotion</span>
        </a>

        <a href="<?php echo getLink('all_category_games.php?cat_id=8'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-dice"></i></div>
            <span class="sb-card-label">Slots</span>
        </a>

        <a href="<?php echo getLink('reward.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-gold"><i class="fa-solid fa-award"></i></div>
            <span class="sb-card-label">Reward Center</span>
        </a>

        <a href="<?php echo getLink('all_category_games.php?cat_id=2'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-dharmachakra"></i></div>
            <span class="sb-card-label">Live Casino</span>
        </a>

        <a href="<?php echo getLink('rebate.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-gold"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <span class="sb-card-label">Manual Rebate</span>
        </a>

        <a href="<?php echo getLink('all_category_games.php?cat_id=3'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-cricket-bat-ball"></i></div>
            <span class="sb-card-label">Sports</span>
        </a>

        <a href="<?php echo getLink('vip.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-gold"><i class="fa-solid fa-gem"></i></div>
            <span class="sb-card-label">VIP</span>
        </a>

        <a href="<?php echo getLink('all_category_games.php?cat_id=1'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-gamepad"></i></div>
            <span class="sb-card-label">E-sports</span>
        </a>

        <a href="<?php echo getLink('mission.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-gold"><i class="fa-solid fa-bullseye"></i></div>
            <span class="sb-card-label">Mission</span>
        </a>

        <a href="<?php echo getLink('all_category_games.php?cat_id=4'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-spade"></i></div>
            <span class="sb-card-label">Poker</span>
        </a>

        <a href="<?php echo getLink('all_category_games.php?cat_id=5'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-fish"></i></div>
            <span class="sb-card-label">Fish</span>
        </a>

        <a href="<?php echo getLink('all_category_games.php?cat_id=7'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-red"><i class="fa-solid fa-ticket"></i></div>
            <span class="sb-card-label">Lottery</span>
        </a>

        <a href="<?php echo getLink('download.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-teal"><i class="fa-solid fa-cloud-arrow-down"></i></div>
            <span class="sb-card-label">APP Download</span>
        </a>

        <a href="<?php echo getLink('support_chat.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-teal"><i class="fa-solid fa-headset"></i></div>
            <span class="sb-card-label">Customer Service</span>
        </a>

        <a href="<?php echo getLink('share.php'); ?>" class="sb-menu-card">
            <div class="sb-card-icon ic-gold"><i class="fa-solid fa-handshake"></i></div>
            <span class="sb-card-label">Affiliate</span>
        </a>

    </div>

    <div class="sb-bottom-strip">
        <?php if(!$is_guest): ?>
        <a href="logout.php" class="btn-logout">
            <i class="fas fa-sign-out-alt"></i> Log Out
        </a>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var isDesktop = window.innerWidth >= 900;
    if (isDesktop) return; // desktop-এ toggle করা যাবে না
    sidebar.classList.toggle('open');
    overlay.classList.toggle('hidden');
}
</script>