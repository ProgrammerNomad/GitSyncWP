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
        add_action('wp_ajax_gitsyncwp_process_backup_step', [$this, 'process_backup_step_ajax']);
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

        // Add hidden backup progress page
        add_submenu_page(
            null, // No parent menu
            'Backup Progress',
            'Backup Progress',
            'manage_options',
            'gitsyncwp-backup-progress',
            [$this, 'render_backup_progress_page']
        );
    }

    public function render_admin_page() {
        // Check if settings were just saved
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Settings saved successfully!</strong> Your GitHub backup configuration has been updated.</p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=gitsyncwp_backup')); ?>" class="button button-primary">
                        Run Backup Now
                    </a>
                </p>
            </div>
            <?php
        }

        $github_token = get_option('gitsyncwp_github_token', '');
        $github_repo = get_option('gitsyncwp_github_repo', '');
        $last_backup = get_option('gitsyncwp_last_backup_time', '');
        ?>
        <div class="wrap">
            <h1>GitSyncWP Settings</h1>

            <div class="gitsyncwp-setup-progress">
                <ul class="setup-steps">
                    <li class="<?php echo empty($github_token) ? '' : 'completed'; ?>">
                        <span class="step-number">1</span>
                        <span class="step-title">GitHub Token</span>
                    </li>
                    <li class="<?php echo empty($github_repo) ? '' : 'completed'; ?>">
                        <span class="step-number">2</span>
                        <span class="step-title">Repository</span>
                    </li>
                    <li class="<?php echo (empty($github_token) || empty($github_repo)) ? '' : 'completed'; ?>">
                        <span class="step-number">3</span>
                        <span class="step-title">Ready for Backup</span>
                    </li>
                </ul>
            </div>
            
            <?php if (!empty($github_token) && !empty($github_repo)): ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Status:</strong> 
                    <?php if ($last_backup): ?>
                        Last backup completed on <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_backup))); ?>.
                    <?php else: ?>
                        No backups have been performed yet.
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin-post.php?action=gitsyncwp_backup')); ?>" class="button button-secondary">Backup Now</a>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="gitsyncwp-settings-container">
                <form method="post" action="options.php">
                    <?php settings_fields('gitsyncwp_settings'); ?>
                    
                    <!-- GitHub Token -->
                    <div class="gitsyncwp-section">
                        <h2><span class="dashicons dashicons-admin-network"></span> Step 1: Enter GitHub Token</h2>
                        <div class="input-with-tooltip">
                            <input type="password" 
                                   id="gitsyncwp_github_token" 
                                   name="gitsyncwp_github_token" 
                                   value="<?php echo esc_attr($github_token); ?>" 
                                   class="regular-text"
                                   placeholder="Enter your GitHub Personal Access Token"
                                   required>
                            <div class="gitsyncwp-tooltip">
                                <span class="dashicons dashicons-editor-help"></span>
                                <span class="tooltip-text">GitHub token with repository access permissions. Create one at GitHub.com.</span>
                            </div>
                        </div>
                        <p class="description">
                            <a href="<?php echo admin_url('admin.php?page=gitsyncwp-help'); ?>" class="token-help-link">
                                <span class="dashicons dashicons-info-outline"></span>
                                Learn how to create a Fine-grained Personal Access Token with proper permissions
                            </a>
                        </p>
                    </div>

                    <!-- Repository URL -->
                    <div class="gitsyncwp-section">
                        <h2><span class="dashicons dashicons-portfolio"></span> Step 2: Enter Repository URL</h2>
                        <input type="text" 
                               id="gitsyncwp_github_repo" 
                               name="gitsyncwp_github_repo" 
                               value="<?php echo esc_attr($github_repo); ?>" 
                               class="regular-text"
                               placeholder="Enter repository (e.g., username/repo-name)"
                               required>
                        <p class="description">
                            <a href="<?php echo admin_url('admin.php?page=gitsyncwp-help'); ?>#repo-setup" class="token-help-link">
                                <span class="dashicons dashicons-info-outline"></span>
                                Need help setting up a repository?
                            </a>
                        </p>
                    </div>

                    <!-- Validate Button -->
                    <div class="gitsyncwp-section">
                        <button type="button" id="validate-repo" class="button button-primary">
                            <span class="dashicons dashicons-yes"></span> Validate
                        </button>
                        <div id="validation-feedback" class="notice" style="display: none;"></div>
                    </div>

                    <!-- Save Settings -->
                    <div class="gitsyncwp-section">
                        <?php submit_button('Save Settings', 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>
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

        // Check if a specific repository is selected
        $selected_repo = isset($_POST['selected_repo']) ? sanitize_text_field($_POST['selected_repo']) : '';
        if (!empty($selected_repo)) {
            $api_url = 'https://api.github.com/repos/' . $selected_repo;
            $response = wp_remote_get(
                $api_url,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/vnd.github.v3+json',
                        'User-Agent' => 'GitSyncWP',
                        'Cache-Control' => 'no-cache'
                    ],
                    'timeout' => 15
                ]
            );

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $repo = json_decode(wp_remote_retrieve_body($response), true);

                // Check if the token has write access to the repository
                if (isset($repo['permissions']) && ($repo['permissions']['push'] || $repo['permissions']['admin'])) {
                    return [$repo];
                }
            }
        }

        // If no specific repository is selected, fetch all accessible repositories
        $api_url = 'https://api.github.com/user/repos';
        $response = wp_remote_get(
            $api_url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'GitSyncWP',
                    'Cache-Control' => 'no-cache'
                ],
                'timeout' => 15
            ]
        );

        if (is_wp_error($response)) {
            return [];
        }

        $repos = json_decode(wp_remote_retrieve_body($response), true);

        // Filter repositories with push access
        return array_filter($repos, function($repo) {
            return isset($repo['permissions']) && 
                   ($repo['permissions']['push'] === true || 
                    $repo['permissions']['admin'] === true);
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
            $repo = isset($_POST['repo']) ? sanitize_text_field($_POST['repo']) : '';

            if (empty($token)) {
                wp_send_json_error(['message' => 'Please enter a valid GitHub token']);
                return;
            }
            
            if (empty($repo)) {
                wp_send_json_error(['message' => 'Please enter a repository name (format: username/repository)']);
                return;
            }

            // Validate repository format (username/repository)
            if (!preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $repo)) {
                wp_send_json_error(['message' => 'Invalid repository format. Please use format: username/repository']);
                return;
            }

            $api_url = 'https://api.github.com/repos/' . $repo;
            $response = wp_remote_get(
                $api_url,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/vnd.github+json',
                        'User-Agent' => 'GitSyncWP',
                        'Cache-Control' => 'no-cache'
                    ],
                    'timeout' => 15
                ]
            );

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                wp_send_json_error(['message' => 'Connection error: ' . $error_message]);
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            // Handle different error codes with specific messages
            if ($response_code === 404) {
                wp_send_json_error(['message' => 'Repository not found. Please check the repository name.']);
                return;
            } else if ($response_code === 401) {
                wp_send_json_error(['message' => 'Invalid token. The token provided is incorrect or expired.']);
                return;
            } else if ($response_code === 403) {
                $message = 'Access denied. ';
                if (isset($response_data['message'])) {
                    if (strpos($response_data['message'], 'rate limit') !== false) {
                        $message .= 'GitHub API rate limit exceeded. Please try again later.';
                    } else {
                        $message .= 'Token does not have permission to access this repository.';
                    }
                }
                wp_send_json_error(['message' => $message]);
                return;
            } else if ($response_code !== 200) {
                $message = isset($response_data['message']) ? $response_data['message'] : 'Unknown error occurred';
                wp_send_json_error(['message' => 'GitHub API error: ' . $message]);
                return;
            }

            // Check repository permissions
            if (!isset($response_data['permissions'])) {
                wp_send_json_error(['message' => 'Unable to verify repository permissions. Please check your token.']);
                return;
            }

            if (!$response_data['permissions']['push']) {
                wp_send_json_error(['message' => 'Token does not have write access to the repository. Please update permissions to include "Contents: Read and write"']);
                return;
            }

            wp_send_json_success(['message' => 'Token and repository validated successfully! Repository is ready for backup.']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
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
            wp_die('GitHub settings are not configured. Please configure your token and repository first.');
        }

        // Initialize backup session
        $backup_id = 'backup_' . time();
        update_option('gitsyncwp_current_backup_id', $backup_id);
        
        // Prepare initial backup state
        $backup_state = [
            'id' => $backup_id,
            'status' => 'initializing',
            'started_at' => current_time('mysql'),
            'completed_at' => '',
            'current_step' => 'init',
            'progress' => 0,
            'total_files' => 0,
            'processed_files' => 0,
            'successful_files' => 0,
            'failed_files' => 0,
            'log' => "Starting backup process at " . current_time('mysql') . "\n"
        ];
        
        update_option('gitsyncwp_backup_state_' . $backup_id, $backup_state);
        
        // Redirect to backup progress page
        wp_redirect(admin_url('admin.php?page=gitsyncwp-backup-progress&backup_id=' . $backup_id));
        exit;
    }

    public function render_backup_progress_page() {
        $backup_id = isset($_GET['backup_id']) ? sanitize_text_field($_GET['backup_id']) : '';
        if (empty($backup_id)) {
            wp_redirect(admin_url('admin.php?page=gitsyncwp'));
            exit;
        }
        
        $backup_state = get_option('gitsyncwp_backup_state_' . $backup_id, []);
        if (empty($backup_state)) {
            wp_redirect(admin_url('admin.php?page=gitsyncwp'));
            exit;
        }
        
        ?>
        <div class="wrap">
            <h1>GitSyncWP - Backup Progress</h1>
            
            <div class="gitsyncwp-progress-container">
                <div class="gitsyncwp-progress-bar">
                    <div class="gitsyncwp-progress-fill" style="width: 0%;">0%</div>
                </div>
                
                <div class="gitsyncwp-progress-details">
                    <p class="gitsyncwp-progress-status">Initializing backup...</p>
                    <p class="gitsyncwp-progress-counter">Processed: <span id="processed-count">0</span> / <span id="total-count">0</span></p>
                </div>
                
                <div class="gitsyncwp-progress-log">
                    <h3>Backup Log</h3>
                    <pre id="backup-log"></pre>
                </div>
            </div>
        </div>
        
        <style>
            .gitsyncwp-progress-container {
                max-width: 800px;
                margin: 20px 0;
            }
            
            .gitsyncwp-progress-bar {
                height: 30px;
                background-color: #f0f0f0;
                border-radius: 3px;
                padding: 0;
                margin: 20px 0;
                overflow: hidden;
            }
            
            .gitsyncwp-progress-fill {
                height: 100%;
                background-color: #0073aa;
                color: white;
                text-align: center;
                line-height: 30px;
                transition: width 0.5s;
            }
            
            .gitsyncwp-progress-log {
                max-height: 300px;
                overflow-y: auto;
                background: #f8f8f8;
                padding: 10px;
                border: 1px solid #ddd;
                margin-top: 20px;
            }
            
            .gitsyncwp-progress-log pre {
                margin: 0;
                white-space: pre-wrap;
                font-family: monospace;
                font-size: 12px;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var backup_id = '<?php echo $backup_id; ?>';
            var processStep = function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'gitsyncwp_process_backup_step',
                        backup_id: backup_id,
                        nonce: gitsyncwpAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            updateProgress(response.data);
                            
                            if (response.data.status === 'completed' || response.data.status === 'failed') {
                                finishBackup(response.data);
                            } else {
                                // Continue with next step
                                setTimeout(processStep, 1000);
                            }
                        } else {
                            // Handle error
                            $('#backup-log').prepend("Error: " + (response.data.message || "Unknown error") + "\n");
                            finishBackup({status: 'failed'});
                        }
                    },
                    error: function() {
                        $('#backup-log').prepend("Connection error. Retrying...\n");
                        // Retry after delay
                        setTimeout(processStep, 5000);
                    }
                });
            };
            
            var updateProgress = function(data) {
                var progress = data.progress || 0;
                $('.gitsyncwp-progress-fill').css('width', progress + '%').text(progress + '%');
                
                $('.gitsyncwp-progress-status').text(getStatusText(data.current_step, data.status));
                $('#processed-count').text(data.processed_files);
                $('#total-count').text(data.total_files);
                
                if (data.log) {
                    $('#backup-log').html(data.log);
                }
            };
            
            var getStatusText = function(step, status) {
                if (status === 'failed') return 'Backup failed';
                if (status === 'completed') return 'Backup completed successfully';
                
                switch(step) {
                    case 'init': return 'Initializing backup...';
                    case 'database': return 'Backing up database...';
                    case 'files_scan': return 'Scanning files...';
                    case 'files_backup': return 'Backing up files...';
                    default: return 'Processing backup...';
                }
            };
            
            var finishBackup = function(data) {
                if (data.status === 'completed') {
                    $('.gitsyncwp-progress-status').html('<span style="color: #46b450;">✓ Backup completed successfully!</span>');
                } else {
                    $('.gitsyncwp-progress-status').html('<span style="color: #dc3232;">✗ Backup failed. See log for details.</span>');
                }
                
                // Add a link to view logs
                $('.gitsyncwp-progress-details').append('<p><a href="<?php echo admin_url('admin.php?page=gitsyncwp-logs'); ?>" class="button button-primary">View All Logs</a></p>');
            };
            
            // Start the process
            processStep();
        });
        </script>
        <?php
    }

    // Make sure this function is registered to handle the WordPress AJAX action
    public function process_backup_step_ajax() {
        try {
            check_ajax_referer('gitsyncwp_ajax_nonce', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Unauthorized access']);
                return;
            }
            
            $backup_id = isset($_POST['backup_id']) ? sanitize_text_field($_POST['backup_id']) : '';
            if (empty($backup_id)) {
                wp_send_json_error(['message' => 'Invalid backup ID']);
                return;
            }
            
            $state = get_option('gitsyncwp_backup_state_' . $backup_id, []);
            if (empty($state)) {
                wp_send_json_error(['message' => 'Backup state not found']);
                return;
            }

            // Set a higher per-request time limit
            @set_time_limit(120);
            
            // Process the next step based on current state
            $backup = new GitSyncWP_Backup();
            
            // Add debugging information
            error_log('GitSyncWP: Processing backup step: ' . $state['current_step'] . ' - Progress: ' . $state['progress'] . '%');
            
            // Process step with a smaller chunk size to prevent timeouts
            if ($state['current_step'] === 'files_backup' && isset($state['file_chunk_size'])) {
                // Reduce chunk size for better reliability
                $state['file_chunk_size'] = 5;
            }
            
            $state = $backup->process_step($state);
            
            // Save updated state
            update_option('gitsyncwp_backup_state_' . $backup_id, $state);
            
            // Return current state to the client
            wp_send_json_success($state);
        } catch (Exception $e) {
            error_log('GitSyncWP backup error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}