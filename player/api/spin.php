<?php
// player/api/spin.php
header('Content-Type: application/json');
session_start();
// Adjust DB path if needed
require '../../includes/db.php';

// 1. Auth Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Login required']);
    exit();
}

$uid = $_SESSION['user_id'];
$bet_per_line = isset($_POST['bet_amount']) ? floatval($_POST['bet_amount']) : 0;
$current_theme = isset($_POST['theme']) ? $_POST['theme'] : 'classic'; // <--- NEW: GET THEME

// 2. Validate Balance
$user = $conn->query("SELECT balance FROM users WHERE id=$uid")->fetch_assoc();
if (!$user || $user['balance'] < $bet_per_line) {
    echo json_encode(['status' => 'error', 'message' => 'Insufficient Balance']);
    exit();
}

// 3. DEFINE ALL YOUR GAME THEMES HERE
// The keys (0-7) must match the order in your slot-machine.js 'gameThemes' list
$all_themes = [
    'classic' => [0=>'cherry', 1=>'lemon', 2=>'orange', 3=>'grapes', 4=>'bell', 5=>'diamond', 6=>'star', 7=>'wild'],
    'ocean'   => [0=>'fish', 1=>'octopus', 2=>'whale', 3=>'shark', 4=>'dolphin', 5=>'treasure', 6=>'pearl', 7=>'wild'],
    'space'   => [0=>'alien', 1=>'rocket', 2=>'planet', 3=>'ufo', 4=>'astronaut', 5=>'galaxy', 6=>'star', 7=>'wild'],
    'animals' => [0=>'monkey', 1=>'tiger', 2=>'elephant', 3=>'lion', 4=>'panda', 5=>'unicorn', 6=>'dragon', 7=>'wild'],
    // You can add the others (food, gems, etc) following the same pattern from your JS file
];

// Select the map for the current game (Fallback to classic if not found)
$symbols_map = isset($all_themes[$current_theme]) ? $all_themes[$current_theme] : $all_themes['classic'];

// 4. GET RTP SETTINGS
$bank_q = $conn->query("SELECT setting_value FROM game_settings WHERE setting_key='slot_bank'");
$rtp_q = $conn->query("SELECT setting_value FROM game_settings WHERE setting_key='rtp_percentage'");
$bank = ($bank_q && $bank_q->num_rows > 0) ? floatval($bank_q->fetch_row()[0]) : 10000;
$rtp = ($rtp_q && $rtp_q->num_rows > 0) ? intval($rtp_q->fetch_row()[0]) : 50;

// 5. THE RTP LOGIC
$win_amount = 0;
$is_win = false;
$rows_count = 3;
$cols_count = 5;

// Probability Check
$chance = rand(1, 100);
$allow_win = ($chance <= $rtp) && ($bank > ($bet_per_line * 10));

$final_grid = []; 

if ($allow_win) {
    // --- FORCE WIN ---
    // Win on Middle Row (Row 1)
    $winning_symbol_key = rand(0, 3); // Low tier win
    $winning_symbol_id = $symbols_map[$winning_symbol_key];
    
    $win_amount = $bet_per_line * 5; 
    $is_win = true;

    for ($r = 0; $r < $rows_count; $r++) {
        $row_data = [];
        for ($c = 0; $c < $cols_count; $c++) {
            if ($r === 1) { 
                $row_data[] = $winning_symbol_id;
            } else {
                $random_key = rand(0, 6);
                $row_data[] = $symbols_map[$random_key];
            }
        }
        $final_grid[] = $row_data;
    }
} else {
    // --- FORCE LOSS ---
    for ($r = 0; $r < $rows_count; $r++) {
        $row_data = [];
        for ($c = 0; $c < $cols_count; $c++) {
            $random_key = rand(0, 6);
            
            // Sabotage Middle Row
            if ($r === 1 && $c === 2) {
                $prev = array_search($row_data[$c-1], $symbols_map);
                $random_key = ($prev + 1) % 7; 
            }
            $row_data[] = $symbols_map[$random_key];
        }
        $final_grid[] = $row_data;
    }
}

// 6. UPDATE DB
$new_balance = $user['balance'] - $bet_per_line + $win_amount;

$conn->query("UPDATE users SET balance = balance - $bet_per_line WHERE id=$uid");
$conn->query("UPDATE game_settings SET setting_value = setting_value + $bet_per_line WHERE setting_key='slot_bank'");

if ($is_win) {
    $conn->query("UPDATE users SET balance = balance + $win_amount WHERE id=$uid");
    $conn->query("UPDATE game_settings SET setting_value = setting_value - $win_amount WHERE setting_key='slot_bank'");
}

$desc = $is_win ? "Slot Win ($current_theme)" : "Slot Bet ($current_theme)";
$conn->query("INSERT INTO transactions_fake (user_id, type, amount, method, status, created_at) VALUES ($uid, 'bet', $bet_per_line, '$desc', 'approved', NOW())");

echo json_encode([
    'status' => 'success',
    'new_balance' => number_format($new_balance, 2, '.', ''),
    'win_amount' => $win_amount,
    'grid' => $final_grid 
]);
?>