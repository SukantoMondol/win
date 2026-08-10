<?php
session_start();
$db_path = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (file_exists($db_path)) { require $db_path; }
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/../includes/referral_system_helper.php';
wcb_referral_ensure_schema($conn);

$user_id = intval($_SESSION['user_id']);
$stmtUser = $conn->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
$stmtUser->bind_param('i', $user_id);
$stmtUser->execute();
$user_data = $stmtUser->get_result()->fetch_assoc();
$invite_code = wcb_referral_code($conn, $user_id);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
$referral_link = $base_url . "/player/signup.php?ref=" . urlencode($invite_code);
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($referral_link);
$settings = wcb_referral_settings($conn);
$stats = wcb_referral_stats($conn, $user_id);
$level_progress = wcb_referral_level_progress($conn, $user_id);
$bonus_history = wcb_referral_history($conn, $user_id, 80);
$direct_list = wcb_referral_direct_list($conn, $user_id, 80);
$medals = array('🏅','🥈','💎','🏆','⭐','🌟','🎖️','💰','🔥','👑');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Referral Program</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f0f4f8;--primary:#154b77;--green:#43a047;--muted:#64748b;--border:#e2e8f0}
        *{box-sizing:border-box}
        body{background:var(--bg);color:#263445;font-family:'Roboto',sans-serif;padding-bottom:90px;-webkit-tap-highlight-color:transparent}
        .page{width:100%;max-width:640px;margin:0 auto;min-height:100vh;background:#edf3f9}
        .header-bg{background:linear-gradient(135deg,#0f395c 0%,#1a5c92 100%);box-shadow:0 2px 10px rgba(0,0,0,.1)}
        .tabs-container{display:flex;background:#fff;border-bottom:1px solid var(--border);position:sticky;top:60px;z-index:40}
        .tab-btn{flex:1;text-align:center;padding:15px 0;font-size:13px;font-weight:800;color:var(--muted);cursor:pointer;text-transform:uppercase;letter-spacing:.4px;position:relative}
        .tab-btn.active{color:var(--primary)}
        .tab-btn.active:after{content:'';position:absolute;bottom:0;left:0;width:100%;height:3px;background:var(--green);border-radius:4px 4px 0 0}
        .white-card{background:#fff;border:1px solid var(--border);border-radius:13px;padding:15px;margin-bottom:15px;box-shadow:0 2px 10px rgba(15,35,60,.04)}
        .section-title{border-left:4px solid var(--green);padding-left:10px;font-size:14px;color:var(--primary);margin-bottom:14px;font-weight:900;text-transform:uppercase;letter-spacing:.3px}
        .copy-box{background:#f8fafc;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;padding:4px;margin-top:6px}
        .copy-input{background:transparent;border:0;color:var(--primary);width:100%;padding:8px 10px;font-size:12px;font-weight:800;outline:0}
        .copy-icon-btn{background:linear-gradient(to bottom,#4caf50,#388e3c);width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:7px;color:#fff;box-shadow:0 2px 4px rgba(67,160,71,.2)}
        .stat-grid{display:grid;grid-template-columns:repeat(3,1fr);text-align:center;background:#f8fafc;border-top:1px solid var(--border);margin:0 -15px -15px;border-radius:0 0 13px 13px;overflow:hidden}
        .stat-item{padding:12px 8px;border-right:1px solid var(--border)}.stat-item:last-child{border-right:0}.stat-item h4{font-size:10px;color:var(--muted);margin-bottom:4px;font-weight:800;text-transform:uppercase}.stat-item p{font-size:15px;color:var(--primary);font-weight:900}
        .level-card{display:grid;grid-template-columns:82px 1fr 94px;align-items:center;gap:12px;background:linear-gradient(100deg,#f3f8ff 0%,#e9f4ff 100%);border:1px solid #dfeaf7;border-radius:10px;padding:14px;margin-bottom:12px;box-shadow:0 2px 8px rgba(15,35,60,.035)}
        .medal{width:72px;height:72px;border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:46px;background:rgba(255,255,255,.55)}
        .level-text{font-size:16px;font-weight:800;color:#7b8794;line-height:1.35}.level-amount{font-size:18px;font-weight:900;color:#7d8790;margin-top:8px}.level-progress{font-size:24px;font-weight:900;color:#6057a8;text-align:right}.level-progress small{font-size:14px;color:#333;font-weight:700}.status-btn{border:0;border-radius:8px;background:linear-gradient(120deg,#69c7f0,#b150df);color:#fff;font-size:12px;font-weight:900;padding:9px 10px;margin-top:8px;min-width:86px}.status-done{background:linear-gradient(120deg,#22c55e,#16a34a)}.status-locked{background:linear-gradient(120deg,#cbd5e1,#94a3b8)}
        .table-wrap{overflow-x:auto}.mini-table{width:100%;font-size:12px;border-collapse:separate;border-spacing:0}.mini-table th{background:#f8fafc;color:#64748b;font-size:10px;text-transform:uppercase;padding:10px;text-align:left}.mini-table td{border-top:1px solid #edf2f7;padding:10px;color:#334155}.pill{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:10px;font-weight:900}.pill-green{background:#dcfce7;color:#15803d}.pill-gray{background:#f1f5f9;color:#64748b}.tab-content{display:none;animation:fadeIn .25s ease}.tab-content.active{display:block}@keyframes fadeIn{from{opacity:0}to{opacity:1}}
        @media(max-width:430px){.page{max-width:100%}.level-card{grid-template-columns:64px 1fr 82px;gap:9px;padding:12px}.medal{width:58px;height:58px;font-size:38px}.level-text{font-size:14px}.level-amount{font-size:16px}.level-progress{font-size:22px}.status-btn{font-size:11px;padding:8px 8px;min-width:76px}.stat-item{padding:10px 4px}.stat-item h4{font-size:9px}.stat-item p{font-size:14px}}
    </style>
</head>
<body>
<div class="page">
    <div class="header-bg p-4 flex justify-between items-center sticky top-0 z-50 h-[60px]">
        <a href="index.php" class="text-white text-xl p-1"><i class="fas fa-home"></i></a>
        <h1 class="text-white font-bold text-[15px] uppercase tracking-wider">Referral Program</h1>
        <button onclick="window.history.back()" class="text-white text-xl p-1"><i class="fas fa-times"></i></button>
    </div>

    <div class="tabs-container">
        <div class="tab-btn active" onclick="switchTab('invite', this)">Invite</div>
        <div class="tab-btn" onclick="switchTab('levels', this)">Levels</div>
        <div class="tab-btn" onclick="switchTab('details', this)">Details</div>
    </div>

    <div id="invite" class="tab-content active p-4">
        <div class="white-card p-0 overflow-hidden">
            <div class="p-4 pb-0">
                <div class="section-title">Refer Friends and Earn</div>
                <img src="../assets/img/refer_banner.png" onerror="this.src='https://placehold.co/600x220/154b77/fff?text=REFER+A+FRIEND'" class="w-full rounded-lg mb-5 shadow-sm border border-gray-100">
                <div class="flex gap-4">
                    <div class="bg-white p-1 rounded-lg w-[110px] h-[110px] flex-shrink-0 border border-gray-200 shadow-sm"><img src="<?php echo $qr_code_url; ?>" class="w-full h-full object-contain"></div>
                    <div class="flex-1 flex flex-col justify-between min-w-0">
                        <div>
                            <p class="text-[10.5px] font-bold text-gray-500 uppercase">Invitation Link</p>
                            <div class="copy-box"><button class="w-full text-center text-[12px] font-extrabold text-white bg-gradient-to-b from-[#4caf50] to-[#388e3c] rounded py-2 shadow-sm" onclick="shareLink()"><i class="fas fa-share-alt mr-1"></i> Share Link</button></div>
                        </div>
                        <div class="mt-2">
                            <p class="text-[10.5px] font-bold text-gray-500 uppercase">Invitation Code</p>
                            <div class="copy-box"><input type="text" value="<?php echo htmlspecialchars($invite_code, ENT_QUOTES, 'UTF-8'); ?>" readonly class="copy-input text-center"><button class="copy-icon-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($invite_code, ENT_QUOTES, 'UTF-8'); ?>')"><i class="far fa-copy"></i></button></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stat-grid mt-5">
                <div class="stat-item"><h4>Total Referrals</h4><p class="text-[#43a047]"><?php echo number_format((float)$stats['total_referrals']); ?></p></div>
                <div class="stat-item"><h4>Today Reward</h4><p>৳ <?php echo number_format((float)$stats['today_reward'],2); ?></p></div>
                <div class="stat-item"><h4>Total Earned</h4><p>৳ <?php echo number_format((float)$stats['total_earned'],2); ?></p></div>
            </div>
        </div>

        <div class="white-card">
            <div class="section-title">Referral Conditions</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
                <div class="bg-green-50 rounded-xl p-3 border border-green-100"><div class="text-lg font-black text-green-700"><?php echo intval($settings['is_enabled'])===1?'ON':'OFF'; ?></div><div class="text-[11px] text-gray-500 font-bold mt-1">Referral System</div></div>
                <div class="bg-blue-50 rounded-xl p-3 border border-blue-100"><div class="text-lg font-black text-blue-700">৳<?php echo number_format((float)$settings['min_deposit_amount'],2); ?></div><div class="text-[11px] text-gray-500 font-bold mt-1">Minimum First Deposit</div></div>
                <div class="bg-amber-50 rounded-xl p-3 border border-amber-100"><div class="text-lg font-black text-amber-700"><?php echo number_format((float)$stats['qualified_referrals']); ?></div><div class="text-[11px] text-gray-500 font-bold mt-1">Qualified Referrals</div></div>
            </div>
        </div>
    </div>

    <div id="levels" class="tab-content p-4">
        <div class="white-card">
            <div class="flex justify-between items-center mb-3"><div class="section-title mb-0">Referral Level Rewards</div><span class="text-xs font-bold text-gray-400">no expiration</span></div>
            <?php if(count($level_progress)>0): foreach($level_progress as $idx => $lv):
                $limit = max(1, intval($lv['referral_limit']));
                $earned = min($limit, intval($lv['earned_count']));
                $done = intval($lv['is_completed']) === 1;
                $claimable = intval($lv['is_claimable'] ?? 0) === 1;
                $eligible = intval($lv['is_eligible'] ?? 0) === 1;
                $icon = $medals[$idx % count($medals)];
            ?>
                <div class="level-card">
                    <div class="medal"><?php echo $icon; ?></div>
                    <div>
                        <div class="level-text">Over <?php echo number_format($limit); ?> valid referral in total.</div>
                        <div class="level-amount"><i class="fas fa-coins mr-2 text-gray-400"></i><?php echo number_format((float)$lv['bonus_amount'],2); ?></div>
                    </div>
                    <div class="text-right">
                        <div class="level-progress"><?php echo number_format($earned); ?><small>/<?php echo number_format($limit); ?></small></div>
                        <?php if($done): ?>
                            <button class="status-btn status-done" type="button">Completed</button>
                        <?php elseif($claimable): ?>
                            <button class="status-btn" type="button" onclick="claimReferralLevel(<?php echo intval($lv['level_no']); ?>, this)">Claim</button>
                        <?php else: ?>
                            <button class="status-btn status-locked" type="button"><?php echo $eligible ? 'Processing' : 'Locked'; ?></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div class="text-center py-10 text-gray-400 font-bold">No referral level available.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="details" class="tab-content p-4">
        <div class="white-card">
            <div class="section-title">Referral Bonus History</div>
            <div class="table-wrap">
                <table class="mini-table">
                    <thead><tr><th>Type</th><th>Level</th><th>Bonus</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if(count($bonus_history)>0): foreach($bonus_history as $h): ?>
                        <tr>
                            <td>Referral Milestone</td>
                            <td><span class="pill pill-green">Level <?php echo intval($h['level']); ?></span></td>
                            <td class="font-black text-green-600">৳<?php echo number_format((float)$h['bonus_amount'],2); ?></td>
                            <td><?php echo $h['status']==='credited' ? '<span class="pill pill-green">Credited</span>' : '<span class="pill pill-gray">Pending</span>'; ?></td>
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($h['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center text-gray-400 font-bold py-8">No bonus history found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="white-card">
            <div class="section-title">My Referrals</div>
            <div class="table-wrap">
                <table class="mini-table">
                    <thead><tr><th>User</th><th>Deposit</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php if(count($direct_list)>0): foreach($direct_list as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['phone'] ?: ($r['username'] ?: ('User #'.$r['id'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="font-bold">৳<?php echo number_format((float)$r['deposit_total'],2); ?></td>
                            <td><?php echo intval($r['qualified'])===1 ? '<span class="pill pill-green">Qualified</span>' : '<span class="pill pill-gray">Pending</span>'; ?></td>
                            <td><?php echo htmlspecialchars(date('d M Y', strtotime($r['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center text-gray-400 font-bold py-8">No referral found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'bottom_nav.php'; ?>
</div>
<script>
function switchTab(tabId, btn){document.querySelectorAll('.tab-content').forEach(el=>el.classList.remove('active'));document.getElementById(tabId).classList.add('active');document.querySelectorAll('.tab-btn').forEach(el=>el.classList.remove('active'));btn.classList.add('active')}
function copyToClipboard(text){navigator.clipboard.writeText(text).then(function(){alert('Copied: '+text)})}
function shareLink(){if(navigator.share){navigator.share({title:'Referral Program',text:'Join me and earn rewards!',url:'<?php echo $referral_link; ?>'})}else{copyToClipboard('<?php echo $referral_link; ?>')}}
function claimReferralLevel(level,btn){if(!level||!btn)return;var old=btn.innerText;btn.disabled=true;btn.innerText='Processing';var fd=new FormData();fd.append('level',level);fetch('../api/referral_level_claim.php',{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()).then(d=>{if(d&&d.success){alert(d.message||'Bonus credited.');location.reload()}else{alert((d&&d.message)?d.message:'Claim failed.');btn.disabled=false;btn.innerText=old}}).catch(()=>{alert('Network error.');btn.disabled=false;btn.innerText=old})}
</script>
</body>
</html>
