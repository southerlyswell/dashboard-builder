<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardBuilderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function project_load_returns_valid_layout(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'Test Client',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => '',
        ]);

        $layout = [
            'title' => 'Test Dashboard',
            'theme' => 'dark',
            'cards' => [
                [
                    'id' => 'kpi-1',
                    'type' => 'kpi',
                    'title' => 'Total Users',
                    'w' => 1,
                    'h' => 3,
                    'col' => 0,
                    'row' => 0,
                ],
                [
                    'id' => 'line-1',
                    'type' => 'line',
                    'title' => 'Trend',
                    'w' => 3,
                    'h' => 6,
                    'col' => 0,
                    'row' => 3,
                ],
            ],
        ];

        $dashboard = Dashboard::create([
            'client_id' => $client->id,
            'name' => 'Test Dashboard',
            'layout' => $layout,
            'theme' => 'dark',
            'is_published' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard-builder/projects/' . $dashboard->id . '/load');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'name',
            'client_id',
            'client_name',
            'layout' => [
                'title',
                'theme',
                'cards',
            ],
            'public_id',
            'is_published',
            'updated_at',
        ]);

        // Verify layout is a proper object with cards array, not a fragment
        $content = $response->json();
        $this->assertIsArray($content['layout']['cards']);
        $this->assertCount(2, $content['layout']['cards']);
        $this->assertEquals('Test Dashboard', $content['layout']['title']);
        $this->assertEquals('dark', $content['layout']['theme']);
        $this->assertEquals('Test Dashboard', $content['name']);
    }

    #[Test]
    public function project_load_requires_authentication(): void
    {
        $response = $this->get('/dashboard-builder/projects/999/load');
        $response->assertRedirect('/login');
    }

    #[Test]
    public function project_load_returns_404_for_nonexistent_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard-builder/projects/99999/load');

        $response->assertStatus(404);
    }

    #[Test]
    public function dashboard_builder_index_requires_authentication(): void
    {
        $response = $this->get('/dashboard-builder');
        $response->assertRedirect('/login');
    }

    // ===== WP2: Additional endpoint coverage =====

    #[Test]
    public function store_dashboard_persists_and_returns_public_url(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'WP2 Store Client',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => '',
        ]);

        $layout = [
            'title' => 'WP2 Store Dashboard',
            'theme' => 'dark',
            'cards' => [
                ['id' => 'kpi-1', 'type' => 'kpi', 'title' => 'KPI One', 'w' => 1, 'h' => 3, 'col' => 0, 'row' => 0],
            ],
        ];

        $response = $this
            ->actingAs($user)
            ->postJson('/dashboard-builder/dashboards', [
                'client_id' => $client->id,
                'dashboard' => $layout,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'public_id', 'public_url', 'name', 'cards', 'saved']);
        $this->assertTrue($response->json('saved'));
        $this->assertEquals('WP2 Store Dashboard', $response->json('name'));

        // Persisted to dashboards table
        $this->assertDatabaseHas('dashboards', [
            'client_id' => $client->id,
            'name' => 'WP2 Store Dashboard',
            'is_published' => true,
        ]);
    }

    #[Test]
    public function store_dashboard_requires_client_id(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/dashboard-builder/dashboards', [
                'dashboard' => ['title' => 'No Client', 'cards' => []],
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'client_id and dashboard (or layout) are required']);
    }

    #[Test]
    public function store_dashboard_returns_404_for_nonexistent_client(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson('/dashboard-builder/dashboards', [
                'client_id' => 99999,
                'dashboard' => ['title' => 'Ghost Client', 'cards' => []],
            ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Client not found']);
    }

    #[Test]
    public function batch_query_returns_graceful_error_for_unconfigured_database(): void
    {
        $user = User::factory()->create();

        // Client with db fields but no decryptable password -> connection setup fails
        $client = Client::create([
            'name' => 'WP2 Query Client',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => 'not-encrypted',
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/dashboard-builder/batch-query', [
                'client_id' => $client->id,
                'cards' => [
                    ['id' => 'kpi-1', 'type' => 'kpi', 'query' => 'SELECT COUNT(*) AS value FROM users'],
                ],
                'filters' => [],
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    #[Test]
    public function clients_list_returns_json_with_dashboard_count(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'WP2 List Client',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => '',
        ]);

        Dashboard::create([
            'client_id' => $client->id,
            'name' => 'Existing Dashboard',
            'layout' => ['title' => 'Existing', 'theme' => 'dark', 'cards' => []],
            'theme' => 'dark',
            'is_published' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/dashboard-builder/clients', ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJson([[
            'id' => $client->id,
            'name' => 'WP2 List Client',
            'dashboard_count' => 1,
        ]]);
    }

    #[Test]
    public function public_dashboard_api_returns_layout(): void
    {
        $client = Client::create([
            'name' => 'WP2 Embed Client',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => '',
        ]);

        $dashboard = Dashboard::create([
            'client_id' => $client->id,
            'name' => 'WP2 Embed Dashboard',
            'layout' => [
                'title' => 'WP2 Embed Dashboard',
                'theme' => 'dark',
                'cards' => [['id' => 'kpi-1', 'type' => 'kpi', 'title' => 'KPI', 'w' => 1, 'h' => 3]],
            ],
            'theme' => 'dark',
            'is_published' => true,
        ]);

        $response = $this->get('/api/dashboard/' . $dashboard->public_id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['title', 'cards', 'theme']);
        $response->assertJson([
            'title' => 'WP2 Embed Dashboard',
            'theme' => 'dark',
        ]);
        $this->assertCount(1, $response->json('cards'));
    }

    #[Test]
    public function public_dashboard_api_returns_404_for_unknown_id(): void
    {
        $response = $this->get('/api/dashboard/does-not-exist');

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Dashboard not found']);
    }

    // ===== TEACHING FEATURE: Duplicate a dashboard =====

    #[Test]
    public function duplicate_dashboard_creates_a_copy_with_same_layout(): void
    {
        $user = User::factory()->create();

        $client = Client::create([
            'name' => 'Duplicate Client',
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => '',
        ]);

        $layout = [
            'title' => 'Original Dashboard',
            'theme' => 'dark',
            'cards' => [
                ['id' => 'kpi-1', 'type' => 'kpi', 'title' => 'KPI', 'w' => 1, 'h' => 3],
                ['id' => 'line-1', 'type' => 'line', 'title' => 'Trend', 'w' => 3, 'h' => 6],
            ],
        ];

        $original = Dashboard::create([
            'client_id' => $client->id,
            'name' => 'Original Dashboard',
            'layout' => $layout,
            'theme' => 'dark',
            'is_published' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->postJson('/dashboard-builder/dashboards/' . $original->id . '/duplicate');

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name']);

        $newId = $response->json('id');
        $this->assertNotEquals($original->id, $newId, 'The duplicate should be a NEW dashboard row');

        // The copy belongs to the same client
        $copy = Dashboard::find($newId);
        $this->assertEquals($client->id, $copy->client_id, 'Copy should belong to the same client');

        // The copy has the same layout (all cards copied)
        $this->assertEquals($layout['cards'], $copy->layout['cards'], 'Copy should have the same cards');
        $this->assertEquals($layout['theme'], $copy->layout['theme'], 'Copy should have the same theme');

        // The copy has a distinct name indicating it is a copy
        $this->assertStringContainsStringIgnoringCase('copy', $copy->name, 'Copy name should mention "copy"');

        // Original is untouched
        $this->assertDatabaseHas('dashboards', ['id' => $original->id, 'name' => 'Original Dashboard']);
    }

    #[Test]
    public function duplicate_dashboard_requires_authentication(): void
    {
        $response = $this->postJson('/dashboard-builder/dashboards/1/duplicate');
        // Web routes use the `auth` middleware, which redirects guests to login
        // (302) rather than returning 401.
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    // ===== TEACHING FEATURE: Rename a dashboard (unique names per client) =====

    #[Test]
    public function rename_dashboard_updates_name_and_persists_to_database(): void
    {
        $user = User::factory()->create();
        $client = $this->makeRenameClient();

        $layout = ['title' => 'Original Name', 'theme' => 'dark', 'cards' => []];
        $dashboard = Dashboard::create([
            'client_id' => $client->id,
            'name' => 'Original Name',
            'layout' => $layout,
            'theme' => 'dark',
        ]);

        $response = $this->actingAs($user)
            ->patchJson('/dashboard-builder/dashboards/' . $dashboard->id, ['name' => 'Renamed Dashboard']);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $dashboard->id,
            'name' => 'Renamed Dashboard',
            'updated' => true,
        ]);

        // Persisted to the DB with a regenerated slug
        $this->assertDatabaseHas('dashboards', [
            'id' => $dashboard->id,
            'name' => 'Renamed Dashboard',
            'slug' => 'renamed-dashboard',
        ]);

        // Layout title stays in sync so the next Save doesn't revert the name
        $this->assertEquals('Renamed Dashboard', $dashboard->fresh()->layout['title']);
    }

    #[Test]
    public function rename_dashboard_rejects_duplicate_name_within_same_client(): void
    {
        $user = User::factory()->create();
        $client = $this->makeRenameClient();

        Dashboard::create(['client_id' => $client->id, 'name' => 'Existing Name', 'layout' => [], 'theme' => 'dark']);
        $target = Dashboard::create(['client_id' => $client->id, 'name' => 'Other Name', 'layout' => [], 'theme' => 'dark']);

        $response = $this->actingAs($user)
            ->patchJson('/dashboard-builder/dashboards/' . $target->id, ['name' => 'Existing Name']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');

        // The target was not renamed
        $this->assertDatabaseHas('dashboards', ['id' => $target->id, 'name' => 'Other Name']);
    }

    #[Test]
    public function rename_dashboard_rejects_duplicate_name_case_insensitively(): void
    {
        $user = User::factory()->create();
        $client = $this->makeRenameClient();

        Dashboard::create(['client_id' => $client->id, 'name' => 'Existing Name', 'layout' => [], 'theme' => 'dark']);
        $target = Dashboard::create(['client_id' => $client->id, 'name' => 'Other Name', 'layout' => [], 'theme' => 'dark']);

        $response = $this->actingAs($user)
            ->patchJson('/dashboard-builder/dashboards/' . $target->id, ['name' => 'existing name']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    #[Test]
    public function rename_dashboard_allows_same_name_in_a_different_client(): void
    {
        $user = User::factory()->create();
        $clientA = $this->makeRenameClient('Client A');
        $clientB = $this->makeRenameClient('Client B');

        $dashA = Dashboard::create(['client_id' => $clientA->id, 'name' => 'Shared Name', 'layout' => [], 'theme' => 'dark']);
        // A different client already has the same dashboard name
        Dashboard::create(['client_id' => $clientB->id, 'name' => 'Shared Name', 'layout' => [], 'theme' => 'dark']);

        // Renaming to its own current name is allowed: uniqueness is scoped per client
        $response = $this->actingAs($user)
            ->patchJson('/dashboard-builder/dashboards/' . $dashA->id, ['name' => 'Shared Name']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('dashboards', ['id' => $dashA->id, 'name' => 'Shared Name']);
    }

    #[Test]
    public function rename_dashboard_rejects_empty_name(): void
    {
        $user = User::factory()->create();
        $client = $this->makeRenameClient();

        $dashboard = Dashboard::create(['client_id' => $client->id, 'name' => 'Original Name', 'layout' => [], 'theme' => 'dark']);

        $response = $this->actingAs($user)
            ->patchJson('/dashboard-builder/dashboards/' . $dashboard->id, ['name' => '   ']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    #[Test]
    public function rename_dashboard_returns_404_for_unknown_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->patchJson('/dashboard-builder/dashboards/999999', ['name' => 'Nope']);

        $response->assertStatus(404);
    }

    #[Test]
    public function rename_dashboard_requires_authentication(): void
    {
        $response = $this->patchJson('/dashboard-builder/dashboards/1', ['name' => 'Nope']);
        // Web routes use the `auth` middleware, which redirects guests to login
        // (302) rather than returning 401.
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    private function makeRenameClient(string $name = 'Rename Client'): Client
    {
        return Client::create([
            'name' => $name,
            'db_host' => '127.0.0.1',
            'db_port' => 3306,
            'db_database' => 'test_db',
            'db_username' => 'root',
            'db_password' => '',
        ]);
    }
}
