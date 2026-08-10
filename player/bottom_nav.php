<?php
/**
 * Clean & Generic Bottom Navigation Bar
 * Social buttons, login checks, and specific names have been removed.
 */
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* =============================================
   BOTTOM NAV BAR — Pill Style
   ============================================= */
.bottom-nav {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    z-index: 10000;
    display: flex;
    align-items: flex-end;
    justify-content: space-around;
    padding: 0 10px 8px 10px;
    height: 70px;
    background: #071f18; /* Outer background */
}

.bottom-nav-pill {
    width: 100%;
    max-width: 480px;
    margin: 0 auto;
    height: 58px;
    background: linear-gradient(180deg, #0e3d2c 0%, #0a2d1f 100%);
    border-radius: 999px;
    border: 1.5px solid #1a5c40;
    box-shadow:
        0 0 0 2px #071f18,
        inset 0 1px 0 rgba(30,200,130,0.18),
        0 -2px 0 0 #1edd96,
        0 4px 24px rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: space-around;
    padding: 0 6px;
    position: relative;
}

.bnav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    flex: 1;
    text-decoration: none;
    color: #3db88a;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.2px;
    padding: 6px 0;
    transition: color 0.2s;
    position: relative;
}

/* Active State - Automatically detects current page */
.bnav-item.active { color: #f5c518; }
.bnav-item.active .bnav-label {
    border-bottom: 2px solid #f5c518;
    padding-bottom: 1px;
}

.bnav-icon { font-size: 20px; line-height: 1; }
.bnav-label { font-size: 10px; font-weight: 700; }

/* Center Invite Button Style */
.bnav-item.center-btn {
    position: relative;
    flex: 1;
    justify-content: flex-end;
    padding-bottom: 0;
}
.bnav-center-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    position: relative;
    top: -18px; /* Lifted above pill */
}
.bnav-center-circle {
    width: 54px; height: 54px;
    border-radius: 50%;
    background: linear-gradient(145deg, #1de9b6, #00897b);
    display: flex;
    align-items: center; justify-content: center;
    box-shadow:
        0 0 0 3px #071f18,
        0 0 0 5px #1edd96,
        0 6px 20px rgba(0,188,140,0.55);
    font-size: 24px;
    color: #fff;
    transition: transform 0.2s;
    border: none;
    overflow: hidden;
}
.bnav-item.center-btn:active .bnav-center-circle { transform: scale(0.93); }

.bnav-center-label {
    font-size: 10px;
    font-weight: 700;
    color: #3db88a;
    margin: 0;
}
</style>

<nav class="bottom-nav">
    <div class="bottom-nav-pill">
        
        <a href="index.php" class="bnav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
            <span class="bnav-icon"><i class="fas fa-home"></i></span>
            <span class="bnav-label">Home</span>
        </a>

        <a href="promotions.php" class="bnav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'promotions.php') ? 'active' : ''; ?>">
            <span class="bnav-icon"><i class="fas fa-gift"></i></span>
            <span class="bnav-label">Promotion</span>
        </a>

        <a href="referral.php" class="bnav-item center-btn">
            <div class="bnav-center-wrap">
                <div class="bnav-center-circle"><i class="fas fa-share-nodes"></i></div>
                <span class="bnav-center-label">Invite</span>
            </div>
        </a>

        <a href="rewards.php" class="bnav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'rewards.php') ? 'active' : ''; ?>">
            <span class="bnav-icon"><i class="fas fa-trophy"></i></span>
            <span class="bnav-label">Reward</span>
        </a>

        <a href="account.php" class="bnav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'account.php') ? 'active' : ''; ?>">
            <span class="bnav-icon"><i class="fas fa-user-circle"></i></span>
            <span class="bnav-label">Member</span>
        </a>
        
    </div>
</nav>
<?php
// Global PWA installer + Daily Free Bonus popup for player pages.
$pwa_helper = __DIR__ . '/../includes/pwa_install.php';
if (file_exists($pwa_helper)) { include $pwa_helper; }
if (isset($conn) && isset($_SESSION['user_id'])) {
    $wcb_bonus_helper = __DIR__ . '/../includes/bonus_system_helper.php';
    if (file_exists($wcb_bonus_helper)) {
        require_once $wcb_bonus_helper;
        echo wcb_daily_bonus_popup_html($conn, intval($_SESSION['user_id']));
    }
}
?>
