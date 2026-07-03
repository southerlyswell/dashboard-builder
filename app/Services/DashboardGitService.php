<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DashboardGitService
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = storage_path('dashboards');
    }

    /**
     * Save a dashboard JSON file and commit to git.
     */
    public function save(string $clientSlug, string $dashboardName, array $layout): array
    {
        $dir = $this->ensureClientDir($clientSlug);
        $filename = $this->slugify($dashboardName) . '.json';
        $filepath = $dir . DIRECTORY_SEPARATOR . $filename;

        $json = json_encode($layout, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Write file
        if (file_put_contents($filepath, $json) === false) {
            throw new \RuntimeException("Failed to write dashboard file: {$filepath}");
        }

        // Git add + commit (these commands have no % signs, safe with exec)
        $cardCount = count($layout['cards'] ?? []);
        $message = "{$dashboardName} ({$cardCount} cards)";

        $this->runGit("add \"{$filename}\"", $dir);
        $this->runGit("commit --allow-empty -m " . escapeshellarg($message), $dir);
        $hash = trim($this->runGit("rev-parse --short HEAD", $dir));

        // Also write a copy to public for embed access
        $publicDir = public_path('dashboards');
        if (!is_dir($publicDir)) mkdir($publicDir, 0755, true);
        file_put_contents($publicDir . DIRECTORY_SEPARATOR . $hash . '.json', $json);
        $meta = $this->loadMeta($dir);
        $meta[] = [
            'hash' => $hash,
            'message' => $message,
            'date' => date('Y-m-d H:i:s'),
            'cards' => $cardCount,
        ];
        // Keep last 50 versions
        if (count($meta) > 50) $meta = array_slice($meta, -50);
        file_put_contents($dir . DIRECTORY_SEPARATOR . '.versions.json', json_encode($meta));

        return [
            'file' => $filepath,
            'git_hash' => $hash,
            'message' => $message,
            'cards' => $cardCount,
        ];
    }

    /**
     * Load a dashboard JSON from file.
     */
    public function load(string $clientSlug, string $dashboardSlug): ?array
    {
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $clientSlug . DIRECTORY_SEPARATOR . $dashboardSlug . '.json';
        if (!file_exists($filepath)) return null;

        $json = file_get_contents($filepath);
        return json_decode($json, true);
    }

    /**
     * Get git history for a specific dashboard file.
     */
    public function history(string $clientSlug, string $dashboardSlug): array
    {
        $dir = $this->basePath . DIRECTORY_SEPARATOR . $clientSlug;
        $filename = $dashboardSlug . '.json';

        if (!is_dir($dir)) return [];

        $output = $this->runGit(
            "log --oneline --max-count=30 -- \"{$filename}\"",
            $dir
        );

        if (empty(trim($output))) return [];

        $commits = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (preg_match('/^([a-f0-9]+)\s(.+)$/', $line, $m)) {
                // Get date from commit object (avoids Windows cmd % escaping)
                $cat = trim($this->runGit("cat-file -p {$m[1]}", $dir));
                $date = '';
                if (preg_match('/^author.*?(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/m', $cat, $dm)) {
                    $date = $dm[1];
                }
                $commits[] = [
                    'hash' => $m[1],
                    'short_hash' => substr($m[1], 0, 7),
                    'message' => $m[2],
                    'date' => $date,
                ];
            }
        }

        return $commits;
    }

    /**
     * Load a specific git revision of a dashboard.
     */
    public function loadRevision(string $clientSlug, string $dashboardSlug, string $hash): ?array
    {
        $dir = $this->basePath . DIRECTORY_SEPARATOR . $clientSlug;
        $filename = $dashboardSlug . '.json';
        if (!is_dir($dir)) return null;
        // Load from metadata first (fast path), fallback to git show
        $meta = $this->loadMeta($dir);
        foreach ($meta as $m) {
            if (isset($m['hash']) && strpos($m['hash'], $hash) === 0) {
                // Found the revision — load from file at that point
                $json = $this->runGit("show {$m['hash']}:{$filename}", $dir);
                if (!empty(trim($json))) return json_decode($json, true);
            }
        }
        // Fallback: try direct git show
        $json = $this->runGit("show {$hash}:{$filename}", $dir);
        if (empty(trim($json))) return null;
        return json_decode($json, true);
    }

    /**
     * List all dashboards for a client.
     */
    public function listForClient(string $clientSlug): array
    {
        $dir = $this->basePath . DIRECTORY_SEPARATOR . $clientSlug;
        if (!is_dir($dir)) return [];

        $dashboards = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $json = json_decode(file_get_contents($file), true);
            $title = $json['title'] ?? $name;
            $cards = count($json['cards'] ?? []);
            $dashboards[] = [
                'slug' => $name,
                'title' => $title,
                'cards' => $cards,
                'modified' => date('Y-m-d H:i', filemtime($file)),
            ];
        }

        return $dashboards;
    }

    /**
     * Delete a dashboard file and commit the removal.
     */
    public function delete(string $clientSlug, string $dashboardSlug): bool
    {
        $filepath = $this->basePath . DIRECTORY_SEPARATOR . $clientSlug . DIRECTORY_SEPARATOR . $dashboardSlug . '.json';
        if (!file_exists($filepath)) return false;

        unlink($filepath);

        $dir = dirname($filepath);
        $this->runGit("rm \"{$dashboardSlug}.json\"", $dir);
        $this->runGit("commit -m " . escapeshellarg("Delete {$dashboardSlug}"), $dir);

        return true;
    }

    // --- Private helpers ---

    private function ensureClientDir(string $clientSlug): string
    {
        $dir = $this->basePath . DIRECTORY_SEPARATOR . $clientSlug;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s\-_]/', '', $slug);
        $slug = preg_replace('/[\s\-_]+/', '-', $slug);
        return $slug ?: 'dashboard';
    }

    private function runGit(string $command, string $cwd): string
    {
        $output = []; $code = 0;
        exec('git -C ' . escapeshellarg($cwd) . ' ' . $command . ' 2>&1', $output, $code);
        return implode("\n", $output);
    }

    private function loadMeta(string $dir): array
    {
        $p = $dir . DIRECTORY_SEPARATOR . '.versions.json';
        if (!file_exists($p)) return [];
        return json_decode(file_get_contents($p), true) ?: [];
    }
}
