<?php
/**
 * Shared betting/turnover data loader.
 * The live database in this project has two possible bet-history schemas:
 *  - game_logs: user_id, game_uid, game_name, bet_amount, win_amount, status, created_at
 *  - game_bet_history: raw API transaction rows with type/amount but without status/bet_amount columns
 * These helpers prevent HTTP 500 errors caused by querying missing columns.
 */

if (!function_exists('player_table_exists')) {
    function player_table_exists($conn, $table) {
        if (!$conn) return false;
        $table = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$table'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('player_column_exists')) {
    function player_column_exists($conn, $table, $column) {
        if (!$conn) return false;
        $table = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return ($res && $res->num_rows > 0);
    }
}

if (!function_exists('player_fetch_assoc_all')) {
    function player_fetch_assoc_all($result) {
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('player_bet_records')) {
    function player_bet_records($conn, $user_id, $mode = 'completed', $limit = 50) {
        $records = [];
        if (!$conn || $conn->connect_error) return $records;

        $uid = (int)$user_id;
        $limit = max(1, min(200, (int)$limit));
        $mode = strtolower((string)$mode);

        // Preferred schema: game_logs has the display-ready columns used by these pages.
        if (player_table_exists($conn, 'game_logs')
            && player_column_exists($conn, 'game_logs', 'bet_amount')
            && player_column_exists($conn, 'game_logs', 'win_amount')
            && player_column_exists($conn, 'game_logs', 'status')) {

            $statusCondition = ($mode === 'active' || $mode === 'pending' || $mode === 'unsettled')
                ? "gl.status='pending'"
                : "gl.status<>'pending'";

            $joinGames = player_table_exists($conn, 'games') ? "LEFT JOIN games g ON gl.game_uid = g.game_uid" : "";
            $providerExpr = $joinGames ? "COALESCE(NULLIF(g.provider_id,''), 'Unknown')" : "'Unknown'";
            $gameNameExpr = $joinGames ? "COALESCE(NULLIF(gl.game_name,''), NULLIF(g.name,''), 'Casino Game')" : "COALESCE(NULLIF(gl.game_name,''), 'Casino Game')";

            $sql = "SELECT gl.id, gl.user_id, gl.game_uid,
                           $gameNameExpr AS game_name,
                           $providerExpr AS provider_name,
                           gl.bet_amount, gl.win_amount, gl.status, gl.created_at
                    FROM game_logs gl
                    $joinGames
                    WHERE gl.user_id=$uid AND $statusCondition
                    ORDER BY gl.created_at DESC, gl.id DESC
                    LIMIT $limit";

            $result = $conn->query($sql);
            if ($result) {
                $rows = player_fetch_assoc_all($result);
                // For active/unsettled tab the raw API table cannot safely identify pending bets.
                // For completed tab, if game_logs is empty, continue to the raw-history fallback below.
                if (!empty($rows) || $mode === 'active' || $mode === 'pending' || $mode === 'unsettled') {
                    return $rows;
                }
            } else {
                error_log('player_bet_records game_logs query failed: ' . $conn->error);
            }
        }

        // Fallback schema: raw game_bet_history API rows. It does not contain status/bet_amount,
        // so we aggregate per round/game to make the page usable instead of crashing.
        if (player_table_exists($conn, 'game_bet_history')
            && player_column_exists($conn, 'game_bet_history', 'type')
            && player_column_exists($conn, 'game_bet_history', 'amount')) {

            // The raw history cannot reliably identify currently pending bets; return empty for active tab.
            if ($mode === 'active' || $mode === 'pending' || $mode === 'unsettled') {
                return [];
            }

            $joinGames = player_table_exists($conn, 'games') ? "LEFT JOIN games g ON gbh.game_uid = g.game_uid" : "";
            $providerExpr = $joinGames ? "COALESCE(NULLIF(MAX(g.provider_id),''), 'Unknown')" : "'Unknown'";
            $gameNameExpr = $joinGames ? "COALESCE(NULLIF(MAX(g.name),''), NULLIF(gbh.game_uid,''), 'Casino Game')" : "COALESCE(NULLIF(gbh.game_uid,''), 'Casino Game')";

            $sql = "SELECT MAX(gbh.id) AS id,
                           gbh.user_id,
                           gbh.game_uid,
                           $gameNameExpr AS game_name,
                           $providerExpr AS provider_name,
                           SUM(CASE WHEN gbh.type='bet' THEN gbh.amount ELSE 0 END) AS bet_amount,
                           SUM(CASE WHEN gbh.type='win' THEN gbh.amount ELSE 0 END) AS win_amount,
                           CASE
                             WHEN SUM(CASE WHEN gbh.type='cancel' THEN 1 ELSE 0 END) > 0 THEN 'cancelled'
                             WHEN SUM(CASE WHEN gbh.type='win' THEN gbh.amount ELSE 0 END) >= SUM(CASE WHEN gbh.type='bet' THEN gbh.amount ELSE 0 END) THEN 'win'
                             ELSE 'loss'
                           END AS status,
                           MAX(gbh.created_at) AS created_at
                    FROM game_bet_history gbh
                    $joinGames
                    WHERE gbh.user_id=$uid
                    GROUP BY gbh.round_id, gbh.game_uid, gbh.user_id
                    HAVING bet_amount > 0
                    ORDER BY created_at DESC, id DESC
                    LIMIT $limit";

            $result = $conn->query($sql);
            if ($result) return player_fetch_assoc_all($result);
            error_log('player_bet_records game_bet_history query failed: ' . $conn->error);
        }

        return $records;
    }
}
?>
