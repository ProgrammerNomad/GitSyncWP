<?php
/**
 * Plugin Name: GitSyncWP
 * Description: Backup WordPress files and database to GitHub.
 * Version: 1.0.0
 * Author: Shiv Singh
 * License: MIT
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-github.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-backup.php';

// Initialize the plugin
function gitsyncwp_init() {
    new GitSyncWP_Admin();
}
add_action('plugins_loaded', 'gitsyncwp_init');