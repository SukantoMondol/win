<?php
// লগইন স্ট্যাটাস চেক
$is_logged_in = isset($_SESSION['user_id']);

if (!function_exists('get_support_setting_link')) {
function get_support_setting_link($settings, $primaryKey, $legacyKey = '', $type = '') {
    $value = '';
    if (!empty($settings[$primaryKey])) {
        $value = trim($settings[$primaryKey]);
    } elseif ($legacyKey !== '' && !empty($settings[$legacyKey])) {
        $value = trim($settings[$legacyKey]);
    }

    if ($value === '' || $value === '#') {
        return '#';
    }

    // WhatsApp field also accepts a raw phone number from Admin Panel.
    if ($type === 'whatsapp' && preg_match('/^\+?[0-9\s\-()]+$/', $value)) {
        $digits = preg_replace('/\D+/', '', $value);
        return $digits !== '' ? 'https://wa.me/' . $digits : '#';
    }

    // Telegram field also accepts @username.
    if ($type === 'telegram' && isset($value[0]) && $value[0] === '@') {
        return 'https://t.me/' . ltrim($value, '@');
    }

    // Admin can write facebook.com/page, t.me/page etc. without https://
    if (!preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $value) && !preg_match('/^(mailto:|tel:)/i', $value)) {
        return 'https://' . ltrim($value, '/');
    }

    return $value;
}
}

$fb_link = get_support_setting_link($settings ?? [], 'facebook_link', 'facebook', 'facebook');
$ins_link = get_support_setting_link($settings ?? [], 'instagram_link', 'instagram', 'instagram');
$tg_link = get_support_setting_link($settings ?? [], 'telegram_link', 'telegram', 'telegram');
$wa_link = get_support_setting_link($settings ?? [], 'whatsapp_link', 'whatsapp', 'whatsapp');

$fb_link_attr = htmlspecialchars($fb_link, ENT_QUOTES, 'UTF-8');
$tg_link_attr = htmlspecialchars($tg_link, ENT_QUOTES, 'UTF-8');
$wa_link_attr = htmlspecialchars($wa_link, ENT_QUOTES, 'UTF-8');

// যদি লগইন না থাকে, তবে সোশ্যাল বাটন বা অন্য লিঙ্কে ক্লিক করলে login.php তে যাবে
$protected_login = !$is_logged_in ? 'login.php' : '';

$footer_site_name = !empty($settings['site_name']) ? $settings['site_name'] : 'BAJIXWIN';
$currentYear = date("Y");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* =============================================
   BOTTOM NAV BAR — Pill style (exact match)
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
    background: #071f18;
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

.bnav-item.active { color: #f5c518; }
.bnav-item.active .bnav-label {
    border-bottom: 2px solid #f5c518;
    padding-bottom: 1px;
}

.bnav-icon {
    font-size: 20px;
    line-height: 1;
}
.bnav-label { font-size: 10px; font-weight: 700; }

.bnav-item.center-invite {
    position: relative;
    flex: 1;
    justify-content: flex-end;
    padding-bottom: 0;
}
.bnav-invite-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    position: relative;
    top: -18px;
}
.bnav-invite-circle {
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
.bnav-invite-circle img {
    width: 100%; height: 100%; object-fit: contain;
}
.bnav-invite-circle i { font-size: 22px; }
.bnav-item.center-invite:active .bnav-invite-circle { transform: scale(0.93); }

.bnav-invite-label {
    font-size: 10px;
    font-weight: 700;
    color: #3db88a;
    margin: 0;
    letter-spacing: 0.2px;
}

.float-social-btns {
    position: fixed;
    right: 12px;
    bottom: 80px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.float-btn {
    width: 46px; height: 46px;
    border-radius: 50%;
    display: flex;
    align-items: center; justify-content: center;
    font-size: 20px;
    text-decoration: none;
    color: #fff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.4);
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
}
.float-btn:active { transform: scale(0.9); }
.float-btn img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.float-btn.wa-float  { background: #25d366; }
.float-btn.fb-float  { background: #1877f2; }
.float-btn.tg-float  { background: #0088cc; }
.float-btn.live-float { background: linear-gradient(135deg, #0d7a55, #13a36e); }

.footer-section {
    background: linear-gradient(180deg, #071f18 0%, #050f0c 100%);
    color: #7eb89a;
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 11px;
    padding: 24px 14px 90px 14px;
    line-height: 1.6;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.footer-container { max-width: 100%; margin: 0 auto; }

.cv-license-block {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: rgba(255,255,255,0.03);
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 18px;
    border: 1px solid rgba(255,255,255,0.06);
}
.cv-site-logo-text {
    background: linear-gradient(135deg, #0d4a3a, #1a7a5e);
    color: #ffd84d;
    font-weight: 900; font-style: italic;
    font-size: 13px;
    width: 52px; height: 52px;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px;
    border: 1px solid #1a7a5e;
}
.cv-license-text {
    font-size: 10px;
    color: #7eb89a;
    line-height: 1.6;
    flex: 1;
}

.cv-media-section { margin-bottom: 20px; }
.cv-media-title {
    font-size: 11px;
    font-weight: 800;
    color: rgba(255,255,255,0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
    display: block;
}
.cv-media-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.cv-media-item {
    height: 28px;
    opacity: 0.7;
    transition: opacity 0.2s;
    object-fit: contain;
}
.cv-media-item:hover { opacity: 1; }

.ft-separator { border: none; border-top: 1px solid rgba(255,255,255,0.05); margin: 16px 0; }

.ft-links-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.ft-link-col { display: flex; flex-direction: column; gap: 10px; }
.ft-link-item {
    border-left: 2px solid #0d7a55;
    padding-left: 8px;
    line-height: 1.3;
}
.ft-link-item a {
    color: #7eb89a;
    text-decoration: none;
    font-size: 10.5px;
    font-weight: 500;
    display: block;
    transition: color 0.2s;
}
.ft-link-item a:hover { color: #ffd84d; }

/* =============================================
   FIXED PROVIDER GRID — Full Box Style
   ============================================= */
.cv-providers-section { margin-bottom: 20px; }
.cv-providers-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 7px;
}
.cv-provider-item {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 7px;
    padding: 6px 10px; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    height: 40px;
    overflow: hidden; 
    transition: background 0.2s, border-color 0.2s;
}
.cv-provider-item:hover { 
    background: rgba(255,255,255,0.07); 
    border-color: rgba(255,255,255,0.15);
}
.cv-provider-item img {
    max-width: 90%; 
    max-height: 26px; 
    width: auto;
    height: auto;
    object-fit: contain;
    filter: brightness(0.85);
    transition: filter 0.2s, transform 0.2s;
}
.cv-provider-item:hover img { 
    filter: brightness(1); 
    transform: scale(1.05);
}
.cv-provider-item span { display: none; } 

.ft-brand {
    display: flex; align-items: center; gap: 12px;
    margin-top: 20px;
    background: rgba(0,0,0,0.2);
    padding: 10px 12px; border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.05);
}
.bb-logo {
    background: linear-gradient(135deg, #0d4a3a, #071f18);
    color: #ffd84d;
    font-weight: 900; font-style: italic;
    padding: 4px 10px; border-radius: 6px;
    font-size: 16px;
    border: 1px solid #1a7a5e;
}
.brand-text h4 { color: #e8f5ee; font-size: 12px; font-weight: 800; margin: 0 0 2px 0; }
.brand-text p { font-size: 10px; color: #7eb89a; margin: 0; }

.seo-content h3 {
    color: #e8f5ee; font-size: 12px; font-weight: 800;
    margin-bottom: 10px; margin-top: 20px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.seo-content p {
    margin-bottom: 10px; text-align: justify;
    color: #4a7a5e; line-height: 1.6; font-size: 10px;
}
</style>

<div class="float-social-btns">
    <a href="<?php echo !$is_logged_in ? 'login.php' : $wa_link_attr; ?>" class="float-btn wa-float" target="<?php echo !$is_logged_in ? '_self' : '_blank'; ?>">
        <i class="fab fa-whatsapp"></i>
    </a>
    <a href="<?php echo !$is_logged_in ? 'login.php' : $fb_link_attr; ?>" class="float-btn fb-float" target="<?php echo !$is_logged_in ? '_self' : '_blank'; ?>">
        <i class="fab fa-facebook-f"></i>
    </a>
    <a href="<?php echo !$is_logged_in ? 'login.php' : $tg_link_attr; ?>" class="float-btn tg-float" target="<?php echo !$is_logged_in ? '_self' : '_blank'; ?>">
        <i class="fab fa-telegram-plane"></i>
    </a>
    <a href="<?php echo !$is_logged_in ? 'login.php' : 'support_chat.php'; ?>" class="float-btn live-float">
        <i class="fas fa-headset"></i>
    </a>
</div>

<nav class="bottom-nav">
    <div class="bottom-nav-pill">
        <a href="index.php" class="bnav-item active">
            <span class="bnav-icon"><i class="fas fa-home"></i></span>
            <span class="bnav-label">Home</span>
        </a>
        <a href="<?php echo !$is_logged_in ? 'login.php' : 'promotions.php'; ?>" class="bnav-item">
            <span class="bnav-icon"><i class="fas fa-gift"></i></span>
            <span class="bnav-label">Promotion</span>
        </a>
        <a href="<?php echo !$is_logged_in ? 'login.php' : 'referral.php'; ?>" class="bnav-item center-invite">
            <div class="bnav-invite-wrap">
                <div class="bnav-invite-circle"><i class="fas fa-share-nodes"></i></div>
                <span class="bnav-invite-label">Invite</span>
            </div>
        </a>
        <a href="<?php echo !$is_logged_in ? 'login.php' : 'rewards.php'; ?>" class="bnav-item">
            <span class="bnav-icon"><i class="fas fa-trophy"></i></span>
            <span class="bnav-label">Reward</span>
        </a>
        <a href="<?php echo !$is_logged_in ? 'login.php' : 'account.php'; ?>" class="bnav-item">
            <span class="bnav-icon"><i class="fas fa-user-circle"></i></span>
            <span class="bnav-label">Member</span>
        </a>
    </div>
</nav>

<?php
$imgPrefix = file_exists('assets/img/bkash.png') ? 'assets/img' : '../assets/img';
?>
<div class="footer-section">
    <div class="footer-container">

        <div class="cv-media-section">
            <span class="cv-media-title">Payment Methods</span>
            <div class="cv-media-grid">
                <img src="<?php echo $imgPrefix; ?>/bkash.png"   class="cv-media-item" alt="bKash">
                <img src="<?php echo $imgPrefix; ?>/nagad.png"   class="cv-media-item" alt="Nagad">
                <img src="<?php echo $imgPrefix; ?>/rocket.png"  class="cv-media-item" alt="Rocket">
                <img src="<?php echo $imgPrefix; ?>/upay.png"    class="cv-media-item" alt="Upay">
                <img src="<?php echo $imgPrefix; ?>/bank.png"    class="cv-media-item" alt="Bank">
                <img src="<?php echo $imgPrefix; ?>/crypto.png"  class="cv-media-item" alt="Crypto">
            </div>
        </div>

        <hr class="ft-separator">

        <div class="cv-providers-section">
            <div class="cv-providers-grid">
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/pg.png" alt="PG"><span>PG</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/bng.png" alt="BNG"><span>BNG</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/pp.png" alt="Pragmatic"><span>PRAGMATIC</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/jili.png" alt="JILI"><span>JILI</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/bom.png" alt="SPRIBE"><span>BOM</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/fc.png" alt="FA CHAI"><span>FA CHAI</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/CG-COLOR.png" alt="NetEnt"><span>CG</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/jdb.png" alt="JDB"><span>JDB</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/FTG-COLOR.png" alt="Gameplay"><span>FTG</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/AMBS-COLOR.png" alt="Booming"><span>AMBS</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/mega.png" alt="MEGA"><span>MEGA</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/jk.png" alt="JOKER"><span>JOKER</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/TPG-COLOR.png" alt="Spadegaming"><span>TPG</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/YB-COLOR.png" alt="AskMeSlot"><span>YB</span></div>
                <div class="cv-provider-item"><img src="https://s3-eu-west-1.amazonaws.com/tpd/logos/62ee6a82d2b7fa45d5216e8f/0x0.png" alt="Evolution"><span>Evolation</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/YL-COLOR.png" alt="PlayStar"><span>YL</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/AE-COLOR.png" alt="Yellow Bat"><span>AE</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/NE-COLOR.png" alt="First Person"><span>NE</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/PS-COLOR.png" alt="KK Gaming"><span>PS</span></div>
                <div class="cv-provider-item"><img src="<?php echo $imgPrefix; ?>/providers/SG-COLOR.png" alt="7 Mojos"><span>SG</span></div>
            </div>
        </div>

        <hr class="ft-separator">

        <div class="ft-brand">
            <div class="bb-logo"><?php echo strtoupper(substr($footer_site_name, 0, 2)); ?></div>
            <div class="brand-text">
                <h4>Best Quality Platform</h4>
                <p>© <?php echo $currentYear; ?> <?php echo $footer_site_name; ?>. All Rights Reserved</p>
            </div>
        </div>

        <div class="seo-content">
            <h3><?php echo $footer_site_name; ?> Bangladesh — Top Casino & Betting Platform</h3>
            <p><?php echo $footer_site_name; ?> is one of the most trusted online casino and betting platforms in Bangladesh, offering cricket betting, slots, live casino, and more with top-tier security and fast payments via bKash, Nagad, Rocket, and Crypto.</p>
            <p>Join <?php echo $footer_site_name; ?> today and try your luck!</p>
        </div>
    </div>
</div>