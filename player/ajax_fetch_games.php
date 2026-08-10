<?php
// Fast AJAX game fetch for category/provider pages.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/category_games_helper.php';

$cat_id = isset($_GET['cat_id']) ? max(1, (int)$_GET['cat_id']) : 1;
$provider = isset($_GET['provider']) ? trim((string)$_GET['provider']) : 'all';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit = min(max(1, $limit), 100);

if ($provider === '') {
    $provider = 'all';
}

$data = aj_fetch_category_games_fast($conn, $cat_id, $provider, $search, $offset, $limit);

if (!empty($data['error'])) {
    http_response_code(500);
    exit;
}

echo aj_render_game_cards($data['rows']);
