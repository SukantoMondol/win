<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// 1. Validate Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$msg = "";
$msg_type = "";

// --- AUTO-GENERATE REFERRAL CODE (AGT0001, AGT0002...) ---
$last_q = $conn->query("SELECT id FROM agents ORDER BY id DESC LIMIT 1");
$next_id = 1;
if ($last_q && $last_q->num_rows > 0) {
    $next_id = $last_q->fetch_assoc()['id'] + 1;
}
$auto_ref_code = "AGT" . str_pad($next_id, 4, '0', STR_PAD_LEFT);


// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $name = $conn->real_escape_string(trim($_POST['name']));
    $phone = $conn->real_escape_string(trim($_POST['phone']));
    $ref_code = $conn->real_escape_string(trim($_POST['referral_code']));
    $type = $conn->real_escape_string($_POST['type']);
    $wallet = $conn->real_escape_string($_POST['wallet_number']);

    // Basic Validation
    if (empty($username) || empty($email) || empty($password) || empty($ref_code)) {
        $msg = "Please fill all required fields.";
        $msg_type = "error";
    } else {
        // Check Duplicates
        $check = $conn->query("SELECT id FROM users WHERE email='$email' OR username='$username'");
        $check_ref = $conn->query("SELECT id FROM agents WHERE referral_code='$ref_code'");

        if ($check->num_rows > 0) {
            $msg = "Username or Email already exists!";
            $msg_type = "error";
        } elseif ($check_ref->num_rows > 0) {
            $msg = "Referral Code already taken!";
            $msg_type = "error";
        } else {
            // Start Transaction
            $conn->begin_transaction();
            try {
                // A. Create User Login
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (role, username, email, phone, password, status) VALUES ('agent', ?, ?, ?, ?, 'active')");
                $stmt->bind_param("ssss", $username, $email, $phone, $hashed_pass);
                $stmt->execute();
                $new_user_id = $conn->insert_id;

                // B. Create Agent Profile
                $stmt2 = $conn->prepare("INSERT INTO agents (user_id, name, email, phone, referral_code, type, wallet_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt2->bind_param("issssss", $new_user_id, $name, $email, $phone, $ref_code, $type, $wallet);
                $stmt2->execute();
                $new_agent_id = $conn->insert_id; // Capture the NEW Agent ID

                // C. *** THE FIX: LINK AGENT ID BACK TO USERS TABLE ***
                // This updates the NULL agent_id to the actual ID (1, 2, 3...)
                $conn->query("UPDATE users SET agent_id = $new_agent_id WHERE id = $new_user_id");

                // Commit Changes
                $conn->commit();
                $msg = "New Agent created successfully! (Agent ID: $new_agent_id)";
                $msg_type = "success";
                
                // Refresh for next entry
                $next_id++;
                $auto_ref_code = "AGT" . str_pad($next_id, 4, '0', STR_PAD_LEFT);

            } catch (Exception $e) {
                $conn->rollback(); // Undo if error
                $msg = "System Error: " . $e->getMessage();
                $msg_type = "error";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Agent | BetPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="max-w-4xl mx-auto">
            
            <div class="flex items-center gap-4 mb-6">
                <a href="agents.php" class="bg-white p-2 rounded-lg shadow-sm border hover:bg-gray-50 transition text-gray-600">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Create New Agent</h1>
                    <p class="text-sm text-gray-500">Add a new partner to your network</p>
                </div>
            </div>

            <?php if($msg): ?>
                <div class="<?php echo $msg_type == 'success' ? 'bg-green-100 text-green-700 border-green-400' : 'bg-red-100 text-red-700 border-red-400'; ?> border px-4 py-3 rounded relative mb-6 flex items-center shadow-sm">
                    <i class="fas <?php echo $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-3 text-lg"></i>
                    <span class="font-medium"><?php echo $msg; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                
                <div class="p-6 md:p-8 space-y-8">
                    
                    <div>
                        <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b pb-2">1. Login Credentials</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-user"></i></span>
                                    <input type="text" name="username" required class="w-full pl-10 pr-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition" placeholder="e.g. agent_dhaka">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Login Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3 top-3 text-gray-400"><i class="fas fa-lock"></i></span>
                                    <input type="password" name="password" required class="w-full pl-10 pr-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition" placeholder="********">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b pb-2">2. Agent Profile</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Full Name</label>
                                <input type="text" name="name" required class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition" placeholder="e.g. Rahim Khan">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Email Address <span class="text-red-500">*</span></label>
                                <input type="email" name="email" required class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition" placeholder="agent@email.com">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                                <input type="text" name="phone" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition" placeholder="017xxxxxxxx">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Referral Code (Unique) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="text" name="referral_code" value="<?php echo $auto_ref_code; ?>" required class="w-full px-4 py-2.5 border rounded-lg bg-gray-50 focus:ring-2 focus:ring-indigo-500 font-mono font-bold text-indigo-600 outline-none transition">
                                    <span class="absolute right-3 top-3 text-xs text-gray-400">Auto-Generated</span>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b pb-2">3. Operation Type</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Agent Type</label>
                                <select name="type" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                                    <option value="local">Local Agent (Offline)</option>
                                    <option value="ewallet">E-Wallet Agent (Online)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Wallet Number (Optional)</label>
                                <input type="text" name="wallet_number" class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition" placeholder="Bkash/Nagad Number if applicable">
                            </div>

                        </div>
                    </div>

                </div>

                <div class="px-8 py-5 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <a href="agents.php" class="px-6 py-2.5 rounded-lg text-gray-600 font-bold hover:bg-gray-200 transition">Cancel</a>
                    <button type="submit" class="px-8 py-2.5 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-md transition flex items-center gap-2">
                        <i class="fas fa-check"></i> Create Agent
                    </button>
                </div>

            </form>

        </div>
    </main>

</body>
</html>