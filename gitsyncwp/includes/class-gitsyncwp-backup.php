<?php
/**
 * GitSyncWP Backup Class
 *
 * Handles the backup functionality for the GitSyncWP plugin.
 *
 * @package GitSyncWP
 */
if (!defined('ABSPATH')) {
    exit;
}

class GitSyncWP_Backup {
    private $excluded_paths = [
        '.git',
        '.gitignore',
        'wp-content/uploads/backups',
        'wp-content/cache',
        'wp-content/upgrade',
        'wp-content/uploads/wp-database-backup.sql'
    ];

    public function run_backup($github_token, $github_repo) {
        // Set time limit to allow for larger backups
        @set_time_limit(600); // 10 minutes
        
        // Start log
        $log = "Starting backup process at " . current_time('mysql') . "\n";
        $log .= "-------------------------------------------\n";
        
        // Create a backup directory if it doesn't exist
        $backup_dir = WP_CONTENT_DIR . '/uploads/backups';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        // Add an index.php file to prevent directory listing
        if (!file_exists($backup_dir . '/index.php')) {
            file_put_contents($backup_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Add a .htaccess file to protect the directory
        if (!file_exists($backup_dir . '/.htaccess')) {
            file_put_contents($backup_dir . '/.htaccess', 'Deny from all');
        }
        
        try {
            // Create GitHub instance
            $github = new GitSyncWP_GitHub($github_token, $github_repo);
            
            // Step 1: Export database
            $log .= "Step 1: Exporting database...\n";
            $db_file = $this->backup_database($backup_dir);
            
            if ($db_file) {
                $log .= "✅ Database exported to: " . $db_file . "\n";
                
                // Push database file to GitHub
                $log .= "Pushing database to GitHub...\n";
                $db_push_result = $github->push_file($db_file, 'database/wp-database-backup.sql', 'Database backup from ' . home_url());
                
                if ($db_push_result === true) {
                    $log .= "✅ Database pushed to GitHub successfully.\n";
                } else {
                    $log .= "❌ Failed to push database: " . $db_push_result . "\n";
                }
            } else {
                $log .= "❌ Failed to export database.\n";
            }
            
            // Step 2: Backup WordPress files
            $log .= "\nStep 2: Backing up WordPress files...\n";
            
            // Get all WordPress files
            $files = $this->get_all_files(ABSPATH);
            $log .= "Found " . count($files) . " files to backup.\n";
            
            // Track progress
            $total_files = count($files);
            $processed_files = 0;
            $successful_pushes = 0;
            $failed_pushes = 0;
            
            // Process files in batches of 20 to avoid timeout
            $batch_size = 20;
            $total_batches = ceil($total_files / $batch_size);
            
            for ($i = 0; $i < $total_batches; $i++) {
                $batch_start = $i * $batch_size;
                $batch_files = array_slice($files, $batch_start, $batch_size);
                
                $log .= "Processing batch " . ($i + 1) . " of " . $total_batches . " (" . count($batch_files) . " files)...\n";
                
                foreach ($batch_files as $file) {
                    // Skip excluded paths
                    if ($this->is_excluded($file)) {
                        $log .= "Skipping excluded file: " . $file . "\n";
                        $processed_files++;
                        continue;
                    }
                    
                    // Get relative path to ABSPATH
                    $relative_path = str_replace(ABSPATH, '', $file);
                    
                    // Push file to GitHub
                    $push_result = $github->push_file($file, $relative_path, 'Backup WordPress file: ' . $relative_path);
                    
                    if ($push_result === true) {
                        $successful_pushes++;
                    } else {
                        $failed_pushes++;
                        $log .= "❌ Failed to push file: " . $relative_path . " - Error: " . $push_result . "\n";
                    }
                    
                    $processed_files++;
                }
                
                // Update progress
                $log .= "Progress: " . $processed_files . " / " . $total_files . " files processed.\n";
                
                // Save checkpoint log
                update_option('gitsyncwp_last_log', $log);
                
                // Prevent timeout by sleeping briefly
                usleep(100000); // 100ms
            }
            
            // Final summary
            $log .= "\nBackup Summary:\n";
            $log .= "-------------------------------------------\n";
            $log .= "Total files processed: " . $processed_files . "\n";
            $log .= "Files pushed successfully: " . $successful_pushes . "\n";
            $log .= "Files failed to push: " . $failed_pushes . "\n";
            $log .= "Backup completed at: " . current_time('mysql') . "\n";
            
            // Update last backup time
            update_option('gitsyncwp_last_backup_time', current_time('mysql'));
            
            return $log;
            
        } catch (Exception $e) {
            $log .= "❌ Error during backup process: " . $e->getMessage() . "\n";
            return $log;
        }
    }

    /**
     * Run backup with Git
     * 
     * @param string $github_token GitHub token
     * @param string $github_repo GitHub repository name
     * @param string $branch Branch to push to
     * @return string Log output
     */
    public function run_git_backup($github_token, $github_repo, $branch = 'main') {
        // Set time limit to allow for larger backups
        @set_time_limit(600); // 10 minutes
        
        // Start log
        $log = "Starting Git backup process at " . current_time('mysql') . "\n";
        $log .= "-------------------------------------------\n";
        
        try {
            // Initialize Git manager
            $git_manager = new GitSyncWP_Git_Manager($github_token, $github_repo, $branch);
            
            // Pull latest changes
            $log .= "Pulling latest changes...\n";
            $pull_result = $git_manager->pull();
            if (!$pull_result) {
                $log .= "⚠️ Warning: Could not pull latest changes. Continuing with backup.\n";
            } else {
                $log .= "✅ Latest changes pulled successfully.\n";
            }
            
            // Create a backup directory for database
            $backup_dir = WP_CONTENT_DIR . '/uploads/backups';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
            }
            
            // Step 1: Export database
            $log .= "Step 1: Exporting database...\n";
            $db_file = $this->backup_database($backup_dir);
            
            if ($db_file) {
                $log .= "✅ Database exported to: " . $db_file . "\n";
                
                // Add database file to Git
                $log .= "Adding database to Git repository...\n";
                $git_manager->add_file($db_file, 'database/wp-database-backup.sql', 'Database backup');
            } else {
                $log .= "❌ Failed to export database.\n";
            }
            
            // Step 2: Backup WordPress files
            $log .= "\nStep 2: Backing up WordPress files...\n";
            
            // Get all WordPress files
            $files = $this->get_all_files(ABSPATH);
            $log .= "Found " . count($files) . " files to backup.\n";
            
            // Track progress
            $total_files = count($files);
            $processed_files = 0;
            $successful_files = 0;
            $failed_files = 0;
            
            // Process files in batches to avoid memory issues
            $batch_size = 50;
            $total_batches = ceil($total_files / $batch_size);
            
            for ($i = 0; $i < $total_batches; $i++) {
                $batch_start = $i * $batch_size;
                $batch_files = array_slice($files, $batch_start, $batch_size);
                
                $log .= "Processing batch " . ($i + 1) . " of " . $total_batches . " (" . count($batch_files) . " files)...\n";
                
                foreach ($batch_files as $file) {
                    // Skip excluded paths
                    if ($this->is_excluded($file)) {
                        $processed_files++;
                        continue;
                    }
                    
                    // Get relative path to ABSPATH
                    $relative_path = str_replace(ABSPATH, '', $file);
                    
                    try {
                        // Add file to Git
                        $git_manager->add_file($file, $relative_path, 'Add ' . $relative_path);
                        $successful_files++;
                    } catch (Exception $e) {
                        $failed_files++;
                        $log .= "❌ Failed to add file: " . $relative_path . " - Error: " . $e->getMessage() . "\n";
                    }
                    
                    $processed_files++;
                }
                
                // Update progress
                $log .= "Progress: " . $processed_files . " / " . $total_files . " files processed.\n";
                
                // Save checkpoint log
                update_option('gitsyncwp_last_log', $log);
            }
            
            // Commit all changes
            $log .= "\nCommitting changes...\n";
            $commit_result = $git_manager->commit('WordPress backup - ' . current_time('mysql'));
            
            if ($commit_result) {
                $log .= "✅ Changes committed successfully.\n";
            } else {
                $log .= "❌ Failed to commit changes.\n";
            }
            
            // Push to remote
            $log .= "Pushing changes to GitHub...\n";
            $push_result = $git_manager->push();
            
            if ($push_result) {
                $log .= "✅ Changes pushed to GitHub successfully.\n";
            } else {
                $log .= "❌ Failed to push changes to GitHub.\n";
            }
            
            // Final summary
            $log .= "\nBackup Summary:\n";
            $log .= "-------------------------------------------\n";
            $log .= "Total files processed: " . $processed_files . "\n";
            $log .= "Files added successfully: " . $successful_files . "\n";
            $log .= "Files failed to add: " . $failed_files . "\n";
            $log .= "Backup completed at: " . current_time('mysql') . "\n";
            
            // Update last backup time
            update_option('gitsyncwp_last_backup_time', current_time('mysql'));
            
            return $log;
            
        } catch (Exception $e) {
            $log .= "❌ Error during backup process: " . $e->getMessage() . "\n";
            return $log;
        }
    }

    /**
     * Backup the WordPress database
     * 
     * @param string $backup_dir The directory to store the backup
     * @return string|bool The database backup file path or false on failure
     */
    private function backup_database($backup_dir) {
        global $wpdb;
        
        // Use timestamp for unique filename
        $timestamp = date('Y-m-d-His');
        $backup_file = $backup_dir . '/wp-database-backup-' . $timestamp . '.sql';
        
        // Create dump using WordPress database credentials
        $dump_command = sprintf(
            'mysqldump --single-transaction --no-tablespaces --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASSWORD),
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_NAME),
            escapeshellarg($backup_file)
        );
        
        // Execute the command and capture output
        $output = [];
        $return_var = 0;
        exec($dump_command . ' 2>&1', $output, $return_var);
        
        // Check if command succeeded
        if ($return_var !== 0) {
            // Log the error
            error_log('GitSyncWP DB Backup Error: ' . implode("\n", $output));
            return false;
        }
        
        // Verify file was created and has content
        if (!file_exists($backup_file) || filesize($backup_file) < 100) {
            return false;
        }
        
        return $backup_file;
    }
    
    /**
     * Get all files in a directory recursively
     * 
     * @param string $dir The directory to scan
     * @return array An array of file paths
     */
    private function get_all_files($dir) {
        $files = [];
        $di = new RecursiveDirectoryIterator($dir);
        $iterator = new RecursiveIteratorIterator($di);
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Check if a file path is excluded
     * 
     * @param string $file_path The file path to check
     * @return bool Whether the file is excluded
     */
    private function is_excluded($file_path) {
        foreach ($this->excluded_paths as $excluded_path) {
            if (strpos($file_path, $excluded_path) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function backup_files_step_with_git($state) {
        // Make sure we have a Git manager instance
        if (!isset($this->git_manager)) {
            $github_token = get_option('gitsyncwp_github_token');
            $github_repo = get_option('gitsyncwp_github_repo');
            $this->git_manager = new GitSyncWP_Git_Manager($github_token, $github_repo);
        }

        $chunk_size = $state['file_chunk_size'];
        $start = $state['processed_files'];
        $end = min($start + $chunk_size, $state['total_files']);
        
        // Get the chunk of files to process
        $files_to_process = array_slice($state['files'], $start, $chunk_size);
        
        // Initialize counters if they don't exist
        if (!isset($state['successful_files'])) {
            $state['successful_files'] = 0;
        }
        
        if (!isset($state['failed_files'])) {
            $state['failed_files'] = 0;
        }
        
        // Process each file in the chunk
        foreach ($files_to_process as $file) {
            // Skip excluded paths
            if ($this->is_excluded($file)) {
                $state['processed_files']++;
                continue;
            }
            
            // Get relative path to ABSPATH
            $relative_path = str_replace(ABSPATH, '', $file);
            
            try {
                // Add file to Git
                $this->git_manager->add_file($file, $relative_path, 'Add ' . $relative_path);
                $state['successful_files']++;
            } catch (Exception $e) {
                $state['failed_files']++;
                $state['log'] .= "❌ Failed to add file: " . $relative_path . " - Error: " . $e->getMessage() . "\n";
            }
            
            $state['processed_files']++;
        }
        
        // Calculate progress percentage
        $progress_pct = min(95, 30 + (($state['processed_files'] / $state['total_files']) * 65));
        $state['progress'] = round($progress_pct);
        
        // Check if we're done with all files
        if ($state['processed_files'] >= $state['total_files']) {
            $state['log'] .= "All files processed. Committing changes...\n";
            
            // Commit changes
            try {
                $commit_result = $this->git_manager->commit('WordPress backup - ' . current_time('mysql'));
                if ($commit_result) {
                    $state['log'] .= "✅ Changes committed successfully.\n";
                } else {
                    $state['log'] .= "❌ Failed to commit changes.\n";
                }
            } catch (Exception $e) {
                $state['log'] .= "❌ Error committing changes: " . $e->getMessage() . "\n";
            }
            
            // Push to remote
            $state['log'] .= "Pushing changes to GitHub...\n";
            try {
                $push_result = $this->git_manager->push();
                if ($push_result) {
                    $state['log'] .= "✅ Changes pushed to GitHub successfully.\n";
                } else {
                    $state['log'] .= "❌ Failed to push changes to GitHub.\n";
                }
            } catch (Exception $e) {
                $state['log'] .= "❌ Error pushing changes: " . $e->getMessage() . "\n";
            }
            
            // Finalize backup
            $state['log'] .= "\nBackup Summary:\n";
            $state['log'] .= "-------------------------------------------\n";
            $state['log'] .= "Total files processed: " . $state['processed_files'] . "\n";
            $state['log'] .= "Files added successfully: " . $state['successful_files'] . "\n";
            $state['log'] .= "Files failed to add: " . $state['failed_files'] . "\n";
            $state['log'] .= "Backup completed at: " . current_time('mysql') . "\n";
            
            $state['completed_at'] = current_time('mysql');
            $state['status'] = 'completed';
            $state['current_step'] = 'complete';
            $state['progress'] = 100;
            
            // Store in logs
            $logs = get_option('gitsyncwp_sync_logs', array());
            array_unshift($logs, array(
                'timestamp' => current_time('Y-m-d H:i:s'),
                'status' => $state['failed_files'] > 0 ? 'warning' : 'success',
                'message' => $state['log']
            ));
            
            // Keep only last 50 logs
            if (count($logs) > 50) {
                $logs = array_slice($logs, 0, 50);
            }
            
            update_option('gitsyncwp_sync_logs', $logs);
            update_option('gitsyncwp_last_log', $state['log']);
            update_option('gitsyncwp_last_backup_time', current_time('mysql'));
        }
        
        return $state;
    }

    public function process_step($state) {
        // Check if we should use Git or API-based backup
        $use_git = get_option('gitsyncwp_use_git', true);  // Default to Git
        
        if ($use_git) {
            // Initialize Git manager if needed
            if (!isset($this->git_manager)) {
                $github_token = get_option('gitsyncwp_github_token');
                $github_repo = get_option('gitsyncwp_github_repo');
                $this->git_manager = new GitSyncWP_Git_Manager($github_token, $github_repo);
            }
        } else {
            // Initialize GitHub API if needed
            if (!isset($this->github)) {
                $github_token = get_option('gitsyncwp_github_token');
                $github_repo = get_option('gitsyncwp_github_repo');
                $this->github = new GitSyncWP_GitHub($github_token, $github_repo);
            }
        }
        
        // Process different steps
        switch ($state['current_step']) {
            case 'init':
                return $this->init_backup($state);
                
            case 'database':
                return $this->backup_database_step($state);
                
            case 'files_scan':
                return $this->scan_files_step($state);
                
            case 'files_backup':
                return $use_git ? $this->backup_files_step_with_git($state) : $this->backup_files_step($state);
                
            default:
                $state['status'] = 'failed';
                $state['log'] .= "❌ Unknown backup step: {$state['current_step']}\n";
                return $state;
        }
    }

    private function init_backup($state) {
        // Create backup directory if needed
        $backup_dir = WP_CONTENT_DIR . '/uploads/backups';
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        // Add protection files
        if (!file_exists($backup_dir . '/index.php')) {
            file_put_contents($backup_dir . '/index.php', '<?php // Silence is golden');
        }
        
        if (!file_exists($backup_dir . '/.htaccess')) {
            file_put_contents($backup_dir . '/.htaccess', 'Deny from all');
        }
        
        // Update state to move to next step
        $state['current_step'] = 'database';
        $state['status'] = 'processing';
        $state['log'] .= "✓ Initialized backup process\n";
        $state['progress'] = 5;
        
        return $state;
    }

    private function backup_database_step($state) {
        // Create backup directory
        $backup_dir = WP_CONTENT_DIR . '/uploads/backups';
        
        // Run database backup
        $state['log'] .= "Backing up database...\n";
        $db_file = $this->backup_database($backup_dir);
        
        if ($db_file) {
            $state['log'] .= "✓ Database exported to: " . $db_file . "\n";
            
            // Push database to GitHub
            $state['log'] .= "Pushing database to GitHub...\n";
            $db_push_result = $this->github->push_file(
                $db_file, 
                'database/wp-database-backup.sql', 
                'Database backup from ' . home_url()
            );
            
            if ($db_push_result === true) {
                $state['log'] .= "✓ Database pushed to GitHub successfully\n";
            } else {
                $state['log'] .= "❌ Failed to push database: " . $db_push_result . "\n";
            }
        } else {
            $state['log'] .= "❌ Failed to export database\n";
        }
        
        // Move to next step
        $state['current_step'] = 'files_scan';
        $state['progress'] = 20;
        
        return $state;
    }

    private function scan_files_step($state) {
        // Scan files
        $state['log'] .= "Scanning WordPress files...\n";
        $files = $this->get_all_files(ABSPATH);
        
        // Store files in state
        $state['files'] = $files;
        $state['total_files'] = count($files);
        $state['processed_files'] = 0;
        $state['file_chunk_size'] = 10; // Process 10 files per AJAX request
        
        $state['log'] .= "Found " . count($files) . " files to backup.\n";
        
        // Move to next step
        $state['current_step'] = 'files_backup';
        $state['progress'] = 30;
        
        return $state;
    }

    private function backup_files_step($state) {
        $chunk_size = $state['file_chunk_size'];
        $start = $state['processed_files'];
        $end = min($start + $chunk_size, $state['total_files']);
        
        // Get the chunk of files to process
        $files_to_process = array_slice($state['files'], $start, $chunk_size);
        
        // Process each file in the chunk
        foreach ($files_to_process as $file) {
            // Skip excluded paths
            if ($this->is_excluded($file)) {
                $state['processed_files']++;
                continue;
            }
            
            // Get relative path to ABSPATH
            $relative_path = str_replace(ABSPATH, '', $file);
            
            // Push file to GitHub
            $push_result = $this->github->push_file(
                $file, 
                $relative_path, 
                'Backup WordPress file: ' . $relative_path
            );
            
            if ($push_result === true) {
                $state['successful_files']++;
            } else {
                $state['failed_files']++;
                $state['log'] .= "❌ Failed to push file: " . $relative_path . " - Error: " . $push_result . "\n";
            }
            
            $state['processed_files']++;
        }
        
        // Calculate progress percentage
        $progress_pct = min(95, 30 + (($state['processed_files'] / $state['total_files']) * 65));
        $state['progress'] = round($progress_pct);
        
        // Check if we're done with all files
        if ($state['processed_files'] >= $state['total_files']) {
            // Finalize backup
            $state['log'] .= "\nBackup Summary:\n";
            $state['log'] .= "-------------------------------------------\n";
            $state['log'] .= "Total files processed: " . $state['processed_files'] . "\n";
            $state['log'] .= "Files pushed successfully: " . $state['successful_files'] . "\n";
            $state['log'] .= "Files failed to push: " . $state['failed_files'] . "\n";
            $state['log'] .= "Backup completed at: " . current_time('mysql') . "\n";
            
            $state['completed_at'] = current_time('mysql');
            $state['status'] = 'completed';
            $state['current_step'] = 'complete';
            $state['progress'] = 100;
            
            // Store in logs
            $logs = get_option('gitsyncwp_sync_logs', array());
            array_unshift($logs, array(
                'timestamp' => current_time('Y-m-d H:i:s'),
                'status' => $state['failed_files'] > 0 ? 'warning' : 'success',
                'message' => $state['log']
            ));
            
            // Keep only last 50 logs
            if (count($logs) > 50) {
                $logs = array_slice($logs, 0, 50);
            }
            
            update_option('gitsyncwp_sync_logs', $logs);
            update_option('gitsyncwp_last_log', $state['log']);
            update_option('gitsyncwp_last_backup_time', current_time('mysql'));
        }
        
        return $state;
    }
}

/**
 * GitSyncWP Git Manager Class
 *
 * Handles the Git operations for the GitSyncWP plugin using czproject/git-php.
 *
 * @package GitSyncWP
 */
if (!defined('ABSPATH')) {
    exit;
}

// Check if Composer autoloader exists
if (file_exists(plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';
}

class GitSyncWP_Git_Manager {
    /**
     * The Git instance
     *
     * @var \CzProject\GitPhp\Git
     */
    private $git;
    
    /**
     * The Git repository
     *
     * @var \CzProject\GitPhp\GitRepository
     */
    private $repository;
    
    /**
     * Path to the local Git repository
     *
     * @var string
     */
    private $repo_path;
    
    /**
     * Constructor
     *
     * @param string $token GitHub token
     * @param string $github_repo GitHub repository in format username/repo
     * @param string $branch The branch to push to, defaults to main
     * @throws Exception If Git binary is not found
     */
    public function __construct($token, $github_repo, $branch = 'main') {
        $this->git = new \CzProject\GitPhp\Git();
        
        // Set up repo path in the uploads directory
        $upload_dir = wp_upload_dir();
        $this->repo_path = trailingslashit($upload_dir['basedir']) . 'gitsyncwp-repo';
        
        // Connect to existing repo or clone
        if ($this->repo_exists()) {
            $this->repository = $this->git->open($this->repo_path);
        } else {
            $this->init_repository($token, $github_repo, $branch);
        }
    }
    
    /**
     * Check if repository exists
     *
     * @return bool Whether the repository exists
     */
    private function repo_exists() {
        return is_dir($this->repo_path . '/.git');
    }
    
    /**
     * Initialize the repository (clone or create)
     *
     * @param string $token GitHub token
     * @param string $github_repo GitHub repository name
     * @param string $branch The branch to use
     * @throws Exception If repository initialization fails
     */
    private function init_repository($token, $github_repo, $branch) {
        // Create directory if it doesn't exist
        if (!file_exists($this->repo_path)) {
            wp_mkdir_p($this->repo_path);
        }
        
        try {
            // Format the GitHub URL with token
            $github_url = sprintf(
                'https://%s@github.com/%s.git',
                $token,
                $github_repo
            );
            
            // Clone the repository
            $this->repository = $this->git->cloneRepository($github_url, $this->repo_path);
            
            // Switch to the specified branch
            try {
                $this->repository->checkout($branch);
            } catch (\Exception $e) {
                // If branch doesn't exist, create it
                $this->repository->createBranch($branch, true);
            }
        } catch (\Exception $e) {
            // If cloning fails, initialize a new repository
            $this->repository = $this->git->init($this->repo_path);
            
            // Set the remote
            $this->repository->addRemote('origin', $github_url);
            
            // Create and checkout branch
            $this->repository->createBranch($branch, true);
            
            // Create initial commit if needed
            $this->create_initial_commit();
        }
    }
    
    /**
     * Create initial commit with README
     */
    private function create_initial_commit() {
        // Create README file
        $readme_content = "# WordPress Backup\n\nThis repository contains backups created by GitSyncWP plugin.\n\nCreated: " . date('Y-m-d H:i:s');
        file_put_contents($this->repo_path . '/README.md', $readme_content);
        
        // Add and commit
        $this->repository->addFile('README.md');
        $this->repository->commit('Initial commit from GitSyncWP');
        
        // Try to push
        try {
            $this->repository->push('origin', ['--set-upstream']);
        } catch (\Exception $e) {
            // Log error but continue
            error_log('GitSyncWP: Failed to push initial commit - ' . $e->getMessage());
        }
    }
    
    /**
     * Add and commit a file to the repository
     *
     * @param string $file_path Local file path
     * @param string $target_path Target path in the repository
     * @param string $commit_message Commit message
     * @return bool True on success
     * @throws Exception If operation fails
     */
    public function add_file($file_path, $target_path, $commit_message) {
        // Make sure target directory exists
        $target_dir = dirname($this->repo_path . '/' . $target_path);
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Copy the file to the repository
        copy($file_path, $this->repo_path . '/' . $target_path);
        
        // Add the file to staging
        $this->repository->addFile($target_path);
        
        return true;
    }
    
    /**
     * Commit changes
     *
     * @param string $message Commit message
     * @return bool True on success
     */
    public function commit($message) {
        try {
            $this->repository->commit($message);
            return true;
        } catch (\Exception $e) {
            error_log('GitSyncWP: Commit failed - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Push changes to remote
     *
     * @return bool True on success
     */
    public function push() {
        try {
            $this->repository->push('origin');
            return true;
        } catch (\Exception $e) {
            error_log('GitSyncWP: Push failed - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Pull latest changes from remote
     *
     * @return bool True on success
     */
    public function pull() {
        try {
            $this->repository->pull('origin');
            return true;
        } catch (\Exception $e) {
            error_log('GitSyncWP: Pull failed - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current status of the repository
     *
     * @return array Status information
     */
    public function get_status() {
        try {
            return $this->repository->getStatus();
        } catch (\Exception $e) {
            error_log('GitSyncWP: Status check failed - ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Initialize the plugin
 */
public static function init() {
    // Include required files
    require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-admin.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-github.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-backup.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-gitsyncwp-git-manager.php';

    // Initialize admin
    self::$admin_instance = new GitSyncWP_Admin();

    // Add AJAX handlers
    add_action('wp_ajax_gitsyncwp_fetch_repositories', [self::$admin_instance, 'fetch_repositories_ajax']);
    add_action('wp_ajax_gitsyncwp_process_backup_step', [self::$admin_instance, 'process_backup_step_ajax']);

    // Add admin scripts
    add_action('admin_enqueue_scripts', [self::class, 'admin_scripts']);

    // Add dashboard widget
    add_action('wp_dashboard_setup', [self::class, 'add_dashboard_widget']);

    // Add admin notices
    add_action('admin_notices', [self::class, 'maybe_show_setup_notice']);
    add_action('admin_notices', [self::class, 'show_backup_status_notice']);
}

/**
 * GitSyncWP Admin Class
 *
 * Handles the admin interface for the GitSyncWP plugin.
 *
 * @package GitSyncWP
 */
class GitSyncWP_Admin {
    /**
     * Register settings for the plugin.
     */
    public function register_settings() {
        register_setting('gitsyncwp_settings', 'gitsyncwp_github_token');
        register_setting('gitsyncwp_settings', 'gitsyncwp_github_repo');
        register_setting('gitsyncwp_settings', 'gitsyncwp_use_git', array(
            'type' => 'boolean',
            'default' => true,
        ));
    }

    /**
     * Render the admin page for the plugin.
     */
    public function render_admin_page() {
        // Existing code...

        $github_token = get_option('gitsyncwp_github_token', '');
        $github_repo = get_option('gitsyncwp_github_repo', '');
        $last_backup = get_option('gitsyncwp_last_backup_time', '');
        $use_git = get_option('gitsyncwp_use_git', true);
        
        // Form HTML...

        // Add toggle switch for Git/API
        ?>
        <!-- Advanced Options -->
        <div class="gitsyncwp-section">
            <h2><span class="dashicons dashicons-admin-tools"></span> Advanced Options</h2>
            
            <label for="gitsyncwp_use_git" class="toggle-switch">
                <input type="checkbox" 
                      id="gitsyncwp_use_git" 
                      name="gitsyncwp_use_git" 
                      value="1" 
                      <?php checked($use_git); ?>>
                Use Git-based backup (recommended)
            </label>
            <p class="description">
                Using Git-based backup requires Git to be installed on your server, but provides more reliable backups.
            </p>
        </div>
        <?php
        // Rest of the form...
    }
}