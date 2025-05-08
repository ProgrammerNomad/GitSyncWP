<?php
/**
 * GitSyncWP CLI Commands
 *
 * @package GitSyncWP
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Implements GitSyncWP WP-CLI commands.
 */
class GitSyncWP_CLI extends WP_CLI_Command {
    /**
     * Runs a backup of WordPress files and database to GitHub.
     *
     * ## OPTIONS
     *
     * [--files]
     * : Backup only files (skip database)
     *
     * [--db]
     * : Backup only database (skip files)
     *
     * [--exclude=<dir1,dir2>]
     * : Additional directories to exclude (comma separated)
     * 
     * [--use-git]
     * : Use Git-based backup instead of GitHub API
     *
     * ## EXAMPLES
     *
     *     # Run a full backup
     *     $ wp gitsyncwp backup
     *
     *     # Backup only database
     *     $ wp gitsyncwp backup --db
     *
     *     # Backup only files, excluding specific directories
     *     $ wp gitsyncwp backup --files --exclude=wp-content/uploads,wp-content/cache
     *
     *     # Run a full backup with Git
     *     $ wp gitsyncwp backup --use-git
     */
    public function backup($args, $assoc_args) {
        $github_token = get_option('gitsyncwp_github_token');
        $github_repo = get_option('gitsyncwp_github_repo');

        if (!$github_token || !$github_repo) {
            WP_CLI::error('GitHub settings are not configured. Please configure your token and repository first.');
            return;
        }

        $backup_db = true;
        $backup_files = true;

        // Check command options
        if (isset($assoc_args['files']) && !isset($assoc_args['db'])) {
            $backup_db = false;
            WP_CLI::log('Running files-only backup...');
        } elseif (isset($assoc_args['db']) && !isset($assoc_args['files'])) {
            $backup_files = false;
            WP_CLI::log('Running database-only backup...');
        } else {
            WP_CLI::log('Running full backup (files + database)...');
        }

        // Get extra exclusions
        $extra_exclusions = [];
        if (isset($assoc_args['exclude'])) {
            $extra_exclusions = explode(',', $assoc_args['exclude']);
            $extra_exclusions = array_map('trim', $extra_exclusions);
            WP_CLI::log('Extra exclusions: ' . implode(', ', $extra_exclusions));
        }

        // Check if we should use Git-based backup
        $use_git = WP_CLI\Utils\get_flag_value($assoc_args, 'use-git', get_option('gitsyncwp_use_git', true));
        
        // If using Git, switch to Git-based backup
        if ($use_git) {
            WP_CLI::log('Using Git-based backup...');
            
            try {
                // Start Git-based backup
                $git_manager = new GitSyncWP_Git_Manager($github_token, $github_repo);
                
                // Pull latest changes
                WP_CLI::log('Pulling latest changes...');
                $pull_result = $git_manager->pull();
                if (!$pull_result) {
                    WP_CLI::warning('Could not pull latest changes. Continuing with backup.');
                }
                
                // Rest of the backup process...
            } catch (Exception $e) {
                WP_CLI::error('Git operation failed: ' . $e->getMessage());
            }
        } else {
            // Start backup
            $backup = new GitSyncWP_Backup();
            if (!empty($extra_exclusions)) {
                $backup->add_exclusions($extra_exclusions);
            }

            // Initialize GitHub
            $github = new GitSyncWP_GitHub($github_token, $github_repo);
            
            $log = "Starting backup via WP-CLI at " . current_time('mysql') . "\n";
            $log .= "-------------------------------------------\n";
            
            // Create backup directory
            $backup_dir = WP_CONTENT_DIR . '/uploads/backups';
            if (!file_exists($backup_dir)) {
                wp_mkdir_p($backup_dir);
                $log .= "Created backup directory\n";
            }
            
            // Backup database if needed
            if ($backup_db) {
                WP_CLI::log('Backing up database...');
                $log .= "Backing up database...\n";
                
                $db_file = $backup->backup_database($backup_dir);
                
                if ($db_file) {
                    $log .= "✓ Database exported to: " . $db_file . "\n";
                    WP_CLI::success('Database exported successfully');
                    
                    // Push to GitHub
                    WP_CLI::log('Pushing database to GitHub...');
                    $db_push_result = $github->push_file(
                        $db_file, 
                        'database/wp-database-backup.sql', 
                        'Database backup from WP-CLI'
                    );
                    
                    if ($db_push_result === true) {
                        $log .= "✓ Database pushed to GitHub successfully\n";
                        WP_CLI::success('Database pushed to GitHub');
                    } else {
                        $log .= "❌ Failed to push database: " . $db_push_result . "\n";
                        WP_CLI::error('Failed to push database: ' . $db_push_result);
                    }
                } else {
                    $log .= "❌ Failed to export database\n";
                    WP_CLI::error('Failed to export database');
                }
            }
            
            // Backup files if needed
            if ($backup_files) {
                WP_CLI::log('Scanning WordPress files...');
                $log .= "Scanning WordPress files...\n";
                
                $files = $backup->get_all_files(ABSPATH);
                $total_files = count($files);
                
                $log .= "Found " . $total_files . " files to backup\n";
                WP_CLI::log("Found {$total_files} files to backup");
                
                // Initialize progress bar
                $progress = \WP_CLI\Utils\make_progress_bar('Backing up files', $total_files);
                
                // Track results
                $processed = 0;
                $successful = 0;
                $failed = 0;
                
                foreach ($files as $file) {
                    // Skip excluded paths
                    if ($backup->is_excluded($file)) {
                        $processed++;
                        $progress->tick();
                        continue;
                    }
                    
                    // Get relative path to ABSPATH
                    $relative_path = str_replace(ABSPATH, '', $file);
                    
                    // Push file to GitHub
                    $push_result = $github->push_file(
                        $file, 
                        $relative_path, 
                        'Backup WordPress file: ' . $relative_path
                    );
                    
                    if ($push_result === true) {
                        $successful++;
                    } else {
                        $failed++;
                        $log .= "❌ Failed to push file: " . $relative_path . " - Error: " . $push_result . "\n";
                    }
                    
                    $processed++;
                    $progress->tick();
                    
                    // Give occasional feedback
                    if ($processed % 100 === 0) {
                        WP_CLI::log("Processed {$processed} of {$total_files} files...");
                    }
                }
                
                $progress->finish();
            }
            
            // Finalize backup
            $log .= "\nBackup Summary:\n";
            $log .= "-------------------------------------------\n";
            
            if ($backup_files) {
                $log .= "Total files processed: " . $processed . "\n";
                $log .= "Files pushed successfully: " . $successful . "\n";
                $log .= "Files failed to push: " . $failed . "\n";
            }
            
            $log .= "Backup completed at: " . current_time('mysql') . "\n";
            
            // Store in logs
            $logs = get_option('gitsyncwp_sync_logs', array());
            array_unshift($logs, array(
                'timestamp' => current_time('Y-m-d H:i:s'),
                'status' => $failed > 0 ? 'warning' : 'success',
                'message' => $log
            ));
            
            // Keep only last 50 logs
            if (count($logs) > 50) {
                $logs = array_slice($logs, 0, 50);
            }
            
            update_option('gitsyncwp_sync_logs', $logs);
            update_option('gitsyncwp_last_log', $log);
            update_option('gitsyncwp_last_backup_time', current_time('mysql'));
            
            WP_CLI::success('Backup completed. Files: ' . $successful . ' successful, ' . $failed . ' failed.');
        }
    }
}