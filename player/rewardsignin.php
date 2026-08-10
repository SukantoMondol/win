<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) { require $db_path; }
else { $conn = new mysqli('localhost', 'root', '', 'bating'); }
require_once __DIR__ . '/../includes/bonus_system_helper.php';
wcb_bonus_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$user_q = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1");
$user = $user_q ? $user_q->fetch_assoc() : array();
$state = wcb_deposit_bonus_page_state($conn, $user_id);
$settings = $state['settings'];
$rules = $state['rules'];
$today_deposit = (float)$state['today_total'];
$max_deposit = (float)$state['max_deposit'];
$min_required_display = (float)$state['min_required'];
$claim_today = $state['claim_today'];
$best = $state['best'];
$best_rule_id = !empty($best['rule']) ? intval($best['rule']['id']) : 0;
$best_bonus_amount = !empty($best['bonus_amount']) ? (float)$best['bonus_amount'] : 0.00;
$best_turnover = !empty($best['turnover_required']) ? (float)$best['turnover_required'] : 0.00;

$checkin_days = 0;
$total_reward = 0.00;
$stat_q = $conn->prepare("SELECT COUNT(*) AS days, COALESCE(SUM(bonus_amount),0) AS total FROM deposit_bonus_claims WHERE user_id=?");
if ($stat_q) {
    $stat_q->bind_param('i', $user_id);
    $stat_q->execute();
    $stat_res = $stat_q->get_result();
    if ($stat_res && $stat_res->num_rows > 0) {
        $stat = $stat_res->fetch_assoc();
        $checkin_days = intval($stat['days'] ?? 0);
        $total_reward = (float)($stat['total'] ?? 0);
    }
}

$rules_text = array();
foreach ($rules as $r) { if (!empty($r['rules_text'])) { $rules_text[] = $r['rules_text']; } }
if ($min_required_display <= 0) { $min_required_display = 500; }

$avatar = '../assets/images/avatar.png';
$username = $user['username'] ?? ($user['phone'] ?? 'User');
$balance = (float)($user['balance'] ?? 0);
$bonus_balance = (float)($user['bonus_balance'] ?? 0);
$turnover_target = (float)($user['turnover_target'] ?? 0);
$turnover_completed = (float)($user['turnover_completed'] ?? 0);
$turnover_remaining = max(0, $turnover_target - $turnover_completed);

$status_headline = !empty($claim_today) ? 'Checked in today' : ($settings['status_text'] ?: 'Not checked in today ?');
$status_amount = !empty($claim_today) ? (float)($claim_today['bonus_amount'] ?? 0) : $best_bonus_amount;
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>সাইন ইন - Deposit Bonus</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root{--app-bg:#f4f5fa;--primary-blue:#2f7df6;--soft-card:#fffdf2;--soft-border:#c7f2dc;--accent-red:#ef4444;--text-main:#293244;--text-soft:#6b7280}
        *{box-sizing:border-box;min-width:0}
        html,body{margin:0;width:100%;overflow-x:hidden;-webkit-text-size-adjust:100%}
        body{font-family:'Hind Siliguri',sans-serif;background:var(--app-bg);color:var(--text-main);padding-bottom:20px;font-weight:400;-webkit-font-smoothing:antialiased;text-rendering:geometricPrecision}
        .page-wrap{width:100%;max-width:480px;margin:0 auto;min-height:100vh;background:var(--app-bg);overflow-x:hidden}
        .page-wrap .font-black,.page-wrap .font-extrabold{font-weight:600!important}.page-wrap .font-bold{font-weight:600!important}
        .nav-header{height:58px;background:#1a0b2e;color:white;padding:0 16px!important;box-shadow:none}.nav-header h1{font-size:18px!important;font-weight:600!important;letter-spacing:.1px}.nav-header button{font-size:23px!important;font-weight:400!important;line-height:1}.nav-header i{font-weight:400!important}
        .hero-bg{background:linear-gradient(180deg,#f8bd2d 0%,#ff7b28 62%,#fb5d61 100%);padding:24px 24px 72px!important;position:relative;overflow:hidden}.circle-deco{position:absolute;border-radius:50%;background:rgba(255,255,255,.22)}.wave-one{position:absolute;left:-72px;right:-72px;bottom:-57px;height:122px;background:rgba(255,255,255,.18);border-radius:52% 52% 0 0;transform:rotate(3deg)}.profile-ring{background:linear-gradient(135deg,#b8e3ff,#65a9ff);padding:5px;width:82px!important;height:82px!important}.hero-bg p.text-white{font-size:16px!important;font-weight:600!important}.hero-bg h2{font-size:32px!important;line-height:1.15!important;font-weight:600!important}.hero-bg .text-yellow-100{font-size:10px!important;font-weight:400!important;opacity:.85}
        .stats-card{background:#fff;border-radius:24px;margin:-48px 12px 18px;padding:18px 10px;box-shadow:0 9px 24px rgba(31,41,55,.07);position:relative;z-index:10;display:flex;text-align:center}.stats-card p:first-child{font-size:29px!important;line-height:1.05!important;font-weight:600!important}.stats-card p:last-child{font-size:17px!important;line-height:1.2;color:#a1a1aa!important;font-weight:400!important;margin-top:8px!important}.stats-card .border-r{border-color:#d1d5db!important}
        .plan-card{background:#fff;border-radius:21px;margin:0 12px 16px;box-shadow:0 8px 22px rgba(31,41,55,.06);overflow:hidden}.section-title{font-size:24px;line-height:1.28;color:#16a6d8;font-weight:500;text-align:center;padding:24px 12px 12px;letter-spacing:-.2px}.notice-row{display:flex;align-items:center;gap:12px;padding:12px 23px 15px}.notice-icon{width:56px;min-width:56px;height:56px;border-radius:16px;background:#fff5e8;display:flex;align-items:center;justify-content:center;font-size:30px;box-shadow:0 2px 8px rgba(249,115,22,.05)}.notice-row h3{font-size:20px!important;line-height:1.33!important;font-weight:600!important;color:var(--accent-red)!important}.notice-row p{font-size:14px!important;line-height:1.25!important;font-weight:600!important}.notice-row .w-5{width:20px!important;height:20px!important;font-size:12px!important;font-weight:600!important;background:#8b1e20!important}
        .minimum-box{background:#fff7ee;border-radius:15px;padding:18px 20px;color:#55504d;font-size:19px;font-weight:400;line-height:1.26}.minimum-box .text-pink-600{font-size:22px!important;font-weight:600!important;margin-top:10px!important;color:#d6337a!important}.minimum-box .text-xs{font-size:13px!important;line-height:1.45!important;font-weight:500!important;color:#6b7280!important;margin-top:10px!important}
        .bonus-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin:0 12px 16px}.bonus-card{background:var(--soft-card);border:1.4px solid var(--soft-border);border-radius:12px;overflow:hidden;box-shadow:0 3px 10px rgba(31,41,55,.05);position:relative}.bonus-card.locked{border-color:#dff3e8;background:#fffdf5}.bonus-card.claimed{border-color:#bbf7d0;background:#f5fff8}.day-title{background:var(--primary-blue);color:#fff;text-align:center;font-size:16px;font-weight:600;padding:7px 5px;line-height:1.12}.card-body{padding:9px 8px 10px;text-align:center}.cash-icon{height:66px;display:flex;align-items:center;justify-content:center;font-size:41px;filter:none;line-height:1}.bonus-line{display:flex;align-items:center;justify-content:space-between;gap:5px;font-size:13.5px;font-weight:600;color:#545b66;margin:0 0 5px;line-height:1.2;text-align:left}.bonus-line span:last-child{color:var(--accent-red);white-space:nowrap}.min-line{font-size:9.4px;color:#667085;font-weight:500;margin-bottom:9px;min-height:25px;line-height:1.34;word-break:normal}.status-pill{position:absolute;top:41px;right:7px;border-radius:999px;padding:3px 7px;font-size:9px;font-weight:600;line-height:1.15}.status-available{background:#dcfce7;color:#15803d}.status-locked{background:rgba(243,244,246,.92);color:#6b7280}.status-claimed{background:#dbeafe;color:#1d4ed8}.btn-signin,.btn-claimed,.btn-signin-disabled{width:100%;height:36px;border:0;border-radius:999px;font-size:15px;font-weight:600;padding:0 8px;box-shadow:none;line-height:36px}.btn-signin{background:#ff4d3d;color:white;cursor:pointer}.btn-signin:disabled{opacity:.75;cursor:not-allowed}.btn-claimed{background:#16a34a;color:white}.btn-signin-disabled{background:#d1d5db;color:white;cursor:pointer}
        .rules-list{font-size:13px;color:#374151;line-height:1.65;margin:18px 12px;background:#fff;border-radius:15px;padding:15px;border:1px solid #eef0f4;font-weight:400}.rules-list h3,.rules-list p{font-weight:600!important}.modal-overlay{background-color:rgba(0,0,0,.58);backdrop-filter:blur(2px)}
        @media(max-width:390px){.page-wrap{max-width:100%}.hero-bg{padding-left:20px!important;padding-right:20px!important}.profile-ring{width:76px!important;height:76px!important}.stats-card{margin-left:10px;margin-right:10px}.plan-card{margin-left:10px;margin-right:10px}.section-title{font-size:22px;padding-top:22px}.notice-row{padding-left:18px;padding-right:18px}.notice-icon{width:50px;min-width:50px;height:50px;font-size:27px}.notice-row h3{font-size:18px!important}.minimum-box{font-size:17px;padding:16px 18px}.bonus-grid{gap:7px;margin-left:10px;margin-right:10px}.day-title{font-size:15px}.card-body{padding:8px 7px 9px}.cash-icon{height:58px;font-size:36px}.bonus-line{font-size:12.5px}.min-line{font-size:8.7px}.btn-signin,.btn-claimed,.btn-signin-disabled{height:34px;line-height:34px;font-size:14px}.status-pill{top:38px;font-size:8.5px;padding:3px 6px}}
        @media(max-width:340px){.bonus-grid{gap:6px}.section-title{font-size:21px}.notice-row h3{font-size:17px!important}.cash-icon{height:54px;font-size:33px}.bonus-line{font-size:11.8px}.min-line{font-size:8px}.btn-signin,.btn-claimed,.btn-signin-disabled{font-size:13px}}
        @media(min-width:481px){body{background:#eef0f6}.page-wrap{box-shadow:0 0 0 1px rgba(0,0,0,.03)}}


        /* WEB CORNER BD: thin/light mobile UI patch - frontend only */
        .page-wrap{max-width:390px!important}
        body{font-size:14px!important;font-weight:400!important;line-height:1.35!important}
        .page-wrap *{text-shadow:none!important}
        .page-wrap .font-black,
        .page-wrap .font-extrabold,
        .page-wrap .font-bold,
        .page-wrap .font-semibold,
        .page-wrap b,
        .page-wrap strong{font-weight:500!important}
        .nav-header{height:52px!important;padding:0 14px!important}
        .nav-header h1{font-size:17px!important;font-weight:500!important}
        .nav-header button{font-size:25px!important;font-weight:300!important;width:30px!important;height:30px!important;display:flex!important;align-items:center!important;justify-content:center!important}
        .back-arrow{display:block;font-family:Arial,Helvetica,sans-serif;font-size:34px;font-weight:300;line-height:24px;margin-top:-2px}
        .hero-bg{padding:20px 22px 58px!important}
        .hero-bg .flex.items-center{gap:14px!important}
        .profile-ring{width:72px!important;height:72px!important;padding:4px!important}
        .hero-bg p.text-white{font-size:14px!important;font-weight:500!important;letter-spacing:0!important}
        .hero-bg h2{font-size:27px!important;line-height:1.15!important;font-weight:500!important;margin-top:6px!important}
        .circle-deco{opacity:.75!important;transform:scale(.82)!important}
        .wave-one{height:100px!important;bottom:-50px!important;opacity:.75!important}
        .stats-card{margin:-39px 18px 17px!important;padding:14px 8px!important;border-radius:22px!important;box-shadow:0 7px 18px rgba(31,41,55,.055)!important}
        .stats-card p:first-child{font-size:25px!important;font-weight:500!important}
        .stats-card p:last-child{font-size:15px!important;font-weight:400!important;margin-top:7px!important;color:#9ca3af!important}
        .plan-card{margin:0 18px 15px!important;border-radius:20px!important;box-shadow:0 7px 18px rgba(31,41,55,.05)!important}
        .section-title{font-size:21px!important;line-height:1.25!important;font-weight:400!important;padding:22px 12px 10px!important;letter-spacing:0!important}
        .notice-row{gap:11px!important;padding:10px 22px 13px!important;align-items:center!important}
        .notice-icon{width:48px!important;min-width:48px!important;height:48px!important;border-radius:14px!important;font-size:25px!important;box-shadow:none!important}
        .notice-row h3{font-size:17px!important;line-height:1.32!important;font-weight:500!important}
        .notice-row p{font-size:12.5px!important;line-height:1.28!important;font-weight:500!important}
        .notice-row .w-5{width:18px!important;height:18px!important;font-size:11px!important;font-weight:500!important}
        .minimum-box{border-radius:14px!important;padding:15px 17px!important;font-size:16.5px!important;line-height:1.28!important;font-weight:400!important}
        .minimum-box .text-pink-600{font-size:20px!important;font-weight:500!important;margin-top:9px!important}
        .minimum-box .text-xs{font-size:11.5px!important;line-height:1.42!important;font-weight:400!important;margin-top:9px!important}
        .plan-card .px-5{padding-left:20px!important;padding-right:20px!important;padding-bottom:20px!important}
        .bonus-grid{margin:0 18px 15px!important;gap:9px!important}
        .bonus-card{border-radius:11px!important;border-width:1px!important;box-shadow:0 2px 8px rgba(31,41,55,.04)!important}
        .day-title{font-size:14.5px!important;font-weight:500!important;padding:6px 5px!important}
        .card-body{padding:8px 7px 9px!important}
        .cash-icon{height:50px!important;font-size:31px!important}
        .bonus-line{font-size:12.2px!important;font-weight:500!important;line-height:1.22!important;margin-bottom:5px!important}
        .min-line{font-size:8.6px!important;font-weight:400!important;line-height:1.28!important;min-height:23px!important;margin-bottom:8px!important;color:#667085!important}
        .status-pill{top:36px!important;right:6px!important;font-size:8px!important;font-weight:500!important;padding:2.5px 6px!important}
        .btn-signin,.btn-claimed,.btn-signin-disabled{height:31px!important;line-height:31px!important;font-size:13.5px!important;font-weight:500!important;padding:0 7px!important}
        .rules-list{font-size:12px!important;line-height:1.58!important;margin:16px 18px!important;padding:13px!important;border-radius:13px!important}
        .rules-list h3,.rules-list p{font-weight:500!important}
        #confirmModal h3{font-weight:500!important;font-size:17px!important}
        #confirmModal p,#confirmModal button{font-weight:400!important}

        @media(max-width:430px){
            .page-wrap{max-width:100%!important}
        }
        @media(max-width:390px){
            .hero-bg{padding-left:20px!important;padding-right:20px!important;padding-bottom:56px!important}
            .profile-ring{width:70px!important;height:70px!important}
            .hero-bg h2{font-size:25px!important}
            .stats-card{margin-left:16px!important;margin-right:16px!important}
            .plan-card{margin-left:16px!important;margin-right:16px!important}
            .section-title{font-size:20px!important;padding-top:20px!important}
            .notice-row{padding-left:18px!important;padding-right:18px!important}
            .notice-icon{width:45px!important;min-width:45px!important;height:45px!important;font-size:23px!important}
            .notice-row h3{font-size:16px!important}
            .minimum-box{font-size:15.5px!important;padding:14px 15px!important}
            .minimum-box .text-pink-600{font-size:19px!important}
            .bonus-grid{margin-left:16px!important;margin-right:16px!important;gap:8px!important}
            .cash-icon{height:47px!important;font-size:29px!important}
            .bonus-line{font-size:11.5px!important}
            .min-line{font-size:8px!important}
            .btn-signin,.btn-claimed,.btn-signin-disabled{height:30px!important;line-height:30px!important;font-size:13px!important}
        }
        @media(max-width:340px){
            .hero-bg h2{font-size:23px!important}
            .stats-card p:first-child{font-size:23px!important}
            .stats-card p:last-child{font-size:14px!important}
            .section-title{font-size:19px!important}
            .notice-row h3{font-size:15px!important}
            .minimum-box{font-size:14.5px!important}
            .bonus-grid{gap:7px!important}
            .day-title{font-size:13.5px!important}
            .cash-icon{height:43px!important;font-size:27px!important}
            .bonus-line{font-size:10.8px!important}
            .min-line{font-size:7.5px!important}
        }

    </style>
</head>
<body>
<div class="page-wrap">
    <header class="nav-header flex items-center justify-center p-4 sticky top-0 z-50">
        <button onclick="window.history.back()" class="absolute left-4 text-white text-xl" aria-label="Back"><span class="back-arrow">‹</span></button>
        <h1 class="text-base font-bold">সাইন ইন</h1>
    </header>

    <div class="hero-bg px-6 pt-8">
        <div class="circle-deco w-24 h-24 top-8 right-14"></div>
        <div class="circle-deco w-14 h-14 top-16 left-14"></div>
        <div class="circle-deco w-32 h-32 bottom-8 right-24"></div>
        <div class="wave-one"></div>
        <div class="flex items-center gap-5 relative z-10">
            <div class="profile-ring w-24 h-24 rounded-full">
                <div class="w-full h-full rounded-full bg-white overflow-hidden">
                    <img src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=random'" class="w-full h-full object-cover">
                </div>
            </div>
            <div>
                <p class="text-white text-xl font-black tracking-wide"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>
                <h2 class="text-white text-4xl font-black mt-2">৳ <?php echo number_format($balance, 2); ?></h2>
            </div>
        </div>
    </div>

    <div class="stats-card">
        <div class="w-1/2 border-r border-gray-300">
            <p class="text-blue-600 font-black text-4xl"><?php echo $checkin_days; ?></p>
            <p class="text-lg text-gray-400 mt-2">Last sign in</p>
        </div>
        <div class="w-1/2">
            <p class="text-red-500 font-black text-4xl"><?php echo number_format($total_reward, 2); ?></p>
            <p class="text-lg text-gray-400 mt-2">Sign in total bonus</p>
        </div>
    </div>

    <div class="plan-card">
        <h2 class="section-title"><?php echo htmlspecialchars($settings['section_title'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <div class="notice-row">
            <div class="notice-icon">🎁</div>
            <div class="flex-1">
                <h3 class="text-red-500 text-xl font-black"><?php echo htmlspecialchars($settings['section_subtitle'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="text-red-700 text-sm font-black mt-1"><?php echo htmlspecialchars($status_headline, ENT_QUOTES, 'UTF-8'); ?> <span class="inline-flex items-center justify-center bg-red-900 text-white rounded-full w-5 h-5 text-xs ml-1">?</span></p>
                <?php if($status_amount > 0): ?>
                    <p class="text-emerald-600 text-xs font-black mt-1">Available/Claimed Bonus: ৳ <?php echo number_format($status_amount, 2); ?><?php if($best_turnover > 0 && empty($claim_today)): ?> · Turnover: ৳ <?php echo number_format($best_turnover, 2); ?><?php endif; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="px-5 pb-6">
            <div class="minimum-box">
                <div>Minimum deposit<br>amount:</div>
                <div class="text-pink-600 font-black mt-2">৳ <?php echo number_format($min_required_display, 2); ?></div>
            </div>
        </div>
    </div>

    <div class="bonus-grid">
        <?php if(empty($rules)): ?>
        <?php else: ?>
            <?php foreach($rules as $i => $rule):
                $rule_id = intval($rule['id']);
                $is_claimed_rule = (!empty($claim_today) && intval($claim_today['rule_id'] ?? 0) === $rule_id);
                $is_any_claimed = !empty($claim_today);
                $is_available = (!$is_any_claimed && intval($settings['is_enabled']) === 1 && $best_rule_id === $rule_id);
                $class = $is_claimed_rule ? 'claimed' : ($is_available ? 'available' : 'locked');
                $display_bonus = $is_available ? $best_bonus_amount : wcb_deposit_bonus_amount($rule, max((float)$rule['min_deposit_amount'], $max_deposit));
                if ($rule['bonus_type'] === 'fixed') { $display_bonus = (float)$rule['bonus_value']; }
                $bonus_text = '৳ ' . number_format((float)$display_bonus, 2);
                $min_amount = (float)$rule['min_deposit_amount'];
            ?>
            <div class="bonus-card <?php echo $class; ?>">
                <div class="day-title">Day <?php echo $i + 1; ?></div>
                <?php if($is_available): ?><div class="status-pill status-available">Available</div><?php endif; ?>
                <?php if($is_claimed_rule): ?><div class="status-pill status-claimed">Done</div><?php endif; ?>
                <?php if(!$is_available && !$is_claimed_rule): ?><div class="status-pill status-locked">Locked</div><?php endif; ?>
                <div class="card-body">
                    <div class="cash-icon">💵</div>
                    <div class="bonus-line"><span>বোনাস</span><span><?php echo htmlspecialchars($bonus_text, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="min-line">Min Deposit ৳<?php echo number_format($min_amount, 0); ?> ·<?php echo rtrim(rtrim(number_format((float)($rule['turnover_multiplier'] ?? 1), 2), '0'), '.'); ?></div>
                    <?php if($is_claimed_rule): ?>
                        <button class="btn-claimed" disabled><?php echo htmlspecialchars($settings['claimed_button_text'], ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php elseif($is_available): ?>
                        <button onclick="claimDepositBonus(<?php echo $rule_id; ?>, this)" class="btn-signin"><?php echo htmlspecialchars($settings['claim_button_text'], ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php else: ?>
                        <button onclick="openModal(<?php echo $min_amount; ?>, <?php echo $max_deposit; ?>, '<?php echo $is_any_claimed ? 'claimed' : 'locked'; ?>')" class="btn-signin-disabled"><?php echo htmlspecialchars($settings['locked_button_text'], ENT_QUOTES, 'UTF-8'); ?></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="rules-list">
        <h3 class="text-red-500 font-black mb-2">সাইন-ইন নিয়ম</h3>
        <ol class="list-decimal pl-5 space-y-2">
            <?php
            $global_rules = preg_split('/\r\n|\r|\n/', (string)($settings['claim_rules_text'] ?? ''));
            foreach ($global_rules as $line):
                $line = trim($line);
                if ($line === '') { continue; }
            ?>
            <li><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
            <?php foreach(array_unique($rules_text) as $txt): ?><li><?php echo htmlspecialchars($txt, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?>
        </ol>
    </div>

    <div id="confirmModal" class="fixed inset-0 modal-overlay z-[60] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl w-full max-w-xs p-6 text-center shadow-2xl">
            <h3 class="text-lg font-black text-gray-800 mb-3">নিশ্চিতকরণ</h3>
            <p id="modalMessage" class="text-sm text-gray-600 mb-2">Deposit condition পূরণ করা হয়নি।</p>
            <div class="text-sm text-gray-700 font-medium space-y-1 mb-6 bg-gray-50 rounded-xl p-3">
                <p>Highest approved deposit : <span id="modalCurrentDeposit"><?php echo number_format($max_deposit, 2); ?></span></p>
                <p>Required deposit : <span id="modalRequiredDeposit"><?php echo number_format($min_required_display, 2); ?></span></p>
            </div>
            <div class="flex justify-between gap-4">
                <button onclick="closeModal()" class="flex-1 py-2 text-gray-500 font-bold hover:text-gray-700">বাতিল</button>
                <button onclick="goToDeposit()" class="flex-1 py-2 bg-blue-600 text-white rounded-xl font-black">ডিপোজিট</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openModal(required, current, reason) {
        var msg = 'Deposit condition পূরণ করা হয়নি।';
        if(reason === 'claimed') msg = 'আজকের deposit bonus already claimed. Next daily cycle এ আবার claim করা যাবে।';
        if(<?php echo intval($settings['is_enabled']); ?> !== 1) msg = 'Deposit bonus বর্তমানে বন্ধ আছে।';
        document.getElementById('modalMessage').innerText = msg;
        document.getElementById('modalCurrentDeposit').innerText = Number(current || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('modalRequiredDeposit').innerText = Number(required || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        document.getElementById('confirmModal').classList.remove('hidden');
    }
    function closeModal() { document.getElementById('confirmModal').classList.add('hidden'); }
    function goToDeposit() { window.location.href = 'deposit.php'; }
    function claimDepositBonus(ruleId, btn) {
        if(!ruleId || !btn) return;
        const old = btn.innerText;
        btn.disabled = true;
        btn.innerText = 'Processing...';
        const fd = new FormData();
        fd.append('rule_id', ruleId);
        fetch('../api/deposit_bonus_claim.php', {method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}})
          .then(r => r.json())
          .then(data => {
            if(data && data.success){
                alert('Bonus claimed: ৳ ' + Number(data.amount || 0).toFixed(2) + '\nTurnover added: ৳ ' + Number(data.turnover_required || 0).toFixed(2));
                location.reload();
            } else {
                alert((data && data.message) ? data.message : 'Claim failed.');
                btn.disabled = false; btn.innerText = old;
            }
          })
          .catch(() => { alert('Network error.'); btn.disabled = false; btn.innerText = old; });
    }
</script>
</body>
</html>
