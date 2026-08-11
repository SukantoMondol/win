<?php
session_start();
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/propay_gateway_helper.php';
require_once __DIR__ . '/../includes/lgpay_gateway_helper.php';
require_once __DIR__ . '/../includes/nekpay_gateway_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

propay_ensure_schema($conn);
lgpay_ensure_schema($conn);
nekpay_ensure_schema($conn);

$uid = intval($_SESSION['user_id']);
$user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$txn_settings = propay_get_site_transaction_settings($conn);

$nek_settings = nekpay_get_settings($conn);
if (!empty($nek_settings['is_enabled']) && !empty($nek_settings['merchant_code'])) {
    $target_action = "nekpay_deposit_start.php";
} else {
    $target_action = "lgpay_deposit_start.php";
}

$notice_text = !empty($settings['deposit_notice']) ? $settings['deposit_notice'] : 'Please check the correct number before payment.';
$promotions = $conn->query("SELECT id, title FROM promotions WHERE status='active' AND (category IN ('all','deposit') OR category='' OR category IS NULL) AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$min_deposit = max(1, (float)($txn_settings['min_deposit_amount'] ?? 100));
$bkash_bonus = max(0, (float)($txn_settings['deposit_bonus_bkash'] ?? 0));
$nagad_bonus = max(0, (float)($txn_settings['deposit_bonus_nagad'] ?? 0));
$fast_amts = [100, 200, 300, 500, 1000, 5000, 10000, 20000, 25000];
$min_fast_amount = (int)ceil($min_deposit);
if (!in_array($min_fast_amount, $fast_amts, true)) { array_unshift($fast_amts, $min_fast_amount); }
$fast_amts = array_values(array_unique(array_filter($fast_amts, function($v) use ($min_deposit) { return $v >= $min_deposit; })));
$channels = array();
$active_gateway = 'lgpay';
$needs_channel = false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Deposit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f1f4f9; font-family: 'Roboto', sans-serif; padding-bottom: 120px; overflow-x: hidden; }
        .header-premium { background: linear-gradient(90deg, #0e3d2c 0%, #1a5c40 100%); }
        .modern-notice-container { background: #ffffff; border-left: 5px solid #1de9b6; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); border-radius: 8px; padding: 12px 20px; display: flex; align-items: center; }
        .notice-badge { background: #1de9b6; color: #071f18; font-size: 11px; font-weight: bold; padding: 3px 8px; border-radius: 4px; margin-right: 15px; letter-spacing: 1px; }
        .notice-content i { color: #1de9b6; margin-right: 10px; font-size: 18px; }
        .pay-card { background: #fff; border: 2px solid #e2e8f0; border-radius: 12px; height: 95px; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: 0.3s; position: relative; }
        .pay-card.active { border-color: #1de9b6; background: #f0fffb; }
        .bonus-badge { position: absolute; top: -10px; right: -5px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 900; padding: 2px 8px; border-radius: 6px; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
        .amt-btn { background: #fff; border: 1px solid #d1d9e6; color: #0d4a3a; padding: 12px 0; border-radius: 10px; font-size: 13px; font-weight: 800; transition: all 0.2s; }
        .amt-btn.active { background: #1de9b6; color: #071f18; border-color: #1de9b6; box-shadow: 0 4px 10px rgba(30, 233, 182, 0.2); }
        .confirm-btn { background: linear-gradient(180deg, #1de9b6 0%, #00897b 100%); color: #fff; width: 100%; padding: 18px; border-radius: 15px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; justify-content: center; }
        .promo-dropdown { display: none; position: absolute; width: 100%; left: 0; top: 100%; background: white; border: 1px solid #e2e8f0; border-radius: 0 0 12px 12px; z-index: 100; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .promo-dropdown.show { display: block; }
        .channel-card { background: #fff; border: 1.5px solid #d1d9e6; border-radius: 13px; padding: 14px; min-height: 74px; display: flex; align-items: center; justify-content: space-between; transition: 0.22s; cursor: pointer; }
        .channel-card.active { border-color: #1de9b6; background: #f0fffb; box-shadow: 0 5px 16px rgba(30, 233, 182, 0.13); }
        .channel-dot { width: 18px; height: 18px; border-radius: 999px; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; }
        .channel-card.active .channel-dot { border-color: #1de9b6; }
        .channel-card.active .channel-dot:after { content: ''; width: 8px; height: 8px; border-radius: 999px; background: #1de9b6; display: block; }
    </style>
</head>
<body>

    <div class="header-premium p-4 flex justify-between items-center text-white shadow-lg">
        <button onclick="window.history.back()" class="w-10 h-10 flex items-center p-1 active:scale-90 transition"><i class="fas fa-chevron-left"></i></button>
        <h1 class="font-black uppercase tracking-tighter italic text-lg">Funds Management</h1>
        <div class="w-10 h-10"></div>
    </div>

    <div class="modern-notice-container mt-4 mx-4">
        <span class="notice-badge">NOTICE</span>
        <div class="notice-content">
            <i class="fas fa-bullhorn"></i>
            <span class="text-wrapper text-xs font-bold text-gray-600"><?php echo htmlspecialchars($notice_text, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <form id="depositForm" action="<?php echo $target_action; ?>" method="POST" class="p-4 space-y-6" onsubmit="return validateDepositForm()">
        <input type="hidden" name="channel" id="channelInput" value="">

        <div class="flex bg-gray-200 rounded-xl p-1">
            <div class="flex-1 text-center py-2.5 bg-white text-[#0d4a3a] font-black rounded-lg shadow-sm text-sm uppercase italic">Deposit</div>
            <a href="withdraw.php" class="flex-1 text-center py-2.5 text-gray-500 font-bold text-sm uppercase italic">Withdraw</a>
        </div>

        <div class="relative">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Available Promotion</label>
            <div class="bg-white border-2 border-dashed border-emerald-100 p-3 rounded-xl bg-emerald-50/30 flex justify-between items-center cursor-pointer" onclick="togglePromo()">
                <span id="selectedPromoLabel" class="text-xs font-bold text-[#0d4a3a]"><i class="fas fa-gift mr-2"></i> Regular Deposit</span>
                <i class="fas fa-chevron-down text-[#0d4a3a]"></i>
            </div>
            <input type="hidden" name="promo_id" id="promoIdInput" value="0">
            <div id="promoDropdownList" class="promo-dropdown overflow-hidden">
                <div class="p-3 text-xs border-b hover:bg-gray-50 font-bold text-gray-600" onclick="selectPromo('Regular Deposit', 0)">Regular Deposit</div>
                <?php foreach($promotions as $p): ?>
                    <div class="p-3 text-xs border-b hover:bg-gray-50 font-bold text-gray-600" onclick="selectPromo('<?php echo addslashes($p['title']); ?>', <?php echo intval($p['id']); ?>)">
                        <?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-3 ml-1">Select Payment Method</label>
            <div class="grid grid-cols-2 gap-4">
                <label class="cursor-pointer">
                    <input type="radio" name="method" value="bKash" class="hidden" onchange="updateUI()">
                    <div class="pay-card">
                        <?php if($bkash_bonus > 0): ?> <div class="bonus-badge">+<?php echo rtrim(rtrim(number_format($bkash_bonus, 2), '0'), '.'); ?>% Bonus</div> <?php endif; ?>
                        <img src="https://freelogopng.com/images/all_img/1656234745bkash-app-logo-png.png" class="h-10">
                        <span class="text-[11px] font-black mt-2 text-[#0d4a3a]">BKASH</span>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="method" value="Nagad" class="hidden" onchange="updateUI()">
                    <div class="pay-card">
                        <?php if($nagad_bonus > 0): ?> <div class="bonus-badge">+<?php echo rtrim(rtrim(number_format($nagad_bonus, 2), '0'), '.'); ?>% Bonus</div> <?php endif; ?>
                        <img src="https://freelogopng.com/images/all_img/1679248828Nagad-Logo-PNG.png" class="h-10">
                        <span class="text-[11px] font-black mt-2 text-[#0d4a3a]">NAGAD</span>
                    </div>
                </label>
            </div>
        </div>

        <?php if($needs_channel): ?>
        <div class="bg-white rounded-2xl p-5 border border-emerald-50 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Select Channel</label>
                    <p class="text-[11px] text-gray-400 font-bold mt-1">Selected amount will be sent through this channel.</p>
                </div>
                <span id="activeMethodLabel" class="text-[10px] font-black text-[#0d4a3a] bg-emerald-50 px-2 py-1 rounded uppercase">Select Method</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="channelList">
                <?php foreach($channels as $channel_key => $channel_label): ?>
                    <button type="button" class="channel-card" data-channel="<?php echo htmlspecialchars($channel_label, ENT_QUOTES, 'UTF-8'); ?>" onclick="selectChannel(this)">
                        <div class="text-left">
                            <p class="text-sm font-black text-[#0d4a3a]"><?php echo htmlspecialchars($channel_label, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="channel-amount text-[11px] font-bold text-gray-400 mt-1">৳ --</p>
                        </div>
                        <span class="channel-dot"></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php endif; ?>

        <div class="bg-white rounded-2xl p-5 border border-emerald-50 shadow-sm">
            <div class="flex justify-between items-center mb-4">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Amount (BDT)</label>
                <span class="text-[10px] font-black text-rose-500 uppercase italic">Min <?php echo number_format($min_deposit, 0); ?></span>
            </div>
            <div class="flex items-center border-b-2 border-emerald-400 pb-2 mb-6">
                <span class="text-3xl font-black text-[#0d4a3a] mr-3">৳</span>
                <input type="number" name="amount" id="amtInput" value="" placeholder="<?php echo number_format($min_deposit, 0); ?>" min="<?php echo htmlspecialchars((string)$min_deposit, ENT_QUOTES, 'UTF-8'); ?>" max="25000" class="w-full text-3xl font-black text-[#0d4a3a] outline-none bg-transparent" oninput="syncAmtButtons()">
            </div>
            <div class="grid grid-cols-3 gap-2">
                <?php foreach($fast_amts as $a): ?>
                    <button type="button" class="amt-btn" data-val="<?php echo $a; ?>" onclick="setAmt(<?php echo $a; ?>, this)"><?php echo $a; ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="p-4">
            <button type="submit" id="submitBtn" class="confirm-btn active:scale-95 transition-all shadow-xl">Deposit Now</button>
        </div>
    </form>

    <?php include 'bottom_nav.php'; ?>

    <script>
    const minDeposit = <?php echo json_encode((float)$min_deposit); ?>;
    const needsChannel = <?php echo $needs_channel ? 'true' : 'false'; ?>;

    function togglePromo() {
        document.getElementById('promoDropdownList').classList.toggle('show');
    }

    function selectPromo(title, id) {
        document.getElementById('selectedPromoLabel').innerHTML = '<i class="fas fa-gift mr-2"></i> ' + title;
        document.getElementById('promoIdInput').value = id;
        togglePromo();
    }

    function setAmt(v, b) {
        document.getElementById('amtInput').value = v;
        document.querySelectorAll('.amt-btn').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        refreshChannelAmounts();
    }

    function syncAmtButtons() {
        let currentVal = document.getElementById('amtInput').value;
        document.querySelectorAll('.amt-btn').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-val') == currentVal);
        });
        refreshChannelAmounts();
    }

    function updateUI() {
        document.querySelectorAll('.pay-card').forEach(c => c.classList.remove('active'));
        const checked = document.querySelector('input[name="method"]:checked');
        const label = document.getElementById('activeMethodLabel');
        if (!checked) {
            if (label) { label.innerText = 'Select Method'; }
            return;
        }
        checked.parentElement.querySelector('.pay-card').classList.add('active');
        if (label) { label.innerText = checked.value.toUpperCase(); }
    }

    function selectChannel(el) {
        document.querySelectorAll('.channel-card').forEach(c => c.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('channelInput').value = el.getAttribute('data-channel');
    }

    function refreshChannelAmounts() {
        const val = parseFloat(document.getElementById('amtInput').value || 0);
        document.querySelectorAll('.channel-amount').forEach(el => {
            el.innerText = '৳ ' + (isNaN(val) ? 0 : val).toLocaleString('en-US');
        });
    }

    function validateDepositForm() {
        const amount = parseFloat(document.getElementById('amtInput').value || 0);
        const checkedMethod = document.querySelector('input[name="method"]:checked');
        if (!checkedMethod) {
            alert('Please select a payment method.');
            return false;
        }
        if (needsChannel && !document.getElementById('channelInput').value) {
            alert('Please select a channel.');
            return false;
        }
        if (isNaN(amount) || amount < minDeposit) {
            alert('Minimum deposit amount is ৳' + minDeposit.toLocaleString('en-US'));
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
