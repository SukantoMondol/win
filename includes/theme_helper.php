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

        return "<style id='dynamic-site-theme-customizer'>
            :root {
                --bg-deep: {$primary} !important;
                --bg-main: {$primary} !important;
                --bg-card: {$primary} !important;
                --teal: {$primary} !important;
                --teal-light: {$primary} !important;
                --gold: {$accent} !important;
                --gold-text: {$accent} !important;
                --gold-dark: {$accent} !important;
                --green-btn: {$primary} !important;
                --text-main: {$text} !important;
            }
            html, body {
                {$bgCss}
                color: {$text} !important;
            }

            /* Left Sidebar Container & Header */
            #sidebar, .sb-header, #desktop-sidebar {
                background: {$primary} !important;
                background-color: {$primary} !important;
                border-right: 1px solid rgba(255, 255, 255, 0.08) !important;
            }

            /* Left Sidebar Menu Tiles & Category Cards */
            .sb-menu-card, .cat-pill, .cat-card, .sidebar-card, .sidebar-box {
                background: rgba(0, 0, 0, 0.35) !important;
                border: 1px solid rgba(255, 255, 255, 0.1) !important;
            }
            .sb-menu-card:hover, .cat-pill.active {
                border-color: {$accent} !important;
                background: rgba(255, 255, 255, 0.1) !important;
            }

            /* Sidebar Icons & Brand Titles */
            .sb-card-icon, .sb-card-title, .sb-logo-text, .sec-title, .sec-title i, .cat-pill.active i {
                color: {$accent} !important;
            }

            /* Header & Bottom Navigation Bar */
            .site-header, .header-green, .app-banner-top, .bottom-nav, .bottom-nav-pill, .guest-nav, .announce-bar {
                background: {$primary} !important;
                background-color: {$primary} !important;
                border-color: rgba(255, 255, 255, 0.1) !important;
            }

            /* Bottom Nav Center Button & Action Buttons */
            .nav-center-circle, .app-banner-top-install, .btn-accent, .bg-gold, .btn-primary, .btn-register {
                background: {$accent} !important;
                color: #000000 !important;
            }
            .nav-item.active i, .nav-item.active span, .bnav-item.active {
                color: {$accent} !important;
            }
        </style>";
    }
}
?>
