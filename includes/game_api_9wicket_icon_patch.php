<?php
/**
 * 9Wicket icon auto patch.
 *
 * This keeps the 9Wicket game card image fixed after cPanel file replace.
 * The database row in some backups has an empty/placeholder image, so pages show
 * https://placehold.co/... text=GAME. This patch safely updates only the 9Wicket
 * game row(s) to the local uploaded icon without changing layout or provider API data.
 */
if (!function_exists('apply_9wicket_icon_patch')) {
    function apply_9wicket_icon_patch($conn) {
        if (!$conn || !is_object($conn) || (property_exists($conn, 'connect_error') && $conn->connect_error)) {
            return false;
        }

        $iconPath = '/uploads/game_icons/9wickets.jpg';

        // Make sure required columns exist before running the patch on older/custom DBs.
        $hasGames = @$conn->query("SHOW TABLES LIKE 'games'");
        if (!$hasGames || $hasGames->num_rows < 1) {
            return false;
        }

        $imageCol = @$conn->query("SHOW COLUMNS FROM games LIKE 'image'");
        if (!$imageCol || $imageCol->num_rows < 1) {
            return false;
        }

        $sql = "UPDATE games
                SET image = ?
                WHERE (
                    game_uid = '11539'
                    OR provider_id = '141'
                    OR LOWER(COALESCE(api_vendor_code,'')) IN ('9w','9wicket','9wickets','ninewicket','ninewickets')
                    OR LOWER(COALESCE(api_provider_name,'')) IN ('9w','9wicket','9wickets','ninewicket','ninewickets')
                    OR LOWER(COALESCE(name,'')) IN ('9wicket','9wickets','nine wicket','nine wickets')
                    OR LOWER(COALESCE(name,'')) LIKE '%9wicket%'
                    OR LOWER(COALESCE(name,'')) LIKE '%9wickets%'
                )
                AND COALESCE(image,'') <> ?";

        $stmt = @$conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ss', $iconPath, $iconPath);
            @$stmt->execute();
            @$stmt->close();
        }

        return true;
    }
}
?>
