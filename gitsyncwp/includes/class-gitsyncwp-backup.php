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
    public function run_backup() {
        $db_file = $this->backup_database();
        $github = new GitSyncWP_GitHub('your_github_token', 'your_username/your_repo');

        $log = "Starting backup...\n";

        // Push database dump
        if ($github->push_to_github($db_file, 'Database backup')) {
            $log .= "Database backup pushed successfully.\n";
        } else {
            $log .= "Failed to push database backup.\n";
        }

        // Push WordPress files
        $wp_files = ABSPATH;
        if ($github->push_to_github($wp_files, 'WordPress files backup')) {
            $log .= "WordPress files pushed successfully.\n";
        } else {
            $log .= "Failed to push WordPress files.\n";
        }

        return $log;
    }

    private function backup_database() {
        global $wpdb;
        $file = WP_CONTENT_DIR . '/uploads/wp-database-backup.sql';
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            DB_USER,
            DB_PASSWORD,
            DB_HOST,
            DB_NAME,
            $file
        );
        exec($command);
        return $file;
    }
}