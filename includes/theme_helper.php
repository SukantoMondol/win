<?php
/**
 * Dynamic Website Theme & Background Customizer Helper
 */

if (!function_exists('get_site_theme_css')) {
    function get_site_theme_css($settings = array()) {
        $bg = !empty($settings['theme_bg']) ? trim((string)$settings['theme_bg']) : 'linear-gradient(135deg, #071f18 0%, #0a2e22 50%, #071f18 100%)';
        $primary = !empty($settings['theme_primary']) ? trim((string)$settings['theme_primary']) : '#0d7a55';
        $accent = !empty($settings['theme_accent']) ? trim((string)$settings['theme_accent']) : '#f0c030';
        $text = !empty($settings['theme_text']) ? trim((string)$settings['theme_text']) : '#e8f5ee';

        $bgCss = (strpos($bg, 'gradient') !== false) 
            ? "background: {$bg} !important; background-attachment: fixed !important;" 
            : "background-color: {$bg} !important; background-image: none !important;";

        return "
        <!-- Dynamic Site Theme Customizer -->
        <style id='dynamic-site-theme-customizer'>
            :root {
                --bg-deep: {$primary};
                --bg-main: {$primary};
                --bg-card: {$primary};
                --teal: {$primary};
                --teal-light: {$primary};
                --gold: {$accent};
                --gold-text: {$accent};
                --gold-dark: {$accent};
                --green-btn: {$primary};
                --text-main: {$text};
            }
            body {
                {$bgCss}
                color: {$text} !important;
            }
            .site-header, .header-green, .app-banner-top {
                background: {$primary} !important;
            }
            .app-banner-top-install, .btn-accent, .bg-gold, .btn-primary {
                background: {$accent} !important;
                color: #000000 !important;
            }
        </style>
        ";
    }
}
?>
