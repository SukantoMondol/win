<?php
$current_role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

$admin_brand_name = 'Admin Panel';
$admin_logo_path = '';
if (!isset($conn) && file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
}
if (isset($conn) && !$conn->connect_error) {
    $brand_q = @$conn->query("SELECT * FROM settings WHERE id=1");
    if ($brand_q && $brand_q->num_rows > 0) {
        $brand_settings = $brand_q->fetch_assoc();
        $admin_brand_name = !empty($brand_settings['admin_panel_name']) ? $brand_settings['admin_panel_name'] : (!empty($brand_settings['site_name']) ? $brand_settings['site_name'] : $admin_brand_name);
        $admin_logo_path = $brand_settings['app_logo'] ?? '';
    }
}
$admin_brand_safe = htmlspecialchars($admin_brand_name, ENT_QUOTES, 'UTF-8');
$admin_logo_safe = htmlspecialchars($admin_logo_path, ENT_QUOTES, 'UTF-8');
$admin_logo_version = '';
if (!empty($admin_logo_path)) {
    $admin_logo_file = dirname(__DIR__) . '/' . ltrim($admin_logo_path, '/');
    $admin_logo_version = is_file($admin_logo_file) ? (string)filemtime($admin_logo_file) : '1';
}
if (!function_exists('get_admin_logo_src')) {
    function get_admin_logo_src($path) {
        $path = trim((string)$path);
        if ($path === '') return '';
        if (strpos($path, 'assets/img/logo/data:') === 0) { $path = substr($path, 16); }
        if (strpos($path, 'data:') === 0 || strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) { return $path; }
        if (strpos($path, '/') === 0) { return $path; }
        return '../' . ltrim($path, '/');
    }
}
$admin_logo_clean_src = get_admin_logo_src($admin_logo_path);
?>

<style>
    details.admin-profile-dropdown > summary { list-style: none; }
    details.admin-profile-dropdown > summary::-webkit-details-marker { display: none; }
</style>

<div class="md:hidden fixed top-0 left-0 w-full h-16 bg-[#126E51] border-b border-[#264d3e] flex items-center justify-between px-4 z-40 shadow-md">
    <div class="flex items-center min-w-0">
        <button id="mobile-menu-open" class="text-gray-300 hover:text-white focus:outline-none p-2">
            <i class="fas fa-bars text-2xl"></i>
        </button>
        <span class="ml-3 text-lg font-bold tracking-wider font-sans flex items-center gap-2 min-w-0">
            <?php if(!empty($admin_logo_clean_src)): ?>
                <img src="<?php echo htmlspecialchars($admin_logo_clean_src); ?>" class="h-8 w-8 rounded object-contain bg-white/10 p-1" alt="Logo" decoding="async">
            <?php endif; ?>
            <span class="truncate"><span class="text-white"><?php echo $admin_brand_safe; ?></span><span class="text-red-500 text-xs">.<?php echo strtoupper($current_role); ?></span></span>
        </span>
    </div>
    <?php if($current_role === 'admin'): ?>
    <details class="admin-profile-dropdown relative shrink-0">
        <summary aria-label="Open admin profile menu" title="Admin Menu" class="list-none cursor-pointer h-9 w-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition focus:outline-none focus:ring-2 focus:ring-white/50">
            <i class="fas fa-user-shield"></i>
        </summary>
        <div class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-[60] text-left">
            <a href="admin_profile.php#credentials" class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-gray-700 hover:bg-indigo-50 hover:text-indigo-700 transition">
                <i class="fas fa-key w-4 text-center"></i>
                <span>Change Password</span>
            </a>
            <form method="POST" action="logout.php" class="border-t border-gray-100">
                <button type="submit" class="w-full flex items-center gap-3 px-4 py-3 text-sm font-bold text-red-600 hover:bg-red-50 transition text-left">
                    <i class="fas fa-sign-out-alt w-4 text-center"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </details>
    <?php endif; ?>
</div>

<div id="sidebar-overlay" onclick="closeSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

<aside id="sidebar" class="w-64 bg-[#126E51] text-[#ffffff] flex flex-col h-screen fixed left-0 top-0 border-r border-[#264d3e] font-sans z-50 transition-transform duration-300 ease-in-out transform -translate-x-full md:translate-x-0">
    
    <div class="h-16 flex items-center justify-between px-6 border-b border-[#264d3e] bg-[#126E51] shadow-md shrink-0">
        <h1 class="text-xl font-bold tracking-wider font-sans flex items-center gap-2 min-w-0">
            <?php if(!empty($admin_logo_clean_src)): ?>
                <img src="<?php echo htmlspecialchars($admin_logo_clean_src); ?>" class="h-9 w-9 rounded object-contain bg-white/10 p-1 shrink-0" alt="Logo" decoding="async">
            <?php endif; ?>
            <span class="truncate"><span class="text-white"><?php echo $admin_brand_safe; ?></span><span class="text-red-600">.<?php echo strtoupper($current_role); ?></span></span>
        </h1>
        
        <button id="mobile-menu-close" onclick="closeSidebar()" class="md:hidden text-gray-400 hover:text-red-600 focus:outline-none transition-colors">
            <i class="fas fa-times text-2xl"></i>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 scrollbar-hide">
        
        <?php if($current_role === 'admin'): ?>
        
        <p class="px-6 text-[10px] font-bold uppercase tracking-widest text-[#55826f] mb-2 mt-2">Main</p>
        
        <a href="dashboard.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'dashboard.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">📊</span> <span class="text-sm font-medium">Dashboard</span>
        </a>
        <a href="users_all.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'users_all.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">👥</span> <span class="text-sm font-medium">User Management</span>
        </a>
        <a href="agents.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'agents.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🕴️</span> <span class="text-sm font-medium">Agents & Comm.</span>
        </a>

        <p class="px-6 text-[10px] font-bold uppercase tracking-widest text-[#55826f] mt-6 mb-2">Operations</p>
        
        <a href="payment_setup.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'payment_setup.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">💳</span> <span class="text-sm font-medium">MFS Accounts</span>
        </a>
        <details class="group" <?php echo ($current_page == 'withdrawal_methods.php' || ($current_page == 'finance.php' && ($_GET['type'] ?? '') == 'withdraw')) ? 'open' : ''; ?>>
            <summary class="flex items-center justify-between px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition cursor-pointer select-none <?php echo ($current_page == 'withdrawal_methods.php' || ($current_page == 'finance.php' && ($_GET['type'] ?? '') == 'withdraw')) ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
                <div class="flex items-center">
                    <span class="mr-3 w-5 text-center">🏦</span> <span class="text-sm font-medium">Withdrawal System</span>
                </div>
                <i class="fas fa-chevron-down text-xs transition-transform group-open:rotate-180 text-gray-300"></i>
            </summary>
            <div class="pl-12 py-1 space-y-1 bg-[#0e5740]">
                <a href="finance.php?type=withdraw&status=pending&period=lifetime" class="flex items-center justify-between py-2 px-3 text-xs font-bold rounded text-[#dbece3] hover:text-white hover:bg-[#1a4735] transition <?php echo ($current_page == 'finance.php' && ($_GET['status'] ?? '') == 'pending' && ($_GET['type'] ?? '') == 'withdraw') ? 'text-white bg-[#1a4735] font-extrabold' : ''; ?>">
                    <span>Pending Withdrawals</span>
                    <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full font-black animate-pulse">Pending</span>
                </a>
                <a href="withdrawal_methods.php" class="block py-2 px-3 text-xs font-bold rounded text-[#dbece3] hover:text-white hover:bg-[#1a4735] transition <?php echo $current_page == 'withdrawal_methods.php' ? 'text-white bg-[#1a4735] font-extrabold' : ''; ?>">
                    Withdrawal Methods
                </a>
            </div>
        </details>
        <a href="payment_gateway_settings.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'payment_gateway_settings.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🔐</span> <span class="text-sm font-medium">Payment Gateway</span>
        </a>
        <a href="deposit_transaction_settings.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'deposit_transaction_settings.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">⚙️</span> <span class="text-sm font-medium">Deposit & Transaction</span>
        </a>
        <a href="promotions.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'promotions.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🖼️</span> <span class="text-sm font-medium">Promotion Banners</span>
        </a>
        <a href="kyc_queue.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'kyc_queue.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🆔</span> <span class="text-sm font-medium">KYC Verification</span>
        </a>
        
        <a href="manage_display.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'manage_display.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🎮</span> <span class="text-sm font-medium">Game Manage</span>
        </a>
        
        <a href="ggr-panel.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'ggr-panel.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">📶</span> <span class="text-sm font-medium">GGR Panel</span>
        </a>
        
        <a href="daily_bonus_settings.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'daily_bonus_settings.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🎁</span> <span class="text-sm font-medium">Daily Bonus Settings</span>
        </a>
        <a href="deposit_bonus_settings.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'deposit_bonus_settings.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">✅</span> <span class="text-sm font-medium">Deposit Bonus Settings</span>
        </a>
        <a href="vip_settings.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'vip_settings.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">👑</span> <span class="text-sm font-medium">VIP Settings</span>
        </a>
        <a href="referral_management.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'referral_management.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🤝</span> <span class="text-sm font-medium">Referral Management</span>
        </a>
        <?php endif; ?>

        <p class="px-6 text-[10px] font-bold uppercase tracking-widest text-[#55826f] mt-6 mb-2">Support</p>

        <a href="support.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'support.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🎧</span> <span class="text-sm font-medium">Live Support</span>
            <?php if($current_role === 'admin'): ?>
            <span class="ml-auto bg-blue-600 text-white text-[10px] px-1.5 py-0.5 rounded-full">New</span>
            <?php endif; ?>
        </a>

        <?php if($current_role === 'admin'): ?>
        
        <p class="px-6 text-[10px] font-bold uppercase tracking-widest text-[#55826f] mt-6 mb-2">Control Center</p>
        
        <a href="providers.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'providers.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🎮</span> <span class="text-sm font-medium">Providers & API</span>
        </a>
        <a href="game_api_key.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'game_api_key.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🔑</span> <span class="text-sm font-medium">Game API Key</span>
        </a>
        <a href="fetch_jili_games.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'fetch_jili_games.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🔄</span> <span class="text-sm font-medium">Sync JILI Games</span>
        </a>
        <a href="risk_control.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'risk_control.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">🛡️</span> <span class="text-sm font-medium">Risk & Security</span>
        </a>

        <p class="px-6 text-[10px] font-bold uppercase tracking-widest text-[#55826f] mt-6 mb-2">Finance & Logs</p>
        
        <a href="finance.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'finance.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">💰</span> <span class="text-sm font-medium">Transactions</span>
        </a>
        <a href="system_logs.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'system_logs.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">📜</span> <span class="text-sm font-medium">Audit Logs</span>
        </a>

        <p class="px-6 text-[10px] font-bold uppercase tracking-widest text-[#55826f] mt-6 mb-2">System</p>
        
        <a href="settings.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'settings.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">⚙️</span> <span class="text-sm font-medium">Settings</span>
        </a>
        <a href="pwa_settings.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'pwa_settings.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">📲</span> <span class="text-sm font-medium">PWA App</span>
        </a>
        <a href="admin_profile.php" class="flex items-center px-6 py-3 text-[#dbece3] hover:bg-[#1f4234] hover:text-white transition <?php echo $current_page == 'admin_profile.php' ? 'bg-[#1f4234] text-white border-l-4 border-[#ffdf1b]' : ''; ?>">
            <span class="mr-3 w-5 text-center">👤</span> <span class="text-sm font-medium">My Profile</span>
        </a>
        
        <?php endif; ?>

        <div class="mt-8 mb-4 px-6">
            <form method="POST" action="logout.php">
                <button type="submit" class="flex items-center justify-center w-full bg-red-900/20 hover:bg-red-900/40 text-red-500 py-3 rounded-lg transition border border-red-900/30 group">
                    <span class="mr-2 group-hover:scale-110 transition-transform">🚪</span> <span class="font-bold text-sm">Logout</span>
                </button>
            </form>
        </div>
    </nav>
</aside>

<script>
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openBtn = document.getElementById('mobile-menu-open');

    if(openBtn) {
        openBtn.addEventListener('click', () => {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        });
    }

    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }

    // Close profile menus when clicking elsewhere or pressing Escape.
    document.addEventListener('click', function(event) {
        document.querySelectorAll('details.admin-profile-dropdown[open]').forEach(function(menu) {
            if (!menu.contains(event.target)) {
                menu.removeAttribute('open');
            }
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('details.admin-profile-dropdown[open]').forEach(function(menu) {
                menu.removeAttribute('open');
            });
        }
    });

    // Keep Back/Forward navigation instant. Server-side auth is still enforced on every real request.
</script>
