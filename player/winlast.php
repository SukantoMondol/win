<?php

if (!isset($conn)) {
   
    include 'includes/db.php'; 
}

function generateRandomWinner() {
    $users = ['***oni', '***993', '***lau', '***rak', '***mim', '***boss', '***king'];
    $amount = rand(5000, 150000); 
    $user = $users[array_rand($users)];
    $time = date("Y-m-d H:i:s", strtotime("-" . rand(1, 10) . " minutes"));
    
    return [
        'user' => $user,
        'amount' => number_format($amount),
        'time' => $time
    ];
}


$sql = "SELECT * FROM games ORDER BY RAND() LIMIT 20"; 
$result = $conn->query($sql);
?>

<style>
    .winner-section {
        background-color: #000;
        padding: 20px;
        color: #fff;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        border-top: 1px solid #222;
        border-bottom: 1px solid #222;
        overflow: hidden; /* স্ক্রল এরিয়ার বাইরে কন্টেন্ট লুকানোর জন্য */
    }

    .winner-title {
        color: #EAB308; /* গোল্ডেন কালার */
        text-align: center;
        font-size: 22px;
        font-weight: bold;
        margin-bottom: 20px;
        text-shadow: 0px 0px 10px rgba(234, 179, 8, 0.3);
    }

    /* স্ক্রল কন্টেইনার */
    .marquee-container {
        height: 400px; /* এই উচ্চতার মধ্যে স্ক্রল হবে */
        overflow: hidden;
        position: relative;
    }

    .marquee-content {
        display: flex;
        flex-direction: column;
        gap: 15px;
        animation: scrollUp 20s linear infinite; /* ২০ সেকেন্ডে একবার লুপ হবে */
    }

    /* স্ক্রল এনিমেশন (নিচ থেকে উপরে) */
    @keyframes scrollUp {
        0% { transform: translateY(0); }
        100% { transform: translateY(-50%); } /* কন্টেন্ট ডুপ্লিকেট করা হবে তাই ৫০% */
    }

    /* হোভার করলে স্ক্রল থামবে */
    .marquee-container:hover .marquee-content {
        animation-play-state: paused;
    }

    /* উইনার কার্ড ডিজাইন */
    .winner-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #111;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #333;
    }

    .game-img {
        width: 70px;
        height: 70px;
        border-radius: 8px;
        object-fit: cover;
        margin-right: 15px;
    }

    .winner-info {
        flex-grow: 1;
    }

    .game-name {
        font-size: 16px;
        font-weight: bold;
        color: #fff;
        margin: 0;
    }

    .user-name {
        font-size: 13px;
        color: #bbb;
        margin: 2px 0;
    }

    .win-amount {
        font-size: 15px;
        color: #EAB308;
        font-weight: bold;
    }

    .win-time {
        font-size: 10px;
        color: #666;
        display: block;
    }

    /* বাটন ডিজাইন */
    .play-btn {
        background: linear-gradient(90deg, #8e2de2, #4a00e0); /* পার্পল গ্রাডিয়েন্ট */
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        text-decoration: none;
        font-weight: bold;
        white-space: nowrap;
        box-shadow: 0 4px 15px rgba(120, 40, 220, 0.4);
    }

    .play-btn:hover {
        background: linear-gradient(90deg, #4a00e0, #8e2de2);
    }
</style>

<div class="winner-section">
    <h2 class="winner-title">সর্বশেষ বিজয়ীরা</h2>

    <div class="marquee-container">
        <div class="marquee-content">
            <?php
            // গেমের ডাটা অ্যারেতে রাখা (লুপ চালানোর সুবিধার্থে)
            $gamesData = [];
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $gamesData[] = $row;
                }
            }

            // ফাংশন যা কার্ড প্রিন্ট করবে
            function printCard($game) {
                $fakeData = generateRandomWinner();
                // ইমেজ পাথ ঠিক করা (তোমার assets/img ফোল্ডার অনুযায়ী)
                // যদি DB তে শুধু নাম থাকে (যেমন fish1.jpg), তাহলে পাথ যোগ হবে
                // আর যদি DB তে আগে থেকেই পাথ থাকে, তাহলে সরাসরি বসবে।
                // আমি ধরে নিচ্ছি assets/img/games/ ফোল্ডারে ছবি আছে। যদি অন্য ফোল্ডার হয়, পাথ চেঞ্জ করে নিও।
                $imagePath = '../assets/img/games/' . $game['image']; 
                
                // যদি ইমেজ না পাওয়া যায়, ডিফল্ট ইমেজ
                if(empty($game['image'])) {
                     $imagePath = 'assets/img/no-image.png'; 
                }

                echo '
                <div class="winner-card">
                    <img src="' . $imagePath . '" alt="' . $game['name'] . '" class="game-img">
                    
                    <div class="winner-info">
                        <h3 class="game-name">' . $game['name'] . '</h3>
                        <p class="user-name">ব্যবহারকারী: ' . $fakeData['user'] . '</p>
                        <p class="win-amount">জ্যাকপট জয়: ৳' . $fakeData['amount'] . '</p>
                        <span class="win-time">' . $fakeData['time'] . '</span>
                    </div>

                    <a href="game_play.php?id=' . $game['id'] . '" class="play-btn">
                        এখন খেলুন
                    </a>
                </div>';
            }

            // স্মুথ ইনফিনিটি লুপের জন্য ডাটা দুইবার প্রিন্ট করা হচ্ছে
            foreach ($gamesData as $game) { printCard($game); }
            foreach ($gamesData as $game) { printCard($game); } 
            ?>
        </div>
    </div>
</div>