<?php
// admin/manage_display.php
require '../includes/auth_session.php';
require '../includes/db.php'; 

// --- ACTIONS & LOGIC ---

// ১. ড্র্যাগ করার পর পজিশন সেভ করার লজিক (AJAX হ্যান্ডলার)
if (isset($_POST['update_order'])) {
    $order = $_POST['order']; // এটি একটি অ্যারে হিসেবে আসবে
    if (is_array($order)) {
        foreach ($order as $index => $map_id) {
            $map_id = (int)$map_id;
            $index = (int)$index;
            $conn->query("UPDATE front_category_games SET sort_order = $index WHERE id = $map_id");
        }
    }
    echo "Success"; exit;
}

// 1. Create Category
if (isset($_POST['add_cat'])) {
    $name = $conn->real_escape_string($_POST['cat_name']);
    $prio = (int)$_POST['priority'];
    $conn->query("INSERT INTO front_categories (name, priority) VALUES ('$name', '$prio')");
    header("Location: manage_display.php"); exit;
}

// 2. Update Category
if (isset($_POST['update_cat'])) {
    $cat_id = (int)$_POST['cat_id'];
    $name = $conn->real_escape_string($_POST['cat_name']);
    $prio = (int)$_POST['priority'];
    $conn->query("UPDATE front_categories SET name='$name', priority='$prio' WHERE id=$cat_id");
    header("Location: manage_display.php"); exit;
}

// 3. Delete Entire Category
if (isset($_GET['del_cat'])) {
    $id = (int)$_GET['del_cat'];
    $conn->query("DELETE FROM front_categories WHERE id=$id");
    $conn->query("DELETE FROM front_category_games WHERE category_id=$id");
    header("Location: manage_display.php"); exit;
}

// 4. Clear All Games
if (isset($_GET['clear_cat'])) {
    $id = (int)$_GET['clear_cat'];
    $conn->query("DELETE FROM front_category_games WHERE category_id=$id");
    header("Location: manage_display.php"); exit;
}

// 5. BULK ADD GAMES
if (isset($_POST['bulk_add_games'])) {
    if(!empty($_POST['games']) && !empty($_POST['target_cat'])) {
        $cat_id = (int)$_POST['target_cat'];
        $selected_games = $_POST['games']; 

        foreach($selected_games as $game_uid) {
            $game_uid = $conn->real_escape_string($game_uid);
            $chk = $conn->query("SELECT id FROM front_category_games WHERE category_id=$cat_id AND game_uid='$game_uid'");
            if($chk->num_rows == 0){
                // নতুন গেম সবার শেষে যোগ হবে (sort_order বড় রাখা হয়েছে)
                $conn->query("INSERT INTO front_category_games (category_id, game_uid, sort_order) VALUES ('$cat_id', '$game_uid', 9999)");
            }
        }
    }
    header("Location: manage_display.php"); exit;
}

// 6. Move Single Game
if (isset($_POST['move_game'])) {
    $map_id = (int)$_POST['map_id'];
    $new_cat_id = (int)$_POST['new_cat_id'];
    $conn->query("UPDATE front_category_games SET category_id=$new_cat_id WHERE id=$map_id");
    header("Location: manage_display.php"); exit;
}

// 7. Remove Single Game
if (isset($_GET['remove_game'])) {
    $id = (int)$_GET['remove_game'];
    $conn->query("DELETE FROM front_category_games WHERE id=$id");
    header("Location: manage_display.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Display Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .game-img { object-fit: cover; aspect-ratio: 1/1; pointer-events: none; }
        .sortable-ghost { opacity: 0.4; background: #ebf5ff !important; border: 2px dashed #3b82f6 !important; }
        .drag-handle { cursor: grab; }
        .drag-handle:active { cursor: grabbing; }
    </style>
</head>
<body class="text-gray-800">

<div class="flex h-screen overflow-hidden">
    <div class="hidden md:flex flex-col w-64 bg-gray-900 text-white">
        <?php include '../includes/sidebar_admin.php'; ?>
    </div>

    <div class="flex-1 flex flex-col overflow-hidden">
        <div class="md:hidden bg-gray-900 text-white p-4 flex justify-between items-center">
            <h1 class="font-bold">Game Manager</h1>
            <a href="dashboard.php" class="text-sm bg-blue-600 px-3 py-1 rounded">Back</a>
        </div>

        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <div class="flex justify-between items-end mb-6 border-b pb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Frontend Game Manager</h1>
                    <p class="text-sm text-gray-500">Drag games to reorder. Changes save automatically.</p>
                </div>
                <form method="post" class="flex gap-2 items-end">
                    <div>
                        <label class="text-xs font-bold text-gray-600">New Category</label>
                        <input type="text" name="cat_name" placeholder="Name" class="border p-2 rounded text-sm w-40" required>
                    </div>
                    <div>
                        <label class="text-xs font-bold text-gray-600">Priority</label>
                        <input type="number" name="priority" placeholder="1" class="border p-2 rounded text-sm w-20" required>
                    </div>
                    <button type="submit" name="add_cat" class="bg-green-600 text-white px-4 py-2 rounded text-sm font-bold">Create</button>
                </form>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-4 space-y-6">
                    <div class="bg-white p-4 rounded-lg shadow border-t-4 border-blue-600">
                        <h3 class="font-bold text-lg mb-3 flex items-center gap-2"><i class="fas fa-search text-blue-600"></i> Search & Add Games</h3>
                        <form method="get" class="mb-4 flex gap-2">
                            <input type="text" name="q" placeholder="Search..." class="w-full border p-2 rounded" value="<?php echo $_GET['q'] ?? ''; ?>">
                            <button type="submit" class="bg-gray-800 text-white px-4 rounded"><i class="fas fa-search"></i></button>
                        </form>

                        <?php if(isset($_GET['q']) && !empty($_GET['q'])): ?>
                            <form method="post">
                                <div class="max-h-[500px] overflow-y-auto border rounded bg-gray-50 p-2 mb-3">
                                    <?php 
                                    $q = $conn->real_escape_string($_GET['q']);
                                    $games = $conn->query("SELECT * FROM games WHERE name LIKE '%$q%' LIMIT 50");
                                    if($games->num_rows > 0):
                                        while($g = $games->fetch_assoc()): ?>
                                        <div class="flex items-center gap-3 p-2 border-b hover:bg-white transition">
                                            <input type="checkbox" name="games[]" value="<?php echo $g['game_uid']; ?>">
                                            <img src="<?php echo $g['image']; ?>" class="w-10 h-10 rounded object-cover">
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-bold truncate"><?php echo $g['name']; ?></p>
                                                <p class="text-xs text-gray-500"><?php echo $g['provider_id']; ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; endif; ?>
                                </div>
                                <div class="bg-blue-50 p-3 rounded border">
                                    <select name="target_cat" class="w-full border p-2 rounded text-sm mb-2" required>
                                        <option value="">Select Category</option>
                                        <?php 
                                        $cats = $conn->query("SELECT * FROM front_categories ORDER BY priority ASC");
                                        while($c = $cats->fetch_assoc()) echo "<option value='{$c['id']}'>{$c['name']}</option>";
                                        ?>
                                    </select>
                                    <button type="submit" name="bulk_add_games" class="w-full bg-blue-600 text-white py-2 rounded text-sm font-bold">Add Selected</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lg:col-span-8 space-y-8">
                    <?php 
                    $all_cats = $conn->query("SELECT * FROM front_categories ORDER BY priority ASC");
                    while($cat = $all_cats->fetch_assoc()): 
                        $cat_id = $cat['id'];
                    ?>
                        <div class="bg-white rounded-lg shadow-md border overflow-hidden">
                            <div class="bg-gray-100 p-3 flex justify-between items-center border-b">
                                <form method="post" class="flex items-center gap-2">
                                    <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                    <input type="number" name="priority" value="<?php echo $cat['priority']; ?>" class="w-12 p-1 border rounded text-center text-sm">
                                    <input type="text" name="cat_name" value="<?php echo $cat['name']; ?>" class="p-1 border rounded font-bold text-sm">
                                    <button type="submit" name="update_cat" class="text-blue-600"><i class="fas fa-save"></i></button>
                                </form>
                                <div class="flex gap-3 text-xs font-bold">
                                    <a href="?clear_cat=<?php echo $cat['id']; ?>" onclick="return confirm('Clear all games?')" class="text-orange-500"><i class="fas fa-eraser"></i> Clear</a>
                                    <a href="?del_cat=<?php echo $cat['id']; ?>" onclick="return confirm('Delete category?')" class="text-red-500"><i class="fas fa-trash"></i> Delete</a>
                                </div>
                            </div>

                            <div class="p-4 bg-gray-50 min-h-[100px] sortable-list" data-category-id="<?php echo $cat_id; ?>">
                                <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                                    <?php 
                                    $c_games = $conn->query("
                                        SELECT fcg.id as map_id, g.name, g.image 
                                        FROM front_category_games fcg 
                                        JOIN games g ON fcg.game_uid = g.game_uid 
                                        WHERE fcg.category_id = $cat_id
                                        ORDER BY fcg.sort_order ASC
                                    ");
                                    while($item = $c_games->fetch_assoc()): ?>
                                        <div class="bg-white rounded border p-2 relative group hover:shadow-lg drag-handle" data-id="<?php echo $item['map_id']; ?>">
                                            <img src="<?php echo $item['image']; ?>" class="w-full rounded game-img">
                                            <p class="text-[10px] font-bold mt-1 truncate text-center"><?php echo htmlspecialchars($item['name']); ?></p>
                                            <div class="mt-2 flex gap-1">
                                                <a href="?remove_game=<?php echo $item['map_id']; ?>" class="w-full bg-red-50 text-red-600 text-[10px] py-1 rounded text-center hover:bg-red-600 hover:text-white transition">Remove</a>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ড্র্যাগ অ্যান্ড ড্রপ অ্যাক্টিভেট করা
document.querySelectorAll('.sortable-list .grid').forEach(el => {
    new Sortable(el, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        handle: '.drag-handle',
        onEnd: function (evt) {
            let order = [];
            // নতুন সিরিয়াল অনুযায়ী map_id গুলো নেওয়া হচ্ছে
            el.querySelectorAll('.drag-handle').forEach(item => {
                let id = item.getAttribute('data-id');
                if(id) order.push(id);
            });

            // AJAX এর মাধ্যমে ডাটা পাঠানো
            const params = new URLSearchParams();
            params.append('update_order', '1');
            order.forEach(id => params.append('order[]', id)); // অ্যারে আকারে পাঠানো হচ্ছে

            fetch('manage_display.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(response => response.text())
            .then(data => {
                console.log('Server Response:', data);
            })
            .catch(error => console.error('Error:', error));
        }
    });
});
</script>

</body>
</html>