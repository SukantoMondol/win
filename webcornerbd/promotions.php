<?php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

$msg = '';
$msg_type = 'success';

function promo_clean_text($value, $limit = 255) {
    $value = trim((string)$value);
    $value = strip_tags($value);
    if (function_exists('mb_substr')) return mb_substr($value, 0, $limit, 'UTF-8');
    return substr($value, 0, $limit);
}

function promo_ensure_schema($conn) {
    if (!$conn || $conn->connect_error) return;
    $exists = @$conn->query("SHOW TABLES LIKE 'promotions'");
    if (!$exists || $exists->num_rows === 0) {
        @$conn->query("CREATE TABLE promotions (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            category varchar(50) DEFAULT 'all',
            image_path varchar(255) NOT NULL DEFAULT '',
            subtitle varchar(255) DEFAULT NULL,
            description text,
            bonus_amount varchar(100) DEFAULT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            is_new tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            wager_multiplier int(11) DEFAULT 10,
            target_group varchar(50) DEFAULT 'all',
            bonus_percent int(11) DEFAULT 0,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return;
    }
    $columns = array(
        'subtitle' => "varchar(255) DEFAULT NULL",
        'wager_multiplier' => "int(11) DEFAULT 10",
        'target_group' => "varchar(50) DEFAULT 'all'",
        'bonus_percent' => "int(11) DEFAULT 0",
        'is_new' => "tinyint(1) DEFAULT 0",
        'status' => "varchar(20) DEFAULT 'active'"
    );
    foreach ($columns as $col => $def) {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        $check = @$conn->query("SHOW COLUMNS FROM promotions LIKE '$safe'");
        if (!$check || $check->num_rows === 0) {
            @$conn->query("ALTER TABLE promotions ADD COLUMN `$safe` $def");
        }
    }
    // Make category flexible so WELCOME/SLOTS tabs can be managed without enum errors.
    @$conn->query("ALTER TABLE promotions MODIFY category varchar(50) DEFAULT 'all'");
    @$conn->query("ALTER TABLE promotions MODIFY status varchar(20) DEFAULT 'active'");
}

function promo_upload_image($field = 'promo_image') {
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return array('ok' => false, 'error' => 'No image uploaded.');
    }
    $file = $_FILES[$field];
    if (($file['size'] ?? 0) > 12 * 1024 * 1024) return array('ok' => false, 'error' => 'Banner image is too large. Maximum 12MB allowed.');
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = array('jpg','jpeg','png','webp','gif');
    if (!in_array($ext, $allowed, true)) return array('ok' => false, 'error' => 'Invalid banner type. Use JPG, PNG, WEBP or GIF.');

    $new_name = 'promo_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

    $possible_roots = array(
        dirname(__DIR__),
        $_SERVER['DOCUMENT_ROOT'] ?? '',
        '/app',
        '/var/www/html',
        '/Applications/XAMPP/xamppfiles/htdocs/jb66.net'
    );

    $saved = false;
    $saved_path = '';

    foreach ($possible_roots as $root) {
        if (empty($root)) continue;
        $abs_dir = rtrim($root, '/') . '/assets/img/promos/';
        if (!is_dir($abs_dir)) {
            @mkdir($abs_dir, 0777, true);
        }
        @chmod($abs_dir, 0777);

        if (is_dir($abs_dir) && is_writable($abs_dir)) {
            $target_file = $abs_dir . $new_name;
            $tmp = $file['tmp_name'];
            $saved = @move_uploaded_file($tmp, $target_file);
            if (!$saved) {
                $saved = @copy($tmp, $target_file);
            }
            if (!$saved && file_exists($tmp)) {
                $content = @file_get_contents($tmp);
                if ($content !== false && strlen($content) > 0) {
                    $saved = (@file_put_contents($target_file, $content) !== false);
                }
            }
            if ($saved) {
                @chmod($target_file, 0666);
                $saved_path = 'assets/img/promos/' . $new_name;
                break;
            }
        }
    }

    if ($saved) {
        return array('ok' => true, 'path' => $saved_path);
    }

    // Ultimate Fallback: Base64 Data URI
    $tmpContent = @file_get_contents($file['tmp_name']);
    if ($tmpContent !== false && strlen($tmpContent) > 0) {
        $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($tmpContent);
        return array('ok' => true, 'path' => $dataUri);
    }

    return array('ok' => false, 'error' => 'Unable to save banner image. Check server directory permissions.');
}

promo_ensure_schema($conn);
$categories = array('all' => 'ALL', 'welcome' => 'WELCOME', 'slots' => 'SLOTS', 'deposit' => 'DEPOSIT', 'electronic' => 'ELECTRONIC', 'fishing' => 'FISHING', 'rebate' => 'REBATE');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_promotion']) || isset($_POST['update_promotion'])) {
        $isUpdate = isset($_POST['update_promotion']);
        $id = intval($_POST['promo_id'] ?? 0);
        $title = promo_clean_text($_POST['title'] ?? '', 255);
        $subtitle = promo_clean_text($_POST['subtitle'] ?? '', 255);
        $description = promo_clean_text($_POST['description'] ?? '', 3000);
        $category = strtolower(promo_clean_text($_POST['category'] ?? 'all', 50));
        if (!array_key_exists($category, $categories)) $category = 'all';
        $start_date = trim($_POST['start_date'] ?? '') ?: date('Y-m-d');
        $end_date = trim($_POST['end_date'] ?? '') ?: '2030-12-31';
        $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $bonus_percent = max(0, min(1000, intval($_POST['bonus_percent'] ?? 0)));
        $wager_multiplier = max(0, min(1000, intval($_POST['wager_multiplier'] ?? 0)));
        $bonus_amount = $bonus_percent > 0 ? ($bonus_percent . '% Bonus') : promo_clean_text($_POST['bonus_amount'] ?? '', 100);
        $image_path = '';

        if ($title === '') {
            $msg = 'Promotion title is required.';
            $msg_type = 'error';
        } elseif ($isUpdate && $id <= 0) {
            $msg = 'Valid promotion ID is required.';
            $msg_type = 'error';
        } else {
            if ($isUpdate) {
                $old = $conn->prepare("SELECT image_path FROM promotions WHERE id=? LIMIT 1");
                if ($old) {
                    $old->bind_param('i', $id);
                    $old->execute();
                    $old_res = $old->get_result();
                    if ($old_res && $old_res->num_rows > 0) $image_path = $old_res->fetch_assoc()['image_path'] ?? '';
                }
            }
            if (isset($_FILES['promo_image']) && ($_FILES['promo_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $up = promo_upload_image('promo_image');
                if (!$up['ok']) {
                    $msg = $up['error'];
                    $msg_type = 'error';
                } else {
                    $image_path = $up['path'];
                }
            }
            if (!$isUpdate && $image_path === '') {
                $msg = 'Banner image is required for a new promotion.';
                $msg_type = 'error';
            }
            if ($msg_type !== 'error') {
                if ($isUpdate) {
                    $stmt = $conn->prepare("UPDATE promotions SET title=?, category=?, image_path=?, subtitle=?, description=?, bonus_amount=?, start_date=?, end_date=?, is_new=?, status=?, wager_multiplier=?, bonus_percent=? WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param('ssssssssisiii', $title, $category, $image_path, $subtitle, $description, $bonus_amount, $start_date, $end_date, $is_new, $status, $wager_multiplier, $bonus_percent, $id);
                        if ($stmt->execute()) $msg = 'Promotion banner updated successfully.'; else { $msg = 'Unable to update promotion: ' . $stmt->error; $msg_type = 'error'; }
                    } else { $msg = 'Unable to prepare promotion update.'; $msg_type = 'error'; }
                } else {
                    $stmt = $conn->prepare("INSERT INTO promotions (title, category, image_path, subtitle, description, bonus_amount, start_date, end_date, is_new, status, wager_multiplier, target_group, bonus_percent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'all', ?)");
                    if ($stmt) {
                        $stmt->bind_param('ssssssssisii', $title, $category, $image_path, $subtitle, $description, $bonus_amount, $start_date, $end_date, $is_new, $status, $wager_multiplier, $bonus_percent);
                        if ($stmt->execute()) $msg = 'New promotion banner uploaded successfully.'; else { $msg = 'Unable to add promotion: ' . $stmt->error; $msg_type = 'error'; }
                    } else { $msg = 'Unable to prepare promotion insert.'; $msg_type = 'error'; }
                }
            }
        }
    }

    if (isset($_POST['delete_promotion'])) {
        $id = intval($_POST['promo_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM promotions WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) $msg = 'Promotion banner deleted successfully.'; else { $msg = 'Unable to delete promotion: ' . $stmt->error; $msg_type = 'error'; }
            }
        }
    }
}

$promotions = $conn->query("SELECT * FROM promotions ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Banners | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="bg-gray-100 text-slate-800">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="lg:ml-64 p-4 lg:p-8 min-h-screen">
        <?php include '../includes/header.php'; ?>
        <div class="max-w-7xl mx-auto pt-16 lg:pt-20">
            <div class="mb-6">
                <h1 class="text-2xl font-black text-gray-800 flex items-center gap-2"><i class="fas fa-images text-indigo-600"></i> Promotion Banner Management</h1>
                <p class="text-sm text-gray-500">Upload, change and delete promotion banners shown on the player Promotion section.</p>
            </div>

            <?php if($msg): ?>
                <div class="mb-6 p-4 rounded-xl border-l-4 bg-white shadow-sm <?php echo $msg_type === 'success' ? 'border-green-500 text-green-700' : 'border-red-500 text-red-700'; ?>">
                    <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <span class="font-bold text-sm"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="font-black text-lg mb-4 flex items-center gap-2"><i class="fas fa-cloud-upload-alt text-indigo-600"></i> Add New Banner</h2>
                <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
                    <div class="xl:col-span-2">
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Title</label>
                        <input type="text" name="title" required class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold" placeholder="Welcome Bonus">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Category</label>
                        <select name="category" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                            <?php foreach($categories as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Bonus %</label>
                        <input type="number" name="bonus_percent" min="0" max="1000" value="0" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Wager x</label>
                        <input type="number" name="wager_multiplier" min="0" max="1000" value="0" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Status</label>
                        <select name="status" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 xl:col-span-3">
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Subtitle / Short Text</label>
                        <input type="text" name="subtitle" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold" placeholder="Short promo text">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">End Date</label>
                        <input type="date" name="end_date" value="2030-12-31" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                    </div>
                    <div class="flex items-center pt-7">
                        <label class="inline-flex items-center gap-2 text-sm font-black text-gray-700"><input type="checkbox" name="is_new" value="1" class="h-4 w-4" checked> New</label>
                    </div>
                    <div class="md:col-span-2 xl:col-span-4">
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Details / Description</label>
                        <textarea name="description" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold" placeholder="Promotion rules/details"></textarea>
                    </div>
                    <div class="md:col-span-2 xl:col-span-2">
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Banner Image</label>
                        <input type="file" name="promo_image" required accept="image/*" class="w-full text-sm border border-gray-300 rounded-xl p-2 bg-white">
                        <p class="text-[11px] text-gray-400 mt-1">Recommended: wide banner, JPG/PNG/WEBP.</p>
                    </div>
                    <div class="md:col-span-2 xl:col-span-6 flex justify-end">
                        <button type="submit" name="add_promotion" class="bg-indigo-600 hover:bg-indigo-700 text-white px-7 py-3 rounded-xl font-black shadow-md"><i class="fas fa-plus mr-2"></i> Add Promotion Banner</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b bg-gray-50 flex justify-between items-center">
                    <h2 class="font-black text-lg">Existing Promotion Banners</h2>
                    <span class="text-xs font-bold text-gray-500"><?php echo $promotions ? intval($promotions->num_rows) : 0; ?> items</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-black">
                            <tr>
                                <th class="text-left px-4 py-3">Banner</th>
                                <th class="text-left px-4 py-3">Title</th>
                                <th class="text-center px-4 py-3">Category</th>
                                <th class="text-center px-4 py-3">End</th>
                                <th class="text-center px-4 py-3">Status</th>
                                <th class="text-right px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if($promotions && $promotions->num_rows > 0): ?>
                                <?php while($row = $promotions->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="px-4 py-3"><img src="../<?php echo htmlspecialchars($row['image_path'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onerror="this.src='https://placehold.co/240x90/1a5c92/FFF?text=PROMO'" class="w-36 h-16 object-cover rounded-lg border" alt="Banner"></td>
                                    <td class="px-4 py-3 max-w-md">
                                        <div class="font-black text-gray-800 truncate"><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($row['subtitle'] ?? ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-bold"><?php echo htmlspecialchars(strtoupper($row['category'] ?? 'all'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-3 text-center text-gray-500"><?php echo htmlspecialchars($row['end_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-full text-xs font-black <?php echo ($row['status'] ?? '') === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>"><?php echo htmlspecialchars(ucfirst($row['status'] ?? 'inactive'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>)' class="text-blue-600 hover:bg-blue-50 px-3 py-2 rounded-lg"><i class="fas fa-edit"></i></button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this promotion banner?');">
                                            <input type="hidden" name="promo_id" value="<?php echo intval($row['id']); ?>">
                                            <button type="submit" name="delete_promotion" class="text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400 font-bold">No promotion banners found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[92vh] overflow-y-auto">
            <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center sticky top-0">
                <h3 class="font-black text-lg">Update Promotion Banner</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-700"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="promo_id" id="edit_promo_id">
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Title</label>
                    <input type="text" name="title" id="edit_title" required class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Category</label>
                    <select name="category" id="edit_category" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                        <?php foreach($categories as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Subtitle</label>
                    <input type="text" name="subtitle" id="edit_subtitle" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Description</label>
                    <textarea name="description" id="edit_description" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Bonus %</label>
                    <input type="number" name="bonus_percent" id="edit_bonus_percent" min="0" max="1000" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Wager x</label>
                    <input type="number" name="wager_multiplier" id="edit_wager_multiplier" min="0" max="1000" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Start Date</label>
                    <input type="date" name="start_date" id="edit_start_date" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">End Date</label>
                    <input type="date" name="end_date" id="edit_end_date" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Status</label>
                    <select name="status" id="edit_status" class="w-full border border-gray-300 rounded-xl px-4 py-3 font-bold">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex items-center pt-7">
                    <label class="inline-flex items-center gap-2 text-sm font-black text-gray-700"><input type="checkbox" name="is_new" id="edit_is_new" value="1" class="h-4 w-4"> New</label>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Change Banner Image</label>
                    <input type="file" name="promo_image" accept="image/*" class="w-full text-sm border border-gray-300 rounded-xl p-2 bg-white">
                    <p class="text-[11px] text-gray-400 mt-1">Leave empty to keep current banner.</p>
                </div>
                <div class="md:col-span-2 flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-xl font-black">Cancel</button>
                    <button type="submit" name="update_promotion" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-black"><i class="fas fa-save mr-2"></i> Update Banner</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(row) {
        document.getElementById('edit_promo_id').value = row.id || '';
        document.getElementById('edit_title').value = row.title || '';
        document.getElementById('edit_category').value = row.category || 'all';
        document.getElementById('edit_subtitle').value = row.subtitle || '';
        document.getElementById('edit_description').value = row.description || '';
        document.getElementById('edit_bonus_percent').value = row.bonus_percent || 0;
        document.getElementById('edit_wager_multiplier').value = row.wager_multiplier || 0;
        document.getElementById('edit_start_date').value = row.start_date || '';
        document.getElementById('edit_end_date').value = row.end_date || '';
        document.getElementById('edit_status').value = row.status || 'active';
        document.getElementById('edit_is_new').checked = Number(row.is_new || 0) === 1;
        const modal = document.getElementById('editModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    </script>
</body>
</html>
