<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// ---------------------------------------------------------
// 1. HANDLE MANUAL SYNC (Only when clicked)
// ---------------------------------------------------------
if (isset($_POST['sync_providers'])) {
    $active_providers_query = $conn->query("SELECT DISTINCT provider_id FROM games WHERE provider_id IS NOT NULL AND provider_id != ''");
    $added_count = 0;
    
    while ($ap = $active_providers_query->fetch_assoc()) {
        $pid = $conn->real_escape_string($ap['provider_id']);
        $check = $conn->query("SELECT id FROM game_providers WHERE provider_id = '$pid'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO game_providers (provider_id, name, type, status) VALUES ('$pid', '$pid', 'slots', 'active')");
            $added_count++;
        }
    }
    header("Location: providers.php?msg=synced&count=$added_count"); exit();
}

// ---------------------------------------------------------
// 2. HANDLE IMAGE UPDATE
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_config'])) {
    $id = intval($_POST['provider_id']);
    $image_path = $_POST['current_image'];
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload_dir = '../uploads/providers/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $file_name = 'prov_' . $id . '_' . time() . '.' . $file_ext;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $file_name)) {
            $image_path = 'uploads/providers/' . $file_name;
        }
    }

    $stmt = $conn->prepare("UPDATE game_providers SET image=? WHERE id=?");
    $stmt->bind_param("si", $image_path, $id);
    $stmt->execute();
    header("Location: providers.php?msg=updated"); exit();
}

// 3. HANDLE TOGGLE STATUS
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = $_GET['toggle'] == 'active' ? 'maintenance' : 'active';
    $conn->query("UPDATE game_providers SET status='$status' WHERE id=$id");
    header("Location: providers.php"); exit();
}

// ---------------------------------------------------------
// 4. STATS & SEARCH LOGIC
// ---------------------------------------------------------

// Stats Query
$stats_q = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status='maintenance' THEN 1 ELSE 0 END) as inactive
    FROM game_providers");
$stats = $stats_q->fetch_assoc();

// Search Logic
$search_q = isset($_GET['q']) ? sanitize($conn, $_GET['q']) : '';
$sql = "SELECT * FROM game_providers";
if ($search_q) {
    $sql .= " WHERE provider_id LIKE '%$search_q%'";
}
$sql .= " ORDER BY provider_id ASC";
$providers = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Providers | BetPro Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-50 font-sans text-slate-800">

    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen transition-all duration-300">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Game Providers</h1>
                <p class="text-sm text-gray-500">Manage images & visibility.</p>
            </div>
            <form method="POST">
                <button type="submit" name="sync_providers" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-bold shadow transition flex items-center gap-2">
                    <i class="fas fa-sync-alt"></i> Sync from Games
                </button>
            </form>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Total</p>
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></h3>
                </div>
                <div class="h-10 w-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600"><i class="fas fa-cubes"></i></div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-green-200 shadow-sm flex items-center justify-between bg-green-50/50">
                <div>
                    <p class="text-xs text-green-600 uppercase font-bold">Active</p>
                    <h3 class="text-2xl font-bold text-green-700"><?php echo $stats['active']; ?></h3>
                </div>
                <div class="h-10 w-10 bg-green-100 rounded-full flex items-center justify-center text-green-600"><i class="fas fa-check"></i></div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-red-200 shadow-sm flex items-center justify-between bg-red-50/50">
                <div>
                    <p class="text-xs text-red-600 uppercase font-bold">Inactive</p>
                    <h3 class="text-2xl font-bold text-red-700"><?php echo $stats['inactive']; ?></h3>
                </div>
                <div class="h-10 w-10 bg-red-100 rounded-full flex items-center justify-center text-red-600"><i class="fas fa-ban"></i></div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm mb-6">
            <form class="relative w-full">
                <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                <input type="text" name="q" value="<?php echo $search_q; ?>" placeholder="Search provider name..." 
                       class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:outline-none focus:border-indigo-500 bg-gray-50 focus:bg-white transition text-sm">
            </form>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6 border-l-4 border-green-500 font-medium text-sm flex items-center gap-2">
                <i class="fas fa-check-circle"></i> 
                <?php 
                    if($_GET['msg'] == 'synced') echo "Sync Complete! Found " . intval($_GET['count']) . " new providers.";
                    else echo "Update Successful!"; 
                ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
            <?php 
            if ($providers->num_rows > 0):
                while($row = $providers->fetch_assoc()): 
                    $img_src = !empty($row['image']) ? '../'.$row['image'] : 'https://placehold.co/100x50/f3f4f6/a3a3a3?text=No+Logo';
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-3 flex flex-col justify-between h-full group hover:border-indigo-300 transition">
                
                <div class="text-center mb-3">
                    <div class="h-12 w-full bg-gray-50 rounded-lg flex items-center justify-center overflow-hidden mb-2 relative">
                        <img src="<?php echo $img_src; ?>" class="max-h-full max-w-full object-contain p-1">
                    </div>
                    <h3 class="font-bold text-gray-700 text-xs truncate" title="<?php echo htmlspecialchars($row['provider_id']); ?>">
                        <?php echo htmlspecialchars($row['provider_id']); ?>
                    </h3>
                    
                    <div class="mt-1">
                        <?php if($row['status'] == 'active'): ?>
                            <span class="text-[10px] text-green-600 font-bold flex items-center justify-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                            </span>
                        <?php else: ?>
                            <span class="text-[10px] text-red-500 font-bold flex items-center justify-center gap-1">
                                <i class="fas fa-ban text-[8px]"></i> Inactive
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex gap-1 border-t border-gray-100 pt-2">
                    <button onclick="openConfigModal(
                        '<?php echo $row['id']; ?>', 
                        '<?php echo $row['provider_id']; ?>', 
                        '<?php echo $row['image']; ?>'
                    )" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600 py-1.5 rounded text-[10px] font-bold transition">
                        Edit
                    </button>
                    
                    <a href="?toggle=<?php echo $row['status']; ?>&id=<?php echo $row['id']; ?>" 
                       class="w-7 flex items-center justify-center rounded text-[10px] border transition <?php echo $row['status'] == 'active' ? 'border-red-200 text-red-500 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50'; ?>" title="Toggle">
                        <i class="fas fa-power-off"></i>
                    </a>
                </div>

            </div>
            <?php endwhile; 
            else: ?>
                <div class="col-span-full py-10 text-center text-gray-400">
                    <i class="fas fa-search text-3xl mb-2 opacity-50"></i>
                    <p>No providers found matching your search.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <div id="configModal" class="hidden fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm m-auto transform transition-all scale-100 relative">
            <div class="flex justify-between items-center px-5 py-3 border-b border-gray-100">
                <h3 class="font-bold text-sm text-gray-800" id="modalTitle">Update Logo</h3>
                <button onclick="document.getElementById('configModal').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-times"></i></button>
            </div>
            
            <form method="POST" class="p-5" enctype="multipart/form-data">
                <input type="hidden" name="update_config" value="1">
                <input type="hidden" name="provider_id" id="conf_id">
                <input type="hidden" name="current_image" id="conf_current_img">

                <div class="mb-4 text-center">
                    <div class="relative w-full h-24 mx-auto border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center overflow-hidden bg-gray-50 hover:bg-gray-100 transition cursor-pointer group">
                        <img id="preview_img" src="" class="absolute inset-0 w-full h-full object-contain p-2">
                        <div class="absolute inset-0 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 bg-black/10 transition">
                            <i class="fas fa-cloud-upload-alt text-2xl text-indigo-600"></i>
                        </div>
                        <input type="file" name="logo" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile(this)" accept="image/*">
                    </div>
                    <p class="text-[10px] text-gray-400 mt-2">Click box to change logo</p>
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-lg font-bold shadow hover:bg-indigo-700 text-sm transition">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <script>
        function openConfigModal(id, name, image) {
            document.getElementById('conf_id').value = id;
            document.getElementById('modalTitle').innerText = 'Logo: ' + name;
            document.getElementById('conf_current_img').value = image;
            
            const preview = document.getElementById('preview_img');
            preview.src = image ? '../' + image : 'https://placehold.co/200x100/e2e8f0/94a3b8?text=Upload';
            
            document.getElementById('configModal').classList.remove('hidden');
        }

        function previewFile(input) {
            const preview = document.getElementById('preview_img');
            const file = input.files[0];
            const reader = new FileReader();
            reader.onloadend = function () { preview.src = reader.result; }
            if (file) reader.readAsDataURL(file);
        }
    </script>
</body>
</html>