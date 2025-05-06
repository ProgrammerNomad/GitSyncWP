<?php
/**
 * GitSyncWP Admin Class
 *
 * Handles the admin area functionality for the GitSyncWP plugin.
 *
 * @package GitSyncWP
 */
if (!defined('ABSPATH')) {
    exit;
}

class GitSyncWP_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_gitsyncwp_backup', [$this, 'handle_backup']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'GitSyncWP',
            'GitSyncWP',
            'manage_options',
            'gitsyncwp',
            [$this, 'render_admin_page'],
            'dashicons-backup',
            100
        );
    }

    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>GitSyncWP</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="gitsyncwp_backup">
                <?php submit_button('Backup Now'); ?>
            </form>
            <h2>Last Sync Log</h2>
            <pre><?php echo esc_html(get_option('gitsyncwp_last_log', 'No syncs yet.')); ?></pre>
        </div>
        <?php
    }

    public function handle_backup() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $backup = new GitSyncWP_Backup();
        $log = $backup->run_backup();

        update_option('gitsyncwp_last_log', $log);
        wp_redirect(admin_url('admin.php?page=gitsyncwp'));
        exit;
    }
}