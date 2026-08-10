<?php
// Maintenance Mode public access guard
$__maintenance_db = __DIR__ . '/../includes/db.php';
if (file_exists($__maintenance_db)) { require_once $__maintenance_db; }

// player/slot_game.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// ... (Include your standard headers/sidebar here) ...
?>
<!DOCTYPE html>
<html>
<head>
    <title>Super Slots</title>
    <style>
        #slot-container { width: 100%; max-width: 800px; margin: 0 auto; }
        canvas { border: 2px solid #gold; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-900 text-white">

    <div class="flex flex-col items-center justify-center min-h-screen">
        <h1 class="text-2xl text-yellow-400 font-bold mb-4">Casino Slots</h1>
        
        <div id="slot-container">
            <canvas id="slotCanvas"></canvas>
        </div>

        <div class="controls mt-4 flex gap-4">
            <div class="text-xl">Balance: $<span id="balanceDisplay">Loading...</span></div>
            <button id="spinBtn" class="bg-yellow-500 text-black font-bold py-3 px-8 rounded-full text-xl hover:bg-yellow-400 transition">
                SPIN!
            </button>
        </div>
    </div>

    <script type="module">
        // Import the library (assuming you downloaded the JS files to assets/js/slots/)
        import SlotMachine from '../assets/js/slots/slot-machine.js';

        const canvas = document.getElementById('slotCanvas');
        const spinBtn = document.getElementById('spinBtn');
        const balanceDisplay = document.getElementById('balanceDisplay');

        // Initialize Game
        const game = new SlotMachine(canvas, {
            reels: 5,
            rows: 3,
            symbolHeight: 80,
            symbolWidth: 100
        });

        // --- OVERRIDE THE SPIN FUNCTION ---
        spinBtn.addEventListener('click', async () => {
            if(game.isSpinning) return;

            // 1. Disable Button
            spinBtn.disabled = true;
            spinBtn.classList.add('opacity-50');

            try {
                // 2. Call YOUR Backend
                const formData = new FormData();
                formData.append('bet', 10); // Hardcoded bet for demo
                formData.append('lines', 1);

                const response = await fetch('api/spin.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if(data.status === 'success') {
                    // 3. Force the Game to Stop where PHP said
                    // The library likely has a method like start() and stop(positions)
                    // You might need to tweak slot-machine.js to accept 'positions' in the stop method
                    
                    game.spin(); // Start visual spin
                    
                    // Wait a moment then force stop
                    setTimeout(() => {
                        // We map PHP grid to the format the JS library expects
                        // Note: You must modify the library's stop() function to accept an array!
                        game.stop(data.grid); 
                        
                        balanceDisplay.innerText = data.balance.toFixed(2);
                        spinBtn.disabled = false;
                        spinBtn.classList.remove('opacity-50');
                        
                        if(data.win > 0) {
                            alert(`YOU WON $${data.win}!`);
                        }
                    }, 2000);

                } else {
                    alert(data.message);
                    spinBtn.disabled = false;
                }

            } catch (error) {
                console.error(error);
                alert("Server Error");
                spinBtn.disabled = false;
            }
        });
    </script>
</body>
</html>