<?php
/**
 * DP Live Sports mapping seed.
 * Vendor codes collected from the provided vendor-code screenshots:
 * - dpesports
 * - dpsports
 *
 * The existing source/SQL contains these local game rows but they were left pending,
 * so launch.php skipped them during the provider phase. Keep the existing local IDs
 * as launch game codes unless the merchant API later provides a separate 32-char UID.
 */
return array(
    array('game_name' => 'DPEsportsGaming', 'local_game_uid' => '1991', 'api_game_id' => '1991', 'api_game_code' => '1991', 'api_vendor_code' => 'dpesports', 'api_provider_name' => 'DpEsports', 'api_game_type' => 'Esports'),
    array('game_name' => 'DPSportsGaming', 'local_game_uid' => '8541', 'api_game_id' => '8541', 'api_game_code' => '8541', 'api_vendor_code' => 'dpsports', 'api_provider_name' => 'DpSports', 'api_game_type' => 'Sports Game'),
);
