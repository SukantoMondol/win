<?php
/**
 * Gamblly Auto Setup & Database Seeding Helper.
 * This runs automatically on page load to configure the database
 * with the correct Gamblly API keys and seed all provider games.
 */

if (!function_exists('gamblly_auto_setup_run')) {
    function gamblly_auto_setup_run($conn) {
        // Only run setup if game_api_provider is not set to GAMBLLY,
        // or if the api_token does not match the active key.
        $res = @$conn->query("SELECT setting_value FROM game_settings WHERE setting_key='game_api_provider' LIMIT 1");
        $currentProvider = '';
        if ($res && $res->num_rows > 0) {
            $currentProvider = trim((string)$res->fetch_assoc()['setting_value']);
        }

        $resKey = @$conn->query("SELECT setting_value FROM game_settings WHERE setting_key='api_token' LIMIT 1");
        $currentKey = '';
        if ($resKey && $resKey->num_rows > 0) {
            $currentKey = trim((string)$resKey->fetch_assoc()['setting_value']);
        }

        $resEndpoint = @$conn->query("SELECT setting_value FROM game_settings WHERE setting_key='api_endpoint' LIMIT 1");
        $currentEndpoint = '';
        if ($resEndpoint && $resEndpoint->num_rows > 0) {
            $currentEndpoint = trim((string)$resEndpoint->fetch_assoc()['setting_value']);
        }

        $resLaunchUrl = @$conn->query("SELECT setting_value FROM game_settings WHERE setting_key='gamblly_launch_url' LIMIT 1");
        $currentLaunchUrl = '';
        if ($resLaunchUrl && $resLaunchUrl->num_rows > 0) {
            $currentLaunchUrl = trim((string)$resLaunchUrl->fetch_assoc()['setting_value']);
        }

        $expectedKey = '07d92b12ebaCodeHub944d2237b6af09';
        $expectedEndpoint = 'https://game.gambllyapi.com/production/v1/gameLaunch.php';

        if ($currentProvider !== 'GAMBLLY' || $currentKey !== $expectedKey || $currentEndpoint !== $expectedEndpoint || ($currentLaunchUrl !== '' && $currentLaunchUrl !== $expectedEndpoint)) {
            require_once __DIR__ . '/game_api_helper.php';
            
            // Ensure the schema is ready
            game_api_ensure_schema($conn, false);

            // Update settings in database
            game_api_set_setting($conn, 'game_api_provider', 'GAMBLLY');
            game_api_set_setting($conn, 'api_token', $expectedKey);
            game_api_set_setting($conn, 'secret_key', '7605d'); // Suffix
            game_api_set_setting($conn, 'agent_code', 'hfb20f'); // Prefix
            game_api_set_setting($conn, 'api_endpoint', $expectedEndpoint);
            game_api_set_setting($conn, 'gamblly_launch_url', $expectedEndpoint);

            // Force mappings to seed so all games populate
            game_api_seed_mappings($conn);
        }
    }
}
?>
