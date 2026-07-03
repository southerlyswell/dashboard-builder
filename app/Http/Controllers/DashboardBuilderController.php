<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Dashboard;
use App\Services\DashboardAIService;
use App\Services\DashboardGitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardBuilderController extends Controller
{
    /**
     * Main dashboard builder page — AI chat + layout editor + live preview.
     */
    public function index(Request $request)
    {
        // If a project ID is specified, load the builder with that project
        if ($request->query('project')) {
            $clients = Client::orderBy('name')->get();
            return view('dashboard-builder.index', [
                'clients' => $clients,
                'activeClient' => $request->query('client') 
                    ? Client::find($request->query('client')) 
                    : $clients->first(),
            ]);
        }
        
        // If a client ID is specified, open builder for that client
        if ($request->query('client')) {
            $clients = Client::orderBy('name')->get();
            return view('dashboard-builder.index', [
                'clients' => $clients,
                'activeClient' => Client::find($request->query('client')),
            ]);
        }
        
        // Default: redirect to projects home
        return redirect()->route('dashbuilder.projects');
    }

    /**
     * Database connection wizard page.
     */
    public function connect()
    {
        return view('dashboard-builder.connect');
    }

    /**
     * Store a new client database connection.
     */
    public function storeConnection(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'db_host' => 'required|string',
            'db_port' => 'required|integer',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        // Test the connection
        try {
            config([
                'database.connections.temp_client' => [
                    'driver' => 'mysql',
                    'host' => $validated['db_host'],
                    'port' => $validated['db_port'],
                    'database' => $validated['db_database'],
                    'username' => $validated['db_username'],
                    'password' => $validated['db_password'],
                ]
            ]);
            DB::connection('temp_client')->getPdo();
        } catch (\Exception $e) {
            return back()->withErrors(['db' => 'Connection failed: ' . $e->getMessage()]);
        }

        // Scan schema
        $tables = DB::connection('temp_client')->select('SHOW TABLES');
        $tableNames = array_map(fn($t) => array_values((array)$t)[0], $tables);
        $schema = [];
        foreach (array_slice($tableNames, 0, 50) as $table) {
            $columns = DB::connection('temp_client')->select("SHOW COLUMNS FROM `{$table}`");
            $count = DB::connection('temp_client')->table($table)->count();
            $schema[$table] = [
                'columns' => array_map(fn($c) => ['name' => $c->Field, 'type' => $c->Type], $columns),
                'row_count' => $count,
            ];
        }

        $client = Client::create([
            'name' => $validated['name'],
            'db_host' => $validated['db_host'],
            'db_port' => $validated['db_port'],
            'db_database' => $validated['db_database'],
            'db_username' => $validated['db_username'],
            'db_password' => encrypt($validated['db_password'] ?? ''),
            'schema_snapshot' => $schema,
        ]);

        return redirect()->route('dashbuilder.client', $client)
            ->with('success', "Connected! Found " . count($schema) . " tables.");
    }

    /**
     * Client management list.
     */
    public function clients()
    {
        if (request()->wantsJson()) {
            $clients = Client::orderBy('name')->get()->map(function ($c) {
                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'contact_name' => $c->contact_name,
                    'contact_email' => $c->contact_email,
                    'contact_phone' => $c->contact_phone,
                    'address_line1' => $c->address_line1,
                    'city' => $c->city,
                    'province' => $c->province,
                    'postal_code' => $c->postal_code,
                    'industry' => $c->industry,
                    'is_active' => $c->is_active,
                    'dashboard_count' => $c->dashboards()->count(),
                    'created_at' => $c->created_at?->format('Y-m-d'),
                ];
            });
            return response()->json($clients);
        }
        
        return view('dashboard-builder.projects', [
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    /**
     * Single client dashboard builder view.
     */
    public function clientDashboard(Client $client)
    {
        return view('dashboard-builder.index', [
            'clients' => Client::orderBy('name')->get(),
            'activeClient' => $client,
        ]);
    }

    /**
     * AI chat endpoint — consultant sends message, AI responds.
     */
    public function aiHealth()
    {
        $ai = new \App\Services\DashboardAIService();
        return response()->json($ai->ping());
    }

    /**
     * Chat with the AI — used for the consultant chat panel.
     */
    public function aiChat(Request $request)
    {
        $request->validate(['message' => 'required|string']);
        
        $ai = new \App\Services\DashboardAIService();
        $response = $ai->chat(
            $request->input('message'),
            $request->input('context', []),
            $request->input('client_id'),
        );

        return response()->json($response);
    }

    /**
     * AI generates a full dashboard from natural language description.
     */
    public function aiGenerateDashboard(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'client_id' => 'nullable|integer',
        ]);

        $client = $request->input('client_id') ? Client::find($request->input('client_id')) : null;
        $ai = new \App\Services\DashboardAIService();
        
        $dashboard = $ai->generateDashboard(
            $request->input('prompt'),
            $client,
        );

        return response()->json($dashboard);
    }

    /**
     * Batch query execution — runs all card queries and returns results.
     */
    public function batchQuery(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'cards' => 'required|array',
            ]);

            $client = Client::findOrFail($request->input('client_id'));
            $service = new \App\Services\DashboardQueryService();
            $filters = $request->input('filters', []);
            $results = $service->executeAll($client, $request->input('cards'), $filters);

            return response()->json(['data' => $results]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('batchQuery exception', ['error' => $e->getMessage()]);
            return response()->json(['data' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Run a database query for chart data (single query).
     */
    public function query(Request $request)
    {
        try {
            $request->validate([
                'client_id' => 'required|integer',
                'sql' => 'required|string',
            ]);

            $client = Client::findOrFail($request->input('client_id'));
            
            // Use cache for repeated queries
            $cacheKey = 'db_query:' . md5($client->id . $request->input('sql'));
            
            $result = Cache::remember($cacheKey, 300, function () use ($client, $request) {
                $this->setupClientConnection($client);
                return DB::connection('temp_client')->select($request->input('sql'));
            });

            return response()->json(['data' => $result, 'cached' => Cache::has($cacheKey)]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('query exception', ['error' => $e->getMessage()]);
            return response()->json(['data' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Dashboard JSON endpoint — returns the layout spec.
     */
    public function dashboardJson($dashboard)
    {
        $dashboard = \App\Models\Dashboard::findOrFail($dashboard);
        return response()->json($dashboard->layout);
    }

    /**
     * Store a dashboard — git-backed with automatic versioning.
     * Accepts either {dashboard: {...}} or {name: ..., layout: {...}} format.
     */
    public function storeDashboard(Request $request)
    {
        $clientId = $request->input('client_id');
        $dashboard = $request->input('dashboard');
        $name = $request->input('name', $dashboard['title'] ?? 'Dashboard');
        $layout = $dashboard ?? $request->input('layout', []);

        if (!$clientId || !$layout) {
            return response()->json(['error' => 'client_id and dashboard (or layout) are required'], 422);
        }

        $client = Client::find($clientId);
        if (!$client) {
            return response()->json(['error' => 'Client not found'], 404);
        }

        $git = new DashboardGitService();
        
        try {
            $result = $git->save(
                $this->clientSlug($client->name),
                $name,
                $layout
            );

            // Also save/update the dashboards table for the projects repository
            $dash = Dashboard::updateOrCreate(
                ['client_id' => $clientId, 'name' => $name],
                [
                    'layout' => $layout,
                    'theme' => $layout['theme'] ?? 'dark',
                    'is_published' => true,
                ]
            );

            $publicUrl = url('/embed/' . $result['git_hash']);

            return response()->json([
                'id' => $result['git_hash'],
                'public_id' => $result['git_hash'],
                'public_url' => $publicUrl,
                'name' => $name,
                'git_hash' => $result['git_hash'],
                'cards' => $result['cards'],
                'saved' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get git revision history for a saved dashboard.
     */
    public function dashboardHistory(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'dashboard_name' => 'required|string',
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $git = new DashboardGitService();
        $name = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($validated['dashboard_name'])));

        $history = $git->history($this->clientSlug($client->name), $name);
        
        return response()->json(['history' => $history]);
    }

    /**
     * Load a specific git revision of a dashboard.
     */
    public function loadRevision(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'dashboard_name' => 'required|string',
            'hash' => 'required|string',
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $git = new DashboardGitService();
        $name = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($validated['dashboard_name'])));
        
        $data = $git->loadRevision($this->clientSlug($client->name), $name, $validated['hash']);
        
        if (!$data) {
            return response()->json(['error' => 'Revision not found'], 404);
        }
        
        return response()->json(['dashboard' => $data, 'hash' => $validated['hash']]);
    }

    /**
     * Public embed — standalone page for client sharing.
     */
    public function publicEmbed($publicId)
    {
        // Serve static dashboard JSON via embed viewer
        return view('dashboard-builder.embed-public', [
            'publicId' => $publicId,
        ]);
    }

    /**
     * Projects repository — list all saved dashboards.
     */
    public function projects(Request $request)
    {
        // JSON API response for the frontend
        if ($request->wantsJson()) {
            return $this->projectsJson($request);
        }
        
        $clients = Client::orderBy('name')->get();
        
        return view('dashboard-builder.projects', [
            'clients' => $clients,
        ]);
    }
    
    private function projectsJson(Request $request)
    {
        $query = Dashboard::with('client')->latest();
        
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->input('client_id'));
        }
        
        $dashboards = $query->get()->map(function ($d) {
            $layout = is_array($d->layout) ? $d->layout : json_decode($d->layout, true);
            return [
                'id' => $d->id,
                'name' => $d->name,
                'client_id' => $d->client_id,
                'client_name' => $d->client?->name,
                'card_count' => isset($layout['cards']) ? count($layout['cards']) : 0,
                'layout' => $layout,
                'public_id' => $d->public_id,
                'updated_at' => $d->updated_at?->format('Y-m-d H:i'),
            ];
        });
        
        return response()->json($dashboards);
    }
    
    /**
     * Load a saved dashboard into the builder.
     */
    public function loadProject($id)
    {
        $dashboard = Dashboard::with('client')->findOrFail($id);
        $layout = is_array($dashboard->layout) ? $dashboard->layout : json_decode($dashboard->layout, true);
        
        return response()->json([
            'id' => $dashboard->id,
            'name' => $dashboard->name,
            'client_id' => $dashboard->client_id,
            'client_name' => $dashboard->client?->name,
            'layout' => $layout,
            'public_id' => $dashboard->public_id,
            'is_published' => $dashboard->is_published,
            'updated_at' => $dashboard->updated_at?->format('Y-m-d H:i'),
        ]);
    }
    
    /**
     * Delete a saved dashboard.
     */
    public function deleteProject($id)
    {
        $dashboard = Dashboard::findOrFail($id);
        $dashboard->delete();
        
        return response()->json(['deleted' => true]);
    }

    /**
     * Store a new client.
     */
    public function storeClient(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'industry' => 'nullable|string|max:100',
        ]);
        
        $client = Client::create($validated);
        
        if ($request->wantsJson()) {
            return response()->json(['id' => $client->id, 'name' => $client->name, 'created' => true]);
        }
        
        return redirect()->route('dashbuilder.client-detail', $client);
    }
    
    /**
     * Show client detail with dashboards.
     */
    public function clientDetail(Client $client)
    {
        $dashboards = $client->dashboards()->latest()->get()->map(function ($d) {
            $layout = is_array($d->layout) ? $d->layout : json_decode($d->layout, true);
            $d->card_count = isset($layout['cards']) ? count($layout['cards']) : 0;
            return $d;
        });
        
        return view('dashboard-builder.client-detail', [
            'client' => $client,
            'dashboards' => $dashboards,
        ]);
    }
    
    /**
     * Delete a client and all its dashboards.
     */
    public function deleteClient(Client $client)
    {
        $client->dashboards()->delete();
        $client->delete();
        
        return response()->json(['deleted' => true]);
    }

    // --- Private helpers ---

    private function clientSlug(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/', '-', trim($name)));
    }

    /**
     * Set up a temporary database connection for a client.
     */
    private function setupClientConnection(Client $client): void
    {
        config([
            'database.connections.temp_client' => [
                'driver' => 'mysql',
                'host' => $client->db_host,
                'port' => $client->db_port,
                'database' => $client->db_database,
                'username' => $client->db_username,
                'password' => decrypt($client->db_password),
            ]
        ]);
    }
}
