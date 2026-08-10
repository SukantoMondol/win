<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) { require $db_path; } else { $conn = new mysqli('localhost', 'root', '', 'bating'); }
require_once __DIR__ . '/../includes/vip_system_helper.php';
wcb_vip_ensure_schema($conn);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$state = wcb_vip_state($conn, $user_id);
$user = $state['user'];
$settings = $state['settings'];
$current_level = $state['current_level'];
$next_level = $state['next_level'];
$conversions = wcb_vip_recent_conversions($conn, $user_id, 10);
$username = $user['username'] ?? ($user['phone'] ?? 'User');
$balance = (float)($user['balance'] ?? 0);
$is_enabled = intval($settings['is_enabled']) === 1;
$xp = (float)$state['xp'];
$target_xp = (float)$state['target_xp'];
$need_xp = (float)$state['need_xp'];
$available_vp = (int)$state['available_vp'];
$earned_vp = (int)$state['earned_vp'];
$converted_points = (int)$state['converted_points'];
$ratio = (float)$state['convert_ratio'];
$min_convert = (int)$state['min_convert_points'];
$real_money_available = (float)$state['real_money_available'];
$progress = (float)$state['progress_percent'];
$next_name = $next_level ? $next_level['level_name'] : 'MAX';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My VIP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;min-width:0}
        html,body{margin:0;width:100%;overflow-x:hidden;background:#0f1518;color:#d8e0e5;font-family:'Roboto',sans-serif;-webkit-font-smoothing:antialiased}
        body{padding-bottom:24px}
        .page{width:100%;max-width:620px;margin:0 auto;min-height:100vh;background:#0f1518}
        .vip-header{height:58px;background:#192329;display:flex;align-items:center;justify-content:center;position:sticky;top:0;z-index:50;border-bottom:1px solid rgba(255,255,255,.04)}
        .vip-header h1{font-size:18px;font-weight:600;color:#fff}
        .close-btn{position:absolute;right:16px;top:50%;transform:translateY(-50%);width:34px;height:34px;border:0;background:transparent;color:#fff;font-size:20px;display:flex;align-items:center;justify-content:center}
        .vip-card{margin:16px 14px 14px;background:linear-gradient(145deg,#243338,#192226);border:1px solid #314248;border-radius:18px;box-shadow:0 12px 28px rgba(0,0,0,.23);overflow:hidden}
        .vip-card-main{padding:18px}
        .level-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
        .level-left{display:flex;align-items:center;gap:12px}
        .level-icon{width:48px;height:48px;border-radius:50%;background:#0c1114;border:1px solid #435158;display:flex;align-items:center;justify-content:center;color:#f5c542;font-size:21px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.03)}
        .level-label{font-size:10px;letter-spacing:.08em;color:#9fb1bb;font-weight:700;text-transform:uppercase;line-height:1.1}
        .level-name{font-size:25px;line-height:1.1;font-weight:800;color:#fff;text-transform:uppercase;margin-top:4px}
        .history-btn,.detail-btn{border:0;border-radius:8px;padding:8px 12px;background:linear-gradient(180deg,#ffd65a,#ec990e);color:#fff;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 10px rgba(236,153,14,.22)}
        .xp-top{display:flex;justify-content:flex-end;align-items:center;margin-top:24px;margin-bottom:8px;font-size:12px;color:#a8b6bd;font-weight:600}
        .xp-top span:first-child{color:#ffd22d}
        .progress-track{height:6px;border-radius:50px;background:#3d4d53;position:relative;overflow:visible}
        .progress-fill{height:100%;border-radius:50px;background:linear-gradient(90deg,#fefefe,#ffcf33);width:0;box-shadow:0 0 10px rgba(255,207,51,.38);position:relative}
        .progress-dot{width:12px;height:12px;background:#fff;border-radius:50%;position:absolute;right:-6px;top:50%;transform:translateY(-50%);box-shadow:0 0 10px rgba(255,255,255,.8)}
        .upgrade-text{font-size:12px;line-height:1.55;color:#a8b6bd;margin-top:14px}
        .gold{color:#ffd22d;font-weight:700}
        .card-footer{background:rgba(255,255,255,.035);border-top:1px solid rgba(255,255,255,.06);padding:12px 18px;text-align:right}
        .card-footer button{font-size:12px;color:#b5c5cc;background:transparent;border:0;font-weight:600}
        .summary-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:0 14px 14px}
        .mini-card{background:#172126;border:1px solid #2d3b42;border-radius:14px;padding:14px 12px;min-height:76px}
        .mini-label{font-size:11px;color:#9fb1bb;font-weight:600;margin-bottom:7px}
        .mini-value{font-size:23px;color:#fff;font-weight:700;line-height:1}
        .mini-value small{font-size:10px;color:#91a4ad;font-weight:700;margin-left:3px}
        .section-title{display:flex;align-items:center;gap:8px;margin:18px 16px 10px;font-size:15px;color:#edf4f6;font-weight:700}
        .section-title:before{content:'';width:4px;height:16px;border-radius:20px;background:#ffd22d;display:block}
        .convert-card{margin:0 14px 16px;background:#1a2429;border:1px solid #2d3b42;border-radius:18px;padding:16px;box-shadow:0 8px 22px rgba(0,0,0,.18)}
        .input-row{display:flex;align-items:center;gap:13px;margin-bottom:12px}
        .coin-icon{width:45px;height:45px;border-radius:14px;background:#10171b;border:1px solid #2d3b42;display:flex;align-items:center;justify-content:center;color:#ffd22d;font-size:19px;flex-shrink:0}
        .field{flex:1}
        .field-head{display:flex;justify-content:space-between;gap:8px;font-size:11px;color:#9fb1bb;margin-bottom:7px;line-height:1.3}
        .vip-input{width:100%;height:48px;border-radius:11px;border:1px solid #2d3b42;background:#10171b;color:#dfe8ec;font-size:17px;font-weight:600;outline:none;padding:0 13px}
        .vip-input:focus{border-color:#d8a827;box-shadow:0 0 0 3px rgba(216,168,39,.12)}
        .arrow-box{text-align:center;margin:4px 0 10px;color:#9fb1bb;font-size:11px}
        .arrow-box i{display:block;color:#ffd22d;font-size:18px;animation:moveArrow 1.4s infinite}
        @keyframes moveArrow{0%,100%{transform:translateY(0);opacity:.65}50%{transform:translateY(4px);opacity:1}}
        .convert-btn{width:100%;height:48px;border:0;border-radius:999px;background:linear-gradient(180deg,#f9d95c 0%,#e49b11 100%);color:#fff;font-size:15px;font-weight:800;box-shadow:0 9px 16px rgba(228,155,17,.22);position:relative;overflow:hidden}
        .convert-btn:disabled{opacity:.55;filter:grayscale(.25);box-shadow:none;cursor:not-allowed}
        .convert-btn:after{content:'';position:absolute;left:0;right:0;top:0;height:48%;background:linear-gradient(180deg,rgba(255,255,255,.45),transparent);pointer-events:none}
        .alert-box{margin:0 14px 16px;border-radius:14px;padding:12px;background:#2a1d1d;border:1px solid #5d3030;color:#ffb4b4;font-size:13px;line-height:1.5}
        .level-list{margin:0 14px 16px;display:grid;gap:9px}
        .level-item{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#172126;border:1px solid #2d3b42;border-radius:13px;padding:12px}
        .level-item.active{border-color:#d8a827;background:linear-gradient(145deg,#261f13,#172126)}
        .level-item b{color:#fff;font-size:14px}
        .level-item span{color:#9fb1bb;font-size:12px}
        .table-wrap{margin:0 14px 20px;background:#172126;border:1px solid #2d3b42;border-radius:16px;overflow:hidden}
        .history-table{width:100%;border-collapse:collapse;font-size:12px}
        .history-table th{background:#1f2a30;color:#9fb1bb;text-align:left;font-weight:700;padding:11px 10px}
        .history-table td{padding:11px 10px;border-top:1px solid #2d3b42;color:#d5dfe4}
        .modal{position:fixed;inset:0;background:rgba(0,0,0,.64);z-index:80;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px)}
        .modal.active{display:flex}
        .modal-box{width:100%;max-width:360px;background:#172126;border:1px solid #34444b;border-radius:18px;padding:18px;box-shadow:0 20px 50px rgba(0,0,0,.45)}
        .modal-title{font-size:17px;color:#fff;font-weight:800;margin-bottom:8px}
        .modal-text{font-size:13px;line-height:1.55;color:#afc0c8;margin-bottom:16px}
        .modal-actions{display:flex;gap:10px}
        .modal-btn{flex:1;border:0;border-radius:12px;height:43px;font-size:14px;font-weight:800}
        .modal-cancel{background:#26343a;color:#b9c8ce}
        .modal-ok{background:#efad18;color:#fff}
        @media(max-width:430px){.page{max-width:100%}.vip-card{margin:14px 10px}.vip-card-main{padding:16px}.summary-row{margin-left:10px;margin-right:10px;gap:8px}.mini-card{padding:12px 10px}.mini-value{font-size:20px}.convert-card,.level-list,.table-wrap,.alert-box{margin-left:10px;margin-right:10px}.history-btn,.detail-btn{padding:7px 10px}.level-name{font-size:23px}.input-row{gap:10px}.coin-icon{width:42px;height:42px}}
    </style>
</head>
<body>
<div class="page">
    <div class="vip-header">
        <h1>My VIP</h1>
        <button onclick="window.history.back()" class="close-btn"><i class="fas fa-times"></i></button>
    </div>

    <?php if(!$is_enabled): ?>
        <div class="alert-box">VIP system is currently unavailable.</div>
    <?php endif; ?>

    <div class="vip-card">
        <div class="vip-card-main">
            <div class="level-row">
                <div class="level-left">
                    <div class="level-icon"><i class="fas fa-crown"></i></div>
                    <div>
                        <div class="level-label">VIP LEVEL</div>
                        <div class="level-name"><?php echo htmlspecialchars($current_level['level_name'] ?? 'NORMAL', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
                <button class="history-btn" onclick="scrollToHistory()"><i class="fas fa-history"></i> History</button>
            </div>

            <div class="xp-top"><span><?php echo number_format($xp, 0); ?></span>/<?php echo number_format($target_xp, 0); ?> <i class="fas fa-clover text-green-400 ml-2"></i></div>
            <div class="progress-track">
                <div class="progress-fill" style="width:<?php echo number_format($progress, 2, '.', ''); ?>%"><span class="progress-dot"></span></div>
            </div>

            <div class="upgrade-text">
                <?php if($next_level): ?>
                    You need <span class="gold"><?php echo number_format($need_xp, 0); ?></span> more VIP Experience to upgrade to next <span class="gold"><?php echo htmlspecialchars($next_name, ENT_QUOTES, 'UTF-8'); ?></span> level.
                <?php else: ?>
                    You have reached the highest VIP level.
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer">
            <button onclick="scrollToLevels()">View VIP Details <i class="fas fa-angle-double-right text-[10px]"></i></button>
        </div>
    </div>

    <div class="summary-row">
        <div class="mini-card"><div class="mini-label">VIP Points</div><div class="mini-value"><?php echo number_format($available_vp); ?><small>VP</small></div></div>
        <div class="mini-card"><div class="mini-label">Earned</div><div class="mini-value"><?php echo number_format($earned_vp); ?><small>VP</small></div></div>
        <div class="mini-card"><div class="mini-label">Balance</div><div class="mini-value">৳<?php echo number_format($balance, 2); ?></div></div>
    </div>

    <div class="section-title">Convert VP</div>
    <div class="convert-card">
        <div class="input-row">
            <div class="coin-icon"><i class="fas fa-coins"></i></div>
            <div class="field">
                <div class="field-head"><span>Points</span><span>Minimum VP Required: <?php echo $min_convert; ?></span></div>
                <input id="vpPoints" type="number" min="<?php echo $min_convert; ?>" max="<?php echo $available_vp; ?>" value="0" class="vip-input" oninput="calculateMoney()" <?php echo (!$is_enabled || $available_vp < $min_convert) ? 'disabled' : ''; ?>>
            </div>
        </div>
        <div class="arrow-box"><i class="fas fa-angle-double-down"></i>VP Conversion Ratio : <span class="gold"><?php echo rtrim(rtrim(number_format($ratio, 2), '0'), '.'); ?></span></div>
        <div class="input-row">
            <div class="coin-icon"><i class="fas fa-wallet"></i></div>
            <div class="field">
                <div class="field-head"><span>Real Money</span><span>Available: ৳<?php echo number_format($real_money_available, 2); ?></span></div>
                <input id="realMoney" type="text" value="0.00" class="vip-input" readonly>
            </div>
        </div>
        <button class="convert-btn" onclick="openConvertModal()" <?php echo (!$is_enabled || $available_vp < $min_convert) ? 'disabled' : ''; ?>>Convert to Real Money</button>
    </div>

    <div id="levelsSection" class="section-title">VIP Levels</div>
    <div class="level-list">
        <?php foreach($state['levels'] as $lv): ?>
            <?php $active = (($current_level['id'] ?? 0) == ($lv['id'] ?? -1)); ?>
            <div class="level-item <?php echo $active ? 'active' : ''; ?>">
                <div><b><?php echo htmlspecialchars($lv['level_name'], ENT_QUOTES, 'UTF-8'); ?></b><br><span>Required XP: <?php echo number_format((float)$lv['required_xp'], 0); ?></span></div>
                <span><?php echo $active ? 'Current' : 'Locked'; ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="historySection" class="section-title">Conversion History</div>
    <div class="table-wrap">
        <table class="history-table">
            <thead><tr><th>Points</th><th>Money</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php if(!empty($conversions)): foreach($conversions as $c): ?>
                <tr>
                    <td><?php echo number_format((int)$c['points']); ?></td>
                    <td>৳<?php echo number_format((float)$c['real_amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($c['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(date('d M Y', strtotime($c['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-slate-400">No conversion found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="convertModal" class="modal">
    <div class="modal-box">
        <div class="modal-title">Confirm Conversion</div>
        <div class="modal-text" id="confirmText">Please confirm your VP conversion.</div>
        <div class="modal-actions">
            <button class="modal-btn modal-cancel" onclick="closeConvertModal()">Cancel</button>
            <button class="modal-btn modal-ok" onclick="submitConversion(this)">Confirm</button>
        </div>
    </div>
</div>

<script>
const ratio = <?php echo json_encode($ratio); ?>;
const minPoints = <?php echo json_encode($min_convert); ?>;
const maxPoints = <?php echo json_encode($available_vp); ?>;
function calculateMoney(){
    const input = document.getElementById('vpPoints');
    const output = document.getElementById('realMoney');
    let points = parseInt(input.value || '0', 10);
    if(points < 0) points = 0;
    if(points > maxPoints){ points = maxPoints; input.value = maxPoints; }
    output.value = (points / ratio).toFixed(2);
}
function openConvertModal(){
    const points = parseInt(document.getElementById('vpPoints').value || '0', 10);
    if(points < minPoints){ alert('Minimum VP required: ' + minPoints); return; }
    if(points > maxPoints){ alert('Not enough VP.'); return; }
    const money = (points / ratio).toFixed(2);
    document.getElementById('confirmText').innerText = points + ' VP convert করে ৳' + money + ' balance এ যোগ হবে।';
    document.getElementById('convertModal').classList.add('active');
}
function closeConvertModal(){ document.getElementById('convertModal').classList.remove('active'); }
function submitConversion(btn){
    const points = parseInt(document.getElementById('vpPoints').value || '0', 10);
    const old = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Processing';
    const fd = new FormData();
    fd.append('points', points);
    fetch('../api/vip_convert.php', {method:'POST', body:fd, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(data => {
            if(data && data.success){ alert('Converted: ৳' + Number(data.real_amount || 0).toFixed(2)); location.reload(); }
            else { alert((data && data.message) ? data.message : 'Conversion failed.'); btn.disabled = false; btn.innerText = old; }
        })
        .catch(() => { alert('Network error.'); btn.disabled = false; btn.innerText = old; });
}
function scrollToHistory(){ document.getElementById('historySection').scrollIntoView({behavior:'smooth'}); }
function scrollToLevels(){ document.getElementById('levelsSection').scrollIntoView({behavior:'smooth'}); }
calculateMoney();
</script>
</body>
</html>
