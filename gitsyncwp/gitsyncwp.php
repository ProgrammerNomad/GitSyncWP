<?php
/**
 * Plugin Name: GitSyncWP
 * Description: Backup WordPress files and database to GitHub.
 * Version: 1.0.0
 * Author: Shiv Singh
 * License: MIT
 * Requires PHP: 7.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitSyncWP_Plugin {
    /**
     * @var GitSyncWP_Admin
     */
    private static $admin_instance = null;

    /**
     * Initialize the plugin
     */
    public static function init() {
        // Include required files
        require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-admin.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-github.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-backup.php';

        // Initialize admin
        self::$admin_instance = new GitSyncWP_Admin();

        // Add AJAX handlers
        add_action('wp_ajax_gitsyncwp_fetch_repositories', [self::$admin_instance, 'fetch_repositories_ajax']);

        // Add admin scripts
        add_action('admin_enqueue_scripts', [self::class, 'admin_scripts']);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'gitsyncwp') !== false) {
            // Ensure jQuery is a dependency
            wp_enqueue_script('jquery');
            
            // Enqueue Select2
            wp_enqueue_style(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
                [],
                '4.1.0'
            );
            
            wp_enqueue_script(
                'select2',
                'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
                ['jquery'],  // Make sure jQuery is a dependency
                '4.1.0',
                true
            );

            // Plugin styles
            wp_enqueue_style(
                'gitsyncwp-admin-style',
                plugins_url('assets/css/admin-style.css', __FILE__),
                ['select2'],  // Make Select2 CSS a dependency
                '1.0.0'
            );
            
            // Plugin scripts
            wp_enqueue_script(
                'gitsyncwp-admin-script',
                plugins_url('assets/js/admin-script.js', __FILE__),
                ['jquery', 'select2'],  // Make both jQuery and Select2 dependencies
                '1.0.0',
                true
            );

            // Add AJAX nonce
            wp_localize_script(
                'gitsyncwp-admin-script',
                'gitsyncwpAjax',
                [
                    'nonce' => wp_create_nonce('gitsyncwp_ajax_nonce'),
                    'ajaxurl' => admin_url('admin-ajax.php')
                ]
            );
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', ['GitSyncWP_Plugin', 'init']);