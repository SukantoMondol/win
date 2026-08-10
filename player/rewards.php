<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) { require $db_path; }
require_once __DIR__ . '/../includes/bonus_system_helper.php';
wcb_bonus_ensure_schema($conn);
if (file_exists(__DIR__ . '/../includes/vip_system_helper.php')) { require_once __DIR__ . '/../includes/vip_system_helper.php'; }

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);
$message = '';
$message_type = 'success';

if (function_exists('wcb_table_exists') && wcb_table_exists($conn, 'personal_bonuses')) {
    wcb_add_column_if_missing($conn, 'personal_bonuses', 'claimed_at', 'DATETIME DEFAULT NULL');
}

function wcb_reward_json($payload) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wcb_reward_claim_personal($conn, $user_id, $bonus_id) {
    $user_id = intval($user_id);
    $bonus_id = intval($bonus_id);
    if ($user_id <= 0 || $bonus_id <= 0) { return array('success' => false, 'message' => 'Invalid request.'); }
    if (!function_exists('wcb_table_exists') || !wcb_table_exists($conn, 'personal_bonuses')) { return array('success' => false, 'message' => 'No bonus found.'); }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id, amount, description FROM personal_bonuses WHERE id=? AND user_id=? AND status='pending' LIMIT 1 FOR UPDATE");
        if (!$stmt) { throw new Exception('Unable to prepare bonus.'); }
        $stmt->bind_param('ii', $bonus_id, $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res || $res->num_rows < 1) {
            $conn->rollback();
            return array('success' => false, 'message' => 'This bonus is not available.');
        }
        $row = $res->fetch_assoc();
        $amount = round(max(0, (float)$row['amount']), 2);
        if ($amount <= 0) {
            $conn->rollback();
            return array('success' => false, 'message' => 'Bonus amount is invalid.');
        }
        $up = $conn->prepare('UPDATE users SET balance = balance + ? WHERE id=?');
        if (!$up) { throw new Exception('Unable to update balance.'); }
        $up->bind_param('di', $amount, $user_id);
        $up->execute();
        $done = $conn->prepare("UPDATE personal_bonuses SET status='claimed', claimed_at=NOW() WHERE id=? AND user_id=?");
        if (!$done) { throw new Exception('Unable to update bonus status.'); }
        $done->bind_param('ii', $bonus_id, $user_id);
        $done->execute();
        if (function_exists('wcb_table_exists') && wcb_table_exists($conn, 'transactions_fake')) {
            $method = 'Personal Bonus Claim';
            $note = (string)($row['description'] ?? 'Personal bonus claimed');
            $tx = $conn->prepare("INSERT INTO transactions_fake (user_id, type, amount, method, status, admin_note, created_at) VALUES (?, 'bonus', ?, ?, 'approved', ?, NOW())");
            if ($tx) {
                $tx->bind_param('idss', $user_id, $amount, $method, $note);
                $tx->execute();
            }
        }
        $conn->commit();
        return array('success' => true, 'message' => 'Bonus claimed successfully.', 'amount' => $amount);
    } catch (Exception $e) {
        $conn->rollback();
        return array('success' => false, 'message' => 'Server error.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bonus_action'])) {
    $action = trim((string)$_POST['bonus_action']);
    if ($action === 'claim_daily') {
        $result = wcb_daily_bonus_claim($conn, $user_id);
    } elseif ($action === 'claim_deposit') {
        $result = wcb_deposit_bonus_claim($conn, $user_id, intval($_POST['rule_id'] ?? 0));
    } elseif ($action === 'claim_personal') {
        $result = wcb_reward_claim_personal($conn, $user_id, intval($_POST['bonus_id'] ?? 0));
    } else {
        $result = array('success' => false, 'message' => 'Invalid action.');
    }
    if (!empty($_POST['ajax'])) { wcb_reward_json($result); }
    $message = $result['message'] ?? 'Action completed.';
    $message_type = !empty($result['success']) ? 'success' : 'error';
}

$stmtUser = $conn->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
$stmtUser->bind_param('i', $user_id);
$stmtUser->execute();
$user_res = $stmtUser->get_result();
$user = $user_res && $user_res->num_rows > 0 ? $user_res->fetch_assoc() : array();
$username = $user['username'] ?? ($user['phone'] ?? 'User');
$balance = (float)($user['balance'] ?? 0);

$vip_name = 'VIP0 Member';
$vip_progress = 0;
$vip_progress_text = 'Progress: 0 / 0';
if (function_exists('wcb_vip_state')) {
    $vip_state = wcb_vip_state($conn, $user_id);
    $vip_name = htmlspecialchars((string)($vip_state['current_level']['level_name'] ?? 'NORMAL'), ENT_QUOTES, 'UTF-8') . ' Member';
    $vip_progress = max(0, min(100, (float)($vip_state['progress_percent'] ?? 0)));
    $vip_progress_text = 'Progress: ' . number_format((float)($vip_state['xp'] ?? 0), 0) . ' / ' . number_format((float)($vip_state['target_xp'] ?? 0), 0);
}

$daily_state = wcb_daily_bonus_can_claim($conn, $user_id);
$daily_settings = $daily_state['settings'];
$deposit_state = wcb_deposit_bonus_page_state($conn, $user_id);
$deposit_best = $deposit_state['best'];
$available_bonuses = array();
$locked_bonuses = array();

if (!empty($daily_settings['is_enabled']) && (float)$daily_settings['bonus_amount'] > 0) {
    if (!empty($daily_state['can_claim'])) {
        $available_bonuses[] = array(
            'type' => 'daily',
            'title' => 'Daily Login Bonus',
            'description' => 'Claim your daily free bonus.',
            'amount' => (float)$daily_settings['bonus_amount'],
            'action' => 'claim_daily',
            'seconds' => 0
        );
    } else {
        $locked_bonuses[] = array(
            'type' => 'daily',
            'title' => 'Daily Login Bonus',
            'description' => 'Next bonus available after countdown.',
            'amount' => (float)$daily_settings['bonus_amount'],
            'seconds' => intval($daily_state['seconds_remaining'] ?? 0)
        );
    }
}

if (!empty($deposit_best['rule']) && !empty($deposit_best['deposit']) && (float)$deposit_best['bonus_amount'] > 0) {
    $available_bonuses[] = array(
        'type' => 'deposit',
        'title' => 'Deposit Bonus',
        'description' => 'Approved deposit ৳' . number_format((float)$deposit_best['deposit']['amount'], 2) . ' unlocked this bonus.',
        'amount' => (float)$deposit_best['bonus_amount'],
        'action' => 'claim_deposit',
        'rule_id' => intval($deposit_best['rule']['id']),
        'turnover' => (float)$deposit_best['turnover_required']
    );
} elseif (!empty($deposit_state['settings']['is_enabled'])) {
    $locked_bonuses[] = array(
        'type' => 'deposit',
        'title' => 'Deposit Bonus',
        'description' => 'Complete eligible approved deposit to unlock.',
        'amount' => 0,
        'seconds' => 0
    );
}

$personal_pending = array();
if (function_exists('wcb_table_exists') && wcb_table_exists($conn, 'personal_bonuses')) {
    $stmt = $conn->prepare("SELECT id, amount, description, created_at FROM personal_bonuses WHERE user_id=? AND category='claim' AND status='pending' ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $personal_pending[] = $row;
            $available_bonuses[] = array(
                'type' => 'personal',
                'title' => 'Personal Bonus',
                'description' => (string)($row['description'] ?: 'Special claim bonus'),
                'amount' => (float)$row['amount'],
                'action' => 'claim_personal',
                'bonus_id' => intval($row['id'])
            );
        }
    }
}

$bonus_history = array();
if (function_exists('wcb_table_exists') && wcb_table_exists($conn, 'daily_bonus_claims')) {
    $stmt = $conn->prepare('SELECT amount, claimed_at FROM daily_bonus_claims WHERE user_id=? ORDER BY claimed_at DESC LIMIT 50');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $bonus_history[] = array('type' => 'Daily Bonus', 'amount' => (float)$row['amount'], 'date' => $row['claimed_at'], 'status' => 'Claimed', 'ts' => strtotime($row['claimed_at']));
        }
    }
}
if (function_exists('wcb_table_exists') && wcb_table_exists($conn, 'deposit_bonus_claims')) {
    $stmt = $conn->prepare('SELECT bonus_amount, claim_status, claimed_at FROM deposit_bonus_claims WHERE user_id=? ORDER BY claimed_at DESC LIMIT 50');
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $bonus_history[] = array('type' => 'Deposit Bonus', 'amount' => (float)$row['bonus_amount'], 'date' => $row['claimed_at'], 'status' => ucfirst((string)($row['claim_status'] ?: 'Claimed')), 'ts' => strtotime($row['claimed_at']));
        }
    }
}
if (function_exists('wcb_table_exists') && wcb_table_exists($conn, 'personal_bonuses')) {
    $stmt = $conn->prepare("SELECT amount, description, created_at, claimed_at FROM personal_bonuses WHERE user_id=? AND status='claimed' ORDER BY COALESCE(claimed_at, created_at) DESC LIMIT 50");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $date = $row['claimed_at'] ?: $row['created_at'];
            $title = trim((string)($row['description'] ?? 'Personal Bonus'));
            $bonus_history[] = array('type' => $title !== '' ? $title : 'Personal Bonus', 'amount' => (float)$row['amount'], 'date' => $date, 'status' => 'Claimed', 'ts' => strtotime($date));
        }
    }
}
if (function_exists('wcb_table_exists') && wcb_table_exists($conn, 'transactions_fake')) {
    $stmt = $conn->prepare("SELECT amount, method, status, created_at FROM transactions_fake WHERE user_id=? AND type='bonus' AND (method LIKE '%Invite%' OR method LIKE '%Referral%') ORDER BY created_at DESC LIMIT 30");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $bonus_history[] = array('type' => trim((string)$row['method']) ?: 'Invite Bonus', 'amount' => (float)$row['amount'], 'date' => $row['created_at'], 'status' => ucfirst((string)($row['status'] ?: 'Claimed')), 'ts' => strtotime($row['created_at']));
        }
    }
}
usort($bonus_history, function($a, $b){ return ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0); });
$bonus_history = array_slice($bonus_history, 0, 60);
$claim_count = count($available_bonuses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reward Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#0b1111;color:#fff;font-family:'Segoe UI',Arial,sans-serif;padding-bottom:92px}.reward-header{background:linear-gradient(180deg,#ff7149 0%,#ff9f4a 100%);height:210px;border-bottom-left-radius:28px;border-bottom-right-radius:28px;position:relative;overflow:hidden}.reward-header:after{content:"";position:absolute;left:-15%;right:-15%;bottom:-70px;height:150px;border-radius:50%;background:rgba(255,255,255,.14)}.profile-card{background:linear-gradient(135deg,#ffffff 0%,#f5f7fb 100%);border-radius:22px;box-shadow:0 14px 34px rgba(0,0,0,.32);margin-top:-78px;color:#263140}.grid-btn{border-radius:20px;padding:18px 14px;color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;height:118px;position:relative;overflow:hidden;transition:.25s;cursor:pointer;border:1px solid rgba(255,255,255,.12);box-shadow:0 12px 28px rgba(0,0,0,.24)}.grid-btn:active{transform:scale(.98)}.bg-green-grad{background:linear-gradient(135deg,#10b981 0%,#047857 100%)}.bg-blue-grad{background:linear-gradient(135deg,#3b82f6 0%,#1d4ed8 100%)}.bg-pink-grad{background:linear-gradient(135deg,#ec4899 0%,#be185d 100%)}.bg-orange-grad{background:linear-gradient(135deg,#f97316 0%,#c2410c 100%)}.btn-icon{width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:10px;font-size:22px}.badge-count{position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;min-width:23px;height:23px;padding:0 6px;border-radius:999px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;border:2px solid #fff}.bonus-modal{position:fixed;inset:0;background:rgba(0,0,0,.86);z-index:10001;display:none;align-items:flex-end;justify-content:center;padding:14px}.bonus-modal.show{display:flex}.bonus-panel{width:100%;max-width:480px;max-height:88vh;background:#fff;color:#111827;border-radius:24px 24px 18px 18px;overflow:hidden;box-shadow:0 -14px 44px rgba(0,0,0,.4)}.bonus-panel-head{background:linear-gradient(135deg,#fff7ed,#ecfdf5);padding:18px 18px 14px;border-bottom:1px solid #edf0f4;position:relative}.close-btn{position:absolute;right:14px;top:12px;width:32px;height:32px;border:0;border-radius:50%;background:#f3f4f6;color:#6b7280;font-size:24px;line-height:28px}.tab-btn{border:0;border-radius:999px;padding:9px 14px;font-size:12px;font-weight:800;background:#f3f4f6;color:#6b7280}.tab-btn.active{background:#16a34a;color:#fff}.bonus-body{padding:15px;overflow-y:auto;max-height:calc(88vh - 112px)}.bonus-item{border:1px solid #eef0f4;border-radius:18px;padding:14px;display:flex;align-items:center;gap:12px;background:#fff;box-shadow:0 4px 14px rgba(15,23,42,.04);margin-bottom:11px}.bonus-item.locked{background:#f9fafb;opacity:.88}.bonus-icon{width:48px;height:48px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:23px;background:#fff7ed}.claim-btn{border:0;border-radius:999px;background:linear-gradient(180deg,#22c55e,#16a34a);color:#fff;font-weight:800;font-size:12px;padding:10px 16px;white-space:nowrap;box-shadow:0 6px 14px rgba(22,163,74,.25)}.claim-btn:disabled{background:#d1d5db;color:#fff;box-shadow:none}.history-row{display:grid;grid-template-columns:1fr auto;gap:8px;border-bottom:1px solid #f0f2f5;padding:12px 0}.history-type{font-size:13px;font-weight:800;color:#111827}.history-date{font-size:11px;color:#8a94a6;margin-top:3px}.history-amount{text-align:right;font-weight:900;color:#16a34a}.history-status{font-size:11px;color:#6b7280;text-align:right;margin-top:2px}.empty-box{text-align:center;padding:28px 12px;color:#9ca3af}.toast{position:fixed;left:14px;right:14px;top:14px;z-index:12000;border-radius:14px;padding:13px 14px;text-align:center;font-weight:800;font-size:13px}.toast.success{background:#16a34a;color:#fff}.toast.error{background:#dc2626;color:#fff}@media(min-width:640px){.bonus-modal{align-items:center}.bonus-panel{border-radius:24px}}@media(max-width:370px){.grid-btn{height:108px;padding:14px 10px}.btn-icon{width:44px;height:44px}.bonus-item{padding:12px;gap:10px}.claim-btn{padding:9px 12px}}
    </style>
</head>
<body>
    <?php if($message): ?>
    <div onclick="this.remove()" class="toast <?php echo $message_type === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="reward-header px-4 pt-6">
        <div class="flex items-center text-white mb-6 relative z-10">
            <button onclick="window.history.back()" class="text-xl absolute left-0 bg-transparent border-0 text-white"><i class="fas fa-chevron-left"></i></button>
            <h1 class="text-lg font-bold w-full text-center">Reward Center</h1>
        </div>
        <h1 class="text-6xl font-black text-white opacity-10 text-center tracking-widest mt-4">REWARDS</h1>
    </div>

    <div class="px-4">
        <div class="profile-card p-5 relative">
            <a href="rewardsignin.php" class="absolute top-0 right-0 bg-red-600 text-white text-[10px] font-bold px-4 py-1.5 rounded-bl-2xl rounded-tr-2xl flex items-center gap-1 shadow-md">
                <i class="far fa-calendar-check"></i> Sign In <i class="fas fa-chevron-right text-[8px]"></i>
            </a>
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full border-2 border-white shadow-md overflow-hidden bg-yellow-100">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=ffd700&color=333&bold=true" class="w-full h-full object-cover" alt="Avatar">
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="font-black text-gray-800 text-lg truncate"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <i onclick="copyText('<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>')" class="far fa-copy text-gray-400 text-xs cursor-pointer"></i>
                    </div>
                    <p class="text-gray-500 text-xs font-semibold truncate">Nickname: <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="flex items-center gap-1 text-gray-900 font-black mt-1">
                        <span class="text-lg">৳ <?php echo number_format($balance, 2); ?></span>
                        <i class="fas fa-sync-alt text-gray-400 text-xs ml-2 cursor-pointer" onclick="location.reload()"></i>
                    </div>
                </div>
            </div>
            <div class="mt-5">
                <div class="flex justify-between items-center text-[11px] text-gray-600 mb-1.5 font-bold uppercase tracking-wide">
                    <span class="flex items-center gap-1 text-orange-600"><i class="fas fa-crown"></i> <?php echo $vip_name; ?></span>
                    <a href="vip.php" class="text-blue-600">Benefits <i class="fas fa-chevron-right text-[8px]"></i></a>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 shadow-inner"><div class="bg-gradient-to-r from-orange-400 to-orange-600 h-2 rounded-full" style="width: <?php echo $vip_progress; ?>%"></div></div>
                <p class="text-right text-[10px] text-gray-500 mt-1 font-bold"><?php echo htmlspecialchars($vip_progress_text, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
    </div>

    <div class="px-4 mt-8 grid grid-cols-2 gap-4">
        <div onclick="openBonusCenter()" class="grid-btn bg-green-grad">
            <div class="btn-icon relative"><i class="fas fa-gift"></i><?php if($claim_count > 0): ?><div class="badge-count"><?php echo $claim_count; ?></div><?php endif; ?></div>
            <span class="text-sm font-black uppercase tracking-tighter">Claim Bonus</span>
        </div>
        <a href="rewardsignin.php" class="grid-btn bg-blue-grad no-underline">
            <div class="btn-icon"><i class="far fa-calendar-check"></i></div>
            <span class="text-sm font-black uppercase tracking-tighter">Daily Check-in</span>
        </a>
        <a href="share.php" class="grid-btn bg-pink-grad no-underline">
            <div class="btn-icon"><i class="fas fa-user-plus"></i></div>
            <span class="text-sm font-black uppercase tracking-tighter text-center">Invite Friends</span>
        </a>
        <div onclick="openLuckyTicket()" class="grid-btn bg-orange-grad">
            <div class="btn-icon relative"><i class="fas fa-ticket-alt"></i></div>
            <span class="text-sm font-black uppercase tracking-tighter">Lucky Tickets</span>
        </div>
    </div>

    <div id="bonusCenterModal" class="bonus-modal">
        <div class="bonus-panel">
            <div class="bonus-panel-head">
                <button class="close-btn" onclick="closeBonusCenter()">×</button>
                <h3 class="text-center text-green-600 text-xl font-black uppercase">Bonus Center</h3>
                <p class="text-center text-xs text-gray-500 mt-1">Available bonus and claim history</p>
                <div class="flex justify-center gap-2 mt-4">
                    <button id="tabAvailableBtn" class="tab-btn active" onclick="showBonusTab('available')">Available Bonus</button>
                    <button id="tabHistoryBtn" class="tab-btn" onclick="showBonusTab('history')">Claim History</button>
                </div>
            </div>
            <div class="bonus-body">
                <div id="bonusAvailableTab">
                    <?php if(!empty($available_bonuses)): ?>
                        <?php foreach($available_bonuses as $b): ?>
                        <div class="bonus-item">
                            <div class="bonus-icon"><?php echo $b['type'] === 'deposit' ? '💰' : ($b['type'] === 'personal' ? '🎉' : '🎁'); ?></div>
                            <div class="flex-1 min-w-0">
                                <div class="font-black text-sm text-gray-900"><?php echo htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($b['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if(!empty($b['turnover'])): ?><div class="text-[11px] text-blue-600 font-bold mt-1">Turnover: ৳<?php echo number_format((float)$b['turnover'], 2); ?></div><?php endif; ?>
                            </div>
                            <div class="text-right">
                                <div class="font-black text-green-600 mb-2">৳<?php echo number_format((float)$b['amount'], 2); ?></div>
                                <button class="claim-btn" data-action="<?php echo htmlspecialchars($b['action'], ENT_QUOTES, 'UTF-8'); ?>" data-rule="<?php echo intval($b['rule_id'] ?? 0); ?>" data-bonus="<?php echo intval($b['bonus_id'] ?? 0); ?>" onclick="claimBonusFromCenter(this)">Claim</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-box"><i class="fas fa-box-open text-5xl text-gray-200 mb-3"></i><p class="font-black uppercase text-xs">No claimable bonus now</p></div>
                    <?php endif; ?>
                    <?php if(!empty($locked_bonuses)): ?>
                        <div class="mt-3 mb-2 text-xs font-black text-gray-500 uppercase">Upcoming / Locked</div>
                        <?php foreach($locked_bonuses as $b): ?>
                        <div class="bonus-item locked">
                            <div class="bonus-icon"><?php echo $b['type'] === 'deposit' ? '💰' : '🎁'; ?></div>
                            <div class="flex-1 min-w-0">
                                <div class="font-black text-sm text-gray-900"><?php echo htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($b['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div class="text-right">
                                <?php if((float)$b['amount'] > 0): ?><div class="font-black text-gray-500 mb-2">৳<?php echo number_format((float)$b['amount'], 2); ?></div><?php endif; ?>
                                <?php if(!empty($b['seconds'])): ?><div class="text-xs font-black text-purple-500 bonus-countdown" data-seconds="<?php echo intval($b['seconds']); ?>">00:00:00</div><?php else: ?><span class="text-xs font-black text-gray-400">Locked</span><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="bonusHistoryTab" class="hidden">
                    <?php if(!empty($bonus_history)): ?>
                        <?php foreach($bonus_history as $h): ?>
                        <div class="history-row">
                            <div>
                                <div class="history-type"><?php echo htmlspecialchars($h['type'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="history-date"><?php echo htmlspecialchars($h['date'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                            <div>
                                <div class="history-amount">৳<?php echo number_format((float)$h['amount'], 2); ?></div>
                                <div class="history-status"><?php echo htmlspecialchars($h['status'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-box"><i class="fas fa-clock-rotate-left text-5xl text-gray-200 mb-3"></i><p class="font-black uppercase text-xs">No bonus history found</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="ticketModal" class="bonus-modal">
        <div class="bonus-panel max-w-sm">
            <div class="bonus-panel-head"><button class="close-btn" onclick="closeLuckyTicket()">×</button><h3 class="text-center text-orange-600 text-xl font-black uppercase">Lucky Tickets</h3></div>
            <div class="bonus-body"><div class="empty-box"><i class="fas fa-ticket-alt text-6xl text-orange-200 mb-4"></i><p class="font-black text-gray-500 uppercase text-xs">You have 0 active tickets</p></div></div>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>

    <script>
        function openBonusCenter(){document.getElementById('bonusCenterModal').classList.add('show')}
        function closeBonusCenter(){document.getElementById('bonusCenterModal').classList.remove('show')}
        function openLuckyTicket(){document.getElementById('ticketModal').classList.add('show')}
        function closeLuckyTicket(){document.getElementById('ticketModal').classList.remove('show')}
        function showBonusTab(tab){
            var a=document.getElementById('bonusAvailableTab'),h=document.getElementById('bonusHistoryTab'),ab=document.getElementById('tabAvailableBtn'),hb=document.getElementById('tabHistoryBtn');
            if(tab==='history'){a.classList.add('hidden');h.classList.remove('hidden');ab.classList.remove('active');hb.classList.add('active')}else{h.classList.add('hidden');a.classList.remove('hidden');hb.classList.remove('active');ab.classList.add('active')}
        }
        function pad(n){return n<10?'0'+n:''+n}
        function fmt(sec){sec=Math.max(0,parseInt(sec||0,10));var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),s=sec%60;return pad(h)+':'+pad(m)+':'+pad(s)}
        document.querySelectorAll('.bonus-countdown').forEach(function(el){var sec=parseInt(el.getAttribute('data-seconds')||'0',10);function tick(){el.textContent=fmt(sec);if(sec>0)sec--}tick();setInterval(tick,1000)})
        function claimBonusFromCenter(btn){
            if(!btn||btn.disabled)return;
            var old=btn.textContent;
            btn.disabled=true;
            btn.textContent='...';
            var fd=new FormData();
            fd.append('ajax','1');
            fd.append('bonus_action',btn.getAttribute('data-action')||'');
            fd.append('rule_id',btn.getAttribute('data-rule')||'0');
            fd.append('bonus_id',btn.getAttribute('data-bonus')||'0');
            fetch('rewards.php',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json()}).then(function(data){
                if(data&&data.success){alert('Bonus claimed: ৳ '+Number(data.amount||0).toFixed(2));location.reload()}else{alert((data&&data.message)?data.message:'Claim failed.');btn.disabled=false;btn.textContent=old}
            }).catch(function(){alert('Network error.');btn.disabled=false;btn.textContent=old})
        }
        function copyText(text){if(navigator.clipboard){navigator.clipboard.writeText(text).then(function(){alert('Copied to clipboard!')})}}
    </script>
</body>
</html>
