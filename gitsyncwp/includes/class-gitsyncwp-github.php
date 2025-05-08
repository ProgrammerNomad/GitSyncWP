<?php
/**
 * GitSyncWP GitHub Class
 *
 * Handles the GitHub API interactions for the GitSyncWP plugin.
 *
 * @package GitSyncWP
 */
if (!defined('ABSPATH')) {
    exit;
}

class GitSyncWP_GitHub {
    private $token;
    private $repo;
    private $branch;

    /**
     * Constructor
     * 
     * @param string $token GitHub token
     * @param string $repo GitHub repository in format username/repo
     * @param string $branch The branch to push to, defaults to main
     */
    public function __construct($token, $repo, $branch = 'main') {
        $this->token = $token;
        $this->repo = $repo;
        $this->branch = $branch;
    }

    /**
     * Push a file to GitHub
     * 
     * @param string $file_path Local file path
     * @param string $target_path Target path in the repository
     * @param string $commit_message Commit message
     * @return bool|string True on success, error message on failure
     */
    public function push_file($file_path, $target_path, $commit_message) {
        try {
            if (!file_exists($file_path)) {
                return "File not found: {$file_path}";
            }
            
            // Read file contents
            $content = file_get_contents($file_path);
            if ($content === false) {
                return "Could not read file: {$file_path}";
            }
            
            // URL encode the target path for the API
            $url_path = str_replace('+', '%20', urlencode($target_path));
            $api_url = "https://api.github.com/repos/{$this->repo}/contents/{$url_path}";
            
            // Check if file already exists to get its SHA
            $sha = $this->get_file_sha($url_path);
            
            // Prepare API payload
            $data = [
                'message' => $commit_message,
                'content' => base64_encode($content),
                'branch' => $this->branch,
            ];
            
            // If file exists, include its SHA for update
            if (!empty($sha)) {
                $data['sha'] = $sha;
            }
            
            $response = wp_remote_post($api_url, [
                'headers' => $this->get_headers(),
                'body' => json_encode($data),
                'timeout' => 30,
            ]);
            
            if (is_wp_error($response)) {
                return $response->get_error_message();
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            // Status codes 200 (updated) or 201 (created) indicate success
            if ($status_code == 200 || $status_code == 201) {
                return true;
            } else {
                $error_message = isset($response_data['message']) ? $response_data['message'] : "HTTP Error {$status_code}";
                return $error_message;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    
    /**
     * Get the SHA of an existing file
     * 
     * @param string $file_path File path in the repository
     * @return string|null The SHA of the file, or null if it doesn't exist
     */
    private function get_file_sha($file_path) {
        $api_url = "https://api.github.com/repos/{$this->repo}/contents/{$file_path}";
        
        $response = wp_remote_get($api_url, [
            'headers' => $this->get_headers(),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($status_code == 200 && isset($response_data['sha'])) {
            return $response_data['sha'];
        }
        
        return null;
    }
    
    /**
     * Get headers for GitHub API requests
     * 
     * @return array Headers array
     */
    private function get_headers() {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'GitSyncWP Plugin',
            'X-GitHub-Api-Version' => '2022-11-28',
            'Content-Type' => 'application/json',
        ];
    }
    
    /**
     * Create or update a GitHub repository file
     * 
     * @param string $content File content
     * @param string $path Path in the repository
     * @param string $message Commit message
     * @return bool|string True on success, error message on failure
     */
    public function create_or_update_file($content, $path, $message) {
        try {
            $api_url = "https://api.github.com/repos/{$this->repo}/contents/{$path}";
            
            // Check if file already exists
            $sha = $this->get_file_sha($path);
            
            // Prepare API payload
            $data = [
                'message' => $message,
                'content' => base64_encode($content),
                'branch' => $this->branch,
            ];
            
            if (!empty($sha)) {
                $data['sha'] = $sha;
            }
            
            $response = wp_remote_post($api_url, [
                'headers' => $this->get_headers(),
                'body' => json_encode($data),
                'timeout' => 30,
            ]);
            
            if (is_wp_error($response)) {
                return $response->get_error_message();
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            
            if ($status_code == 200 || $status_code == 201) {
                return true;
            } else {
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                $error_message = isset($response_data['message']) ? $response_data['message'] : "HTTP Error {$status_code}";
                return $error_message;
            }
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}