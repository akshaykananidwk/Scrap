<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Services\SettingsService;

/**
 * Minimal GitHub API client (cURL only, no Composer).
 *
 * The token is read from encrypted settings and only ever leaves this class as
 * an Authorization header — it is never logged, echoed, or returned.
 */
final class GitHubClient
{
    private const API = 'https://api.github.com';

    public function __construct(
        private string $owner,
        private string $repo,
        private string $branch,
        private string $token
    ) {
    }

    public static function fromSettings(): ?self
    {
        $owner = trim((string) SettingsService::get('github_owner', ''));
        $repo = trim((string) SettingsService::get('github_repo', ''));
        $branch = trim((string) SettingsService::get('github_branch', 'main')) ?: 'main';
        $token = (string) SettingsService::get('github_token', '');

        if ($owner === '' || $repo === '') {
            return null;
        }
        return new self($owner, $repo, $branch, $token);
    }

    public function repoPath(): string
    {
        return $this->owner . '/' . $this->repo;
    }

    public function branch(): string
    {
        return $this->branch;
    }

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    /** @return array{ok: bool, data?: array, error?: string, status?: int} */
    public function request(string $path, array $query = []): array
    {
        $url = self::API . $path . ($query !== [] ? '?' . http_build_query($query) : '');
        $response = $this->curl($url);

        if (!$response['ok']) {
            return $response;
        }
        $data = json_decode((string) $response['body'], true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Unexpected response from GitHub.'];
        }
        return ['ok' => true, 'data' => $data, 'status' => $response['status']];
    }

    public function latestCommit(): array
    {
        $result = $this->request(sprintf('/repos/%s/%s/commits/%s', $this->owner, $this->repo, rawurlencode($this->branch)));
        if (!$result['ok']) {
            return $result;
        }
        $commit = $result['data'];
        return [
            'ok' => true,
            'sha' => (string) ($commit['sha'] ?? ''),
            'message' => (string) ($commit['commit']['message'] ?? ''),
            'author' => (string) ($commit['commit']['author']['name'] ?? ''),
            'date' => isset($commit['commit']['author']['date'])
                ? gmdate('Y-m-d H:i:s', strtotime((string) $commit['commit']['author']['date']))
                : null,
            'url' => (string) ($commit['html_url'] ?? ''),
            'files' => array_map(
                static fn (array $f): array => [
                    'path' => (string) ($f['filename'] ?? ''),
                    'status' => (string) ($f['status'] ?? ''),
                    'additions' => (int) ($f['additions'] ?? 0),
                    'deletions' => (int) ($f['deletions'] ?? 0),
                ],
                (array) ($commit['files'] ?? [])
            ),
        ];
    }

    public function latestRelease(): array
    {
        $result = $this->request(sprintf('/repos/%s/%s/releases/latest', $this->owner, $this->repo));
        if (!$result['ok']) {
            return $result;
        }
        $release = $result['data'];
        return [
            'ok' => true,
            'tag' => (string) ($release['tag_name'] ?? ''),
            'name' => (string) ($release['name'] ?? ''),
            'body' => (string) ($release['body'] ?? ''),
            'published_at' => isset($release['published_at'])
                ? gmdate('Y-m-d H:i:s', strtotime((string) $release['published_at']))
                : null,
            'zipball_url' => (string) ($release['zipball_url'] ?? ''),
            'author' => (string) ($release['author']['login'] ?? ''),
        ];
    }

    /** Compare the deployed commit with HEAD to list changed files. */
    public function compare(string $base, string $head): array
    {
        if ($base === '' || $head === '') {
            return ['ok' => false, 'error' => 'Both a base and head commit are required.'];
        }
        $result = $this->request(sprintf(
            '/repos/%s/%s/compare/%s...%s',
            $this->owner,
            $this->repo,
            rawurlencode($base),
            rawurlencode($head)
        ));
        if (!$result['ok']) {
            return $result;
        }
        return [
            'ok' => true,
            'ahead_by' => (int) ($result['data']['ahead_by'] ?? 0),
            'behind_by' => (int) ($result['data']['behind_by'] ?? 0),
            'total_commits' => (int) ($result['data']['total_commits'] ?? 0),
            'commits' => array_map(
                static fn (array $c): array => [
                    'sha' => substr((string) ($c['sha'] ?? ''), 0, 7),
                    'message' => strtok((string) ($c['commit']['message'] ?? ''), "\n"),
                    'author' => (string) ($c['commit']['author']['name'] ?? ''),
                    'date' => (string) ($c['commit']['author']['date'] ?? ''),
                ],
                (array) ($result['data']['commits'] ?? [])
            ),
            'files' => array_map(
                static fn (array $f): array => [
                    'path' => (string) ($f['filename'] ?? ''),
                    'status' => (string) ($f['status'] ?? ''),
                ],
                (array) ($result['data']['files'] ?? [])
            ),
        ];
    }

    /** Fetch version.json from the repository without downloading everything. */
    public function remoteManifest(): ?array
    {
        $result = $this->request(
            sprintf('/repos/%s/%s/contents/version.json', $this->owner, $this->repo),
            ['ref' => $this->branch]
        );
        if (!$result['ok'] || empty($result['data']['content'])) {
            return null;
        }
        $decoded = base64_decode(str_replace("\n", '', (string) $result['data']['content']), true);
        if ($decoded === false) {
            return null;
        }
        $manifest = json_decode($decoded, true);
        return is_array($manifest) ? $manifest : null;
    }

    /** Download the branch/tag zipball to a local path. */
    public function downloadZipball(string $destination, ?string $ref = null): array
    {
        $ref ??= $this->branch;
        $url = sprintf('%s/repos/%s/%s/zipball/%s', self::API, $this->owner, $this->repo, rawurlencode($ref));

        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            return ['ok' => false, 'error' => 'Could not open the download destination for writing.'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'ScrapX-Updater',
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        fclose($handle);

        if ($ok === false) {
            @unlink($destination);
            return ['ok' => false, 'error' => 'Download failed: ' . $error];
        }
        if ($status !== 200) {
            @unlink($destination);
            return ['ok' => false, 'error' => 'GitHub returned HTTP ' . $status . ' for the download.'];
        }

        $size = filesize($destination) ?: 0;
        if ($size < 1024) {
            @unlink($destination);
            return ['ok' => false, 'error' => 'The downloaded archive is too small to be valid.'];
        }

        return ['ok' => true, 'size' => $size, 'path' => $destination];
    }

    /** @return array{ok: bool, body?: string, status?: int, error?: string} */
    private function curl(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers(),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'ScrapX-Updater',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($body === false) {
            return ['ok' => false, 'error' => 'Could not reach GitHub: ' . $error];
        }

        if ($status === 401 || $status === 403) {
            $decoded = json_decode((string) $body, true);
            $message = (string) ($decoded['message'] ?? '');
            if (str_contains($message, 'rate limit')) {
                return ['ok' => false, 'error' => 'GitHub API rate limit reached. Add a token or try again later.', 'status' => $status];
            }
            return [
                'ok' => false,
                'error' => 'GitHub denied access (HTTP ' . $status . '). Check the token and its repository permissions.',
                'status' => $status,
            ];
        }
        if ($status === 404) {
            return [
                'ok' => false,
                'error' => 'Repository, branch or file not found. Check the owner, repository and branch names.',
                'status' => $status,
            ];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'GitHub returned HTTP ' . $status . '.', 'status' => $status];
        }

        return ['ok' => true, 'body' => (string) $body, 'status' => $status];
    }

    private function headers(): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        return $headers;
    }

    /** Validate credentials without exposing the token. */
    public function testConnection(): array
    {
        $result = $this->request(sprintf('/repos/%s/%s', $this->owner, $this->repo));
        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error']];
        }
        $branch = $this->request(sprintf('/repos/%s/%s/branches/%s', $this->owner, $this->repo, rawurlencode($this->branch)));
        if (!$branch['ok']) {
            return ['ok' => false, 'error' => 'Repository reachable, but branch "' . $this->branch . '" was not found.'];
        }
        return [
            'ok' => true,
            'repository' => (string) ($result['data']['full_name'] ?? ''),
            'private' => (bool) ($result['data']['private'] ?? false),
            'default_branch' => (string) ($result['data']['default_branch'] ?? ''),
            'branch' => $this->branch,
            'message' => 'Connected to ' . ($result['data']['full_name'] ?? '') . ' (' . $this->branch . ').',
        ];
    }
}
