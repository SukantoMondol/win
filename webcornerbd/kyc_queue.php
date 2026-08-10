<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// 1. HANDLE ACTIONS
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $uid = intval($_GET['uid']);
    $action = $_GET['action'];
    
    // Determine status based on action
    if ($action == 'approve') {
        $profile_status = 'verified';
        $doc_status = 'approved';
        $msg = "User KYC Verified successfully!";
        $msg_type = "success";
    } elseif ($action == 'reject') {
        $profile_status = 'rejected';
        $doc_status = 'rejected';
        $msg = "User KYC Rejected.";
        $msg_type = "error";
    } elseif ($action == 'reset') {
        $profile_status = 'pending';
        $doc_status = 'pending';
        $msg = "User KYC moved back to Pending.";
        $msg_type = "warning";
    }
    
    // Update Profile Status
    $conn->query("UPDATE player_profiles SET kyc_status='$profile_status' WHERE user_id=$uid");
    
    // Update All Documents for this user
    $conn->query("UPDATE kyc_documents SET status='$doc_status' WHERE user_id=$uid");
}

// 2. TABS & FILTER LOGIC
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
$status_filter = ($tab == 'approved') ? 'verified' : (($tab == 'rejected') ? 'rejected' : 'pending');

// 3. SEARCH & FETCH USERS
$search = isset($_GET['search']) ? sanitize($conn, $_GET['search']) : '';
$where = "p.kyc_status = '$status_filter'";

if ($search) {
    $where .= " AND (u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR p.kyc_nid_number LIKE '%$search%')";
}

// Fetch users matching the status
$sql = "SELECT u.id, u.username, u.email, u.created_at, 
               p.kyc_real_name, p.kyc_nid_number, p.kyc_dob, p.kyc_status
        FROM users u 
        JOIN player_profiles p ON u.id = p.user_id 
        WHERE $where 
        ORDER BY u.created_at ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Management | BetPro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script>
        function openModal(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('imgModal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('imgModal').classList.add('hidden');
        }
    </script>
</head>
<body class="bg-gray-100 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Identity Verification</h1>
                <p class="text-sm text-gray-500">Manage user documents and KYC status</p>
            </div>
            
            <form method="GET" class="w-full md:w-auto relative">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search username, NID..." 
                       class="w-full md:w-64 pl-10 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:border-indigo-500 shadow-sm">
                <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
            </form>
        </div>

        <?php if(isset($msg)): ?>
            <div class="mb-6 p-4 rounded-lg flex items-center gap-3 shadow-sm bg-white border-l-4 <?php echo $msg_type == 'success' ? 'border-green-500 text-green-700' : ($msg_type == 'error' ? 'border-red-500 text-red-700' : 'border-yellow-500 text-yellow-700'); ?>">
                <i class="fas <?php echo $msg_type == 'success' ? 'fa-check' : 'fa-exclamation-circle'; ?>"></i>
                <span class="font-medium"><?php echo $msg; ?></span>
            </div>
        <?php endif; ?>

        <div class="flex border-b border-gray-200 mb-6 overflow-x-auto">
            <a href="?tab=pending" class="px-6 py-3 text-sm font-bold border-b-2 transition whitespace-nowrap <?php echo $tab == 'pending' ? 'border-orange-500 text-orange-600 bg-orange-50' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-clock mr-2"></i> Pending Queue
            </a>
            <a href="?tab=approved" class="px-6 py-3 text-sm font-bold border-b-2 transition whitespace-nowrap <?php echo $tab == 'approved' ? 'border-green-500 text-green-600 bg-green-50' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-check-circle mr-2"></i> Verified Users
            </a>
            <a href="?tab=rejected" class="px-6 py-3 text-sm font-bold border-b-2 transition whitespace-nowrap <?php echo $tab == 'rejected' ? 'border-red-500 text-red-600 bg-red-50' : 'border-transparent text-gray-500 hover:text-gray-700'; ?>">
                <i class="fas fa-times-circle mr-2"></i> Rejected History
            </a>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                    $user_id = $row['id'];
                    // Fetch ALL documents for this user dynamically
                    $docs_sql = "SELECT * FROM kyc_documents WHERE user_id = $user_id";
                    $docs = $conn->query($docs_sql);
                ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex flex-col">
                    
                    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm">
                                <?php echo strtoupper(substr($row['username'], 0, 2)); ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-800 text-sm"><?php echo htmlspecialchars($row['username']); ?></h3>
                                <p class="text-xs text-gray-500"><?php echo wcb_public_email_html($row['email'] ?? ''); ?></p>
                            </div>
                        </div>
                        <span class="text-xs px-3 py-1 rounded-full font-bold uppercase 
                            <?php echo $tab=='pending'?'bg-orange-100 text-orange-700':($tab=='approved'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'); ?>">
                            <?php echo $tab; ?>
                        </span>
                    </div>

                    <div class="p-6 flex-1">
                        <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <span class="block text-[10px] text-gray-400 uppercase font-bold mb-1">Full Name</span>
                                <span class="font-semibold text-gray-800"><?php echo $row['kyc_real_name'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <span class="block text-[10px] text-gray-400 uppercase font-bold mb-1">NID / Passport</span>
                                <span class="font-mono font-semibold text-gray-800"><?php echo $row['kyc_nid_number'] ?? 'N/A'; ?></span>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <span class="block text-[10px] text-gray-400 uppercase font-bold mb-1">DOB</span>
                                <span class="font-semibold text-gray-800"><?php echo $row['kyc_dob'] ? date('d M Y', strtotime($row['kyc_dob'])) : 'N/A'; ?></span>
                            </div>
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <span class="block text-[10px] text-gray-400 uppercase font-bold mb-1">Submitted</span>
                                <span class="font-semibold text-gray-800"><?php echo date('d M Y', strtotime($row['created_at'])); ?></span>
                            </div>
                        </div>

                        <h4 class="text-xs font-bold text-gray-500 uppercase mb-3 flex items-center gap-2">
                            <i class="fas fa-images"></i> Attached Documents (<?php echo $docs->num_rows; ?>)
                        </h4>
                        
                        <?php if($docs->num_rows > 0): ?>
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                <?php while($doc = $docs->fetch_assoc()): ?>
                                    <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden border border-gray-200 relative group cursor-pointer"
                                         onclick="openModal('../<?php echo $doc['file_path']; ?>')">
                                        <img src="../<?php echo $doc['file_path']; ?>" class="w-full h-full object-cover transition duration-300 group-hover:scale-110">
                                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition flex items-center justify-center">
                                            <i class="fas fa-eye text-white opacity-0 group-hover:opacity-100"></i>
                                        </div>
                                        <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-[9px] p-1 text-center truncate">
                                            <?php echo ucfirst(str_replace('_', ' ', $doc['doc_type'])); ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 bg-gray-50 border border-dashed border-gray-300 rounded-lg text-xs text-gray-400">
                                No documents uploaded.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-4 bg-gray-50 border-t border-gray-200 flex gap-3">
                        
                        <?php if($tab == 'pending'): ?>
                            <a href="?action=reject&uid=<?php echo $user_id; ?>&tab=<?php echo $tab; ?>" 
                               onclick="return confirm('Reject this application?')"
                               class="flex-1 py-2.5 bg-white border border-red-200 text-red-600 rounded-xl font-bold text-sm hover:bg-red-50 transition text-center shadow-sm">
                                <i class="fas fa-times mr-1"></i> Reject
                            </a>
                            <a href="?action=approve&uid=<?php echo $user_id; ?>&tab=<?php echo $tab; ?>" 
                               onclick="return confirm('Approve this User?')"
                               class="flex-1 py-2.5 bg-green-600 text-white rounded-xl font-bold text-sm hover:bg-green-700 transition text-center shadow-lg shadow-green-200">
                                <i class="fas fa-check mr-1"></i> Approve
                            </a>
                        <?php elseif($tab == 'approved'): ?>
                            <a href="?action=reject&uid=<?php echo $user_id; ?>&tab=<?php echo $tab; ?>" 
                               onclick="return confirm('Revoke approval and Reject?')"
                               class="flex-1 py-2.5 bg-red-100 text-red-700 rounded-xl font-bold text-sm hover:bg-red-200 transition text-center">
                                <i class="fas fa-ban mr-1"></i> Revoke & Reject
                            </a>
                        <?php elseif($tab == 'rejected'): ?>
                            <a href="?action=reset&uid=<?php echo $user_id; ?>&tab=<?php echo $tab; ?>" 
                               onclick="return confirm('Move back to pending queue?')"
                               class="flex-1 py-2.5 bg-white border border-orange-300 text-orange-600 rounded-xl font-bold text-sm hover:bg-orange-50 transition text-center">
                                <i class="fas fa-undo mr-1"></i> Re-assess (Move to Pending)
                            </a>
                        <?php endif; ?>
                        
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-1 xl:col-span-2 py-20 text-center text-gray-400 bg-white rounded-2xl border-2 border-dashed border-gray-200">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder-open text-4xl text-gray-300"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-600">No <?php echo ucfirst($tab); ?> Applications</h2>
                    <p class="text-sm">There are no users in this list currently.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <div id="imgModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-95 flex items-center justify-center p-4 backdrop-blur-sm" onclick="closeModal()">
        <button class="absolute top-4 right-4 text-white text-4xl hover:text-gray-300 transition">&times;</button>
        <img id="modalImg" src="" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl border-2 border-gray-800 object-contain">
    </div>

</body>
</html>