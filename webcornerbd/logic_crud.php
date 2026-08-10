<?php
// admin/logic_crud.php
require '../includes/auth_session.php';
require '../includes/db.php';
require '../includes/functions.php';

// 1. DELETE HANDLER (Universal)
if (isset($_GET['delete'])) {
    $table = sanitize($conn, $_GET['table']);
    $id = intval($_GET['id']);
    $redirect = sanitize($conn, $_GET['redirect']);

    // Security Whitelist (Prevent deleting wrong tables)
    $allowed_tables = ['users', 'payment_accounts', 'game_providers', 'agents'];
    
    if (in_array($table, $allowed_tables)) {
        // If deleting a user, we also delete their profile via Foreign Key Cascade
        $conn->query("DELETE FROM $table WHERE id=$id");
        header("Location: $redirect?msg=deleted");
    } else {
        die("❌ Security Alert: Illegal Table Access");
    }
    exit();
}

// 2. TOGGLE STATUS HANDLER (Universal)
if (isset($_GET['toggle_status'])) {
    $table = sanitize($conn, $_GET['table']);
    $id = intval($_GET['id']);
    $column = sanitize($conn, $_GET['column']); // e.g., 'is_active' or 'status'
    $redirect = sanitize($conn, $_GET['redirect']);

    // Fetch current status
    $row = $conn->query("SELECT $column FROM $table WHERE id=$id")->fetch_assoc();
    
    // Toggle Logic
    if ($table == 'users' || $table == 'game_providers') {
        // String Toggles ('active' <-> 'banned'/'maintenance')
        $new_val = ($row[$column] == 'active') ? 'banned' : 'active';
        if($table == 'game_providers') $new_val = ($row[$column] == 'active') ? 'maintenance' : 'active';
    } else {
        // Boolean Toggles (1 <-> 0)
        $new_val = ($row[$column] == 1) ? 0 : 1;
    }

    $stmt = $conn->prepare("UPDATE $table SET $column = ? WHERE id = ?");
    $stmt->bind_param("si", $new_val, $id);
    $stmt->execute();
    
    header("Location: $redirect?msg=updated");
    exit();
}

// 3. CREATE HANDLER: ADD PAYMENT NUMBER (For payment_setup.php)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_payment'])) {
    $method = sanitize($conn, $_POST['method']);
    $type = sanitize($conn, $_POST['type']);
    $number = sanitize($conn, $_POST['number']);
    
    $conn->query("INSERT INTO payment_accounts (method, type, number, is_active) VALUES ('$method', '$type', '$number', 1)");
    header("Location: payment_setup.php?msg=added");
    exit();
}

// 4. CREATE HANDLER: ADD PROVIDER (For providers.php)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_provider'])) {
    $name = sanitize($conn, $_POST['name']);
    $slug = strtolower(str_replace(' ', '-', $name));
    $type = sanitize($conn, $_POST['type']);
    
    $conn->query("INSERT INTO game_providers (name, slug, type, status) VALUES ('$name', '$slug', '$type', 'active')");
    header("Location: providers.php?msg=added");
    exit();
}
?>