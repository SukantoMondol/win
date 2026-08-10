<?php
mysqli_report(MYSQLI_REPORT_OFF);
session_start();
$dbPath = file_exists('includes/db.php') ? 'includes/db.php' : '../includes/db.php';
if (!file_exists($dbPath)) { http_response_code(500); exit('Database configuration missing.'); }
require $dbPath;
require_once '../includes/withdrawal_system_helper.php';
require_once '../includes/propay_gateway_helper.php';
require_once '../includes/akpay_gateway_helper.php';
require_once '../includes/lgpay_gateway_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
wcb_withdraw_ensure_schema($conn);
propay_ensure_schema($conn);
akpay_ensure_schema($conn);
lgpay_ensure_schema($conn);
@wcb_sync_pending_withdrawals($conn, $userId, 5);
$transactionSettings = propay_get_site_transaction_settings($conn);
$minWithdraw = max(1, (float)($transactionSettings['min_withdraw_amount'] ?? 100));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_wallet') {
        $result = wcb_withdraw_add_wallet(
            $conn,
            $userId,
            intval($_POST['method_id'] ?? 0),
            (string)($_POST['account_number'] ?? ''),
            (string)($_POST['withdraw_pin'] ?? '')
        );
        $_SESSION['withdraw_flash'] = array('type' => !empty($result['success']) ? 'success' : 'error', 'message' => $result['message'] ?? 'Unable to save withdrawal account.');
        header('Location: withdraw.php');
        exit;
    }

    if ($action === 'set_pin') {
        $walletId = intval($_POST['wallet_id'] ?? 0);
        $pin = trim((string)($_POST['new_pin'] ?? ''));
        $ok = false;
        if ($walletId > 0 && preg_match('/^\d{4}$/', $pin)) {
            $hash = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE player_wallets SET withdraw_pin_hash=? WHERE id=? AND user_id=?');
            $stmt->bind_param('sii', $hash, $walletId, $userId);
            $stmt->execute();
            $ok = $stmt->affected_rows > 0;
            $stmt->close();
        }
        $_SESSION['withdraw_flash'] = array('type' => $ok ? 'success' : 'error', 'message' => $ok ? 'Withdrawal PIN saved successfully.' : 'Enter a valid 4 digit PIN.');
        header('Location: withdraw.php');
        exit;
    }

    if ($action === 'submit_withdraw') {
        $result = wcb_withdraw_create_request(
            $conn,
            $userId,
            (float)($_POST['amount'] ?? 0),
            intval($_POST['selected_wallet'] ?? 0),
            (string)($_POST['withdraw_pin'] ?? ''),
            $minWithdraw
        );
        $_SESSION['withdraw_flash'] = array('type' => !empty($result['success']) ? 'success' : 'error', 'message' => $result['message'] ?? 'Unable to submit withdrawal request.');
        header('Location: withdraw.php');
        exit;
    }
}

$flash = $_SESSION['withdraw_flash'] ?? null;
unset($_SESSION['withdraw_flash']);
$methods = wcb_withdraw_methods($conn, true);
$wallets = wcb_withdraw_wallets($conn, $userId);
$eligibility = wcb_withdraw_eligibility($conn, $userId);
$processingCount = 0;
$stmtPending = $conn->prepare("SELECT COUNT(*) AS c FROM transactions_fake WHERE user_id=? AND type='withdraw' AND status IN ('pending','processing')");
if ($stmtPending) {
    $stmtPending->bind_param('i', $userId);
    $stmtPending->execute();
    $pendingResult = $stmtPending->get_result();
    if ($pendingResult && $row = $pendingResult->fetch_assoc()) { $processingCount = intval($row['c']); }
    $stmtPending->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Withdraw Money</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}html,body{margin:0;max-width:100%;overflow-x:hidden}body{font-family:'Roboto',sans-serif;background:#f2f5f6;color:#173e34;padding-bottom:100px;-webkit-tap-highlight-color:transparent}.page{width:100%;max-width:720px;margin:0 auto;min-height:100vh}.header{background:linear-gradient(135deg,#0d3d2f,#176348);color:#fff;padding:12px 14px 0;box-shadow:0 3px 12px rgba(13,61,47,.18)}.header-top{height:42px;display:flex;align-items:center;justify-content:center;position:relative}.header-top a{position:absolute;left:0;width:38px;height:38px;display:flex;align-items:center;justify-content:center;color:#fff}.tabs{display:grid;grid-template-columns:1fr 1fr;margin-top:4px}.tab{height:43px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#8df0ce;border-radius:10px 10px 0 0}.tab.active{background:#f2f5f6;color:#0d4a3a;border-top:3px solid #25d7a3}.card{background:#fff;border:1px solid #dfe8e5;border-radius:15px;box-shadow:0 5px 18px rgba(22,75,59,.05)}.balance-card{padding:18px;text-align:center;position:relative;overflow:hidden}.balance-card:before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:#0d4a3a}.balance-label{font-size:10px;text-transform:uppercase;letter-spacing:.7px;color:#81928d}.balance-value{font-size:30px;font-weight:700;color:#0d4a3a;margin-top:6px}.balance-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:#e5ece9;border-radius:10px;overflow:hidden;margin-top:16px}.balance-item{background:#f8fbfa;padding:10px 6px}.balance-item span{display:block}.balance-item .label{font-size:9px;color:#8a9994;text-transform:uppercase}.balance-item .value{font-size:12px;font-weight:600;color:#365d52;margin-top:4px}.status-card{padding:14px 15px;margin-bottom:14px;display:flex;gap:12px;align-items:flex-start}.status-icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex:0 0 38px}.status-card.available{background:#ebfaf2;border-color:#c8efd9}.status-card.available .status-icon{background:#d4f5e2;color:#199854}.status-card.locked{background:#fff5ed;border-color:#ffe0c5}.status-card.locked .status-icon{background:#ffe8d1;color:#e57824}.status-title{font-size:13px;font-weight:700}.status-text{font-size:11px;line-height:1.5;color:#6e817b;margin-top:3px}.section-label{font-size:11px;font-weight:700;color:#0d4a3a;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px}.amount-box{position:relative}.amount-box span{position:absolute;left:15px;top:50%;transform:translateY(-50%);font-size:21px;font-weight:600;color:#0d4a3a}.amount-input{width:100%;height:56px;border:1.5px solid #dbe6e2;border-radius:12px;padding:0 15px 0 42px;font-size:22px;font-weight:600;color:#143f34;background:#fff;outline:none}.amount-input:focus{border-color:#27c796;box-shadow:0 0 0 3px rgba(39,199,150,.12)}.live-message{margin-top:9px;min-height:18px;font-size:11px;font-weight:600}.live-message.ok{color:#189657}.live-message.error{color:#dc4f4f}.wallet-card{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;border:1.5px solid #dfe8e5;border-radius:12px;background:#fff;transition:.18s;position:relative}.wallet-card.selected{border-color:#25c994;background:#effcf7;box-shadow:0 4px 12px rgba(37,201,148,.1)}.wallet-radio{position:absolute;inset:0;opacity:0;cursor:pointer}.method-icon{width:43px;height:43px;border-radius:11px;background:linear-gradient(135deg,#eaf8f3,#d8f1e8);border:1px solid #cae6dc;display:flex;align-items:center;justify-content:center;color:#0d6a4e;font-size:13px;font-weight:700;flex:0 0 43px;text-transform:uppercase}.wallet-name{font-size:13px;font-weight:700;color:#0d4a3a}.wallet-number{font-size:11px;color:#74847f;margin-top:4px;word-break:break-all}.pin-badge{font-size:9px;padding:5px 7px;border-radius:7px;font-weight:700;white-space:nowrap}.pin-ready{background:#e5f8ed;color:#188c4e}.pin-missing{background:#fff0e5;color:#d76c1c}.check{width:22px;height:22px;border-radius:50%;border:2px solid #d3dfdb;display:flex;align-items:center;justify-content:center;background:#fff}.wallet-card.selected .check{border-color:#25c994}.wallet-card.selected .check:after{content:'';width:10px;height:10px;border-radius:50%;background:#25c994}.add-btn{display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 12px;border:1px solid #bde9d9;border-radius:9px;background:#eafaf4;color:#0d7454;font-size:10px;font-weight:700}.submit-btn{width:100%;height:50px;border:0;border-radius:12px;background:linear-gradient(135deg,#0e6045,#1c9a6b);color:#fff;font-size:14px;font-weight:700;box-shadow:0 6px 16px rgba(14,96,69,.2)}.submit-btn:disabled{background:#b8c5c1;box-shadow:none;cursor:not-allowed}.empty{padding:28px 15px;text-align:center;border:2px dashed #d9e3df;border-radius:13px;background:#fafcfb;color:#86948f}.flash{padding:12px 14px;border-radius:10px;font-size:12px;font-weight:600;margin-bottom:14px}.flash-success{background:#eaf8ef;color:#21864a;border:1px solid #ccebd7}.flash-error{background:#fff0f0;color:#c93c3c;border:1px solid #ffd1d1}.pending{padding:10px 13px;border-radius:10px;background:#fff8dd;border:1px solid #f2dfa0;color:#8c6d0a;font-size:11px;font-weight:600;margin-bottom:14px}.modal{position:fixed;inset:0;z-index:120;background:rgba(8,28,22,.62);display:none;align-items:center;justify-content:center;padding:18px;backdrop-filter:blur(2px)}.modal.show{display:flex}.modal-card{width:100%;max-width:390px;background:#fff;border-radius:17px;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.24)}.modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:17px}.modal-close{width:34px;height:34px;border:0;border-radius:50%;background:#eff3f2;color:#667974}.field{margin-bottom:14px}.field label{display:block;font-size:10px;font-weight:700;color:#0d4a3a;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}.field-input,.field-select{width:100%;height:48px;border:1px solid #dbe5e2;border-radius:10px;padding:0 12px;font-size:13px;color:#173e34;background:#fff;outline:none}.field-input:focus,.field-select:focus{border-color:#27c796;box-shadow:0 0 0 3px rgba(39,199,150,.11)}.modal-save{width:100%;height:47px;border:0;border-radius:10px;background:#0d5c43;color:#fff;font-size:13px;font-weight:700}.pin-input{text-align:center;font-size:25px;letter-spacing:12px;padding-left:24px}.mini-btn{position:relative;z-index:3;border:0;background:transparent;color:#d56d1e;font-size:9px;font-weight:700;text-decoration:underline}.security-note{font-size:10px;line-height:1.5;color:#81918c;background:#f7faf9;border-radius:9px;padding:9px 10px;margin-top:10px}@media(max-width:480px){.content{padding:14px!important}.balance-value{font-size:27px}.balance-grid{grid-template-columns:1fr 1fr 1fr}.wallet-card{padding:12px}.method-icon{width:40px;height:40px;flex-basis:40px}.pin-input{font-size:23px}}
    </style>
</head>
<body>
<div class="page">
    <header class="header">
        <div class="header-top">
            <a href="account.php"><i class="fas fa-chevron-left"></i></a>
            <h1 class="text-[15px] font-semibold">Withdraw</h1>
        </div>
        <div class="tabs">
            <a href="withdraw.php" class="tab active">Withdraw Money</a>
            <a href="withdraw_rec.php" class="tab">Withdrawal History</a>
        </div>
    </header>

    <main class="content p-4">
        <?php if ($flash): ?>
            <div class="flash <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>"><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($processingCount > 0): ?>
            <div class="pending"><i class="fas fa-spinner fa-spin mr-1"></i><?php echo $processingCount; ?> withdraw request<?php echo $processingCount > 1 ? 's are' : ' is'; ?> pending or processing.</div>
        <?php endif; ?>

        <section class="card balance-card mb-4">
            <div class="balance-label">Current Balance</div>
            <div class="balance-value">৳<?php echo number_format((float)$eligibility['balance'], 2); ?></div>
            <div class="balance-grid">
                <div class="balance-item"><span class="label">Bonus Balance</span><span class="value">৳<?php echo number_format((float)$eligibility['bonus_balance'], 2); ?></span></div>
                <div class="balance-item"><span class="label">Turnover Done</span><span class="value">৳<?php echo number_format((float)$eligibility['turnover_completed'], 2); ?></span></div>
                <div class="balance-item"><span class="label">Withdrawable</span><span class="value">৳<?php echo number_format((float)$eligibility['withdrawable_balance'], 2); ?></span></div>
            </div>
        </section>

        <section id="eligibilityCard" class="card status-card <?php echo $eligibility['allowed'] ? 'available' : 'locked'; ?>">
            <div class="status-icon"><i class="fas <?php echo $eligibility['allowed'] ? 'fa-check' : ($eligibility['balance_type']==='no_balance' ? 'fa-wallet' : 'fa-lock'); ?>"></i></div>
            <div>
                <div id="eligibilityTitle" class="status-title"><?php echo $eligibility['allowed'] ? 'Withdrawal Available' : ($eligibility['balance_type']==='no_balance' ? 'No Withdrawable Balance' : 'Turnover Required'); ?></div>
                <div id="eligibilityText" class="status-text">
                    <?php if ($eligibility['allowed']): ?>Winning and gaming balance is available for withdrawal.<?php elseif ($eligibility['balance_type']==='no_balance'): ?>Your current withdrawable balance is ৳0.00.<?php else: ?>This balance cannot be withdrawn until the required turnover is completed. Remaining Turnover: ৳<?php echo number_format((float)$eligibility['turnover_remaining'], 2); ?><?php endif; ?>
                </div>
            </div>
        </section>

        <form method="post" id="withdrawForm" onsubmit="return handleWithdrawSubmit(event)">
            <input type="hidden" name="action" value="submit_withdraw">
            <input type="hidden" name="withdraw_pin" id="withdrawPinValue" value="">

            <section class="card p-4 mb-4">
                <div class="section-label">Withdrawal Amount</div>
                <div class="amount-box">
                    <span>৳</span>
                    <input class="amount-input" type="number" step="0.01" min="<?php echo htmlspecialchars((string)$minWithdraw, ENT_QUOTES, 'UTF-8'); ?>" name="amount" id="amountInput" placeholder="0.00" autocomplete="off" required>
                </div>
                <div id="liveMessage" class="live-message <?php echo $eligibility['allowed'] ? 'ok' : 'error'; ?>"><?php echo $eligibility['allowed'] ? 'Enter an amount to check availability.' : 'Remaining Turnover: ৳' . number_format((float)$eligibility['turnover_remaining'], 2); ?></div>
            </section>

            <section class="card p-4 mb-4">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="section-label mb-0">Withdrawal Account</div>
                    <?php if (count($wallets) < 5 && count($methods) > 0): ?>
                        <button type="button" class="add-btn" onclick="openModal('addAccountModal')"><i class="fas fa-plus"></i>Add Account</button>
                    <?php endif; ?>
                </div>

                <div class="space-y-3">
                    <?php if (!empty($wallets)): ?>
                        <?php foreach ($wallets as $wallet): ?>
                            <?php $pinReady = !empty($wallet['withdraw_pin_hash']); $methodCode = strtoupper(substr((string)$wallet['method_code'], 0, 4)); ?>
                            <label class="wallet-card <?php echo intval($wallet['method_active']) === 1 ? '' : 'opacity-50'; ?>" data-wallet-id="<?php echo intval($wallet['id']); ?>">
                                <input class="wallet-radio" type="radio" name="selected_wallet" value="<?php echo intval($wallet['id']); ?>" <?php echo intval($wallet['method_active']) === 1 && $pinReady ? '' : 'disabled'; ?> onchange="selectWallet(this)">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="method-icon"><?php echo htmlspecialchars($methodCode, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="min-w-0">
                                        <div class="wallet-name"><?php echo htmlspecialchars($wallet['method_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="wallet-number"><?php echo htmlspecialchars($wallet['wallet_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <?php if ($pinReady): ?>
                                        <span class="pin-badge pin-ready">PIN Ready</span>
                                    <?php else: ?>
                                        <button type="button" class="mini-btn" onclick="event.preventDefault();event.stopPropagation();openPinSetup(<?php echo intval($wallet['id']); ?>)">Set PIN</button>
                                    <?php endif; ?>
                                    <div class="check"></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty"><i class="fas fa-wallet text-3xl text-slate-300 mb-2"></i><div class="text-[12px] font-semibold">No withdrawal account added</div><div class="text-[10px] mt-1">Add an account and create a 4 digit withdrawal PIN.</div></div>
                    <?php endif; ?>
                </div>
            </section>

            <button type="submit" id="submitBtn" class="submit-btn" <?php echo !$eligibility['allowed'] || empty($wallets) ? 'disabled' : ''; ?>>Confirm Withdrawal</button>
        </form>
    </main>
</div>

<div id="addAccountModal" class="modal">
    <div class="modal-card">
        <div class="modal-head">
            <h3 class="text-[16px] font-semibold text-[#0d4a3a]">Add Withdrawal Account</h3>
            <button type="button" class="modal-close" onclick="closeModal('addAccountModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="add_wallet">
            <div class="field">
                <label>Payment Method</label>
                <select class="field-select" name="method_id" required>
                    <option value="">Select method</option>
                    <?php foreach ($methods as $method): ?>
                        <option value="<?php echo intval($method['id']); ?>"><?php echo htmlspecialchars($method['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label>Account Number</label>
                <input class="field-input" type="text" name="account_number" maxlength="120" autocomplete="off" required placeholder="Enter number or wallet address">
            </div>
            <div class="field">
                <label>Withdrawal PIN</label>
                <input class="field-input pin-input" type="password" name="withdraw_pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password" required placeholder="••••">
                <div class="security-note">Create a private 4 digit PIN. This PIN will be required for every withdrawal request.</div>
            </div>
            <button class="modal-save" type="submit">Save Account</button>
        </form>
    </div>
</div>

<div id="pinSetupModal" class="modal">
    <div class="modal-card">
        <div class="modal-head">
            <h3 class="text-[16px] font-semibold text-[#0d4a3a]">Set Withdrawal PIN</h3>
            <button type="button" class="modal-close" onclick="closeModal('pinSetupModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="set_pin">
            <input type="hidden" name="wallet_id" id="pinWalletId" value="">
            <div class="field">
                <label>New 4 Digit PIN</label>
                <input class="field-input pin-input" type="password" name="new_pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password" required placeholder="••••">
            </div>
            <button class="modal-save" type="submit">Save PIN</button>
        </form>
    </div>
</div>

<div id="confirmPinModal" class="modal">
    <div class="modal-card">
        <div class="modal-head">
            <h3 class="text-[16px] font-semibold text-[#0d4a3a]">Enter Withdrawal PIN</h3>
            <button type="button" class="modal-close" onclick="closeModal('confirmPinModal')"><i class="fas fa-times"></i></button>
        </div>
        <div class="field">
            <label>4 Digit PIN</label>
            <input class="field-input pin-input" type="password" id="confirmPinInput" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="off" placeholder="••••">
        </div>
        <div id="pinError" class="text-[11px] text-red-500 font-semibold mb-3 hidden">Enter your 4 digit withdrawal PIN.</div>
        <button class="modal-save" id="pinSubmitButton" type="button" onclick="confirmWithdrawalPin()">Confirm & Send</button>
    </div>
</div>

<?php include 'bottom_nav.php'; ?>
<script>
const userBalance=<?php echo json_encode((float)$eligibility['balance']); ?>;
const turnoverRemaining=<?php echo json_encode((float)$eligibility['turnover_remaining']); ?>;
const minWithdraw=<?php echo json_encode((float)$minWithdraw); ?>;
let pinConfirmed=false;
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}
function openPinSetup(walletId){document.getElementById('pinWalletId').value=walletId;openModal('pinSetupModal')}
function selectWallet(input){document.querySelectorAll('.wallet-card').forEach(function(card){card.classList.remove('selected')});input.closest('.wallet-card').classList.add('selected')}
function amountStatus(){
    const amount=parseFloat(document.getElementById('amountInput').value||0);
    const message=document.getElementById('liveMessage');
    const button=document.getElementById('submitBtn');
    if(turnoverRemaining>0.01){message.className='live-message error';message.textContent='Withdrawal blocked. Remaining Turnover: ৳'+turnoverRemaining.toFixed(2);button.disabled=true;return false}
    if(!amount){message.className='live-message ok';message.textContent='Withdrawal Available. Enter an amount.';button.disabled=false;return false}
    if(amount<minWithdraw){message.className='live-message error';message.textContent='Minimum withdrawal amount is ৳'+minWithdraw.toFixed(2);button.disabled=true;return false}
    if(amount>userBalance){message.className='live-message error';message.textContent='Insufficient withdrawable balance.';button.disabled=true;return false}
    message.className='live-message ok';message.textContent='Withdrawal Available: ৳'+amount.toFixed(2);button.disabled=false;return true
}
function handleWithdrawSubmit(event){
    event.preventDefault();
    if(pinConfirmed){return true}
    if(!amountStatus()){return false}
    const wallet=document.querySelector('input[name="selected_wallet"]:checked');
    if(!wallet){document.getElementById('liveMessage').className='live-message error';document.getElementById('liveMessage').textContent='Select a withdrawal account.';return false}
    document.getElementById('confirmPinInput').value='';
    document.getElementById('pinError').classList.add('hidden');
    openModal('confirmPinModal');
    setTimeout(function(){document.getElementById('confirmPinInput').focus()},100);
    return false
}
function confirmWithdrawalPin(){
    const pin=document.getElementById('confirmPinInput').value.trim();
    if(!/^\d{4}$/.test(pin)){document.getElementById('pinError').classList.remove('hidden');return}
    document.getElementById('withdrawPinValue').value=pin;
    pinConfirmed=true;
    const pinButton=document.getElementById('pinSubmitButton');
    const submitButton=document.getElementById('submitBtn');
    pinButton.disabled=true;
    pinButton.textContent='Submitting...';
    submitButton.disabled=true;
    submitButton.textContent='Submitting...';
    document.getElementById('withdrawForm').submit()
}
document.getElementById('amountInput').addEventListener('input',amountStatus);
document.querySelectorAll('.modal').forEach(function(modal){modal.addEventListener('click',function(event){if(event.target===modal){modal.classList.remove('show')}})});
document.querySelectorAll('input[inputmode="numeric"]').forEach(function(input){input.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,4)})});
</script>
</body>
</html>
