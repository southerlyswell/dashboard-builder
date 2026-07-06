# Spec: AI File Tools — read/write/list from chat

**Branch:** `dev/ai-soul-tools`
**Status:** Spec — not built
**Created:** 2026-07-06

## Problem

The AI can only generate text or dashboard JSON. It cannot:

- Read files (can't show you `soul.md`, can't read notes)
- Write files (can't save scratchpad notes, can't update `soul.md`)
- Remember anything between messages except the last 6 in context

When you say "note that we need to check values for xyz", the AI can only reply with text. It can't actually save that note anywhere.

## Solution

Give the AI **function calling** — the ability to invoke file operations through a controlled, sandboxed API. This uses DeepSeek's OpenAI-compatible function calling API.

## Architecture

### The Agent Loop

```
┌──────────────────────────────────────────────┐
│  User: "note: check values for xyz"          │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│  Backend sends to DeepSeek:                  │
│  - user message                              │
│  - system prompt (from soul.md + rules.md)    │
│  - function definitions (read/write/list)    │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│  DeepSeek responds with function call:       │
│  {                                           │
│    "name": "write_file",                     │
│    "arguments": {                            │
│      "path": "notes.md",                     │
│      "content": "TODO: check values for xyz" │
│    }                                         │
│  }                                           │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│  Backend executes:                           │
│  - Validates path is in storage/ai/          │
│  - Writes storage/ai/notes.md                │
│  - Adds result to message chain              │
│  - Sends BACK to DeepSeek                    │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│  DeepSeek responds with natural text:        │
│  "✓ Saved note to notes.md"                 │
└──────────────────┬───────────────────────────┘
                   ▼
┌──────────────────────────────────────────────┐
│  Displayed in chat                           │
└──────────────────────────────────────────────┘
```

### Function Definitions

Three tools exposed to the AI:

```json
[
  {
    "name": "read_file",
    "description": "Read the contents of a file from the AI storage directory",
    "parameters": {
      "type": "object",
      "properties": {
        "path": { "type": "string", "description": "Relative path within storage/ai/, e.g. 'soul.md' or 'notes.md'" }
      },
      "required": ["path"]
    }
  },
  {
    "name": "write_file",
    "description": "Create or overwrite a file in the AI storage directory",
    "parameters": {
      "type": "object",
      "properties": {
        "path": { "type": "string", "description": "Relative path within storage/ai/" },
        "content": { "type": "string", "description": "File content to write" }
      },
      "required": ["path", "content"]
    }
  },
  {
    "name": "list_files",
    "description": "List all files in the AI storage directory",
    "parameters": { "type": "object", "properties": {}, "required": [] }
  }
]
```

### PHP Implementation

```php
class DashboardAIService
{
    private string $sandboxDir; // = storage_path('ai')
    private int $maxTurns = 5;  // prevent infinite loops

    public function chat(string $message, array $context, ?int $clientId): array
    {
        $this->sandboxDir = storage_path('ai');
        if (!is_dir($this->sandboxDir)) mkdir($this->sandboxDir, 0755, true);

        $messages = $this->buildMessages(
            $this->buildSystemPrompt($client),
            $message,
            $context
        );

        $turns = 0;
        while ($turns < $this->maxTurns) {
            $turns++;
            
            $response = $this->callDeepSeekWithFunctions($messages);

            // Check for function call
            if (isset($response['function_call'])) {
                $result = $this->executeFunctionCall($response['function_call']);
                
                // Add assistant function call + function result to chain
                $messages[] = $response;
                $messages[] = [
                    'role' => 'function',
                    'name' => $response['function_call']['name'],
                    'content' => $result,
                ];
                continue; // loop back — AI sees result
            }

            // Normal text response — done
            return $response;
        }

        return ['role' => 'assistant', 'content' => 'I ran into a loop processing your request. Please try again.'];
    }

    private function executeFunctionCall(array $call): string
    {
        $name = $call['name'] ?? '';
        $args = json_decode($call['arguments'] ?? '{}', true);

        return match ($name) {
            'read_file' => $this->readFile($args['path'] ?? ''),
            'write_file' => $this->writeFile($args['path'] ?? '', $args['content'] ?? ''),
            'list_files' => $this->listFiles(),
            default => json_encode(['error' => "Unknown function: $name"]),
        };
    }

    private function readFile(string $path): string
    {
        $fullPath = $this->resolvePath($path);
        if (!$fullPath) return json_encode(['error' => 'Invalid path']);
        if (!file_exists($fullPath)) return json_encode(['error' => 'File not found: ' . $path]);
        
        $content = file_get_contents($fullPath);
        if (strlen($content) > 100000) {
            $content = substr($content, 0, 100000) . "\n... [truncated]";
        }
        
        return json_encode([
            'path' => $path,
            'size' => strlen($content),
            'content' => $content,
        ]);
    }

    private function writeFile(string $path, string $content): string
    {
        $fullPath = $this->resolvePath($path);
        if (!$fullPath) return json_encode(['error' => 'Invalid path']);
        
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        file_put_contents($fullPath, $content);
        Log::info('AI wrote file', ['path' => $path, 'size' => strlen($content)]);
        
        return json_encode(['success' => true, 'path' => $path, 'size' => strlen($content)]);
    }

    private function listFiles(): string
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sandboxDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace(str_replace('\\', '/', $this->sandboxDir) . '/', '', str_replace('\\', '/', $file->getPathname()));
                $files[] = ['path' => $relativePath, 'size' => $file->getSize()];
            }
        }
        return json_encode(['files' => $files]);
    }

    private function resolvePath(string $path): ?string
    {
        // Sanitize: no ../ escaping
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/\.\.\//', '', $path);
        
        $fullPath = realpath($this->sandboxDir . '/' . $path);
        
        // Must be within sandbox
        if (!$fullPath || !str_starts_with(str_replace('\\', '/', $fullPath), str_replace('\\', '/', $this->sandboxDir))) {
            return null;
        }
        
        return $fullPath;
    }
}
```

## Security

| Guard | How |
|---|---|
| Path traversal | `../` stripped, `realpath()` verified, must start with sandbox dir |
| Writing outside sandbox | `resolvePath()` rejects any path that resolves outside `storage/ai/` |
| Reading sensitive files | Can only read within `storage/ai/` — no `.env`, no `app/`, no config |
| Large reads | 100KB cap, truncated with warning |
| Infinite loops | Max 5 function-call turns per message |
| Malicious writes | Write operations logged to Laravel log |
| File type safety | `.md`, `.txt`, `.json` only — enforced by extension check |

## What the AI can do with this

### Scratchpad workflow
```
You: "note that we need to check attendance values with the client"
AI: calls write_file("notes.md", "TODO: check attendance values with client")
AI: "✓ Saved note to notes.md"

10 minutes later...
You: "what are my notes?"
AI: calls read_file("notes.md") → returns content
AI: "Your notes: TODO: check attendance values with client"
```

### Soul.md editing workflow
```
You: "show me the soul.md"
AI: calls read_file("soul.md") → displays full content

You: "make the tone more direct and remove the word 'knowledgeable'"
AI: calls read_file("soul.md") → edits in memory → calls write_file("soul.md", newContent)
AI: "✓ Updated soul.md. Here's what changed: [diff]"
```

### Dashboard notes
```
You: "We need to verify the KPI values for the EduTech dashboard"
AI: calls write_file("dashboard-notes/edutech.md", "VERIFY: KPI values for EduTech dashboard need checking with client")
AI: "✓ Saved to dashboard-notes/edutech.md"
```

## Files changed

| File | Change |
|---|---|
| `app/Services/DashboardAIService.php` | Add function calling loop, sandboxed file ops |
| `storage/ai/soul.md` | New — AI personality (see Spec #1) |
| `storage/ai/rules.md` | New — dashboard rules (see Spec #1) |
| `storage/ai/notes.md` | Created on-demand by AI |

## Limitations (Phase 1)

- **No file editing of project code**: Can read/write only within `storage/ai/`. Can't edit `app/`, `public/`, `resources/`. That's Phase 2 (Dev Coder).
- **No git integration**: Files written by AI are not auto-committed. You must manually `git add` and commit.
- **No command execution**: No `php -l`, no `git commit`, no `verify-builder.cjs`. Pure file read/write/list only.
- **No diff display**: Reads/writes show full content, not diffs. Diff rendering is Phase 2.

## API Cost Impact

DeepSeek function calling costs the same as regular chat — no premium. Each function-call turn is one API request. Typical: 1 user message → 1 function call → 1 follow-up = 2 API calls per message. Same as today's single call cost ×2 at most.
