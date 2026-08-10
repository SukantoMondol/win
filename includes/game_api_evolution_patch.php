<?php
/**
 * Evolution Live / Evolution Asia compatibility patch.
 * - Keeps credentials/settings dynamic in game_settings.
 * - Seeds only curated popular Evolution games and local thumbnails.
 * - Does not touch wallet/payment/user logic.
 */

if (!function_exists('game_api_evolution_ensure_patch')) {
    function game_api_evolution_sql_value($conn, $value) {
        return "'" . $conn->real_escape_string((string)$value) . "'";
    }

    function game_api_evolution_category_id($conn, $name, $priority) {
        if (!game_api_table_exists($conn, 'front_categories')) { return 0; }
        $nameEsc = $conn->real_escape_string($name);
        $res = @$conn->query("SELECT id FROM front_categories WHERE LOWER(name)=LOWER('$nameEsc') LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return (int)$row['id'];
        }
        @$conn->query("INSERT INTO front_categories (name, priority, status) VALUES ('$nameEsc', " . (int)$priority . ", 1)");
        return (int)$conn->insert_id;
    }

    function game_api_evolution_upsert_game($conn, $row) {
        if (!game_api_table_exists($conn, 'games')) { return ''; }

        $apiGameId = (string)$row['api_game_id'];
        $apiGameCode = (string)$row['api_game_code'];
        $providerId = (string)$row['provider_id'];
        $name = (string)$row['game_name'];
        $image = (string)$row['image'];
        $category = (string)$row['category'];
        $apiGameType = (string)$row['api_game_type'];
        $apiVendorCode = (string)$row['api_vendor_code'];
        $apiProviderName = (string)$row['api_provider_name'];
        $gameUid = 'evo_' . substr($apiGameId, 0, 16);

        // Update every existing Evolution Live/Asia row with the same UID so both providers get the fixed image/mapping.
        $stmt = $conn->prepare("UPDATE games SET api_game_id=?, api_game_code=?, api_vendor_code=?, api_provider_name=?, image=?, category=?, api_game_type=?, api_mapping_status='mapped', status='active' WHERE api_game_id=? AND provider_id IN ('58','59')");
        if ($stmt) {
            $stmt->bind_param('ssssssss', $apiGameId, $apiGameCode, $apiVendorCode, $apiProviderName, $image, $category, $apiGameType, $apiGameId);
            @$stmt->execute();
            $stmt->close();
        }

        $exists = false;
        $stmt = $conn->prepare("SELECT game_uid FROM games WHERE api_game_id=? AND provider_id=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $apiGameId, $providerId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $gameUid = (string)$res->fetch_assoc()['game_uid'];
                $exists = true;
            }
            $stmt->close();
        }

        if (!$exists) {
            $stmt = $conn->prepare("INSERT INTO games (game_uid, api_game_id, api_game_code, api_vendor_code, provider_id, api_provider_name, name, image, category, api_game_type, api_mapping_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'mapped', 'active')");
            if ($stmt) {
                $stmt->bind_param('ssssssssss', $gameUid, $apiGameId, $apiGameCode, $apiVendorCode, $providerId, $apiProviderName, $name, $image, $category, $apiGameType);
                @$stmt->execute();
                $stmt->close();
            }
        }

        return $gameUid;
    }

    function game_api_evolution_add_to_category($conn, $categoryId, $gameUid, $sortOrder) {
        if ($categoryId <= 0 || $gameUid === '' || !game_api_table_exists($conn, 'front_category_games')) { return; }
        $stmt = $conn->prepare("SELECT id FROM front_category_games WHERE category_id=? AND game_uid=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('is', $categoryId, $gameUid);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) { $stmt->close(); return; }
            $stmt->close();
        }
        $stmt = $conn->prepare("INSERT INTO front_category_games (category_id, game_uid, sort_order) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isi', $categoryId, $gameUid, $sortOrder);
            @$stmt->execute();
            $stmt->close();
        }
    }

    function game_api_evolution_cleanup_live_games($conn, $allowedApiIds) {
        // Cleanup is intentionally scoped to Evolution Live provider_id=58 only.
        // Other providers, and Evolution Asia provider_id=59, are not removed.
        if (!game_api_table_exists($conn, 'games') || empty($allowedApiIds)) {
            return array('removed' => 0, 'category_links_removed' => 0, 'deduped' => 0);
        }

        $allowed = array();
        foreach ($allowedApiIds as $id) {
            $id = trim((string)$id);
            if ($id !== '') { $allowed[$id] = true; }
        }
        if (empty($allowed)) { return array('removed' => 0, 'category_links_removed' => 0, 'deduped' => 0); }

        $allowedSql = array();
        foreach (array_keys($allowed) as $id) { $allowedSql[] = game_api_evolution_sql_value($conn, $id); }
        $allowedList = implode(',', $allowedSql);

        // Remove duplicate rows for the curated Evolution Live games, keeping the oldest local game_uid
        // so existing website URLs such as /player/launch.php?game_id=6305 continue to work.
        $deduped = 0;
        $duplicateUids = array();
        $dupSql = "SELECT g1.game_uid FROM games g1 JOIN games g2 ON g1.provider_id='58' AND g2.provider_id='58' AND g1.api_game_id=g2.api_game_id AND g1.api_game_id IN ($allowedList) AND g1.id > g2.id";
        $dupRes = @$conn->query($dupSql);
        if ($dupRes) {
            while ($r = $dupRes->fetch_assoc()) { $duplicateUids[] = (string)$r['game_uid']; }
        }
        if (!empty($duplicateUids)) {
            $uidSql = array();
            foreach ($duplicateUids as $uid) { $uidSql[] = game_api_evolution_sql_value($conn, $uid); }
            $uidList = implode(',', $uidSql);
            if (game_api_table_exists($conn, 'front_category_games')) { @$conn->query("DELETE FROM front_category_games WHERE game_uid IN ($uidList)"); }
            @$conn->query("DELETE FROM games WHERE game_uid IN ($uidList) AND provider_id='58'");
            $deduped = (int)$conn->affected_rows;
        }

        // Delete/not just hide extra Evolution Live games so only the curated added list remains visible.
        $extraUids = array();
        $extraSql = "SELECT game_uid FROM games WHERE provider_id='58' AND (api_game_id IS NULL OR api_game_id='' OR api_game_id NOT IN ($allowedList))";
        $extraRes = @$conn->query($extraSql);
        if ($extraRes) {
            while ($r = $extraRes->fetch_assoc()) { $extraUids[] = (string)$r['game_uid']; }
        }

        $linksRemoved = 0;
        $gamesRemoved = 0;
        if (!empty($extraUids)) {
            $uidSql = array();
            foreach ($extraUids as $uid) { $uidSql[] = game_api_evolution_sql_value($conn, $uid); }
            $uidList = implode(',', $uidSql);
            if (game_api_table_exists($conn, 'front_category_games')) {
                @$conn->query("DELETE FROM front_category_games WHERE game_uid IN ($uidList)");
                $linksRemoved = max(0, (int)$conn->affected_rows);
            }
            @$conn->query("DELETE FROM games WHERE provider_id='58' AND game_uid IN ($uidList)");
            $gamesRemoved = max(0, (int)$conn->affected_rows);
        }

        return array('removed' => $gamesRemoved, 'category_links_removed' => $linksRemoved, 'deduped' => $deduped);
    }

    function game_api_evolution_ensure_patch($conn, $force = false) {
        static $alreadyRan = false;
        if ($alreadyRan && !$force) { return; }
        $alreadyRan = true;
        if (!$conn || (isset($conn->connect_error) && $conn->connect_error)) { return; }

        game_api_ensure_schema($conn, false);
        $version = 'evolution_live_2026_06_19_v4_bdt_currency_redirect';
        if (!$force && game_api_get_setting($conn, 'game_api_evolution_patch_version', '') === $version) {
            return;
        }

        // Multi-domain dynamic settings. Admin can change these from Game API Key page after moving hosting/domain.
        game_api_ensure_setting($conn, 'api_endpoint', 'https://oxentech.asia/api/devtools.php');
        game_api_ensure_setting($conn, 'api_token', '');
        game_api_ensure_setting($conn, 'secret_key', '');
        game_api_ensure_setting($conn, 'agent_code', '');
        game_api_ensure_setting($conn, 'currency_code', 'BDT');
        game_api_ensure_setting($conn, 'evolutionlive_language', 'en');
        game_api_ensure_setting($conn, 'evolutionlive_currency', 'BDT');
        game_api_ensure_setting($conn, 'evolutionlive_launch_transport', 'json_first');
        game_api_ensure_setting($conn, 'evolutionlive_display_mode', 'redirect');

        // v4: previous patch seeded INR for EvolutionLive because docs mention test currency.
        // This merchant/live wrapper returns BDT in the launch response, so auto-correct old INR default to site currency.
        $currentEvoCurrency = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'evolutionlive_currency', '')));
        $currentSiteCurrency = strtoupper(game_api_clean_no_space(game_api_get_setting($conn, 'currency_code', 'BDT')));
        if ($currentSiteCurrency === '') { $currentSiteCurrency = 'BDT'; }
        if ($currentEvoCurrency === '' || ($currentEvoCurrency === 'INR' && $currentSiteCurrency === 'BDT')) {
            game_api_set_setting($conn, 'evolutionlive_currency', $currentSiteCurrency);
        }

        if (game_api_table_exists($conn, 'game_providers')) {
            @$conn->query("UPDATE game_providers SET type='live', status='active' WHERE provider_id IN ('58','59')");
        }

        $popularFile = __DIR__ . '/game_api_evolution_popular_seed.php';
        $popular = file_exists($popularFile) ? include $popularFile : array();
        if (!is_array($popular)) { $popular = array(); }

        $popularCategoryId = game_api_evolution_category_id($conn, 'Popular', 1);
        $liveCategoryId = game_api_evolution_category_id($conn, 'Live Casino', 1);

        $seeded = 0;
        $allowedApiIds = array();
        foreach ($popular as $row) {
            if (!is_array($row) || empty($row['api_game_id']) || empty($row['game_name'])) { continue; }
            $allowedApiIds[] = (string)$row['api_game_id'];
            $gameUid = game_api_evolution_upsert_game($conn, $row);
            if ($gameUid !== '') {
                $sort = isset($row['sort_order']) ? (int)$row['sort_order'] : $seeded;
                game_api_evolution_add_to_category($conn, $liveCategoryId, $gameUid, $sort);
                if ($sort < 36) { game_api_evolution_add_to_category($conn, $popularCategoryId, $gameUid, $sort); }
                $seeded++;
            }
        }

        $cleanup = game_api_evolution_cleanup_live_games($conn, $allowedApiIds);

        game_api_set_setting($conn, 'game_api_evolution_patch_version', $version);
        game_api_set_setting($conn, 'game_api_evolution_popular_seeded_count', (string)$seeded);
        game_api_set_setting($conn, 'game_api_evolution_cleanup_removed', (string)(isset($cleanup['removed']) ? $cleanup['removed'] : 0));
        game_api_debug_log('evolution_patch_applied', array('version' => $version, 'seeded_count' => $seeded, 'cleanup' => $cleanup));
    }
}
?>
