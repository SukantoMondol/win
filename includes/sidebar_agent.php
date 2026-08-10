<div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden md:hidden glass-effect"></div>

<div id="agentSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-[#002b2b] border-r border-gray-700 transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:translate-x-0 flex flex-col h-full shadow-2xl">
    
    <div class="flex justify-between items-center p-4 border-b border-gray-600 md:hidden">
        <span class="text-xl font-bold text-yellow-500">মেনু</span>
        <button onclick="toggleSidebar()" class="text-red-500 hover:text-white transition">
            <i class="fas fa-times text-2xl"></i>
        </button>
    </div>

    <div class="hidden md:flex items-center justify-center h-20 border-b border-gray-700 bg-[#001f1f]">
        <h2 class="text-2xl font-bold text-yellow-500 tracking-wider">AGENT PANEL</h2>
    </div>

    <nav class="flex-1 overflow-y-auto py-4">
        <ul class="space-y-2 px-4">
            <li>
                <a href="dashboard.php" class="flex items-center p-3 text-white rounded-lg hover:bg-yellow-600 hover:text-black transition group">
                    <i class="fas fa-tachometer-alt w-6 group-hover:animate-pulse"></i>
                    <span class="font-medium">ড্যাশবোর্ড</span>
                </a>
            </li>
            <li>
                <a href="add_player.php" class="flex items-center p-3 text-white rounded-lg hover:bg-yellow-600 hover:text-black transition group">
                    <i class="fas fa-user-plus w-6"></i>
                    <span class="font-medium">নতুন প্লেয়ার</span>
                </a>
            </li>
            <li>
                <a href="players.php" class="flex items-center p-3 text-white rounded-lg hover:bg-yellow-600 hover:text-black transition group">
                    <i class="fas fa-users w-6"></i>
                    <span class="font-medium">প্লেয়ার লিস্ট</span>
                </a>
            </li>
            <li>
                <a href="transfer.php" class="flex items-center p-3 text-white rounded-lg hover:bg-yellow-600 hover:text-black transition group">
                    <i class="fas fa-exchange-alt w-6"></i>
                    <span class="font-medium">মানি ট্রান্সফার</span>
                </a>
            </li>
            <li>
                <a href="requests.php" class="flex items-center p-3 text-white rounded-lg hover:bg-yellow-600 hover:text-black transition group">
                    <i class="fas fa-inbox w-6"></i>
                    <span class="font-medium">রিকোয়েস্ট</span>
                </a>
            </li>
            <li>
                <a href="withdraw_money.php" class="flex items-center p-3 text-white rounded-lg hover:bg-yellow-600 hover:text-black transition group">
                    <i class="fas fa-hand-holding-usd w-6"></i>
                    <span class="font-medium">আমার উইথড্র</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="flex items-center p-3 text-white rounded-lg hover:bg-yellow-600 hover:text-black transition group">
                    <i class="fas fa-cog w-6"></i>
                    <span class="font-medium">সেটিংস</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="p-4 border-t border-gray-700 bg-[#001f1f]">
        <a href="logout.php" class="flex items-center justify-center p-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition w-full font-bold">
            <i class="fas fa-sign-out-alt mr-2"></i> লগআউট
        </a>
    </div>
</div>