<?php
/**
 * CloudSync.php
 * Uploads a local backup file to Dropbox using the
 * Dropbox HTTP API v2 (upload endpoint). No SDK needed.
 */
class CloudSync
{
    private string $token;
    private string $folder;

    public function __construct()
    {
        $this->token  = defined('DROPBOX_TOKEN') ? DROPBOX_TOKEN : '';
        $this->folder = defined('DROPBOX_FOLDER') ? DROPBOX_FOLDER : '/Backups';
    }

    public function isConfigured(): bool
    {
        return !empty($this->token);
    }

    /**
     * Upload a local file to Dropbox under {folder}/{filename}.
     * Returns ['ok' => bool, 'error' => string, 'path' => string]
     */
    public function upload(string $localPath, ?string $remoteName = null): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Dropbox token not configured.', 'path' => ''];
        }
        if (!is_file($localPath)) {
            return ['ok' => false, 'error' => 'Local file not found.', 'path' => ''];
        }

        $remoteName = $remoteName ?? basename($localPath);
        $remotePath = rtrim($this->folder, '/') . '/' . $remoteName;

        $contents = file_get_contents($localPath);
        if ($contents === false) {
            return ['ok' => false, 'error' => 'Cannot read local file.', 'path' => $remotePath];
        }

        $url = 'https://content.dropboxapi.com/2/files/upload';
        $args = [
            'path'       => $remotePath,
            'mode'       => 'overwrite',
            'autorename' => true,
            'mute'       => false,
            'strict_conflict' => false,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Dropbox-API-Arg: ' . json_encode($args),
                'Content-Type: application/octet-stream',
            ],
            CURLOPT_POSTFIELDS     => $contents,
        ]);

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $code >= 400) {
            return [
                'ok'    => false,
                'error' => $err ?: "HTTP {$code}: " . substr((string)$resp, 0, 200),
                'path'  => $remotePath,
            ];
        }

        return ['ok' => true, 'error' => '', 'path' => $remotePath];
    }

    /** Test the token by requesting the user's account info. */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'error' => 'Token not set.'];
        }

        $ch = curl_init('https://api.dropboxapi.com/2/users/get_current_account');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => 'null',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $code >= 400) {
            return ['ok' => false, 'error' => $err ?: "HTTP {$code}"];
        }
        $data = json_decode($resp, true);
        return [
            'ok'    => true,
            'name'  => $data['name']['display_name'] ?? 'Unknown',
            'email' => $data['email'] ?? '',
        ];
    }
}