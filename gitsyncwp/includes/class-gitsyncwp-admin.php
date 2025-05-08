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
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_gitsyncwp_backup', [$this, 'handle_backup']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_gitsyncwp_fetch_repositories', [$this, 'fetch_repositories_ajax']);
    }

    public function register_settings() {
        register_setting('gitsyncwp_settings', 'gitsyncwp_github_token');
        register_setting('gitsyncwp_settings', 'gitsyncwp_github_repo');
    }

    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'GitSyncWP',
            'GitSyncWP',
            'manage_options',
            'gitsyncwp',
            [$this, 'render_admin_page'],
            'dashicons-backup',
            100
        );

        // Sync Logs submenu
        add_submenu_page(
            'gitsyncwp',
            'Sync Logs',
            'Sync Logs',
            'manage_options',
            'gitsyncwp-logs',
            [$this, 'render_logs_page']
        );

        // Help/FAQ submenu
        add_submenu_page(
            'gitsyncwp',
            'Help & FAQ',
            'Help & FAQ',
            'manage_options',
            'gitsyncwp-help',
            [$this, 'render_help_page']
        );
    }

    public function render_admin_page() {
        $github_token = get_option('gitsyncwp_github_token', '');
        $github_repo = get_option('gitsyncwp_github_repo', '');
        ?>
        <div class="wrap">
            <h1>GitSyncWP Settings</h1>
            
            <!-- GitHub Settings Form -->
            <div class="gitsyncwp-settings-container">
                <form method="post" action="options.php">
                    <?php settings_fields('gitsyncwp_settings'); ?>
                    
                    <!-- Step 1: GitHub Token -->
                    <div class="gitsyncwp-section">
                        <h2><span class="dashicons dashicons-admin-network"></span> Step 1: Connect to GitHub</h2>
                        <div class="gitsyncwp-token-container">
                            <div class="gitsyncwp-input-group">
                                <input type="password" 
                                       id="gitsyncwp_github_token" 
                                       name="gitsyncwp_github_token" 
                                       value="<?php echo esc_attr($github_token); ?>" 
                                       class="regular-text"
                                       placeholder="Enter your GitHub Personal Access Token"
                                       required>
                                <button type="button" id="fetch-repos" class="button button-secondary">
                                    <span class="dashicons dashicons-update"></span> Validate & Fetch Repos
                                </button>
                            </div>
                            <!-- Add token feedback container -->
                            <div id="token-feedback" class="notice" style="display: none;"></div>
                            <p class="description">
                                <a href="<?php echo admin_url('admin.php?page=gitsyncwp-help'); ?>" class="token-help-link">
                                    <span class="dashicons dashicons-info"></span> How to generate a token?
                                </a>
                            </p>
                        </div>
                    </div>

                    <!-- Step 2: Repository Selection -->
                    <div class="gitsyncwp-section">
                        <h2><span class="dashicons dashicons-portfolio"></span> Step 2: Select Repository</h2>
                        <div class="gitsyncwp-repo-container">
                            <select id="gitsyncwp_github_repo" 
                                    name="gitsyncwp_github_repo" 
                                    class="regular-text select2-repo" 
                                    required>
                                <option value="">Select a repository</option>
                                <?php if ($github_token): ?>
                                    <?php $repositories = $this->get_github_repositories($github_token); ?>
                                    <?php foreach ($repositories as $repo): ?>
                                        <?php 
                                            $icon = $repo['private'] ? '🔒' : '🌐';
                                            $description = isset($repo['description']) ? $repo['description'] : '';
                                            $updated_at = isset($repo['updated_at']) ? date('Y-m-d', strtotime($repo['updated_at'])) : '';
                                        ?>
                                        <option value="<?php echo esc_attr($repo['full_name']); ?>" 
                                                data-description="<?php echo esc_attr($description); ?>"
                                                data-updated="<?php echo esc_attr($updated_at); ?>"
                                                data-private="<?php echo $repo['private'] ? '1' : '0'; ?>"
                                                <?php selected($github_repo, $repo['full_name']); ?>>
                                            <?php echo esc_html($repo['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div id="repo-loading" style="display: none;">
                                <span class="spinner is-active"></span> Loading repositories...
                            </div>
                        </div>
                    </div>

                    <!-- Save Settings Button -->
                    <div class="gitsyncwp-section">
                        <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>

            <!-- Backup Control (only shown after setup) -->
            <?php if ($github_token && $github_repo): ?>
                <div class="gitsyncwp-section backup-section">
                    <h2><span class="dashicons dashicons-backup"></span> Backup Control</h2>
                    <div class="backup-container">
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <input type="hidden" name="action" value="gitsyncwp_backup">
                            <?php submit_button('Start Backup Now', 'primary large', 'backup-button', false); ?>
                        </form>
                    </div>
                </div>

                <!-- Last Sync Status -->
                <div class="gitsyncwp-section">
                    <h2><span class="dashicons dashicons-clock"></span> Last Sync Status</h2>
                    <div class="sync-log-container">
                        <pre><?php echo esc_html(get_option('gitsyncwp_last_log', 'No syncs yet.')); ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get GitHub repositories using API
     * 
     * @param string $token GitHub token
     * @return array
     */
    private function get_github_repositories($token) {
        if (empty($token)) {
            return [];
        }

        // First, try to get installation access repositories (fine-grained token)
        $api_url = 'https://api.github.com/installation/repositories';
        $response = wp_remote_get(
            $api_url,
            [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $token),
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'GitSyncWP',
                    // Prevent caching
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache'
                ],
                'timeout' => 15
            ]
        );

        // If installation access fails, try listing accessible repositories
        if (wp_remote_retrieve_response_code($response) !== 200) {
            $api_url = 'https://api.github.com/user/repos';
            $args = [
                'per_page' => 100,
                'sort' => 'full_name',
                'visibility' => 'all',
                // Add timestamp to prevent caching
                '_nocache' => time()
            ];
            
            $response = wp_remote_get(
                add_query_arg($args, $api_url),
                [
                    'headers' => [
                        'Authorization' => sprintf('Bearer %s', $token),
                        'Accept' => 'application/vnd.github.v3+json',
                        'User-Agent' => 'GitSyncWP',
                        // Prevent caching
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache'
                    ],
                    'timeout' => 15
                ]
            );
        }

        if (is_wp_error($response)) {
            return [];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $repos = json_decode($body, true);

        // For installation access, repositories are nested
        if (isset($repos['repositories'])) {
            $repos = $repos['repositories'];
        }

        // Filter repositories to only those with admin/push access
        return array_filter($repos, function($repo) {
            return isset($repo['permissions']) && 
                   ($repo['permissions']['admin'] === true || 
                    $repo['permissions']['push'] === true);
        });
    }

    /**
     * AJAX handler for fetching repositories
     */
    public function fetch_repositories_ajax() {
        try {
            check_ajax_referer('gitsyncwp_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized access']);
                return;
            }

            $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
            if (empty($token)) {
                wp_send_json_error(['message' => 'Token is required']);
                return;
            }

            $repositories = $this->get_github_repositories($token);
            
            if (empty($repositories)) {
                wp_send_json_error(['message' => 'No repositories found or invalid token']);
                return;
            }

            wp_send_json_success($repositories);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function render_logs_page() {
        // Get all logs (stored as an array)
        $logs = get_option('gitsyncwp_sync_logs', array());
        ?>
        <div class="wrap">
            <h1>GitSyncWP - Sync Logs</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="post" action="">
                        <?php wp_nonce_field('clear_logs', 'gitsyncwp_nonce'); ?>
                        <input type="submit" name="clear_logs" class="button" value="Clear All Logs">
                    </form>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="3">No sync logs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td>
                                    <span class="status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo esc_html(ucfirst($log['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo nl2br(esc_html($log['message'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
            .status-success { color: #46b450; }
            .status-error { color: #dc3232; }
            .status-warning { color: #ffb900; }
        </style>
        <?php
    }

    public function render_help_page() {
        ?>
        <div class="wrap gitsyncwp-help-wrap">
            <h1>GitSyncWP - Help & FAQ</h1>
            
            <div class="gitsyncwp-accordion">
                <!-- Personal Access Token Section -->
                <div class="gitsyncwp-accordion-item">
                    <button class="gitsyncwp-accordion-header" type="button">
                        How to Create a GitHub Personal Access Token (Fine-grained)
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="gitsyncwp-accordion-content">
                        <h3>Steps to Create a Fine-grained Personal Access Token:</h3>
                        <ol>
                            <li>Go to <a href="https://github.com/settings/tokens?type=beta" target="_blank">GitHub Token Settings (Fine-grained)</a></li>
                            <li>Click "Generate new token"</li>
                            <li>Add a token name like "GitSyncWP Backup"</li>
                            <li>Set expiration as needed (recommended: 90 days)</li>
                            <li>Select your repository under "Repository access"
                                <ul>
                                    <li>Choose "Only select repositories"</li>
                                    <li>Select the repository you want to backup to</li>
                                </ul>
                            </li>
                            <li>Under "Permissions", set these:
                                <ul>
                                    <li>Repository permissions:
                                        <ul>
                                            <li>✓ Contents: Read and Write</li>
                                            <li>✓ Metadata: Read-only</li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                            <li>Click "Generate token"</li>
                            <li>Copy the token immediately (you won't see it again!)</li>
                        </ol>
                        <div class="notice notice-warning">
                            <p><strong>Important:</strong> Store your token safely. Never share it or commit it to version control.</p>
                        </div>
                        <div class="notice notice-info">
                            <p><strong>Why Fine-grained Tokens?</strong> They are more secure as they:
                                <ul>
                                    <li>✓ Allow repository-specific access</li>
                                    <li>✓ Provide granular permissions</li>
                                    <li>✓ Have mandatory expiration dates</li>
                                    <li>✓ Can be audited more effectively</li>
                                </ul>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Repository Setup Section -->
                <div class="gitsyncwp-accordion-item">
                    <button class="gitsyncwp-accordion-header" type="button">
                        Setting Up Your GitHub Repository
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="gitsyncwp-accordion-content">
                        <h3>Creating a Repository for Backups:</h3>
                        <ol>
                            <li>Go to <a href="https://github.com/new" target="_blank">GitHub New Repository</a></li>
                            <li>Name your repository (e.g., "wp-site-backup")</li>
                            <li>Choose visibility (private recommended)</li>
                            <li>Initialize with a README</li>
                            <li>Copy the repository name in format: username/repository</li>
                        </ol>
                        <div class="notice notice-info">
                            <p><strong>Tip:</strong> Use a private repository to keep your site data secure.</p>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting Section -->
                <div class="gitsyncwp-accordion-item">
                    <button class="gitsyncwp-accordion-header" type="button">
                        Common Issues & Troubleshooting
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="gitsyncwp-accordion-content">
                        <h3>Common Problems:</h3>
                        <dl>
                            <dt>⚠️ "Authentication Failed" Error</dt>
                            <dd>
                                - Verify your token has correct permissions<br>
                                - Check if token hasn't expired<br>
                                - Ensure repository name is correct
                            </dd>

                            <dt>⚠️ "Repository Not Found" Error</dt>
                            <dd>
                                - Double-check repository name format (username/repo)<br>
                                - Verify repository exists and is accessible<br>
                                - Check if token has repository access
                            </dd>

                            <dt>⚠️ Backup Process Times Out</dt>
                            <dd>
                                - Check your server's max execution time<br>
                                - Consider excluding large files<br>
                                - Contact your hosting provider
                            </dd>
                        </dl>
                    </div>
                </div>

                <!-- Best Practices Section -->
                <div class="gitsyncwp-accordion-item">
                    <button class="gitsyncwp-accordion-header" type="button">
                        Best Practices & Tips
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                    </button>
                    <div class="gitsyncwp-accordion-content">
                        <h3>Recommended Practices:</h3>
                        <ul>
                            <li>🔒 Always use private repositories for backups</li>
                            <li>📅 Regularly rotate your GitHub tokens</li>
                            <li>🗄️ Exclude unnecessary files (uploads, cache)</li>
                            <li>📊 Monitor backup logs regularly</li>
                            <li>💾 Keep multiple backup points</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_backup() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $github_token = get_option('gitsyncwp_github_token');
        $github_repo = get_option('gitsyncwp_github_repo');

        if (!$github_token || !$github_repo) {
            wp_die('GitHub settings are not configured');
        }

        $backup = new GitSyncWP_Backup();
        $log = $backup->run_backup($github_token, $github_repo);

        // Store the log with timestamp
        $logs = get_option('gitsyncwp_sync_logs', array());
        array_unshift($logs, array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'status' => strpos($log, 'Failed') !== false ? 'error' : 'success',
            'message' => $log
        ));

        // Keep only last 50 logs
        if (count($logs) > 50) {
            array_pop($logs);
        }

        update_option('gitsyncwp_sync_logs', $logs);
        update_option('gitsyncwp_last_log', $log);

        wp_redirect(admin_url('admin.php?page=gitsyncwp'));
        exit;
    }
}