<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';
require_once '../includes/propay_gateway_helper.php';
require_once '../includes/lgpay_gateway_helper.php';
require_once '../includes/nekpay_gateway_helper.php';

if (function_exists('propay_ensure_schema')) { @propay_ensure_schema($conn); }
lgpay_ensure_schema($conn);
nekpay_ensure_schema($conn);

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_gateway_settings'])) {
    $lgpay_merchant = sanitize($conn, $_POST['lgpay_merchant_code'] ?? '');
    $lgpay_secret = htmlspecialchars(strip_tags(trim($_POST['lgpay_secret_code'] ?? '')), ENT_QUOTES, 'UTF-8');
    $lgpay_api_base = htmlspecialchars(strip_tags(trim($_POST['lgpay_api_base_url'] ?? lgpay_default_api_base_url())), ENT_QUOTES, 'UTF-8');
    $lgpay_enabled = isset($_POST['lgpay_is_enabled']) ? 1 : 0;

    $nekpay_merchant = sanitize($conn, $_POST['nekpay_merchant_code'] ?? '');
    $nekpay_secret = htmlspecialchars(strip_tags(trim($_POST['nekpay_secret_code'] ?? '')), ENT_QUOTES, 'UTF-8');
    $nekpay_api_base = htmlspecialchars(strip_tags(trim($_POST['nekpay_api_base_url'] ?? nekpay_default_api_base_url())), ENT_QUOTES, 'UTF-8');
    $nekpay_enabled = isset($_POST['nekpay_is_enabled']) ? 1 : 0;

    $okLg = lgpay_save_settings($conn, $lgpay_merchant, $lgpay_secret, $lgpay_api_base, $lgpay_enabled);
    $okNek = nekpay_save_settings($conn, $nekpay_merchant, $nekpay_secret, $nekpay_api_base, $nekpay_enabled);

    if ($okLg && $okNek) {
        $msg = 'Payment Gateway settings updated successfully!';
        $msg_type = 'success';
    } else {
        error_log('Gateway settings save failed: ' . $conn->error);
        $msg = 'Unable to save gateway settings right now.';
        $msg_type = 'error';
    }
}

$lgpay = lgpay_get_settings($conn);
$nekpay = nekpay_get_settings($conn);
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
            <p class="text-sm text-gray-500 font-bold mt-1">Configure and manage active payment gateways (LG Pay & NEKpay).</p>
        </div>

        <?php if($msg): ?>
            <div class="mb-6 p-4 rounded-xl border-l-4 bg-white shadow-sm <?php echo $msg_type === 'success' ? 'border-green-500 text-green-700' : 'border-red-500 text-red-700'; ?>">
                <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <span class="font-bold text-sm"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <div class="max-w-5xl space-y-6">
            <form method="POST" class="space-y-6">

                <!-- NEKpay Gateway Section -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="font-black text-lg text-gray-800 flex items-center gap-2">
                            <i class="fas fa-credit-card text-indigo-600"></i> NEKpay Gateway
                        </h2>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="nekpay_is_enabled" value="1" class="sr-only peer" <?php echo !empty($nekpay['is_enabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span class="ml-2 text-xs font-black uppercase text-gray-700">Enable NEKpay</span>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Merchant ID (mch_id)</label>
                            <input type="text" name="nekpay_merchant_code" value="<?php echo htmlspecialchars($nekpay['merchant_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter NEKpay Merchant ID" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Payment Key / Secret Key</label>
                            <input type="text" name="nekpay_secret_code" value="<?php echo htmlspecialchars($nekpay['secret_code'] ?? '7c1adc2ec9f04bc0a00a1c0fd88eee00', ENT_QUOTES, 'UTF-8'); ?>" placeholder="7c1adc2ec9f04bc0a00a1c0fd88eee00" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">API Base URL</label>
                            <input type="text" name="nekpay_api_base_url" value="<?php echo htmlspecialchars($nekpay['api_base_url'] ?? nekpay_default_api_base_url(), ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            <p class="mt-1 text-[11px] text-gray-400">Default: https://api.nekpayment.com (Deposit: /pay/web | Payout: /pay/transfer)</p>
                        </div>
                        <div class="md:col-span-2 rounded-xl border bg-gray-50 px-4 py-3">
                            <div class="text-[11px] font-black uppercase tracking-widest text-gray-400">NEKpay Callback Info & Whitelist</div>
                            <div class="mt-1 text-xs font-bold text-gray-700 break-all">Deposit Callback URL: <span class="text-indigo-600"><?php echo htmlspecialchars(nekpay_url('/api/nekpay_callback.php'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="mt-1 text-xs font-bold text-gray-700 break-all">Payout Callback URL: <span class="text-indigo-600"><?php echo htmlspecialchars(nekpay_url('/api/nekpay_payout_callback.php'), ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <div class="mt-1 text-xs font-bold text-gray-700">NEKpay Callback IP: <span class="text-emerald-700">18.138.7.232</span></div>
                        </div>
                    </div>
                </div>

                <!-- LG Pay Gateway Section -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="font-black text-lg text-gray-800 flex items-center gap-2"><i class="fas fa-bolt text-emerald-600"></i> LG Pay Gateway</h2>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="lgpay_is_enabled" value="1" class="sr-only peer" <?php echo !empty($lgpay['is_enabled']) ? 'checked' : ''; ?>>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            <span class="ml-2 text-xs font-black uppercase text-gray-700">Enable LG Pay</span>
                        </label>
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
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_gateway_settings" class="bg-[#126E51] hover:bg-[#0d4a3a] text-white px-8 py-4 rounded-xl font-black shadow-md text-base">
                    <i class="fas fa-save mr-2"></i> Save Gateway Settings
                </button>
            </form>
        </div>
    </main>
</body>
</html>
