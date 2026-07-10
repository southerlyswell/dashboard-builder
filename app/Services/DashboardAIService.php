<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DashboardAIService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $maxFunctionTurns = 15;
    private string $sandboxDir;
    private ?array $pendingDashboard = null;

    public function __construct()
    {
        $this->apiKey = env('DEEPSEEK_API_KEY') ?: config('deepseek.api_key', 'sk-30e904fa1cbc4ec5aac6f2d8c8e49a73');
        $this->baseUrl = config('deepseek.base_url', 'https://api.deepseek.com');
        $this->model = config('deepseek.model', 'deepseek-chat');
        $this->sandboxDir = storage_path('ai');
        if (!is_dir($this->sandboxDir)) {
            @mkdir($this->sandboxDir, 0755, true);
        }
    }

    /**
     * Quick connectivity check.
     */
    public function ping(): array
    {
        try {
            $response = Http::timeout(8)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/v1/chat/completions", [
                    'model' => $this->model,
                    'messages' => [['role' => 'user', 'content' => 'OK']],
                    'temperature' => 0.1,
                    'max_tokens' => 5,
                ]);

            if ($response->successful()) {
                return ['online' => true, 'model' => $this->model];
            }

            return ['online' => false, 'error' => 'HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['online' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Chat with the AI — supports function calling for file read/write.
     */
    public function chat(string $message, array $context = [], ?int $clientId = null): array
    {
        $client = $clientId ? Client::find($clientId) : null;

        $systemPrompt = $this->buildSystemPrompt($client);
        $messages = $this->buildMessages($systemPrompt, $message, $context);

        $turns = 0;

        while ($turns < $this->maxFunctionTurns) {
            $turns++;

            try {
                $response = Http::timeout(120)
                    ->withToken($this->apiKey)
                    ->post("{$this->baseUrl}/v1/chat/completions", [
                        'model' => $this->model,
                        'messages' => $messages,
                        'temperature' => 0.3,
                        'max_tokens' => 4096,
                        'tools' => $this->getFunctionDefinitions(),
                        'tool_choice' => 'auto',
                    ]);

                if (!$response->successful()) {
                    Log::error('DeepSeek API error', ['status' => $response->status(), 'body' => $response->body()]);
                    return ['role' => 'assistant', 'content' => 'AI service unavailable. Please try again.'];
                }

                $data = $response->json();
                $choice = $data['choices'][0]['message'] ?? [];
                $content = $choice['content'] ?? '';
                $toolCalls = $choice['tool_calls'] ?? [];

                // Normal text response — no function calls
                if (empty($toolCalls)) {
                    $response = [
                        'role' => 'assistant',
                        'content' => $content ?: 'No response',
                        'usage' => $data['usage'] ?? null,
                    ];
                    if ($this->pendingDashboard) {
                        $response['_render_dashboard'] = $this->pendingDashboard;
                        $this->pendingDashboard = null;
                    }
                    return $response;
                }

                // Process all tool calls
                $messages[] = $choice; // assistant message with tool_calls

                foreach ($toolCalls as $tc) {
                    $result = $this->executeFunctionCall($tc);
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $tc['id'],
                        'content' => $result,
                    ];
                }

                // Continue loop — send results back to AI
            } catch (\Exception $e) {
                Log::error('DeepSeek API exception', ['error' => $e->getMessage()]);
                return ['role' => 'assistant', 'content' => 'Connection error. Please check your API key and try again.'];
            }
        }

        $response = ['role' => 'assistant', 'content' => 'I ran into a processing loop. Please try again with a simpler request.'];
        if ($this->pendingDashboard) {
            $response['_render_dashboard'] = $this->pendingDashboard;
            $this->pendingDashboard = null;
        }
        return $response;
    }

    /**
     * Generate a complete dashboard layout + queries from a natural language prompt.
     */
    public function generateDashboard(string $prompt, ?Client $client = null): array
    {
        $schemaContext = '';
        if ($client && $client->schema_snapshot) {
            $schema = $client->schema_snapshot;
            $schemaContext = "Available database tables:\n";
            foreach ($schema as $table => $info) {
                $cols = collect($info['columns'])->pluck('name')->implode(', ');
                $schemaContext .= "- {$table} ({$info['row_count']} rows): {$cols}\n";
            }
        }

        $userMessage = <<<PROMPT
Generate a dashboard layout in JSON format based on this request:

"$prompt"

{$schemaContext}

Return ONLY valid JSON — no explanation, no markdown. Use this schema:

{
  "title": "Dashboard Title",
  "theme": "dark",
  "cards": [
    {
      "id": "TITLE-1",
      "type": "title",
      "title": "Dashboard Title",
      "w": 4,
      "h": 2,
      "query": null
    },
    {
      "id": "KPI-1",
      "type": "kpi",
      "title": "Card Title",
      "w": 1,
      "h": 3,
      "query": "SELECT COUNT(*) as value FROM table_name",
      "viz_config": {"label": "Total", "format": "number"}
    },
    {
      "id": "DIV-1",
      "type": "divider",
      "title": "",
      "w": 4,
      "h": 1,
      "query": null
    },
    {
      "id": "HDR-1",
      "type": "header",
      "title": "Performance Overview",
      "w": 4,
      "h": 2,
      "query": null
    },
    {
      "id": "LINE-2",
      "type": "line",
      "title": "Trend Title",
      "w": 4,
      "h": 6,
      "query": "SELECT DATE(created_at) as date, COUNT(*) as count FROM table GROUP BY date ORDER BY date",
      "viz_config": {"x_axis": "date", "y_axis": "count"}
    },
    {
      "id": "SUB-1",
      "type": "subheader",
      "title": "Breakdown by category",
      "w": 4,
      "h": 1,
      "query": null
    },
    {
      "id": "DONUT-3",
      "type": "donut",
      "title": "Donut Title",
      "w": 2,
      "h": 5,
      "query": "SELECT gender, COUNT(*) as count FROM persons GROUP BY gender",
      "viz_config": {"dimension": "gender", "metric": "count"}
    },
    {
      "id": "BAR-4",
      "type": "bar",
      "title": "Bar Chart Title",
      "w": 2,
      "h": 5,
      "query": "SELECT category, COUNT(*) as count FROM table GROUP BY category ORDER BY count DESC",
      "viz_config": {"x_axis": "category", "y_axis": "count"}
    },
    {
      "id": "HDR-2",
      "type": "header",
      "title": "Details",
      "w": 4,
      "h": 2,
      "query": null
    },
    {
      "id": "TABLE-5",
      "type": "table",
      "title": "Table Title",
      "w": 4,
      "h": 6,
      "query": "SELECT * FROM table LIMIT 50",
      "viz_config": {"columns": ["col1", "col2"]}
    }
  ]
}

Rules:
- Use the actual table/column names from the schema above
- Generate real SQL queries that work with MySQL
- 4-column grid: w (1-4) for width, h (1-8) for height (50px per unit)
- Card types: title (h=2), header (h=2, orange left border), subheader (h=1, muted text), divider (h=1, thin line), kpi/stat (h=3), line/bar/donut/pie (h=5-6), table (h=6)
- Layout pattern (MANDATORY — include ALL card types listed):
  title → 4 KPI cards → divider → header → main line chart → subheader → 2 breakdown charts (donut + bar) → divider → header → detail table
- EVERY dashboard MUST include: 1 title, 3-4 KPIs, 2 dividers, 2 headers, 1 subheader, 1 line chart, 1 donut/pie, 1 bar chart, 1 table
- Use dividers (type=divider) between major sections for visual separation
- Main trend chart full width (w=4, h=6) after section header
- Breakdown charts 2 per row (w=2 each) below subheader
- Tables at the bottom (w=4, h=6)
- ONLY return JSON, nothing else
PROMPT;

        $response = $this->chat($userMessage, [], $client?->id);

        $content = $response['content'] ?? '';
        $json = $this->extractJson($content);

        if ($json) {
            return ['success' => true, 'dashboard' => $json];
        }

        return [
            'success' => false,
            'raw_response' => $content,
            'dashboard' => $this->fallbackDashboard(),
        ];
    }

    // ========================================================================
    //  PRIVATE — System Prompt
    // ========================================================================

    private function buildSystemPrompt(?Client $client): string
    {
        // Load soul.md (personality + conversation rules)
        $soulPath = $this->sandboxDir . '/soul.md';
        $prompt = file_exists($soulPath)
            ? file_get_contents($soulPath)
            : "You are a knowledgeable AI dashboard assistant. You help users understand their data and build interactive dashboards.";

        $prompt .= "\n\n";

        // Load rules.md (dashboard structure + formatting rules)
        $rulesPath = $this->sandboxDir . '/rules.md';
        if (file_exists($rulesPath)) {
            $prompt .= file_get_contents($rulesPath);
        }

        $prompt .= "\n\n";

        // Dynamic parts — always generated by PHP
        if ($client && $client->schema_snapshot) {
            $schema = $client->schema_snapshot;
            $tables = array_keys($schema);
            $prompt .= "Current client database: {$client->name}\n";
            $prompt .= "Tables: " . implode(', ', $tables) . "\n";
            foreach ($schema as $table => $info) {
                $cols = collect($info['columns'])->pluck('name')->implode(', ');
                $prompt .= "  {$table} ({$info['row_count']} rows): {$cols}\n";
            }
        }

        return $prompt;
    }

    // ========================================================================
    //  PRIVATE — Function Calling (AI File Tools)
    // ========================================================================

    private function getFunctionDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'read_file',
                    'description' => 'Read the contents of a file from the AI storage directory. Use this to show saved notes, view soul.md or rules.md, or read any markdown file.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Relative path within storage/ai/, e.g. \'soul.md\' or \'notes.md\' or \'dashboard-notes/edutech.md\'',
                            ],
                        ],
                        'required' => ['path'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'write_file',
                    'description' => 'Create or overwrite a file in the AI storage directory. Use this to save notes, memos, dashboard comments, or create new markdown files.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'path' => [
                                'type' => 'string',
                                'description' => 'Relative path within storage/ai/, e.g. \'notes.md\' or \'dashboard-notes/edutech.md\'',
                            ],
                            'content' => [
                                'type' => 'string',
                                'description' => 'File content to write — full markdown text',
                            ],
                        ],
                        'required' => ['path', 'content'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_files',
                    'description' => 'List all files in the AI storage directory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'render_dashboard',
                    'description' => 'Call this when you have a complete dashboard JSON ready. It renders the dashboard on the canvas immediately. NEVER save dashboard JSON to a file — always use this function.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'dashboard_title' => [
                                'type' => 'string',
                                'description' => 'Dashboard title shown on the canvas',
                            ],
                            'cards' => [
                                'type' => 'array',
                                'description' => 'Array of card objects. Each card has: id, type (title/kpi/line/bar/donut/table/divider/header/subheader), title, w (1-4 width), h (1-8 height), query (MySQL for data cards), viz_config (optional).

IMPORTANT LAYOUT PATTERN:
- Row 0: Title (type=title, w=4, h=2)
- Row 1: 4 KPI cards (type=kpi, w=1 each, h=3)
- Row 2: Divider (type=divider, w=4, h=1)
- Row 3: Section header (type=header, w=4, h=2) like "Performance Overview"
- Row 4: Main trend chart full width (type=line/bar, w=4, h=6)
- Row 5: Subheader (type=subheader, w=4, h=1)
- Row 6: 2 breakdown charts (type=donut/bar, w=2 each, h=5)
- Row 7: Divider (type=divider, w=4, h=1)
- Row 8: Section header (type=header, w=4, h=2)
- Row 9: Detail table (type=table, w=4, h=6)

Every dashboard needs: title, 3-4 KPIs, 2 dividers, 2 headers, 1 subheader, 1 line/bar chart, 1 donut, 1 table.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'string'],
                                        'type' => ['type' => 'string', 'enum' => ['title', 'kpi', 'line', 'bar', 'donut', 'table', 'divider', 'header', 'subheader']],
                                        'title' => ['type' => 'string'],
                                        'w' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 4],
                                        'h' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 8],
                                        'query' => ['type' => 'string'],
                                        'viz_config' => ['type' => 'object'],
                                    ],
                                    'required' => ['id', 'type', 'title', 'w', 'h'],
                                ],
                            ],
                        ],
                        'required' => ['dashboard_title', 'cards'],
                    ],
                ],
            ],
        ];
    }

    private function executeFunctionCall(array $toolCall): string
    {
        $id = $toolCall['id'] ?? 'unknown';
        $name = $toolCall['function']['name'] ?? '';
        $args = json_decode($toolCall['function']['arguments'] ?? '{}', true);

        Log::info('AI function call', ['id' => $id, 'name' => $name, 'args' => $args]);

        return match ($name) {
            'read_file' => $this->readFile($args['path'] ?? ''),
            'write_file' => $this->writeFile($args['path'] ?? '', $args['content'] ?? ''),
            'list_files' => $this->listFiles(),
            'render_dashboard' => $this->renderDashboard($args),
            default => json_encode(['error' => "Unknown function: $name"]),
        };
    }

    private function readFile(string $path): string
    {
        $fullPath = $this->resolvePath($path);
        if (!$fullPath) {
            return json_encode(['error' => 'Invalid or blocked file path. Only files within storage/ai/ are accessible.']);
        }
        if (!file_exists($fullPath)) {
            return json_encode(['error' => "File not found: $path"]);
        }

        $content = file_get_contents($fullPath);
        $size = strlen($content);

        if ($size > 100000) {
            $content = substr($content, 0, 100000) . "\n\n--- [file truncated at 100KB] ---";
        }

        return json_encode([
            'path' => $path,
            'size' => $size,
            'content' => $content,
        ]);
    }

    private function writeFile(string $path, string $content): string
    {
        $fullPath = $this->resolvePath($path);
        if (!$fullPath) {
            return json_encode(['error' => 'Invalid or blocked file path. Only files within storage/ai/ are accessible.']);
        }

        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);
        Log::info('AI wrote file', ['path' => $path, 'size' => strlen($content)]);

        return json_encode(['success' => true, 'path' => $path, 'size' => strlen($content)]);
    }

    private function listFiles(): string
    {
        $files = [];
        if (!is_dir($this->sandboxDir)) {
            return json_encode(['files' => []]);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sandboxDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace(
                    str_replace('\\', '/', $this->sandboxDir) . '/',
                    '',
                    str_replace('\\', '/', $file->getPathname())
                );
                $files[] = [
                    'path' => $relativePath,
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i', $file->getMTime()),
                ];
            }
        }

        return json_encode(['files' => $files]);
    }

    private function renderDashboard(array $args): string
    {
        $title = $args['dashboard_title'] ?? 'Dashboard';
        $cards = $args['cards'] ?? [];

        if (empty($cards)) {
            return json_encode(['error' => 'Dashboard must have at least one card']);
        }

        $dashboard = [
            'title' => $title,
            'theme' => 'dark',
            'cards' => $cards,
        ];

        $this->pendingDashboard = $dashboard;

        Log::info('AI rendered dashboard', ['title' => $title, 'card_count' => count($cards)]);

        return json_encode(['success' => true, 'dashboard_title' => $title, 'card_count' => count($cards)]);
    }

    private function resolvePath(string $path): ?string
    {
        // Normalize separators
        $path = str_replace('\\', '/', $path);
        // Remove directory traversal attempts
        $path = preg_replace('/\.\.\//', '', $path);
        $path = preg_replace('/\.\.\\\\/', '', $path);

        $fullPath = realpath($this->sandboxDir . '/' . $path);

        // Must exist and be within sandbox
        if ($fullPath === false) {
            // File may not exist yet (new write) — check parent dir
            $parentDir = realpath(dirname($this->sandboxDir . '/' . $path));
            if ($parentDir && str_starts_with(str_replace('\\', '/', $parentDir), str_replace('\\', '/', $this->sandboxDir))) {
                return str_replace('\\', '/', $this->sandboxDir . '/' . $path);
            }
            return null;
        }

        $fullPath = str_replace('\\', '/', $fullPath);
        $sandbox = str_replace('\\', '/', $this->sandboxDir);

        if (!str_starts_with($fullPath, $sandbox)) {
            return null;
        }

        return $fullPath;
    }

    // ========================================================================
    //  PRIVATE — Utilities
    // ========================================================================

    private function extractJson(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if ($decoded && isset($decoded['cards'])) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded && isset($decoded['cards'])) {
                return $decoded;
            }
        }

        return null;
    }

    private function fallbackDashboard(): array
    {
        return [
            'title' => 'Dashboard',
            'theme' => 'dark',
            'cards' => [
                ['id' => 'KPI-1', 'type' => 'kpi', 'title' => 'Total Records', 'w' => 3, 'h' => 3,
                 'query' => 'SELECT COUNT(*) as value FROM persons', 'viz_config' => ['label' => 'Total']],
                ['id' => 'BAR-2', 'type' => 'bar', 'title' => 'Overview', 'w' => 3, 'h' => 5,
                 'query' => 'SELECT 1 as x', 'viz_config' => []],
            ],
        ];
    }

    private function buildMessages(string $systemPrompt, string $userMessage, array $context): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        foreach ($context as $msg) {
            $messages[] = ['role' => $msg['role'] ?? 'user', 'content' => $msg['content'] ?? ''];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        return $messages;
    }
}
