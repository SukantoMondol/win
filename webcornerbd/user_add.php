<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = (string)($_POST['role'] ?? 'player');
    if (!in_array($role, ['player', 'agent'], true)) {
        $role = 'player';
    }
    $username = sanitize($conn, $_POST['username']);
    $email = sanitize($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Check duplicates
    $check = $conn->query("SELECT id FROM users WHERE email='$email' OR username='$username'");
    if ($check->num_rows > 0) {
        $msg = "Error: Username or Email already exists.";
    } else {
        // 1. Insert User
        $stmt = $conn->prepare("INSERT INTO users (role, username, email, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $role, $username, $email, $password);
        
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            
            // 2. If Player, Create Profile
            if ($role == 'player') {
                $conn->query("INSERT INTO player_profiles (user_id, balance, country) VALUES ($new_id, 0.00, 'Unknown')");
            }
            // 3. If Agent, Create Agent Record
            if ($role == 'agent') {
                $conn->query("INSERT INTO agents (user_id, commission_percent) VALUES ($new_id, 10.00)");
            }
            
            header("Location: users_all.php");
            exit();
        } else {
            $msg = "Database Error.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User | BetPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="ml-64 pt-16">
        <?php include '../includes/header.php'; ?>
        
        <div class="p-8 max-w-2xl mx-auto">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Create New Account</h1>
            
            <?php if($msg): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded mb-4"><?php echo $msg; ?></div>
            <?php endif; ?>

            <form method="POST" class="bg-white p-8 rounded-xl shadow-sm border border-gray-100">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Account Role</label>
                    <select name="role" class="w-full border border-gray-300 rounded-lg p-3">
                        <option value="player">Player</option>
                        <option value="agent">Agent</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Username</label>
                        <input type="text" name="username" required class="w-full border border-gray-300 rounded-lg p-3">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Email</label>
                        <input type="email" name="email" required class="w-full border border-gray-300 rounded-lg p-3">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Password</label>
                    <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg p-3">
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-lg shadow transition">
                    Create User
                </button>
            </form>
        </div>
    </main>
</body>
</html>