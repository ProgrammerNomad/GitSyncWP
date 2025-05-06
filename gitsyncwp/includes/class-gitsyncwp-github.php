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

    public function __construct($token, $repo) {
        $this->token = $token;
        $this->repo = $repo;
    }

    public function push_to_github($file_path, $commit_message) {
        $url = "https://api.github.com/repos/{$this->repo}/contents/" . basename($file_path);

        $content = base64_encode(file_get_contents($file_path));
        $data = [
            'message' => $commit_message,
            'content' => $content,
            'branch' => 'main',
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'token ' . $this->token,
                'User-Agent' => 'GitSyncWP',
            ],
            'body' => json_encode($data),
        ]);

        return wp_remote_retrieve_response_code($response) === 201;
    }
}