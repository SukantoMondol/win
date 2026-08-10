<?php
session_start();

//player/dashboard.php

$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) {
    require $db_path;
} else {
    $conn = new mysqli('localhost', 'root', '', 'bating');
}
$functions_path = file_exists('includes/functions.php') ? 'includes/functions.php' : '../includes/functions.php';
if (file_exists($functions_path)) { require_once $functions_path; }
if (isset($conn) && !$conn->connect_error && function_exists('wcb_apply_category_name_patch')) {
    wcb_apply_category_name_patch($conn);
}
if (file_exists('../includes/game_api_helper.php')) { require_once '../includes/game_api_helper.php'; }
if (file_exists('../includes/game_api_evolution_patch.php')) { require_once '../includes/game_api_evolution_patch.php'; }
if (isset($conn) && !$conn->connect_error && function_exists('game_api_seed_jili_mappings')) {
    @game_api_seed_jili_mappings($conn);
}
if (isset($conn) && !$conn->connect_error && function_exists('game_api_evolution_ensure_patch')) {
    game_api_evolution_ensure_patch($conn);
}

$settings = [];
if (isset($conn) && !$conn->connect_error) {
    $res = $conn->query("SELECT * FROM settings WHERE id=1");
    if ($res && $res->num_rows > 0) {
        $settings = $res->fetch_assoc();
    }
}
$site_name = !empty($settings['site_name']) ? $settings['site_name'] : 'SHA75';
$app_logo_src = !empty($settings['app_logo']) ? '../' . ltrim($settings['app_logo'], '/') : '../assets/img/app_logo.jpeg';

$sliders = isset($conn) && !$conn->connect_error ? $conn->query("SELECT * FROM sliders WHERE status='active' ORDER BY sort_order ASC") : false;

// Ensure 9Wicket appears under Sports without changing existing layout/images.
// This is a lightweight auto-migration so cPanel file replace is enough after importing the same SQL backup.
if (isset($conn) && !$conn->connect_error) {
    $sportsRes = @$conn->query("SELECT id FROM front_categories WHERE LOWER(name)='sports' LIMIT 1");
    $sportsId = ($sportsRes && $sportsRes->num_rows > 0) ? (int)$sportsRes->fetch_assoc()['id'] : 0;
    if ($sportsId > 0) {
        @$conn->query("UPDATE game_providers SET type='sports' WHERE provider_id='141' AND type <> 'sports'");
        @$conn->query("UPDATE games SET category='Sports', image='/uploads/game_icons/9wickets.jpg', api_game_type=COALESCE(NULLIF(api_game_type,''),'Sports'), api_vendor_code=COALESCE(NULLIF(api_vendor_code,''),'9wicket'), api_provider_name=COALESCE(NULLIF(api_provider_name,''),'9Wicket') WHERE (game_uid='11539' OR provider_id='141' OR LOWER(COALESCE(api_vendor_code,''))='9wicket' OR LOWER(name)='9wicket') AND status='active'");
        $wicketRes = @$conn->query("SELECT game_uid FROM games WHERE (game_uid='11539' OR provider_id='141' OR LOWER(COALESCE(api_vendor_code,''))='9wicket' OR LOWER(name)='9wicket') AND status='active' ORDER BY (game_uid='11539') DESC LIMIT 1");
        if ($wicketRes && $wicketRes->num_rows > 0) {
            $wicketUid = $conn->real_escape_string($wicketRes->fetch_assoc()['game_uid']);
            $existsRes = @$conn->query("SELECT id FROM front_category_games WHERE category_id={$sportsId} AND game_uid='{$wicketUid}' LIMIT 1");
            if (!$existsRes || $existsRes->num_rows === 0) {
                $sortRes = @$conn->query("SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM front_category_games WHERE category_id={$sportsId}");
                $nextSort = ($sortRes && $sortRes->num_rows > 0) ? (int)$sortRes->fetch_assoc()['next_sort'] : 0;
                @$conn->query("INSERT INTO front_category_games (category_id, game_uid, sort_order) VALUES ({$sportsId}, '{$wicketUid}', {$nextSort})");
            }
        }
    }
}

$is_logged_in = isset($_SESSION['user_id']);
$balance = 0.00;
if ($is_logged_in && isset($conn) && !$conn->connect_error) {
    $uid = $_SESSION['user_id'];
    $u_res = $conn->query("SELECT balance FROM users WHERE id=$uid");
    if($u_res && $u_res->num_rows > 0){
        $balance = $u_res->fetch_assoc()['balance'];
    }
}

// ── HOT GAMES (first category or all) ──
$hot_games = [];
$hot_cat_id = 1;
if (isset($conn) && !$conn->connect_error) {
    $cat_q = $conn->query("SELECT id FROM front_categories WHERE LOWER(name) LIKE '%hot%' OR LOWER(name) LIKE '%popular%' LIMIT 1");
    if ($cat_q && $cat_q->num_rows > 0) {
        $hot_cat_id = (int)$cat_q->fetch_assoc()['id'];
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

$current_page = basename($_SERVER['PHP_SELF']);
$ua = $_SERVER['HTTP_USER_AGENT'];
$is_webview = (strpos($ua, 'BajiPari-WebView-App') !== false);
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

        /* ─── APP INSTALL BANNER (TOP) ─── */
        .app-banner-top {
            width: 100%;
            background: linear-gradient(135deg, #0a2e22 0%, #0d3d2c 100%);
            border-bottom: 1px solid rgba(240,192,48,0.25);
            padding: 8px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 200;
            position: relative;
        }
        .app-banner-top-left { display: flex; align-items: center; gap: 9px; }
        .app-banner-top-close {
            width: 22px; height: 22px; border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--text-muted); font-size: 11px;
            border: none; flex-shrink: 0;
        }
        .app-banner-top-logo {
            width: 34px; height: 34px; border-radius: 8px;
            overflow: hidden; background: var(--teal);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .app-banner-top-logo img { width: 100%; height: 100%; object-fit: contain; }
        .app-banner-top-info {}
        .app-banner-top-name { font-size: 12px; font-weight: 800; color: var(--text-main); line-height: 1.2; }
        .app-banner-top-stars { color: var(--gold-text); font-size: 10px; }
        .app-banner-top-install {
            padding: 7px 15px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #2a1500;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 0 #8a6a00, 0 4px 8px rgba(0,0,0,0.3);
            transition: all 0.15s;
            text-decoration: none;
            display: inline-block;
            border-bottom: 2px solid #ffe57a;
        }
        .app-banner-top-install:active { transform: translateY(3px); box-shadow: 0 1px 0 #8a6a00; }

        /* ─── HEADER ─── */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 100;
            height: 56px;
            background: rgba(7,31,24,0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 12px;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 900;
            font-style: italic;
            color: var(--gold-text);
            letter-spacing: 1px;
            text-shadow: 0 0 20px rgba(240,192,48,0.4);
        }
        .logo-text span { color: #fff; }
        .site-header-logo-img { height: 38px; max-width: 150px; object-fit: contain; display:block; }

        .header-right { display: flex; align-items: center; gap: 8px; }

        /* 3D Button style */
        .btn-login {
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
            background: linear-gradient(180deg, #1a9966 0%, #0d7a55 60%, #0a5c3e 100%);
            color: #fff;
            box-shadow: 0 4px 0 #064028, 0 4px 8px rgba(0,0,0,0.4);
            border-bottom: 2px solid #1dcc85;
        }
        .btn-login:active {
            transform: translateY(3px);
            box-shadow: 0 1px 0 #064028, 0 1px 4px rgba(0,0,0,0.4);
        }

        .btn-register {
            padding: 7px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #3a2500;
            box-shadow: 0 4px 0 #8a6a00, 0 4px 8px rgba(0,0,0,0.4);
            border-bottom: 2px solid #ffe57a;
        }
        .btn-register:active {
            transform: translateY(3px);
            box-shadow: 0 1px 0 #8a6a00, 0 1px 4px rgba(0,0,0,0.4);
        }

        /* ─── HAMBURGER ─── */
        .hamburger-btn {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            cursor: pointer;
            padding: 7px 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            position: relative;
            transition: background 0.2s;
        }
        .hamburger-btn:active { background: rgba(255,255,255,0.15); }
        .hamburger-icon {
            display: flex;
            flex-direction: column;
            gap: 4px;
            position: relative;
            width: 18px;
        }
        .hamburger-icon .hb-arrow {
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .hamburger-icon .hb-arrow-left {
            width: 0;
            height: 0;
            border-top: 4px solid transparent;
            border-bottom: 4px solid transparent;
            border-right: 5px solid #fff;
            flex-shrink: 0;
        }
        .hamburger-icon .hb-line {
            height: 2px;
            background: #fff;
            border-radius: 2px;
            display: block;
        }
        .hamburger-icon .hb-line.l1 { width: 100%; }
        .hamburger-icon .hb-line.l2 { width: 100%; }
        .hamburger-icon .hb-line.l3 { width: 65%; }

        /* ─── BALANCE CHIP WITH DROPDOWN ─── */
        .balance-wrap {
            position: relative;
        }
        .balance-chip {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            padding: 4px 10px;
            text-align: right;
            cursor: pointer;
            transition: background 0.2s;
            user-select: none;
            -webkit-user-select: none;
        }
        .balance-chip:hover, .balance-chip.open {
            background: rgba(255,255,255,0.13);
        }
        .balance-chip .bal-label { font-size: 9px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .balance-chip .bal-amount { font-size: 14px; font-weight: 800; color: var(--gold-text); }

        /* Dropdown menu */
        .balance-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 200px;
            background: linear-gradient(180deg, #0d3d2c 0%, #0a2e22 100%);
            border: 1px solid rgba(255,255,255,0.13);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.55);
            overflow: hidden;
            z-index: 999;
            display: none;
            animation: dropIn 0.18s ease;
        }
        .balance-dropdown.show { display: block; }
        @keyframes dropIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .balance-dropdown a {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-main);
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            transition: background 0.15s;
        }
        .balance-dropdown a:last-child { border-bottom: none; }
        .balance-dropdown a:hover { background: rgba(13,122,85,0.18); color: var(--gold-text); }
        .balance-dropdown a i {
            width: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--teal-light);
            flex-shrink: 0;
        }
        .balance-dropdown a:hover i { color: var(--gold-text); }
        .balance-dropdown .dd-logout { color: #ff6b6b !important; }
        .balance-dropdown .dd-logout i { color: #ff6b6b !important; }

        /* ─── ANNOUNCEMENT BAR ─── */
        .announce-bar {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid var(--border);
            padding: 7px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .announce-bar .ann-icon {
            font-size: 14px;
            color: var(--gold-text);
            flex-shrink: 0;
        }
        .announce-bar marquee {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* ─── SLIDER ─── */
        .slider-wrap {
            padding: 10px 10px 4px;
        }
        .swiper.mainSlider {
            border-radius: 12px;
            overflow: hidden;
        }
        .mainSlider img {
            width: 100%;
            height: 170px;
            object-fit: cover;
            display: block;
            border-radius: 12px;
        }
        .swiper-pagination-bullet {
            background: rgba(255,255,255,0.3) !important;
            opacity: 1 !important;
            width: 6px !important;
            height: 6px !important;
            transition: all 0.3s;
        }
        .swiper-pagination-bullet-active {
            background: var(--gold-text) !important;
            width: 18px !important;
            border-radius: 3px !important;
        }

        /* ─── DEPOSIT & WITHDRAW QUICK BUTTONS ─── */
        .quick-actions {
            display: flex;
            gap: 10px;
            padding: 10px 10px 4px;
        }
        .qa-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 10px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.15s;
            cursor: pointer;
            border: none;
        }
        .qa-btn i { font-size: 16px; }
        .qa-btn.deposit {
            background: linear-gradient(180deg, #1a9966 0%, #0d7a55 60%, #0a5c3e 100%);
            color: #fff;
            box-shadow: 0 4px 0 #064028, 0 4px 12px rgba(13,122,85,0.4);
            border-bottom: 2px solid #1dcc85;
        }
        .qa-btn.deposit:active {
            transform: translateY(3px);
            box-shadow: 0 1px 0 #064028;
        }
        .qa-btn.withdraw {
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #2a1500;
            box-shadow: 0 4px 0 #8a6a00, 0 4px 12px rgba(240,192,48,0.3);
            border-bottom: 2px solid #ffe57a;
        }
        .qa-btn.withdraw:active {
            transform: translateY(3px);
            box-shadow: 0 1px 0 #8a6a00;
        }

        /* ─── HOT GAMES SECTION ─── */
        .hot-section {
            margin-bottom: 6px;
        }
        .sec-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 12px 8px;
        }
        .sec-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 800;
            color: var(--gold-text);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sec-title i { font-size: 18px; color: var(--gold-text); }
        .sec-actions { display: flex; align-items: center; gap: 6px; }

        .btn-see-all {
            padding: 6px 13px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 800;
            color: var(--gold-text);
            background: linear-gradient(180deg, rgba(255,220,70,0.18) 0%, rgba(240,192,48,0.10) 100%);
            border: 1px solid rgba(240,192,48,0.4);
            border-bottom: 2px solid rgba(255,220,80,0.6);
            text-decoration: none;
            transition: all 0.15s;
            box-shadow: 0 3px 0 rgba(0,0,0,0.3);
            display: inline-flex;
            align-items: center;
        }
        .btn-see-all:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 rgba(0,0,0,0.3);
        }
        .btn-nav-arrow {
            width: 30px;
            height: 30px;
            border-radius: 7px;
            background: linear-gradient(180deg, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.05) 100%);
            border: 1px solid rgba(255,255,255,0.15);
            border-bottom: 2px solid rgba(255,255,255,0.25);
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            box-shadow: 0 3px 0 rgba(0,0,0,0.3);
            transition: all 0.15s;
            text-decoration: none;
        }
        .btn-nav-arrow:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 rgba(0,0,0,0.3);
            color: var(--gold-text);
        }

        /* ─── GAME GRID ─── */
        .games-section {
            padding: 0 10px;
            margin-bottom: 6px;
        }
        .game-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        @media (min-width: 600px) {
            .game-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (min-width: 900px) {
            .game-grid { grid-template-columns: repeat(6, 1fr); }
            .mainSlider img { height: 240px; }
        }

        .game-card {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.07);
            border-bottom: 2px solid rgba(255,255,255,0.12);
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            box-shadow: 0 4px 0 rgba(0,0,0,0.4);
        }
        .game-card:active { transform: scale(0.94) translateY(3px); border-color: var(--teal-light); box-shadow: 0 1px 0 rgba(0,0,0,0.4); }
        .game-card-img {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
        }
        .game-card-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            background: var(--gold);
            color: #2a1500;
            font-size: 9px;
            font-weight: 900;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        /* ─── FAVORITE BUTTON ─── */
        .game-card-fav {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            cursor: pointer;
            z-index: 5;
            transition: color 0.2s, background 0.2s;
            border: none;
            flex-shrink: 0;
        }
        .game-card-fav.active {
            color: var(--gold-text);
            background: rgba(240,192,48,0.18);
        }
        .game-card-name {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-main);
            padding: 5px 6px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .game-card-provider {
            font-size: 9px;
            color: var(--text-muted);
            text-align: center;
            padding: 0 6px 5px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* ─── JACKPOT SECTION ─── */
        .jackpot-section {
            margin: 8px 10px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0a2e22 0%, #0d3d2c 50%, #071f18 100%);
            border: 1px solid rgba(240,192,48,0.25);
            padding: 18px 16px 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.06);
        }
        .jackpot-section::before {
            content: '';
            position: absolute;
            top: -30px; left: -30px;
            width: 120px; height: 120px;
            background: radial-gradient(circle, rgba(240,192,48,0.12) 0%, transparent 70%);
            border-radius: 50%;
        }
        .jackpot-section::after {
            content: '';
            position: absolute;
            bottom: -30px; right: -30px;
            width: 120px; height: 120px;
            background: radial-gradient(circle, rgba(13,122,85,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .jackpot-label {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        /* Jackpot image header row */
        .jackpot-img-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .jackpot-plane-img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            /* Floating animation */
            animation: planeFly 3s ease-in-out infinite;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.5));
        }
        @keyframes planeFly {
            0%   { transform: translateY(0px) rotate(-3deg); }
            50%  { transform: translateY(-8px) rotate(3deg); }
            100% { transform: translateY(0px) rotate(-3deg); }
        }
        .jackpot-logo-img {
            height: 48px;
            object-fit: contain;
            filter: drop-shadow(0 0 16px rgba(240,192,48,0.5));
        }

        .jackpot-number-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            flex-wrap: nowrap;
        }
        .jp-digit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 46px;
            background: linear-gradient(180deg, #1a1a0e 0%, #0d0d06 100%);
            border: 1px solid rgba(240,192,48,0.3);
            border-radius: 6px;
            font-size: 26px;
            font-weight: 900;
            color: var(--gold-text);
            text-shadow: 0 0 12px rgba(240,192,48,0.6);
            box-shadow: 0 3px 0 rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);
            font-family: 'Courier New', monospace;
            transition: all 0.15s;
        }
        .jp-digit.changing {
            animation: digitFlip 0.15s ease;
        }
        .jp-sep {
            font-size: 24px;
            font-weight: 900;
            color: var(--gold-text);
            margin: 0 1px;
            line-height: 1;
            padding-bottom: 4px;
        }
        @keyframes digitFlip {
            0%   { transform: scaleY(1); }
            50%  { transform: scaleY(0.1); }
            100% { transform: scaleY(1); }
        }

        /* ─── CATEGORY NAV (pill tabs) ─── */
        .cat-nav-wrap {
            padding: 12px 10px 0;
            overflow-x: auto;
            white-space: nowrap;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .cat-nav-wrap::-webkit-scrollbar { display: none; }
        .cat-nav-inner { display: inline-flex; gap: 8px; padding-bottom: 10px; }
        .cat-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            color: var(--text-muted);
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.07);
            transition: all 0.2s;
            white-space: nowrap;
            cursor: pointer;
        }
        .cat-pill i { font-size: 13px; }
        .cat-pill.active,
        .cat-pill:active {
            background: var(--teal);
            border-color: var(--teal-light);
            color: #fff;
            box-shadow: 0 2px 10px rgba(13,122,85,0.4);
        }

        /* Category swiper section */
        .cat-section {
            margin-bottom: 6px;
            background: rgba(13,61,44,0.3);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
        }
        .cat-section .swiper-pagination { bottom: 0 !important; }
        .cat-section .swiper-pagination-bullet { background: rgba(255,255,255,0.2) !important; }
        .cat-section .swiper-pagination-bullet-active { background: var(--teal-light) !important; }

        /* ─── PARTNER BUTTONS ─── */
        .partner-section {
            padding: 16px 12px;
            display: flex;
            gap: 12px;
        }
        .partner-btn {
            flex: 1;
            padding: 14px 10px;
            border-radius: 10px;
            text-align: center;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
            text-decoration: none;
            display: block;
        }
        .partner-btn.partner {
            background: linear-gradient(135deg, #1a3a2e 0%, #0d2a1e 100%);
            border: 1px solid rgba(255,255,255,0.12);
            border-bottom: 2px solid rgba(255,255,255,0.2);
            color: var(--gold-text);
            box-shadow: 0 4px 0 #040f09, inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .partner-btn.live-chat {
            background: linear-gradient(135deg, #1a4a35 0%, #0d3524 100%);
            border: 1px solid rgba(255,255,255,0.12);
            border-bottom: 2px solid rgba(255,255,255,0.2);
            color: var(--gold-text);
            box-shadow: 0 4px 0 #040f09, inset 0 1px 0 rgba(255,255,255,0.08);
        }
        .partner-btn:active { transform: translateY(3px); box-shadow: 0 1px 0 #040f09; }
        .partner-btn .chat-icon { font-size: 20px; margin-right: 6px; }

        /* ─── GAME CENTER ─── */
        .game-center {
            padding: 8px 12px 16px;
        }
        .game-center-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--gold-text);
            margin-bottom: 12px;
        }
        .game-center-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .gc-pill {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--teal-light);
            border: 1px solid rgba(13,122,85,0.4);
            border-bottom: 2px solid rgba(30,200,130,0.5);
            background: linear-gradient(180deg, rgba(13,122,85,0.12) 0%, rgba(13,122,85,0.06) 100%);
            text-decoration: none;
            transition: all 0.15s;
            box-shadow: 0 3px 0 rgba(0,0,0,0.3);
        }
        .gc-pill:active {
            transform: translateY(2px);
            box-shadow: 0 1px 0 rgba(0,0,0,0.3);
        }

        /* ─── PROVIDER LOGOS ─── */
        .providers-section {
            padding: 12px;
            border-top: 1px solid var(--border);
        }
        .providers-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .provider-logo {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            opacity: 0.7;
        }

        /* ─── BOTTOM NAV ─── */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 64px;
            background: rgba(7,20,14,0.97);
            backdrop-filter: blur(12px);
            border-top: 1px solid var(--border);
            display: flex;
            align-items: stretch;
            z-index: 100;
        }
        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            transition: all 0.2s;
            position: relative;
        }
        .nav-item i { font-size: 20px; }
        .nav-item.active { color: var(--gold-text); }
        .nav-item.active i { filter: drop-shadow(0 0 6px rgba(240,192,48,0.6)); }
        .nav-item:active { opacity: 0.7; transform: scale(0.9); }
        .nav-item.center-btn {
            position: relative;
            margin-top: -16px;
        }
        .nav-center-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-light) 0%, var(--teal) 100%);
            box-shadow: 0 0 0 3px rgba(7,20,14,0.97), 0 0 0 4px var(--teal), 0 4px 16px rgba(13,163,110,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
        }
        .nav-item.center-btn span {
            font-size: 9px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* Guest nav */
        .guest-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 60px;
            display: flex;
            z-index: 100;
            background: rgba(7,20,14,0.97);
            border-top: 1px solid var(--border);
        }
        .guest-lang {
            width: 30%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-main);
            border-right: 1px solid var(--border);
            cursor: pointer;
        }
        .guest-flag { width: 20px; height: 20px; border-radius: 50%; overflow: hidden; }
        .guest-flag img { width: 100%; height: 100%; object-fit: cover; }
        .guest-login-btn {
            width: 35%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            background: linear-gradient(180deg, #1a9966 0%, #0d7a55 60%, #0a5c3e 100%);
            color: #fff;
            border-right: 1px solid var(--border);
            border-top: 2px solid #1dcc85;
            transition: 0.15s;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .guest-login-btn:active { filter: brightness(0.85); }
        .guest-signup-btn {
            width: 35%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            background: linear-gradient(180deg, #ffe066 0%, #f0c030 60%, #c89a10 100%);
            color: #2a1500;
            border-top: 2px solid #ffe57a;
            transition: 0.15s;
        }
        .guest-signup-btn:active { filter: brightness(0.9); }

        /* ─── DESKTOP SIDEBAR ─── */
        @media (min-width: 900px) {
            .main-layout {
                display: flex;
                max-width: 1200px;
                margin: 0 auto;
            }
            .desktop-sidebar {
                width: 120px;
                flex-shrink: 0;
                position: sticky;
                top: 56px;
                height: calc(100vh - 56px);
                overflow-y: auto;
                padding: 12px 6px;
                border-right: 1px solid var(--border);
                scrollbar-width: none;
            }
            .desktop-sidebar::-webkit-scrollbar { display: none; }
            .main-content { flex: 1; min-width: 0; }
            .bottom-nav { display: none; }
            .guest-nav { display: none; }
            .site-header { max-width: 1200px; width: 100%; left: 50%; transform: translateX(-50%); }
        }

        .sidebar-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 10px 6px;
            border-radius: 10px;
            margin-bottom: 4px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-muted);
            font-size: 10px;
            font-weight: 700;
            text-align: center;
            transition: all 0.2s;
        }
        .sidebar-item i { font-size: 20px; }
        .sidebar-item:hover, .sidebar-item.active {
            background: rgba(13,122,85,0.15);
            color: var(--gold-text);
            border: 1px solid rgba(13,122,85,0.3);
        }

        /* overlay to close dropdown when clicking outside */
        #ddOverlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 998;
        }
        #ddOverlay.show { display: block; }
    </style>
<?php include __DIR__ . '/../includes/pwa_install.php'; ?>
<?php
$pwa_banner_icon = isset($pwa['icon_192']) && $pwa['icon_192'] !== '' ? htmlspecialchars($pwa['icon_192'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($app_logo_src, ENT_QUOTES, 'UTF-8');
$pwa_banner_name = isset($pwa['app_name']) && trim((string)$pwa['app_name']) !== '' ? htmlspecialchars($pwa['app_name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($site_name . ' Web App', ENT_QUOTES, 'UTF-8');
$pwa_banner_version = isset($pwa['version']) ? max(1, intval($pwa['version'])) : time();
?>
</head>
<body>

<!-- APP INSTALL BANNER (TOP) -->
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
    <a href="../download.php" class="app-banner-top-install js-pwa-install">Download</a>
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
        <?php if($is_logged_in): ?>
            <!-- ── BALANCE CHIP WITH DROPDOWN ── -->
            <div class="balance-wrap" id="balanceWrap">
                <div class="balance-chip" id="balanceChip" onclick="toggleBalanceMenu()">
                    <div class="bal-label">Balance</div>
                    <div class="bal-amount">৳<?php echo number_format($balance,2); ?></div>
                </div>
                <div class="balance-dropdown" id="balanceDropdown">
                    <a href="account_record.php"><i class="fas fa-clock-rotate-left"></i> Account Record</a>
                    <a href="betting_record.php"><i class="fas fa-dice"></i> Betting Record</a>
                    <a href="profit_loss.php"><i class="fas fa-chart-line"></i> Profit And Loss</a>
                    <a href="messages.php"><i class="fas fa-envelope"></i> Message</a>
                    <a href="deposit.php"><i class="fas fa-wallet"></i> Deposit</a>
                    <a href="withdraw.php"><i class="fas fa-money-bill-wave"></i> Withdrawal</a>
                    <a href="support_chat.php"><i class="fas fa-headset"></i> Customer Service</a>
                    <a href="logout.php" class="dd-logout"><i class="fas fa-right-from-bracket"></i> Logout</a>
                </div>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn-login">Log In</a>
            <a href="signup.php" class="btn-register">Register</a>
        <?php endif; ?>
    </div>
</header>

<!-- Invisible overlay to close dropdown -->
<div id="ddOverlay" onclick="closeBalanceMenu()"></div>

<!-- ANNOUNCEMENT -->
<div class="announce-bar">
    <i class="fas fa-bullhorn ann-icon"></i>
    <marquee scrollamount="4">
        <?php echo $settings['marquee_text'] ?? 'Welcome to '.$site_name.' — The world\'s favorite online betting platform.'; ?>
    </marquee>
</div>

<div class="main-layout">
    <div class="main-content">

        <!-- SLIDER -->
        <div class="slider-wrap">
            <div class="swiper mainSlider" style="border-radius:12px;overflow:hidden">
                <div class="swiper-wrapper">
                    <?php if($sliders && $sliders->num_rows > 0):
                        while($slide = $sliders->fetch_assoc()): ?>
                        <div class="swiper-slide">
                            <img src="/<?php echo ltrim($slide['image_path'], '/'); ?>" loading="lazy" decoding="async" style="width:100%;height:170px;object-fit:cover;display:block;border-radius:12px">
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

        <!-- ── DEPOSIT & WITHDRAW QUICK BUTTONS ── -->
        <div class="quick-actions">
            <a href="<?php echo $is_logged_in ? 'deposit.php' : 'login.php?redirect=deposit.php'; ?>" class="qa-btn deposit">
                <i class="fas fa-plus-circle"></i> Deposit
            </a>
            <a href="<?php echo $is_logged_in ? 'withdraw.php' : 'login.php?redirect=withdraw.php'; ?>" class="qa-btn withdraw">
                <i class="fas fa-arrow-up-from-bracket"></i> Withdraw
            </a>
        </div>
        
        <!-- CATEGORY PILLS (mobile) -->
        <nav class="cat-nav-wrap">
            <div class="cat-nav-inner">
                <a href="index.php" class="cat-pill active" onclick="activatePill(this)">
                    <i class="fas fa-fire"></i> HOT GAMES
                </a>
                <a href="favorites.php" class="cat-pill">
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
                            if(strpos($n,'slot')!==false)   $icon='fa-dice';
                            elseif(strpos($n,'live')!==false)  $icon='fa-video';
                            elseif(strpos($n,'sport')!==false) $icon='fa-futbol';
                            elseif(strpos($n,'crash')!==false) $icon='fa-rocket';
                            elseif(strpos($n,'poker')!==false) $icon='fa-spade';
                            elseif(strpos($n,'fish')!==false)  $icon='fa-fish';
                            elseif(strpos($n,'lotto')!==false||strpos($n,'lottery')!==false) $icon='fa-ticket';
                ?>
                <a href="#cat-<?php echo $nc['id']; ?>" class="cat-pill" onclick="activatePill(this)">
                    <i class="fas <?php echo $icon; ?>"></i> <?php echo strtoupper(htmlspecialchars($cat_label, ENT_QUOTES, 'UTF-8')); ?>
                </a>
                <?php endwhile; endif; } ?>
            </div>
        </nav>

        <!-- ── HOT GAMES SECTION ── -->
        <?php if(!empty($hot_games)): ?>
        <section class="hot-section">
            <div class="sec-header">
                <div class="sec-title">
                    <i class="fas fa-fire"></i> HOT GAMES
                </div>
                <div class="sec-actions">
                    <a href="all_category_games.php?cat_id=<?php echo $hot_cat_id; ?>" class="btn-see-all">See All</a>
                    <a href="#" class="btn-nav-arrow"><i class="fas fa-chevron-left"></i></a>
                    <a href="#" class="btn-nav-arrow"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="games-section">
                <div class="game-grid">
                    <?php foreach($hot_games as $g):
                        if($is_logged_in){
                            $launch_uid = !empty($g['jili_launch_uid']) ? $g['jili_launch_uid'] : $g['game_uid'];
                            $link = "launch.php?game_id=".$launch_uid;
                        } else {
                            $launch_uid = !empty($g['jili_launch_uid']) ? $g['jili_launch_uid'] : $g['game_uid'];
                            $redirect = urlencode("launch.php?game_id=".$launch_uid);
                            $link = "login.php?redirect=".$redirect;
                        }
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

        <!-- ── JACKPOT SECTION ── -->
        <div class="jackpot-section">
            
            <!-- Jackpot image + airplane row -->
            <div class="jackpot-img-row">
                <img src="assets/img/aroplane.png"
                     class="jackpot-plane-img"
                     onerror="this.style.display='none'"
                     alt="Airplane">
                <img src="assets/img/jackpot.png"
                     class="jackpot-logo-img"
                     onerror="this.outerHTML='<span style=\'font-size:28px;font-weight:900;font-style:italic;color:var(--gold-text);text-shadow:0 0 30px rgba(240,192,48,0.5);letter-spacing:1px\'>Jackpot</span>'"
                     alt="Jackpot">
            </div>
            <div class="jackpot-number-wrap" id="jackpotDisplay">
                <!-- digits rendered by JS -->
            </div>
        </div>

        

        <!-- GAME SECTIONS (from DB categories) -->
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
                            ORDER BY g.id DESC LIMIT 30
                        ");
                    } else {
                        $live_filter = "";
                        if (stripos($cat['name'], 'live') !== false) {
                            $live_filter = " AND (LOWER(g.api_game_type) LIKE '%live%' OR LOWER(g.category) LIKE '%live%' OR LOWER(g.api_provider_name) LIKE '%live%' OR g.provider_id IN ('58','59','78','87','88','89'))";
                        }

                        $limit_val = 30;
                        $display_limit = 12;
                        if (stripos($cat['name'], 'live') !== false) {
                            $limit_val = 200;
                            $display_limit = 6;
                        }
                        $games_query = $conn->query("
                            SELECT g.* FROM front_category_games fcg 
                            JOIN games g ON fcg.game_uid = g.game_uid 
                            WHERE fcg.category_id = $cat_id
                              AND (g.provider_id <> '49' OR g.status='active')
                              $live_filter
                            ORDER BY fcg.sort_order ASC LIMIT $limit_val
                        ");
                    }
                    if(!$games_query || $games_query->num_rows < 1) continue;
                    $games_list = [];
                    while($row = $games_query->fetch_assoc()) $games_list[] = $row;
                    if (function_exists('game_api_jili_prepare_display_rows')) {
                        $games_list = game_api_jili_prepare_display_rows($conn, $games_list, $display_limit);
                    } else {
                        $games_list = array_slice($games_list, 0, $display_limit);
                    }
                    if(empty($games_list)) continue;
        ?>
        <section id="cat-<?php echo $cat_id; ?>" class="cat-section scroll-mt-14">
            <div class="sec-header">
                <div class="sec-title">
                    <i class="fas fa-fire"></i>
                    <?php echo strtoupper(htmlspecialchars($cat_label, ENT_QUOTES, 'UTF-8')); ?>
                </div>
                <div class="sec-actions">
                    <a href="all_category_games.php?cat_id=<?php echo $cat_id; ?>" class="btn-see-all">See All</a>
                    <a href="#" class="btn-nav-arrow"><i class="fas fa-chevron-left"></i></a>
                    <a href="#" class="btn-nav-arrow"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="games-section">
                <div class="game-grid">
                    <?php foreach($games_list as $g):
                        if($is_logged_in){
                            $launch_uid = !empty($g['jili_launch_uid']) ? $g['jili_launch_uid'] : $g['game_uid'];
                            $link = "launch.php?game_id=".$launch_uid;
                        } else {
                            $launch_uid = !empty($g['jili_launch_uid']) ? $g['jili_launch_uid'] : $g['game_uid'];
                            $redirect = urlencode("launch.php?game_id=".$launch_uid);
                            $link = "login.php?redirect=".$redirect;
                        }
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
        <?php
            endif;
        }
        ?>

        <!-- PARTNER + LIVE CHAT BUTTONS -->
        <div class="partner-section">
            <a href="support_chat.php" class="partner-btn partner">
                🤝 PARTNER
            </a>
            <a href="support_chat.php" class="partner-btn live-chat">
                <span class="chat-icon">💬</span> Live Chat
            </a>
        </div>

        <div style="height:20px"></div>
    </div><!-- /.main-content -->
</div><!-- /.main-layout -->

<!-- BOTTOM NAV (logged in) -->
<?php if($is_logged_in): ?>
<nav class="bottom-nav">
    <a href="index.php" class="nav-item active">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="promotions.php" class="nav-item">
        <i class="fas fa-gift"></i>
        <span>Promotion</span>
    </a>
    <a href="referral.php" class="nav-item center-btn">
        <div class="nav-center-circle">
            <i class="fas fa-share-nodes"></i>
        </div>
        <span>Invite</span>
    </a>
    <a href="rewards.php" class="nav-item">
        <i class="fas fa-trophy"></i>
        <span>Reward</span>
    </a>
    <a href="account.php" class="nav-item">
        <i class="fas fa-user-circle"></i>
        <span>Member</span>
    </a>
</nav>
<?php else: ?>
<!-- GUEST BOTTOM NAV -->
<div class="guest-nav">
    <div class="guest-lang">
        <div class="guest-flag"><img src="https://flagcdn.com/w40/bd.png"></div>
        <div style="display:flex;flex-direction:column;line-height:1.2">
            <span>BDT</span>
            <span style="font-size:9px;font-weight:400;color:var(--text-muted)">English</span>
        </div>
    </div>
    <div class="guest-login-btn" onclick="location.href='login.php'">Log In</div>
    <div class="guest-signup-btn" onclick="location.href='signup.php'">Registration</div>
</div>
<?php endif; ?>

<?php include '../includes/sidebar_player.php'; ?>
<?php include 'sectionlast.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script>
// ── Main slider ──
new Swiper(".mainSlider", {
    loop: true,
    autoplay: { delay: 3500, disableOnInteraction: false },
    pagination: { el: ".swiper-pagination", clickable: true }
});

// ── Sidebar toggle ──
function toggleSidebar() {
    var isDesktop = window.innerWidth >= 900;
    if (isDesktop) return;
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if(sidebar) sidebar.classList.toggle('open');
    if(overlay) overlay.classList.toggle('hidden');
}

// ── Activate category pill ──
function activatePill(el) {
    document.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
}

// ── Desktop resize ──
function handleResize() {
    const isDesktop = window.innerWidth >= 900;
    const dSidebar = document.getElementById('desktop-sidebar');
    if(dSidebar) dSidebar.style.display = isDesktop ? 'block' : 'none';
}
handleResize();
window.addEventListener('resize', handleResize);

// ── Balance Dropdown ──
function toggleBalanceMenu() {
    var dd = document.getElementById('balanceDropdown');
    var chip = document.getElementById('balanceChip');
    var overlay = document.getElementById('ddOverlay');
    if (!dd) return;
    var isOpen = dd.classList.contains('show');
    if (isOpen) {
        dd.classList.remove('show');
        chip.classList.remove('open');
        overlay.classList.remove('show');
    } else {
        dd.classList.add('show');
        chip.classList.add('open');
        overlay.classList.add('show');
    }
}
function closeBalanceMenu() {
    var dd = document.getElementById('balanceDropdown');
    var chip = document.getElementById('balanceChip');
    var overlay = document.getElementById('ddOverlay');
    if (dd) dd.classList.remove('show');
    if (chip) chip.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
}

// ── Favorite (heart) button toggle ──
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

// ── JACKPOT COUNTER ──
(function() {
    const BASE_NUMBER = 10921288702;
    let currentVal = BASE_NUMBER;

    function formatJackpot(val) {
        let intPart = Math.floor(val / 100);
        let decPart = (val % 100).toString().padStart(2, '0');
        let intStr = intPart.toLocaleString('en-US');
        return intStr + '.' + decPart;
    }

    function buildDigitHTML(numStr) {
        let html = '';
        for (let i = 0; i < numStr.length; i++) {
            let ch = numStr[i];
            if (ch === ',') {
                html += '<span class="jp-sep">,</span>';
            } else if (ch === '.') {
                html += '<span class="jp-sep">.</span>';
            } else {
                html += '<span class="jp-digit" id="jpd-' + i + '">' + ch + '</span>';
            }
        }
        return html;
    }

    function renderJackpot(animated) {
        const container = document.getElementById('jackpotDisplay');
        if (!container) return;
        const numStr = formatJackpot(currentVal);

        if (!animated) {
            container.innerHTML = buildDigitHTML(numStr);
            return;
        }

        const spans = container.querySelectorAll('.jp-digit');
        const digits = numStr.replace(/[,\.]/g, '');
        let digitIdx = 0;
        spans.forEach(span => {
            const newChar = digits[digitIdx] || '0';
            if (span.textContent !== newChar) {
                span.classList.remove('changing');
                void span.offsetWidth;
                span.classList.add('changing');
                span.textContent = newChar;
                setTimeout(() => span.classList.remove('changing'), 200);
            }
            digitIdx++;
        });
    }

    renderJackpot(false);

    function updateJackpot() {
        const increment = Math.floor(Math.random() * 9998) + 1;
        currentVal += increment;
        renderJackpot(true);
    }

    function scheduleNext() {
        const delay = 1500 + Math.random() * 2000;
        setTimeout(function() {
            updateJackpot();
            scheduleNext();
        }, delay);
    }
    scheduleNext();
})();
</script>
<?php include __DIR__ . '/../includes/modal_popup.php'; ?>

<?php
if (isset($conn) && isset($_SESSION['user_id'])) {
    $wcb_bonus_helper = __DIR__ . '/../includes/bonus_system_helper.php';
    if (file_exists($wcb_bonus_helper)) {
        require_once $wcb_bonus_helper;
        echo wcb_daily_bonus_popup_html($conn, intval($_SESSION['user_id']));
    }
}
?>
</body>
</html>