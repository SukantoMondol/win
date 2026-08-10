<style>
    /* Footer & General Styles */
    .footer-section {
        font-family: 'Hind Siliguri', sans-serif;
        background-color: #000000;
        color: #9ca3af;
        font-size: 11px;
    }
    
    /* Winner Row Styles */
    .winner-row {
        background: #111;
        border-bottom: 1px solid #222;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .play-btn-purple {
        background: linear-gradient(135deg, #9333EA 0%, #7E22CE 100%);
        color: white;
        padding: 5px 15px;
        font-size: 11px;
        border-radius: 4px;
        font-weight: bold;
        box-shadow: 0 2px 5px rgba(126, 34, 206, 0.4);
    }

    /* Single Image Styles (Vendor/Payment/License) */
    .footer-img-block {
        width: 100%;
        height: auto;
        object-fit: contain;
        border-radius: 6px;
        opacity: 0.9;
        display: block;
    }

    /* Bottom Nav Styles */
    .bottom-nav-fixed {
        background: linear-gradient(180deg, #1f1f1f 0%, #000000 100%);
        border-top: 1px solid #333;
        box-shadow: 0 -5px 20px rgba(0,0,0,0.5);
    }
    .nav-icon { font-size: 20px; margin-bottom: 2px; }
    .nav-text { font-size: 10px; }
    .nav-center-wrap {
        position: relative;
        width: 100%;
        display: flex;
        justify-content: center;
    }
    .nav-center-btn {
        width: 55px; height: 55px;
        background: linear-gradient(135deg, #FDE047 0%, #F59E0B 100%);
        border-radius: 50%;
        border: 4px solid #0d0d0d;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        margin-top: -25px;
        box-shadow: 0 0 10px rgba(245, 158, 11, 0.4);
        z-index: 50;
        color: black;
    }
</style>

<div class="bg-black border-t border-gray-800 mt-2 pb-4">
    <div class="bg-[#111] rounded border border-white/5 p-2 mb-4 mx-4 mt-4">
        <div class="flex justify-between items-center mb-2 border-b border-white/5 pb-1">
            <span class="text-[10px] text-yellow-400 font-bold"><i class="fas fa-trophy mr-1"></i> সর্বশেষ বিজয়ী</span>
        </div>
        <div class="space-y-2">
            <div class="flex justify-between text-[10px] text-gray-400">
                <span><i class="fas fa-user-circle"></i> 017***89</span>
                <span class="text-green-400">৳5,000</span>
                <span class="text-xs">Aviator</span>
            </div>
            <div class="flex justify-between text-[10px] text-gray-400">
                <span><i class="fas fa-user-circle"></i> 019***22</span>
                <span class="text-green-400">৳12,500</span>
                <span class="text-xs">Crazy Time</span>
            </div>
        </div>
    </div>
</div>

<footer class="footer-section pb-24 px-4">
    
    <div class="mb-6">
        <p class="text-[10px] text-gray-500 mb-2 uppercase tracking-widest text-center">গেম প্রোভাইডার</p>
        <img src="../assets/img/vendor.png" class="footer-img-block" alt="Game Providers">
    </div>

    <div class="mb-6">
        <p class="text-[10px] text-gray-500 mb-2 uppercase tracking-widest text-center">পেমেন্ট মেথড</p>
        <img src="../assets/img/payment.png" class="footer-img-block" alt="Payment Methods">
    </div>

    <div class="text-center border-t border-white/10 pt-4">
        <img src="../assets/img/license.png" class="h-8 mx-auto mb-2 opacity-50 grayscale" alt="Gaming License">
        <p class="text-[9px] text-gray-600">Copyright © 2026 VIPTAKA.</p>
        <p class="text-[9px] text-gray-600">All rights reserved.</p>
    </div>
</footer>

<div class="fixed bottom-0 left-0 w-full h-[70px] bottom-nav-fixed z-50 flex justify-between px-2 items-end pb-2">
    <a href="index.php" class="w-1/5 flex flex-col items-center justify-center pb-1 group">
        <div class="group-hover:text-red-500 transition"><i class="fas fa-home text-xl mb-1 text-red-500"></i></div>
        <span class="text-[10px] text-white font-bold">হোম</span>
    </a>
    
    <a href="promotions.php" class="w-1/5 flex flex-col items-center justify-center pb-1 opacity-70 hover:opacity-100 transition">
        <i class="fas fa-gift text-xl mb-1 text-red-500"></i>
        <span class="text-[10px] text-gray-300">প্রমোশন</span>
    </a>
    
    <div class="w-1/5 flex justify-center relative">
        <a href="share.php" class="nav-center-btn">
            <i class="fas fa-share-alt text-xl mb-0.5"></i>
            <i class="fas fa-coins text-[8px]"></i>
        </a>
        <span class="absolute bottom-1 text-[10px] text-[#FDE047] font-bold">শেয়ার</span>
    </div>
    
    <a href="rewards.php" class="w-1/5 flex flex-col items-center justify-center pb-1 opacity-70 hover:opacity-100 transition">
        <i class="fas fa-trophy text-xl mb-1 text-purple-500"></i>
        <span class="text-[10px] text-gray-300">পুরস্কার</span>
    </a>
    
    <a href="account.php" class="w-1/5 flex flex-col items-center justify-center pb-1 opacity-70 hover:opacity-100 transition">
        <i class="fas fa-user text-xl mb-1 text-blue-500"></i>
        <span class="text-[10px] text-gray-300">সদস্য</span>
    </a>
</div>

<?php include_once __DIR__ . '/../includes/modal_popup.php'; ?>

<div id="commonOverlay" class="fixed inset-0 bg-black/80 z-[90] hidden"></div>

<script>
(function() {
    // Disable right-click context menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    }, false);

    // Disable common shortcuts used for developer tools
    document.addEventListener('keydown', function(e) {
        // F12
        if (e.key === 'F12') {
            e.preventDefault();
            return false;
        }
        // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.key === 'J' || e.key === 'j' || e.key === 'C' || e.key === 'c')) {
            e.preventDefault();
            return false;
        }
        // Ctrl+U (View Source)
        if (e.ctrlKey && (e.key === 'U' || e.key === 'u')) {
            e.preventDefault();
            return false;
        }
    }, false);

    // Continuous debugger statement to halt browser when DevTools is opened
    setInterval(function() {
        (function() {
            return false;
        }
        ['constructor']('debugger')());
    }, 200);
})();
</script>