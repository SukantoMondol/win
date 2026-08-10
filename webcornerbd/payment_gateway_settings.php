<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/propay_gateway_helper.php';
require_once '../includes/lgpay_gateway_helper.php';

if (function_exists('propay_ensure_schema')) { @propay_ensure_schema($conn); }
lgpay_ensure_schema($conn);
if (function_exists('wcb_force_lgpay_only')) { @wcb_force_lgpay_only($conn); }

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateway_settings'])) {
    $lgpay_merchant = sanitize($conn, $_POST['lgpay_merchant_code'] ?? '');
    $lgpay_secret = htmlspecialchars(strip_tags(trim($_POST['lgpay_secret_code'] ?? '')), ENT_QUOTES, 'UTF-8');
    $lgpay_api_base = htmlspecialchars(strip_tags(trim($_POST['lgpay_api_base_url'] ?? lgpay_default_api_base_url())), ENT_QUOTES, 'UTF-8');

    // LG Pay must remain enabled and default. Old gateways remain disabled at DB/backend level.
    $okLg = lgpay_save_settings($conn, $lgpay_merchant, $lgpay_secret, $lgpay_api_base, 1);
    if (function_exists('wcb_force_lgpay_only')) { $okForce = wcb_force_lgpay_only($conn); } else { $okForce = true; }

    if ($okLg && $okForce) {
        $msg = 'LG Pay settings saved successfully. LG Pay is now the only active/default gateway.';
        $msg_type = 'success';
    } else {
        error_log('LG Pay settings save failed: ' . $conn->error);
        $msg = 'Unable to save the LG Pay settings right now.';
        $msg_type = 'error';
    }
}

$lgpay = lgpay_get_settings($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen">
        <div class="mb-6">
            <h1 class="text-2xl font-black text-gray-800">Payment Gateway Settings</h1>
            <p class="text-sm text-gray-500 font-bold mt-1">Only LG Pay is active. ProPay, AKPay and other old gateways are disabled.</p>
        </div>

        <?php if($msg): ?>
            <div class="mb-6 p-4 rounded-xl border-l-4 bg-white shadow-sm <?php echo $msg_type === 'success' ? 'border-green-500 text-green-700' : 'border-red-500 text-red-700'; ?>">
                <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <span class="font-bold text-sm"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <div class="max-w-5xl space-y-6">
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-4 font-bold text-sm">
                <i class="fas fa-check-circle mr-2"></i>
            </div>

            <form method="POST" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="font-black text-lg text-gray-800 flex items-center gap-2"><i class="fas fa-bolt text-emerald-600"></i> LG Pay Gateway</h2>
                        <span class="inline-flex items-center gap-2 text-xs font-black uppercase text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-2 rounded-full">
                            <i class="fas fa-circle text-[8px]"></i> Default & Active
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Merchant / App ID</label>
                            <input type="text" name="lgpay_merchant_code" value="<?php echo htmlspecialchars($lgpay['merchant_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Secret Key</label>
                            <input type="text" name="lgpay_secret_code" value="<?php echo htmlspecialchars($lgpay['secret_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">API Base URL</label>
                            <input type="text" name="lgpay_api_base_url" value="<?php echo htmlspecialchars($lgpay['api_base_url'] ?? lgpay_default_api_base_url(), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-emerald-400">
                            <p class="mt-1 text-[11px] text-gray-400">Default: https://www.lg-pay.com/api</p>
                        </div>
                        <div class="md:col-span-2 rounded-xl border bg-gray-50 px-4 py-3">
                            <div class="text-[11px] font-black uppercase tracking-widest text-gray-400">Callback URLs</div>
                            <div class="mt-1 text-xs font-bold text-gray-700 break-all">Pay-In: <?php echo htmlspecialchars(lgpay_url('/api/lgpay_callback.php'), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="mt-1 text-xs font-bold text-gray-700 break-all">Pay-Out: <?php echo htmlspecialchars(lgpay_url('/api/lgpay_payout_callback.php'), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="md:col-span-2 rounded-xl border bg-gray-50 px-4 py-3">
                            <div class="text-[11px] font-black uppercase tracking-widest text-gray-400">Gateway Status</div>
                            <div class="mt-1 text-sm font-bold text-gray-700">Active Provider: LG Pay</div>
                            <?php if (!empty($lgpay['last_error'])): ?><div class="mt-1 text-xs text-gray-500 break-words"><?php echo htmlspecialchars($lgpay['last_error'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_gateway_settings" class="bg-[#126E51] hover:bg-[#0d4a3a] text-white px-6 py-3 rounded-xl font-black shadow-md">
                    <i class="fas fa-save mr-2"></i> Save LG Pay Settings
                </button>
            </form>
        </div>
    </main>
</body>
</html>
