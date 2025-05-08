<?php
/**
 * Plugin Name: GitSyncWP
 * Description: Backup WordPress files and database to GitHub.
 * Version: 1.0.0
 * Author: Shiv Singh
 * License: MIT
 * Requires PHP: 7.4
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

        // Add dashboard widget
        add_action('wp_dashboard_setup', [self::class, 'add_dashboard_widget']);

        // Add admin notices
        add_action('admin_notices', [self::class, 'maybe_show_setup_notice']);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'gitsyncwp') !== false) {
            // Ensure jQuery is a dependency
            wp_enqueue_script('jquery');

            // Plugin styles
            wp_enqueue_style(
                'gitsyncwp-admin-style',
                plugins_url('assets/css/admin-style.css', __FILE__),
                [],
                '1.0.0'
            );

            // Plugin scripts
            wp_enqueue_script(
                'gitsyncwp-admin-script',
                plugins_url('assets/js/admin-script.js', __FILE__),
                ['jquery'],  // Only jQuery as a dependency
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

    /**
     * Add dashboard widget
     */
    public static function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'gitsyncwp_status_widget',
            'GitSyncWP Backup Status',
            [self::class, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard widget
     */
    public static function render_dashboard_widget() {
        $github_token = get_option('gitsyncwp_github_token', '');
        $github_repo = get_option('gitsyncwp_github_repo', '');
        $last_backup = get_option('gitsyncwp_last_backup_time', '');
        
        if (empty($github_token) || empty($github_repo)) {
            echo '<p>GitSyncWP is not fully configured. <a href="' . 
                 admin_url('admin.php?page=gitsyncwp') . 
                 '">Complete setup</a>.</p>';
            return;
        }
        
        echo '<p><strong>Repository:</strong> ' . esc_html($github_repo) . '</p>';
        
        if ($last_backup) {
            echo '<p><strong>Last backup:</strong> ' . 
                 esc_html(human_time_diff(strtotime($last_backup), current_time('timestamp'))) . 
                 ' ago</p>';
        } else {
            echo '<p><strong>Status:</strong> No backups performed yet.</p>';
        }
        
        echo '<p><a href="' . admin_url('admin-post.php?action=gitsyncwp_backup') . 
             '" class="button button-primary">Backup Now</a> ' .
             '<a href="' . admin_url('admin.php?page=gitsyncwp-logs') . 
             '" class="button button-secondary">View Logs</a></p>';
    }

    /**
     * Maybe show setup notice
     */
    public static function maybe_show_setup_notice() {
        // Only show on admin pages
        if (!is_admin()) {
            return;
        }
        
        // Check if already dismissed
        if (get_option('gitsyncwp_setup_notice_dismissed')) {
            return;
        }
        
        // Check if already configured
        $github_token = get_option('gitsyncwp_github_token', '');
        $github_repo = get_option('gitsyncwp_github_repo', '');
        
        if (!empty($github_token) && !empty($github_repo)) {
            // Already configured, don't show notice
            update_option('gitsyncwp_setup_notice_dismissed', true);
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible gitsyncwp-setup-notice">
            <h3>Welcome to GitSyncWP! 🚀</h3>
            <p>Backup your WordPress site to GitHub in just a few steps.</p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=gitsyncwp'); ?>" class="button button-primary">
                    Set Up Now
                </a>
                <a href="#" class="button button-secondary gitsyncwp-dismiss-notice">
                    Dismiss
                </a>
            </p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('.gitsyncwp-dismiss-notice').on('click', function(e) {
                    e.preventDefault();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'gitsyncwp_dismiss_setup_notice',
                            nonce: '<?php echo wp_create_nonce('gitsyncwp_dismiss_notice'); ?>'
                        }
                    });
                    
                    $(this).closest('.gitsyncwp-setup-notice').remove();
                });
            });
        </script>
        <?php
    }
}

// Initialize the plugin
add_action('plugins_loaded', ['GitSyncWP_Plugin', 'init']);