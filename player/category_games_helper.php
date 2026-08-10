<?php
/**
 * Fast category game loading helper.
 * Scope: player category page + ajax game fetch only.
 */

/**
 * JILI mapping auto patch: keeps provider code/vendor code and game Code/UID
 * synced before the cached category list is generated.
 */
$__jiliHelperPath = __DIR__ . '/../includes/game_api_helper.php';
if (file_exists($__jiliHelperPath)) { require_once $__jiliHelperPath; }
if (isset($conn) && !$conn->connect_error && function_exists('game_api_seed_jili_mappings')) {
    @game_api_seed_jili_mappings($conn);
}

if (!function_exists('aj_html')) {
    function aj_html($value) {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('aj_category_games_cache_dir')) {
    function aj_category_games_cache_dir() {
        $dir = __DIR__ . '/../cache/category_games';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}

if (!function_exists('aj_category_games_cache_key')) {
    function aj_category_games_cache_key($cat_id, $provider, $search, $offset, $limit) {
        return md5((int)$cat_id . '|' . (string)$provider . '|' . (string)$search . '|' . (int)$offset . '|' . (int)$limit);
    }
}

if (!function_exists('aj_stmt_fetch_all_assoc')) {
    function aj_stmt_fetch_all_assoc($stmt) {
        if (method_exists($stmt, 'get_result')) {
            $res = $stmt->get_result();
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : array();
        }

        $meta = $stmt->result_metadata();
        if (!$meta) {
            return array();
        }

        $fields = array();
        $row = array();
        $bind = array();
        while ($field = $meta->fetch_field()) {
            $fields[] = $field->name;
            $row[$field->name] = null;
            $bind[] = &$row[$field->name];
        }
        call_user_func_array(array($stmt, 'bind_result'), $bind);

        $rows = array();
        while ($stmt->fetch()) {
            $copy = array();
            foreach ($fields as $name) {
                $copy[$name] = $row[$name];
            }
            $rows[] = $copy;
        }
        return $rows;
    }
}

if (!function_exists('aj_fetch_category_games_from_db')) {
    function aj_fetch_category_games_from_db($conn, $cat_id, $provider, $search, $offset, $limit) {
        $cat_id = max(1, (int)$cat_id);
        $provider = trim((string)$provider);
        $search = trim((string)$search);
        $offset = max(0, (int)$offset);
        $limit = min(max(1, (int)$limit), 100);

        $select = "SELECT g.id, g.game_uid, g.provider_id, g.name, g.image, COALESCE(gp.name, g.provider_id) AS prov_name";
        $params = array();
        $types = '';

        if ($provider !== '' && strtolower($provider) !== 'all') {
            $sql = $select . "
                FROM games g
                LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                WHERE g.provider_id = ? AND g.status = 'active'";
            $types .= 's';
            $params[] = $provider;

            if ($search !== '') {
                $sql .= " AND g.name LIKE ?";
                $types .= 's';
                $params[] = '%' . $search . '%';
            }

            $sql .= " ORDER BY g.id DESC LIMIT ? OFFSET ?";
        } else {
            $is_poker = false;
            $cat_res = $conn->query("SELECT name FROM front_categories WHERE id = " . intval($cat_id) . " LIMIT 1");
            if ($cat_res && $cat_res->num_rows > 0) {
                $cat_name = $cat_res->fetch_assoc()['name'];
                if (stripos($cat_name, 'poker') !== false) {
                    $is_poker = true;
                }
            }

            if ($is_poker) {
                $sql = $select . "
                    FROM games g
                    LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                    WHERE g.provider_id = '119' AND g.status = 'active'";
                
                if ($search !== '') {
                    $sql .= " AND g.name LIKE ?";
                    $types .= 's';
                    $params[] = '%' . $search . '%';
                }
                
                $sql .= " ORDER BY g.id DESC LIMIT ? OFFSET ?";
            } else {
                $sql = $select . "
                    FROM front_category_games fcg
                    INNER JOIN games g ON g.game_uid = fcg.game_uid
                    LEFT JOIN game_providers gp ON gp.provider_id = g.provider_id
                    WHERE fcg.category_id = ? AND g.status = 'active'";
                if ($cat_id === 1) {
                    $sql .= " AND (g.provider_id = '49' OR UPPER(COALESCE(g.api_vendor_code,''))='JILI' OR LOWER(COALESCE(g.api_provider_name,'')) LIKE '%jili%')";
                } else {
                    $is_live_casino = false;
                    if (isset($cat_name) && stripos($cat_name, 'live') !== false) {
                        $is_live_casino = true;
                    }
                    if ($is_live_casino) {
                        $sql .= " AND (LOWER(g.api_game_type) LIKE '%live%' OR LOWER(g.category) LIKE '%live%' OR LOWER(g.api_provider_name) LIKE '%live%' OR g.provider_id IN ('58','59','78','87','88','89'))";
                    }
                }
                $types .= 'i';
                $params[] = $cat_id;

                if ($search !== '') {
                    $sql .= " AND g.name LIKE ?";
                    $types .= 's';
                    $params[] = '%' . $search . '%';
                }

                $sql .= " ORDER BY fcg.id DESC LIMIT ? OFFSET ?";
            }
        }

        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return array('rows' => array(), 'error' => $conn->error);
        }

        $bind = array($types);
        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return array('rows' => array(), 'error' => $error);
        }

        $rows = aj_stmt_fetch_all_assoc($stmt);
        $stmt->close();

        if (($provider === '' || strtolower($provider) === 'all') && function_exists('game_api_jili_prepare_display_rows')) {
            $rows = game_api_jili_prepare_display_rows($conn, $rows, 0);
        }

        return array('rows' => $rows, 'error' => '');
    }
}

if (!function_exists('aj_fetch_category_games_fast')) {
    function aj_fetch_category_games_fast($conn, $cat_id, $provider, $search, $offset, $limit) {
        $search = trim((string)$search);
        $cache_enabled = ($search === '');
        $ttl = 180; // keep game/category admin changes fresh while reducing repeat-load delay

        if ($cache_enabled) {
            $dir = aj_category_games_cache_dir();
            $key = aj_category_games_cache_key($cat_id, $provider, $search, $offset, $limit);
            $file = $dir . '/' . $key . '.cache.php';

            if (is_file($file) && (time() - filemtime($file)) <= $ttl) {
                $cached = @include $file;
                if (is_array($cached) && isset($cached['rows'])) {
                    return array('rows' => $cached['rows'], 'error' => '');
                }
            }
        }

        $data = aj_fetch_category_games_from_db($conn, $cat_id, $provider, $search, $offset, $limit);

        if ($cache_enabled && empty($data['error'])) {
            $payload = "<?php\nreturn " . var_export(array('rows' => $data['rows']), true) . ";\n";
            @file_put_contents($file, $payload, LOCK_EX);
        }

        return $data;
    }
}

if (!function_exists('aj_render_game_cards')) {
    function aj_render_game_cards($rows) {
        if (empty($rows)) {
            return '';
        }

        $html = '';
        foreach ($rows as $g) {
            $game_uid = aj_html((!empty($g['jili_launch_uid']) ? $g['jili_launch_uid'] : ($g['game_uid'] ?? '')));
            $img_url = $g['image'] ?? '';
            if (function_exists('game_api_prepare_game_image')) {
                $img_url = game_api_prepare_game_image($g);
            }
            $image = aj_html($img_url);
            $prov_name = aj_html($g['prov_name'] ?? '');
            $name = aj_html($g['name'] ?? '');

            // Fix: do not output literal "\n" text. It was visible on page and broke CSS grid layout.
            $html .= '<a href="launch.php?game_id=' . $game_uid . '" class="game-card">'
                . '<div class="aspect-square bg-[#071f18] p-1">'
                . '<img src="' . $image . '" class="w-full h-full object-cover rounded-lg shadow-inner" loading="lazy" decoding="async" onerror="this.src=\'https://placehold.co/150x150/0a2d1f/1de9b6?text=GAME\'">'
                . '</div>'
                . '<div class="p-1.5">'
                . '<p class="text-[7px] text-emerald-400 font-black truncate uppercase">' . $prov_name . '</p>'
                . '<p class="text-[9px] text-gray-200 font-bold truncate">' . $name . '</p>'
                . '</div>'
                . '</a>';
        }
        return $html;
    }
}
