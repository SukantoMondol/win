<?php
// main root index.php

session_start();

// লগইন থাকলে dashboard এ পাঠিয়ে দাও
if (isset($_SESSION['user_id'])) {
    header("Location: player/dashboard.php");
    exit();
}

$db_path = __DIR__ . '/includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
}
$functions_path = __DIR__ . '/includes/functions.php';
if (file_exists($functions_path)) { require_once $functions_path; }
$theme_helper_path = __DIR__ . '/includes/theme_helper.php';
if (file_exists($theme_helper_path)) { require_once $theme_helper_path; }
if (isset($conn) && !$conn->connect_error && function_exists('wcb_apply_category_name_patch')) {
    wcb_apply_category_name_patch($conn);
}

$settings = [];
if (isset($conn) && !$conn->connect_error) {
    $res = $conn->query("SELECT * FROM settings WHERE id=1");
    if ($res && $res->num_rows > 0) {
        $settings = $res->fetch_assoc();
    }
}
$site_name = !empty($settings['site_name']) ? $settings['site_name'] : 'SHA75';
$app_logo_src = !empty($settings['app_logo']) ? $settings['app_logo'] : 'assets/img/app_logo.jpeg';

$sliders = isset($conn) && !$conn->connect_error ? $conn->query("SELECT * FROM sliders WHERE status='active' ORDER BY sort_order ASC") : false;

// এই পেজে সবসময় guest
$is_logged_in = false;
$balance = 0.00;

// HOT GAMES
$hot_games = [];
if (isset($conn) && !$conn->connect_error) {
    $helper_path = __DIR__ . '/includes/game_api_helper.php';
    if (file_exists($helper_path)) {
        require_once $helper_path;
        if (function_exists('game_api_seed_jili_mappings')) {
            @game_api_seed_jili_mappings($conn);
        }
    }

    $hq = $conn->query("
        SELECT g.* FROM games g
        LEFT JOIN front_category_games fcg ON g.game_uid = fcg.game_uid
        LEFT JOIN front_categories fc ON fcg.category_id = fc.id AND fc.status = 1
        WHERE g.status = 'active'
          AND (g.provider_id = '49' OR UPPER(COALESCE(g.api_vendor_code,''))='JILI' OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%jili%')
        GROUP BY g.id
        ORDER BY MIN(CASE WHEN fcg.game_uid IS NOT NULL AND fc.id IS NOT NULL THEN 0 ELSE 1 END) ASC,
                 MIN(COALESCE(fc.priority, 9999) * 10000 + COALESCE(fcg.sort_order, 9999)) ASC,
                 g.id DESC
        LIMIT 200
    ");
    if ($hq && $hq->num_rows > 0) {
        while ($row = $hq->fetch_assoc()) $hot_games[] = $row;
    }
    if (function_exists('game_api_jili_prepare_display_rows')) {
        $hot_games = game_api_jili_prepare_display_rows($conn, $hot_games, 6);
    } else {
        $hot_games = array_slice($hot_games, 0, 6);
    }
}

$ua = $_SERVER['HTTP_USER_AGENT'];
$is_webview = (strpos($ua, 'lucky365-WebView-App') !== false);

// Protected links → login, Social links → direct
$login_url    = 'player/login.php';
$signup_url   = 'player/signup.php';
$deposit_url  = $login_url . '?redirect=player/deposit.php';
$withdraw_url = $login_url . '?redirect=player/withdraw.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="referrer" content="no-referrer">
    <title><?php echo $site_name; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <?php if (function_exists('get_site_theme_css')) { echo get_site_theme_css($settings); } ?>
    <style>
        :root {
            --bg-deep:    #071f18;
            --bg-main:    #0a2e22;
            --bg-card:    #0d3d2c;
            --bg-card2:   #0f4530;
            --teal:       #0d7a55;
            --teal-light: #13a36e;
            --gold:       #f0c030;
            --gold-dark:  #c89a10;
            --gold-text:  #ffd84d;
            --green-btn:  #0a6644;
            --text-main:  #e8f5ee;
            --text-muted: #7eb89a;
            --border:     rgba(255,255,255,0.08);
            --glass:      rgba(13, 61, 44, 0.6);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: var(--bg-deep);
            color: var(--text-main);
            padding-bottom: 70px;
            min-height: 100vh;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
            background-image: linear-gradient(135deg, #071f18 0%, #0a2e22 50%, #071f18 100%);
        }

        /* ─── APP INSTALL BANNER ─── */
        .app-banner-top {
            width: 100%; background: linear-gradient(135deg, #0a2e22 0%, #0d3d2c 100%);
            border-bottom: 1px solid rgba(240,192,48,0.25);
            padding: 8px 10px; display: flex; align-items: center;
            justify-content: space-between; z-index: 200; position: relative;
        }
        .app-banner-top-left { display: flex; align-items: center; gap: 9px; }
        .app-banner-top-close {
            width: 22px; height: 22px; border-radius: 50%;
            background: rgba(255,255,255,0.1); display: flex;
            align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted); font-size: 11px;
            border: none; flex-shrink: 0;
        }
        .app-banner-top-logo {
            width: 34px; height: 34px; border-radius: 8px; overflow: hidden;
            background: var(--teal); display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
        }
        .app-banner-top-logo img { width: 100%; height: 100%; object-fit: contain; }
        .app-banner-top-name { font-size: 12px; font-weight: 800; color: var(--text-main); line-height: 1.2; }
        .app-banner-top-stars { color: var(--gold-text); font-size: 10px; }
        .app-banner-top-install {
            padding: 7px 15px; border-radius: 8px; font-size: 12px; font-weight: 800;
            text-transform: uppercase;
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #2a1500; border: none; cursor: pointer;
            box-shadow: 0 4px 0 #8a6a00, 0 4px 8px rgba(0,0,0,0.3);
            transition: all 0.15s; text-decoration: none; display: inline-block;
            border-bottom: 2px solid #ffe57a;
        }
        .app-banner-top-install:active { transform: translateY(3px); box-shadow: 0 1px 0 #8a6a00; }

        /* ─── HEADER ─── */
        .site-header {
            position: sticky; top: 0; z-index: 100; height: 56px;
            background: rgba(7,31,24,0.92); backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; padding: 0 12px;
        }
        .logo-text {
            font-size: 22px; font-weight: 900; font-style: italic; color: var(--gold-text);
            letter-spacing: 1px; text-shadow: 0 0 20px rgba(240,192,48,0.4);
        }
        .logo-text span { color: #fff; }
        .site-header-logo-img { height: 38px; max-width: 150px; object-fit: contain; display:block; }
        .header-right { display: flex; align-items: center; gap: 8px; }

        .btn-login {
            padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 700;
            cursor: pointer; border: none; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s;
            background: linear-gradient(180deg, #1a9966 0%, #0d7a55 60%, #0a5c3e 100%);
            color: #fff; box-shadow: 0 4px 0 #064028, 0 4px 8px rgba(0,0,0,0.4);
            border-bottom: 2px solid #1dcc85;
        }
        .btn-login:active { transform: translateY(3px); box-shadow: 0 1px 0 #064028, 0 1px 4px rgba(0,0,0,0.4); }

        .btn-register {
            padding: 7px 16px; border-radius: 6px; font-size: 13px; font-weight: 700;
            cursor: pointer; border: none; text-decoration: none;
            display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s;
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #3a2500; box-shadow: 0 4px 0 #8a6a00, 0 4px 8px rgba(0,0,0,0.4);
            border-bottom: 2px solid #ffe57a;
        }
        .btn-register:active { transform: translateY(3px); box-shadow: 0 1px 0 #8a6a00, 0 1px 4px rgba(0,0,0,0.4); }

        /* ─── HAMBURGER ─── */
        .hamburger-btn {
            background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px; cursor: pointer; padding: 7px 8px;
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; position: relative; transition: background 0.2s;
        }
        .hamburger-btn:active { background: rgba(255,255,255,0.15); }
        .hamburger-icon { display: flex; flex-direction: column; gap: 4px; position: relative; width: 18px; }
        .hamburger-icon .hb-arrow { display: flex; align-items: center; gap: 2px; }
        .hamburger-icon .hb-arrow-left {
            width: 0; height: 0; border-top: 4px solid transparent;
            border-bottom: 4px solid transparent; border-right: 5px solid #fff; flex-shrink: 0;
        }
        .hamburger-icon .hb-line { height: 2px; background: #fff; border-radius: 2px; display: block; }
        .hamburger-icon .hb-line.l1 { width: 100%; }
        .hamburger-icon .hb-line.l2 { width: 100%; }
        .hamburger-icon .hb-line.l3 { width: 65%; }

        /* ─── ANNOUNCEMENT BAR ─── */
        .announce-bar {
            background: rgba(255,255,255,0.04); border-bottom: 1px solid var(--border);
            padding: 7px 14px; display: flex; align-items: center; gap: 8px;
        }
        .announce-bar .ann-icon { font-size: 14px; color: var(--gold-text); flex-shrink: 0; }
        .announce-bar marquee { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        /* ─── SLIDER ─── */
        .slider-wrap { padding: 10px 10px 4px; }
        .swiper.mainSlider { border-radius: 12px; overflow: hidden; }
        .mainSlider img { width: 100%; height: 170px; object-fit: cover; display: block; border-radius: 12px; }
        .swiper-pagination-bullet {
            background: rgba(255,255,255,0.3) !important; opacity: 1 !important;
            width: 6px !important; height: 6px !important; transition: all 0.3s;
        }
        .swiper-pagination-bullet-active {
            background: var(--gold-text) !important; width: 18px !important; border-radius: 3px !important;
        }

        /* ─── DEPOSIT & WITHDRAW ─── */
        .quick-actions { display: flex; gap: 10px; padding: 10px 10px 4px; }
        .qa-btn {
            flex: 1; display: flex; align-items: center; justify-content: center;
            gap: 8px; padding: 13px 10px; border-radius: 10px;
            font-size: 14px; font-weight: 800; text-decoration: none;
            transition: all 0.15s; cursor: pointer; border: none;
        }
        .qa-btn i { font-size: 16px; }
        .qa-btn.deposit {
            background: linear-gradient(180deg, #1a9966 0%, #0d7a55 60%, #0a5c3e 100%);
            color: #fff; box-shadow: 0 4px 0 #064028, 0 4px 12px rgba(13,122,85,0.4);
            border-bottom: 2px solid #1dcc85;
        }
        .qa-btn.deposit:active { transform: translateY(3px); box-shadow: 0 1px 0 #064028; }
        .qa-btn.withdraw {
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #2a1500; box-shadow: 0 4px 0 #8a6a00, 0 4px 12px rgba(240,192,48,0.3);
            border-bottom: 2px solid #ffe57a;
        }
        .qa-btn.withdraw:active { transform: translateY(3px); box-shadow: 0 1px 0 #8a6a00; }

        /* ─── SECTION HEADER ─── */
        .hot-section { margin-bottom: 6px; }
        .sec-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 12px 8px; }
        .sec-title {
            display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 800;
            color: var(--gold-text); text-transform: uppercase; letter-spacing: 0.5px;
        }
        .sec-title i { font-size: 18px; color: var(--gold-text); }
        .sec-actions { display: flex; align-items: center; gap: 6px; }

        .btn-see-all {
            padding: 6px 13px; border-radius: 7px; font-size: 12px; font-weight: 800;
            color: var(--gold-text);
            background: linear-gradient(180deg, rgba(255,220,70,0.18) 0%, rgba(240,192,48,0.10) 100%);
            border: 1px solid rgba(240,192,48,0.4); border-bottom: 2px solid rgba(255,220,80,0.6);
            text-decoration: none; transition: all 0.15s; box-shadow: 0 3px 0 rgba(0,0,0,0.3);
            display: inline-flex; align-items: center;
        }
        .btn-see-all:active { transform: translateY(2px); box-shadow: 0 1px 0 rgba(0,0,0,0.3); }

        .btn-nav-arrow {
            width: 30px; height: 30px; border-radius: 7px;
            background: linear-gradient(180deg, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.05) 100%);
            border: 1px solid rgba(255,255,255,0.15); border-bottom: 2px solid rgba(255,255,255,0.25);
            color: var(--text-muted); display: flex; align-items: center; justify-content: center;
            font-size: 12px; cursor: pointer; box-shadow: 0 3px 0 rgba(0,0,0,0.3);
            transition: all 0.15s; text-decoration: none;
        }
        .btn-nav-arrow:active { transform: translateY(2px); box-shadow: 0 1px 0 rgba(0,0,0,0.3); color: var(--gold-text); }

        /* ─── GAME GRID ─── */
        .games-section { padding: 0 10px; margin-bottom: 6px; }
        .game-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        @media (min-width: 600px) { .game-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (min-width: 900px) {
            .game-grid { grid-template-columns: repeat(6, 1fr); }
            .mainSlider img { height: 240px; }
        }

        .game-card {
            position: relative; border-radius: 10px; overflow: hidden; background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.07); border-bottom: 2px solid rgba(255,255,255,0.12);
            transition: all 0.2s; text-decoration: none; display: block; box-shadow: 0 4px 0 rgba(0,0,0,0.4);
        }
        .game-card:active { transform: scale(0.94) translateY(3px); border-color: var(--teal-light); box-shadow: 0 1px 0 rgba(0,0,0,0.4); }
        .game-card-img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }

        .game-card-fav {
            position: absolute; top: 5px; right: 5px; width: 26px; height: 26px;
            border-radius: 50%; background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.6); font-size: 13px; cursor: pointer;
            z-index: 5; transition: color 0.2s, background 0.2s; border: none; flex-shrink: 0;
        }
        .game-card-fav.active { color: var(--gold-text); background: rgba(240,192,48,0.18); }

        .game-card-name {
            font-size: 10px; font-weight: 700; color: var(--text-main); padding: 5px 6px;
            text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .game-card-provider {
            font-size: 9px; color: var(--text-muted); text-align: center;
            padding: 0 6px 5px; font-weight: 600; text-transform: uppercase;
        }

        /* ─── JACKPOT ─── */
        .jackpot-section {
            margin: 8px 10px; border-radius: 14px;
            background: linear-gradient(135deg, #0a2e22 0%, #0d3d2c 50%, #071f18 100%);
            border: 1px solid rgba(240,192,48,0.25); padding: 18px 16px 16px;
            text-align: center; position: relative; overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .jackpot-section::before {
            content: ''; position: absolute; top: -30px; left: -30px; width: 120px; height: 120px;
            background: radial-gradient(circle, rgba(240,192,48,0.12) 0%, transparent 70%); border-radius: 50%;
        }
        .jackpot-section::after {
            content: ''; position: absolute; bottom: -30px; right: -30px; width: 120px; height: 120px;
            background: radial-gradient(circle, rgba(13,122,85,0.15) 0%, transparent 70%); border-radius: 50%;
        }
        .jackpot-label { font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; color: var(--text-muted); margin-bottom: 6px; }
        .jackpot-img-row {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; margin-bottom: 10px; position: relative; z-index: 1;
        }
        .jackpot-plane-img {
            width: 64px; height: 64px; object-fit: contain;
            animation: planeFly 3s ease-in-out infinite;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.5));
        }
        @keyframes planeFly {
            0%   { transform: translateY(0px) rotate(-3deg); }
            50%  { transform: translateY(-8px) rotate(3deg); }
            100% { transform: translateY(0px) rotate(-3deg); }
        }
        .jackpot-logo-img { height: 48px; object-fit: contain; filter: drop-shadow(0 0 16px rgba(240,192,48,0.5)); }
        .jackpot-number-wrap { display: flex; align-items: center; justify-content: center; gap: 2px; flex-wrap: nowrap; }
        .jp-digit {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 46px;
            background: linear-gradient(180deg, #1a1a0e 0%, #0d0d06 100%);
            border: 1px solid rgba(240,192,48,0.3); border-radius: 6px;
            font-size: 26px; font-weight: 900; color: var(--gold-text);
            text-shadow: 0 0 12px rgba(240,192,48,0.6);
            box-shadow: 0 3px 0 rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);
            font-family: 'Courier New', monospace; transition: all 0.15s;
        }
        .jp-digit.changing { animation: digitFlip 0.15s ease; }
        .jp-sep { font-size: 24px; font-weight: 900; color: var(--gold-text); margin: 0 1px; line-height: 1; padding-bottom: 4px; }
        @keyframes digitFlip {
            0%   { transform: scaleY(1); }
            50%  { transform: scaleY(0.1); }
            100% { transform: scaleY(1); }
        }

        /* ─── CATEGORY NAV ─── */
        .cat-nav-wrap {
            padding: 12px 10px 0; overflow-x: auto; white-space: nowrap;
            scrollbar-width: none; -ms-overflow-style: none;
        }
        .cat-nav-wrap::-webkit-scrollbar { display: none; }
        .cat-nav-inner { display: inline-flex; gap: 8px; padding-bottom: 10px; }
        .cat-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 20px; font-size: 12px; font-weight: 700;
            text-decoration: none; color: var(--text-muted);
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.07);
            transition: all 0.2s; white-space: nowrap; cursor: pointer;
        }
        .cat-pill i { font-size: 13px; }
        .cat-pill.active, .cat-pill:active {
            background: var(--teal); border-color: var(--teal-light); color: #fff;
            box-shadow: 0 2px 10px rgba(13,122,85,0.4);
        }

        /* Category section */
        .cat-section {
            margin-bottom: 6px; background: rgba(13,61,44,0.3);
            border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); padding-bottom: 8px;
        }

        /* ─── PARTNER ─── */
        .partner-section { padding: 16px 12px; display: flex; gap: 12px; }
        .partner-btn {
            flex: 1; padding: 14px 10px; border-radius: 10px; text-align: center;
            font-size: 14px; font-weight: 800; cursor: pointer; border: none;
            transition: all 0.15s; text-decoration: none; display: block;
        }
        .partner-btn.partner {
            background: linear-gradient(135deg, #1a3a2e 0%, #0d2a1e 100%);
            border: 1px solid rgba(255,255,255,0.12); border-bottom: 2px solid rgba(255,255,255,0.2);
            color: var(--gold-text); box-shadow: 0 4px 0 #040f09, inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .partner-btn.live-chat {
            background: linear-gradient(135deg, #1a4a35 0%, #0d3524 100%);
            border: 1px solid rgba(255,255,255,0.12); border-bottom: 2px solid rgba(255,255,255,0.2);
            color: var(--gold-text); box-shadow: 0 4px 0 #040f09, inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .partner-btn:active { transform: translateY(3px); box-shadow: 0 1px 0 #040f09; }
        .partner-btn .chat-icon { font-size: 20px; margin-right: 6px; }

        /* ─── GAME CENTER ─── */
        .game-center { padding: 8px 12px 16px; }
        .game-center-title { font-size: 18px; font-weight: 800; color: var(--gold-text); margin-bottom: 12px; }
        .game-center-pills { display: flex; flex-wrap: wrap; gap: 8px; }
        .gc-pill {
            padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 700;
            color: var(--teal-light); border: 1px solid rgba(13,122,85,0.4);
            border-bottom: 2px solid rgba(30,200,130,0.5);
            background: linear-gradient(180deg, rgba(13,122,85,0.12) 0%, rgba(13,122,85,0.06) 100%);
            text-decoration: none; transition: all 0.15s; box-shadow: 0 3px 0 rgba(0,0,0,0.3);
        }
        .gc-pill:active { transform: translateY(2px); box-shadow: 0 1px 0 rgba(0,0,0,0.3); }

        /* ─── PROVIDER LOGOS ─── */
        .providers-section { padding: 12px; border-top: 1px solid var(--border); }
        .providers-grid { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .provider-logo { font-size: 11px; font-weight: 800; color: var(--text-muted); letter-spacing: 0.5px; opacity: 0.7; }

        /* ─── GUEST BOTTOM NAV ─── */
        .guest-nav {
            position: fixed; bottom: 0; left: 0; width: 100%; height: 60px;
            display: flex; z-index: 100; background: rgba(7,20,14,0.97); border-top: 1px solid var(--border);
        }
        .guest-lang {
            width: 30%; display: flex; align-items: center; justify-content: center;
            gap: 6px; font-size: 12px; font-weight: 700; color: var(--text-main);
            border-right: 1px solid var(--border); cursor: pointer;
        }
        .guest-flag { width: 20px; height: 20px; border-radius: 50%; overflow: hidden; }
        .guest-flag img { width: 100%; height: 100%; object-fit: cover; }
        .guest-login-btn {
            width: 35%; display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 800; cursor: pointer;
            background: linear-gradient(180deg, #1a9966 0%, #0d7a55 60%, #0a5c3e 100%);
            color: #fff; border-right: 1px solid var(--border); border-top: 2px solid #1dcc85;
            transition: 0.15s; box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .guest-login-btn:active { filter: brightness(0.85); }
        .guest-signup-btn {
            width: 35%; display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 800; cursor: pointer;
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #2a1500; border-top: 2px solid #ffe57a; transition: 0.15s;
        }
        .guest-signup-btn:active { filter: brightness(0.9); }

        /* ─── DESKTOP SIDEBAR ─── */
        @media (min-width: 900px) {
            .main-layout { display: flex; max-width: 1200px; margin: 0 auto; }
            .desktop-sidebar {
                width: 120px; flex-shrink: 0; position: sticky; top: 56px;
                height: calc(100vh - 56px); overflow-y: auto; padding: 12px 6px;
                border-right: 1px solid var(--border); scrollbar-width: none;
            }
            .desktop-sidebar::-webkit-scrollbar { display: none; }
            .main-content { flex: 1; min-width: 0; }
            .guest-nav { display: none; }
            .site-header { max-width: 1200px; width: 100%; left: 50%; transform: translateX(-50%); }
        }
        .sidebar-item {
            display: flex; flex-direction: column; align-items: center; gap: 5px;
            padding: 10px 6px; border-radius: 10px; margin-bottom: 4px; cursor: pointer;
            text-decoration: none; color: var(--text-muted); font-size: 10px;
            font-weight: 700; text-align: center; transition: all 0.2s;
        }
        .sidebar-item i { font-size: 20px; }
        .sidebar-item:hover, .sidebar-item.active {
            background: rgba(13,122,85,0.15); color: var(--gold-text); border: 1px solid rgba(13,122,85,0.3);
        }
    </style>
<?php include __DIR__ . '/includes/pwa_install.php'; ?>
<?php
$pwa_banner_icon = isset($pwa['icon_192']) && $pwa['icon_192'] !== '' ? htmlspecialchars($pwa['icon_192'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($app_logo_src, ENT_QUOTES, 'UTF-8');
$pwa_banner_name = isset($pwa['app_name']) && trim((string)$pwa['app_name']) !== '' ? htmlspecialchars($pwa['app_name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($site_name . ' Web App', ENT_QUOTES, 'UTF-8');
$pwa_banner_version = isset($pwa['version']) ? max(1, intval($pwa['version'])) : time();
?>
</head>
<body>

<!-- APP INSTALL BANNER -->
<?php if(!$is_webview): ?>
<div id="dlBannerTop" class="app-banner-top">
    <div class="app-banner-top-left">
        <button class="app-banner-top-close" onclick="document.getElementById('dlBannerTop').style.display='none'">
            <i class="fas fa-times"></i>
        </button>
        <div class="app-banner-top-logo">
            <img src="<?php echo $pwa_banner_icon; ?>?v=<?php echo $pwa_banner_version; ?>" alt="<?php echo $pwa_banner_name; ?>" onerror="this.src='https://placehold.co/34x34/0d7a55/fff?text=App'">
        </div>
        <div class="app-banner-top-info">
            <div class="app-banner-top-name"><?php echo $pwa_banner_name; ?></div>
            <div class="app-banner-top-stars">★★★★½</div>
        </div>
    </div>
    <a href="download.php" class="app-banner-top-install js-pwa-install">Download</a>
</div>
<?php endif; ?>

<!-- HEADER -->
<header class="site-header">
    <button class="hamburger-btn" onclick="toggleSidebar()">
        <div class="hamburger-icon">
            <div class="hb-arrow">
                <span class="hb-arrow-left"></span>
                <span class="hb-line l1"></span>
            </div>
            <span class="hb-line l2"></span>
            <span class="hb-line l3"></span>
        </div>
    </button>
    <div class="logo-text">
        <?php if(!empty($settings['app_logo'])): ?>
            <img src="<?php echo htmlspecialchars($app_logo_src); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="site-header-logo-img">
        <?php else: ?>
            <?php
            $name = $site_name;
            $name = str_replace('75',  '<span style="color:#a8d8ff">75</span>', $name);
            $name = str_replace('365', '<span style="color:#a8d8ff">365</span>', $name);
            echo $name;
            ?>
        <?php endif; ?>
    </div>
    <div class="header-right">
        <a href="<?php echo $login_url; ?>" class="btn-login">Log In</a>
        <a href="<?php echo $signup_url; ?>" class="btn-register">Register</a>
    </div>
</header>

<!-- ANNOUNCEMENT -->
<div class="announce-bar">
    <i class="fas fa-bullhorn ann-icon"></i>
    <marquee scrollamount="4">
        <?php echo $settings['marquee_text'] ?? 'Welcome to '.$site_name.' — The world\'s favorite online betting platform.'; ?>
    </marquee>
</div>

<div class="main-layout">

    <!-- DESKTOP SIDEBAR -->
    <aside class="desktop-sidebar" id="desktop-sidebar" style="display:none">
        <?php
        $sidebar_items = [
            ['icon'=>'fa-fire',    'label'=>'গরম খেলা'],
            ['icon'=>'fa-heart',   'label'=>'প্রিয়'],
            ['icon'=>'fa-dice',    'label'=>'স্লট'],
            ['icon'=>'fa-video',   'label'=>'লাইভ'],
            ['icon'=>'fa-futbol',  'label'=>'স্পোর্টস'],
            ['icon'=>'fa-gamepad', 'label'=>'ই-স্পোর্টস'],
            ['icon'=>'fa-cards',   'label'=>'পোকার'],
            ['icon'=>'fa-fish',    'label'=>'ফিশিং'],
            ['icon'=>'fa-ticket',  'label'=>'লটারি'],
        ];
        foreach($sidebar_items as $i => $si):
        ?>
        <a href="<?php echo $login_url; ?>" class="sidebar-item <?php echo $i===0 ? 'active' : ''; ?>">
            <i class="fas <?php echo $si['icon']; ?>"></i>
            <span><?php echo $si['label']; ?></span>
        </a>
        <?php endforeach; ?>
    </aside>

    <div class="main-content">

        <!-- SLIDER -->
        <div class="slider-wrap">
            <div class="swiper mainSlider" style="border-radius:12px;overflow:hidden">
                <div class="swiper-wrapper">
                    <?php if($sliders && $sliders->num_rows > 0):
                        while($slide = $sliders->fetch_assoc()): ?>
                        <div class="swiper-slide">
                            <?php 
                            $s_path = trim((string)$slide['image_path']);
                            $s_src = (strpos($s_path, 'data:') === 0 || strpos($s_path, 'http') === 0 || strpos($s_path, '/') === 0) ? $s_path : ('/' . ltrim($s_path, '/'));
                            ?>
                            <img src="<?php echo $s_src; ?>" loading="lazy" decoding="async" style="width:100%;height:170px;object-fit:cover;display:block;border-radius:12px">
                        </div>
                    <?php endwhile; else: ?>
                        <div class="swiper-slide">
                            <img src="https://placehold.co/800x340/0d4a3a/ffd84d?text=Welcome+Bonus" style="width:100%;height:170px;object-fit:cover;display:block;border-radius:12px">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination" style="bottom:10px"></div>
            </div>
        </div>

        <!-- DEPOSIT & WITHDRAW → login -->
        <div class="quick-actions">
            <a href="<?php echo $deposit_url; ?>" class="qa-btn deposit">
                <i class="fas fa-plus-circle"></i> Deposit
            </a>
            <a href="<?php echo $withdraw_url; ?>" class="qa-btn withdraw">
                <i class="fas fa-arrow-up-from-bracket"></i> Withdraw
            </a>
        </div>
        
        <!-- CATEGORY PILLS -->
        <nav class="cat-nav-wrap">
            <div class="cat-nav-inner">
                <a href="#" class="cat-pill active" onclick="activatePill(this)">
                    <i class="fas fa-fire"></i> HOT GAMES
                </a>
                <a href="<?php echo $login_url; ?>" class="cat-pill">
                    <i class="fas fa-heart"></i> FAVORITES
                </a>
                <?php
                if(isset($conn) && !$conn->connect_error){
                    $nav_cats = $conn->query("SELECT * FROM front_categories WHERE status=1 ORDER BY priority ASC");
                    if($nav_cats && $nav_cats->num_rows > 0):
                        while($nc = $nav_cats->fetch_assoc()):
                            $icon = 'fa-gamepad';
                            $cat_label = function_exists('wcb_front_category_label') ? wcb_front_category_label($nc['name']) : $nc['name'];
                            $n = strtolower($nc['name']);
                            if(strpos($n,'slot')!==false)        $icon='fa-dice';
                            elseif(strpos($n,'live')!==false)    $icon='fa-video';
                            elseif(strpos($n,'sport')!==false)   $icon='fa-futbol';
                            elseif(strpos($n,'crash')!==false)   $icon='fa-rocket';
                            elseif(strpos($n,'poker')!==false)   $icon='fa-spade';
                            elseif(strpos($n,'fish')!==false)    $icon='fa-fish';
                            elseif(strpos($n,'lotto')!==false||strpos($n,'lottery')!==false) $icon='fa-ticket';
                ?>
                <a href="#cat-<?php echo $nc['id']; ?>" class="cat-pill" onclick="activatePill(this)">
                    <i class="fas <?php echo $icon; ?>"></i> <?php echo strtoupper(htmlspecialchars($cat_label, ENT_QUOTES, 'UTF-8')); ?>
                </a>
                <?php endwhile; endif; } ?>
            </div>
        </nav>

        <!-- HOT GAMES → all links go to login -->
        <?php if(!empty($hot_games)): ?>
        <section class="hot-section">
            <div class="sec-header">
                <div class="sec-title"><i class="fas fa-fire"></i> HOT GAMES</div>
                <div class="sec-actions">
                    <a href="<?php echo $login_url; ?>" class="btn-see-all">See All</a>
                    <a href="<?php echo $login_url; ?>" class="btn-nav-arrow"><i class="fas fa-chevron-left"></i></a>
                    <a href="<?php echo $login_url; ?>" class="btn-nav-arrow"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="games-section">
                <div class="game-grid">
                    <?php foreach($hot_games as $g):
                        $redirect = urlencode('/player/login.php');
                        $link = $login_url . '?redirect=' . $redirect;
                    ?>
                    <a href="<?php echo $link; ?>" class="game-card">
                        <img class="game-card-img"
                             loading="lazy" decoding="async"
                             src="<?php echo function_exists('game_api_prepare_game_image') ? game_api_prepare_game_image($g) : $g['image']; ?>"
                             onerror="this.src='https://placehold.co/120x120/0d3d2c/ffd84d?text=GAME'"
                             alt="<?php echo htmlspecialchars($g['name']); ?>">
                        <button class="game-card-fav" data-game-uid="<?php echo htmlspecialchars($g['game_uid'], ENT_QUOTES); ?>" onclick="toggleFav(event, this)" title="Favorite">
                            <i class="fas fa-heart"></i>
                        </button>
                        <div class="game-card-name"><?php echo $g['name']; ?></div>
                        <?php if(!empty($g['provider'])): ?>
                        <div class="game-card-provider"><?php echo $g['provider']; ?></div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- JACKPOT SECTION -->
        <div class="jackpot-section">
            
            <div class="jackpot-img-row">
                <img src="/player/assets/img/aroplane.png" class="jackpot-plane-img"
                     onerror="this.style.display='none'" alt="Airplane">
                <img src="/player/assets/img/jackpot.png" class="jackpot-logo-img"
                     onerror="this.outerHTML='<span style=\'font-size:28px;font-weight:900;font-style:italic;color:var(--gold-text);text-shadow:0 0 30px rgba(240,192,48,0.5);letter-spacing:1px\'>Jackpot</span>'"
                     alt="Jackpot">
            </div>
            <div class="jackpot-number-wrap" id="jackpotDisplay"></div>
        </div>

        

        <!-- GAME SECTIONS (category) → all links go to login -->
        <?php
        if(isset($conn) && !$conn->connect_error){
            $cats_content = $conn->query("SELECT * FROM front_categories WHERE status=1 ORDER BY priority ASC");
            if($cats_content && $cats_content->num_rows > 0):
                while($cat = $cats_content->fetch_assoc()):
                    $cat_id = $cat['id'];
                    $cat_label = function_exists('wcb_front_category_label') ? wcb_front_category_label($cat['name']) : $cat['name'];
                    
                    $is_poker = (stripos($cat['name'], 'poker') !== false);
                    if ($is_poker) {
                        $games_query = $conn->query("
                            SELECT g.* FROM games g
                            WHERE g.provider_id = '119' AND g.status = 'active'
                            ORDER BY g.id DESC LIMIT 12
                        ");
                    } else {
                        $live_filter = "";
                        if (stripos($cat['name'], 'live') !== false) {
                            $live_filter = " AND (LOWER(g.api_game_type) LIKE '%live%' OR LOWER(g.category) LIKE '%live%' OR LOWER(g.api_provider_name) LIKE '%live%' OR g.provider_id IN ('58','59','78','87','88','89'))";
                        }

                        $limit_val = 12;
                        if (stripos($cat['name'], 'live') !== false) {
                            $limit_val = 6;
                        }
                        $games_query = $conn->query("
                            SELECT g.* FROM front_category_games fcg
                            JOIN games g ON fcg.game_uid = g.game_uid
                            WHERE fcg.category_id = $cat_id
                              $live_filter
                            ORDER BY fcg.sort_order ASC LIMIT $limit_val
                        ");
                    }
                    if(!$games_query || $games_query->num_rows < 1) continue;
                    $games_list = [];
                    while($row = $games_query->fetch_assoc()) $games_list[] = $row;
        ?>
        <section id="cat-<?php echo $cat_id; ?>" class="cat-section">
            <div class="sec-header">
                <div class="sec-title">
                    <i class="fas fa-fire"></i>
                    <?php echo strtoupper(htmlspecialchars($cat_label, ENT_QUOTES, 'UTF-8')); ?>
                </div>
                <div class="sec-actions">
                    <a href="<?php echo $login_url; ?>" class="btn-see-all">See All</a>
                    <a href="<?php echo $login_url; ?>" class="btn-nav-arrow"><i class="fas fa-chevron-left"></i></a>
                    <a href="<?php echo $login_url; ?>" class="btn-nav-arrow"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="games-section">
                <div class="game-grid">
                    <?php foreach($games_list as $g):
                        $redirect = urlencode('/player/launch.php?game_id='.$g['game_uid']);
                        $link = $login_url . '?redirect=' . $redirect;
                    ?>
                    <a href="<?php echo $link; ?>" class="game-card">
                        <img class="game-card-img"
                             loading="lazy" decoding="async"
                             src="<?php echo function_exists('game_api_prepare_game_image') ? game_api_prepare_game_image($g) : $g['image']; ?>"
                             onerror="this.src='https://placehold.co/120x120/0d3d2c/ffd84d?text=GAME'"
                             alt="<?php echo htmlspecialchars($g['name']); ?>">
                        <button class="game-card-fav" data-game-uid="<?php echo htmlspecialchars($g['game_uid'], ENT_QUOTES); ?>" onclick="toggleFav(event, this)" title="Favorite">
                            <i class="fas fa-heart"></i>
                        </button>
                        <div class="game-card-name"><?php echo $g['name']; ?></div>
                        <?php if(!empty($g['provider'])): ?>
                        <div class="game-card-provider"><?php echo $g['provider']; ?></div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php
                endwhile;
            else:
        ?>
        <div style="text-align:center;padding:48px 20px;color:var(--text-muted)">
            <i class="fas fa-ghost" style="font-size:48px;margin-bottom:12px;display:block;opacity:0.4"></i>
            <p style="font-size:14px;font-weight:700">No games configured yet.</p>
        </div>
        <?php endif; } ?>

        <!-- PARTNER + LIVE CHAT — login ছাড়াও যেতে পারে -->
        <div class="partner-section">
            <a href="player/partner.php" class="partner-btn partner">🤝 PARTNER</a>
            <a href="player/support_chat.php" class="partner-btn live-chat">
                <span class="chat-icon">💬</span> Live Chat
            </a>
        </div>

        <!-- GAME CENTER → login -->
        <div class="game-center">
            <div class="game-center-title">Game Center</div>
            <div class="game-center-pills">
                <?php foreach(['Slots','Live Casino','Sports','E-sports','Poker','Fish','Lottery'] as $gc): ?>
                <a href="<?php echo $login_url; ?>" class="gc-pill"><?php echo $gc; ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- PROVIDER LOGOS -->
        

        <div style="height:20px"></div>
    </div>
</div>

<!-- GUEST BOTTOM NAV -->
<div class="guest-nav">
    <div class="guest-lang">
        <div class="guest-flag"><img src="https://flagcdn.com/w40/bd.png"></div>
        <div style="display:flex;flex-direction:column;line-height:1.2">
            <span>BDT</span>
            <span style="font-size:9px;font-weight:400;color:var(--text-muted)">English</span>
        </div>
    </div>
    <div class="guest-login-btn" onclick="location.href='<?php echo $login_url; ?>'">Log In</div>
    <div class="guest-signup-btn" onclick="location.href='<?php echo $signup_url; ?>'">Registration</div>
</div>

<?php include 'includes/sidebar_player.php'; ?>
<?php include 'player/sectionlast.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script>
new Swiper(".mainSlider", {
    loop: true,
    autoplay: { delay: 3500, disableOnInteraction: false },
    pagination: { el: ".swiper-pagination", clickable: true }
});

function toggleSidebar() {
    if (window.innerWidth >= 900) return;
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if(sidebar) sidebar.classList.toggle('open');
    if(overlay) overlay.classList.toggle('hidden');
}

function activatePill(el) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
}

function handleResize() {
    const dSidebar = document.getElementById('desktop-sidebar');
    if(dSidebar) dSidebar.style.display = window.innerWidth >= 900 ? 'block' : 'none';
}
handleResize();
window.addEventListener('resize', handleResize);

const FAV_STORAGE_KEY = 'redjili_favorites';
function getFavoriteGames() {
    try { return JSON.parse(localStorage.getItem(FAV_STORAGE_KEY) || '[]'); }
    catch (e) { return []; }
}
function setFavoriteGames(list) {
    localStorage.setItem(FAV_STORAGE_KEY, JSON.stringify([...new Set(list)]));
}
function toggleFav(e, btn) {
    e.preventDefault();
    e.stopPropagation();
    const uid = btn.getAttribute('data-game-uid');
    if (!uid) return;
    let favs = getFavoriteGames();
    if (favs.includes(uid)) {
        favs = favs.filter(id => id !== uid);
        btn.classList.remove('active');
    } else {
        favs.push(uid);
        btn.classList.add('active');
    }
    setFavoriteGames(favs);
}
function initFavoriteButtons() {
    const favs = getFavoriteGames();
    document.querySelectorAll('.game-card-fav[data-game-uid]').forEach(btn => {
        if (favs.includes(btn.getAttribute('data-game-uid'))) btn.classList.add('active');
    });
}
document.addEventListener('DOMContentLoaded', initFavoriteButtons);

// JACKPOT COUNTER
(function() {
    const BASE_NUMBER = 10921288702;
    let currentVal = BASE_NUMBER;

    function formatJackpot(val) {
        let intPart = Math.floor(val / 100);
        let decPart = (val % 100).toString().padStart(2, '0');
        return intPart.toLocaleString('en-US') + '.' + decPart;
    }

    function buildDigitHTML(numStr) {
        let html = '';
        for (let i = 0; i < numStr.length; i++) {
            let ch = numStr[i];
            if (ch === ',')      html += '<span class="jp-sep">,</span>';
            else if (ch === '.') html += '<span class="jp-sep">.</span>';
            else                 html += '<span class="jp-digit" id="jpd-' + i + '">' + ch + '</span>';
        }
        return html;
    }

    function renderJackpot(animated) {
        const container = document.getElementById('jackpotDisplay');
        if (!container) return;
        const numStr = formatJackpot(currentVal);
        if (!animated) { container.innerHTML = buildDigitHTML(numStr); return; }
        const spans = container.querySelectorAll('.jp-digit');
        const digits = numStr.replace(/[,.]/g, '');
        let idx = 0;
        spans.forEach(span => {
            const newChar = digits[idx] || '0';
            if (span.textContent !== newChar) {
                span.classList.remove('changing');
                void span.offsetWidth;
                span.classList.add('changing');
                span.textContent = newChar;
                setTimeout(() => span.classList.remove('changing'), 200);
            }
            idx++;
        });
    }

    renderJackpot(false);
    (function scheduleNext() {
        setTimeout(function() {
            currentVal += Math.floor(Math.random() * 9998) + 1;
            renderJackpot(true);
            scheduleNext();
        }, 1500 + Math.random() * 2000);
    })();
})();
</script>
<?php include __DIR__ . '/includes/modal_popup.php'; ?>

<?php
if (isset($conn) && isset($_SESSION['user_id'])) {
    $wcb_bonus_helper = __DIR__ . '/includes/bonus_system_helper.php';
    if (file_exists($wcb_bonus_helper)) {
        require_once $wcb_bonus_helper;
        echo wcb_daily_bonus_popup_html($conn, intval($_SESSION['user_id']));
    }
}
?>
<script>
(function() {
    // Disable right-click context menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    }, false);

    // Disable common shortcuts used for developer tools
    document.addEventListener('keydown', function(e) {
        // F12
        if (e.key === 'F12') {
            e.preventDefault();
            return false;
        }
        // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
            e.preventDefault();
            return false;
        }
        // Ctrl+U (View Source)
        if (e.ctrlKey && (e.key === 'U' || e.key === 'u')) {
            e.preventDefault();
            return false;
        }
    }, false);

    // Continuous debugger statement to halt browser when DevTools is opened
    setInterval(function() {
        (function() {
            return false;
        }
        ['constructor']('debugger')());
    }, 200);
})();
</script>
</body>
</html>