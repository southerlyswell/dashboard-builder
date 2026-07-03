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

    public function __construct()
    {
        $this->apiKey = env('DEEPSEEK_API_KEY') ?: config('deepseek.api_key', 'sk-30e904fa1cbc4ec5aac6f2d8c8e49a73');
        $this->baseUrl = config('deepseek.base_url', 'https://api.deepseek.com');
        $this->model = config('deepseek.model', 'deepseek-chat');
    }

    /**
     * Quick connectivity check — no auth needed, minimal cost.
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
     * Chat with the AI — used for the consultant chat panel.
     */
    public function chat(string $message, array $context = [], ?int $clientId = null): array
    {
        $client = $clientId ? Client::find($clientId) : null;
        
        $systemPrompt = $this->buildSystemPrompt($client);
        $messages = $this->buildMessages($systemPrompt, $message, $context);

        try {
            $response = Http::timeout(120)
                ->withToken($this->apiKey)
                ->post("{$this->baseUrl}/v1/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 4096,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'role' => 'assistant',
                    'content' => $data['choices'][0]['message']['content'] ?? 'No response',
                    'usage' => $data['usage'] ?? null,
                ];
            }

            Log::error('DeepSeek API error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['role' => 'assistant', 'content' => 'AI service unavailable. Please try again.'];
        } catch (\Exception $e) {
            Log::error('DeepSeek API exception', ['error' => $e->getMessage()]);
            return ['role' => 'assistant', 'content' => 'Connection error. Please check your API key and try again.'];
        }
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
  ],
  "filters": [
    {"name": "Date Range", "slug": "date_range", "type": "date"}
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
- Include sensible filters (date range, branch name, category)
- ONLY return JSON, nothing else
PROMPT;

        $response = $this->chat($userMessage, [], $client?->id);
        
        // Extract JSON from response
        $content = $response['content'] ?? '';
        $json = $this->extractJson($content);

        if ($json) {
            return ['success' => true, 'dashboard' => $json];
        }

        // Fallback: return a basic dashboard template if AI returned non-JSON
        return [
            'success' => false,
            'raw_response' => $content,
            'dashboard' => $this->fallbackDashboard(),
        ];
    }

    /**
     * Extract JSON from AI response (handles markdown code blocks).
     */
    private function extractJson(string $content): ?array
    {
        // Try direct parse
        $decoded = json_decode($content, true);
        if ($decoded && isset($decoded['cards'])) {
            return $decoded;
        }

        // Try extracting from code block
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded && isset($decoded['cards'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Fallback dashboard template when AI fails.
     */
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
            'filters' => [],
        ];
    }

    private function buildSystemPrompt(?Client $client): string
    {
        $prompt = "You are the brain of a live dashboard builder. Your JSON output is automatically rendered as a real interactive dashboard. DO NOT explain or describe — the JSON parses and renders instantly.\n\n";
        
        // Layout framework — the AI must follow this structure to avoid "shotgun" dashboards
        $prompt .= "DASHBOARD STRUCTURE (follow exactly):\n";
        $prompt .= "  FIRST: Include a 'filters' array. Analyze the schema below and pick filters that make sense:\n";
        $prompt .= "    - date_range: for any table with date columns (attendance_date, created_at, etc). Add 'Date Range' filter.\n";
        $prompt .= "    - dropdown: for any FK column (branch_id, project_id, category, gender, status). Add dropdown filter with label + SQL to get options.\n";
        $prompt .= "    - toggle: for boolean columns (active, status). Add toggle filter.\n";
        $prompt .= "  THEN the card rows:\n";
        $prompt .= "  Row 1: Title card (type=title, w=4, h=2)\n";
        $prompt .= "  Row 2: 3-4 KPI cards (type=kpi, w=1 each, h=3) — pick the 3-4 MOST IMPORTANT metrics\n";
        $prompt .= "  Row 3: Divider (type=divider, w=4, h=1)\n";
        $prompt .= "  Row 4: Section header (type=header, w=4, h=2) — like 'Performance Overview'\n";
        $prompt .= "  Row 5: 1 trend chart (type=line or type=bar, w=4, h=6) — main insight\n";
        $prompt .= "  Row 6: Subheader (type=subheader, w=4, h=1) — REQUIRED, like 'Breakdown by category'\n";
        $prompt .= "  Row 7: 1-2 supporting charts (type=donut/pie or type=bar, w=2 each, h=5)\n";
        $prompt .= "  Row 8: Divider (type=divider, w=4, h=1)\n";
        $prompt .= "  Row 9: Section header (type=header, w=4, h=2) — like 'Details'\n";
        $prompt .= "  Row 10: 1 table (type=table, w=4, h=6) — recent records or detailed view\n\n";
        
        $prompt .= "RULES:\n";
        $prompt .= "- MAX 12 cards total. Less is more. A dashboard with 6 focused cards is better than 15 scattered ones.\n";
        $prompt .= "- Every KPI must answer a business question. Don't show 'total rows in table' — show 'active beneficiaries (30d)' or 'enrollment rate %.\n";
        $prompt .= "- Pick metrics that tell a story together: KPI row → trend → breakdown.\n";
        $prompt .= "- Chart type matching: trend over time = line, category comparison = horizontal bar, part-to-whole = donut, progress = gauge.\n";
        $prompt .= "- 6-column grid. colspan must total 6 per row. Use colspan (1-6) for width, rowspan for height.\n";
        $prompt .= "- Include a 'filters' array BEFORE 'cards'. Analyze the schema and add 1-3 relevant filters (date_range for date columns, dropdown for FK/lookup columns like branch_id, project_id, gender, status).\n";
        $prompt .= "- Real MySQL queries only. Use CURDATE(), DATE_SUB(), real table and column names from the schema below.\n";
        $prompt .= "\nDASHBOARD INTEGRITY (CRITICAL — DO NOT BREAK THE LAYOUT):\n";
        $prompt .= "- When modifying an existing dashboard: ADD new cards to the BOTTOM, never reposition existing cards unless the user explicitly asks you to move something.\n";
        $prompt .= "- When adding a card to an existing dashboard, return the FULL dashboard JSON with all existing cards PLUS the new card at the bottom.\n";
        $prompt .= "- Never change the ID, type, title, query, or position of any existing card unless the user explicitly asks for it.\n";
        $prompt .= "- If a user says 'add X', ADD it. If they say 'change X to Y', change only that. Never silently modify unrelated cards.\n";
        $prompt .= "\nUSABILITY:\n";
        $prompt .= "- After building a dashboard, offer ONE specific, useful suggestion (e.g. 'Would you like me to add a trend line to the attendance chart?' or 'Want me to add a regional breakdown?'). Wait for the user to say yes before doing anything.\n";
        $prompt .= "- Keep explanations short. The dashboard speaks for itself.\n";
        $prompt .= "- Output ONLY the JSON dashboard in a ```json code block. No conversational text before or after the JSON.\n\n";
        $prompt .= "JSON FORMAT:\n```json\n{\"dashboard\":{\"title\":\"Dashboard Title\",\"theme\":\"dark\",\"filters\":[{\"id\":\"date-range\",\"type\":\"date_range\",\"label\":\"Date Range\",\"column\":\"attendance_date\"},{\"id\":\"branch\",\"type\":\"dropdown\",\"label\":\"Branch\",\"query\":\"SELECT branch_id as value, branch_name as label FROM branches\"}],\"cards\":[{\"id\":\"title-1\",\"type\":\"title\",\"title\":\"Dashboard Title\",\"w\":4,\"h\":2},{\"id\":\"kpi-1\",\"type\":\"kpi\",\"title\":\"Metric Name\",\"w\":1,\"h\":3,\"query\":\"SELECT ...\"},{\"id\":\"divider-1\",\"type\":\"divider\",\"title\":\"\",\"w\":4,\"h\":1},{\"id\":\"header-1\",\"type\":\"header\",\"title\":\"Section Title\",\"w\":4,\"h\":2},{\"id\":\"line-1\",\"type\":\"line\",\"title\":\"Chart Title\",\"w\":4,\"h\":6,\"query\":\"SELECT ...\"}]}}\n```\n\n";

        if ($client && $client->schema_snapshot) {
            $schema = $client->schema_snapshot;
            $tables = array_keys($schema);
            $prompt .= "\nCurrent client database: {$client->name}\n";
            $prompt .= "Tables: " . implode(', ', $tables) . "\n";
            foreach ($schema as $table => $info) {
                $cols = collect($info['columns'])->pluck('name')->implode(', ');
                $prompt .= "  {$table} ({$info['row_count']} rows): {$cols}\n";
            }
        }

        return $prompt;
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
