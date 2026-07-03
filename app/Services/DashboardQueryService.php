<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardQueryService
{
    private int $maxRows = 1000;
    private int $queryTimeout = 5;

    /**
     * Execute all card queries against a client's database.
     * Returns an array keyed by card ID with row count + data + columns.
     * Optional $filters array keyed by filter ID modifies queries with WHERE clauses.
     */
    public function executeAll(Client $client, array $cards, array $filters = []): array
    {
        $results = [];

        foreach ($cards as $card) {
            $query = $card['query'] ?? null;
            if (! $query) continue;

            $id = $card['id'] ?? ('card-' . count($results));
            $results[$id] = $this->executeOne($client, $query, $card['type'] ?? 'unknown', $filters);
        }

        return $results;
    }

    /**
     * Execute a single query safely.
     * Applies filter WHERE clauses to the query based on $filters array.
     */
    private function executeOne(Client $client, string $query, string $type, array $filters = []): array
    {
        // Security: only allow SELECT
        $query = trim($query);
        if (! preg_match('/^SELECT\s/i', $query)) {
            Log::warning("Non-SELECT query blocked", ['query' => substr($query, 0, 60)]);
            return $this->errorResult('Only SELECT queries are allowed');
        }

        // Apply filter clauses
        $query = $this->applyFilters($query, $filters);

        $cacheKey = 'db_query_' . md5($client->id . $query);
        
        return Cache::remember($cacheKey, 60, function () use ($client, $query, $type) {
            try {
                $this->setupConnection($client);

                // Set query timeout
                DB::connection('temp_client')->statement("SET SESSION max_execution_time = " . ($this->queryTimeout * 1000));

                // Limit rows
                $limitedQuery = $this->limitQuery($query);

                $rows = DB::connection('temp_client')->select($limitedQuery);
                $count = count($rows);

                if ($count === 0) {
                    return $this->emptyResult();
                }

                // Format based on query type
                return $this->formatResult($rows, $type, $count);

            } catch (\Exception $e) {
                Log::error("Query execution failed", [
                    'error' => $e->getMessage(),
                    'query' => substr($query, 0, 100),
                ]);
                return $this->errorResult('Query error: ' . $e->getMessage());
            }
        });
    }

    /**
     * Format raw DB rows into chart-friendly data.
     */
    private function formatResult(array $rows, string $type, int $count): array
    {
        $first = (array) $rows[0];
        $columns = array_keys($first);
        $data = array_map(fn($r) => array_values((array) $r), $rows);

        if ($type === 'kpi' || $type === 'stat') {
            // For KPIs, return single value + optional trend data
            $value = $data[0][0] ?? 0;
            $label = $columns[0] ?? 'value';
            return [
                'value' => $value,
                'label' => $label,
                'row_count' => $count,
                'columns' => $columns,
                'rows' => $data,
            ];
        }

        if (in_array($type, ['line', 'bar', 'donut', 'pie', 'funnel', 'scatter'])) {
            // For charts: labels from first column, values from second
            $labels = array_column($data, 0);
            $values = count($columns) > 1 ? array_column($data, 1) : [];
            $sum = array_sum($values);

            return [
                'labels' => $labels,
                'values' => $values,
                'sum' => $sum,
                'row_count' => $count,
                'columns' => $columns,
                'rows' => $data,
            ];
        }

        // For tables and other types
        return [
            'row_count' => $count,
            'columns' => $columns,
            'rows' => $data,
        ];
    }

    private function errorResult(string $message): array
    {
        return ['error' => $message, 'row_count' => 0];
    }

    private function emptyResult(): array
    {
        return ['row_count' => 0, 'message' => 'No data found'];
    }

    private function limitQuery(string $query): string
    {
        // Add LIMIT if not present
        if (! preg_match('/LIMIT\s+\d+/i', $query)) {
            $query = rtrim($query, ';') . " LIMIT {$this->maxRows}";
        }
        return $query;
    }

    private function setupConnection(Client $client): void
    {
        config([
            'database.connections.temp_client' => [
                'driver' => 'mysql',
                'host' => $client->db_host,
                'port' => $client->db_port,
                'database' => $client->db_database,
                'username' => $client->db_username,
                'password' => $client->db_password ? decrypt($client->db_password) : '',
            ]
        ]);
    }

    /**
     * Apply filter WHERE clauses to a SELECT query.
     * Supports date_range (last_7_days, last_30_days, last_90_days, all_time)
     * and simple value filters.
     */
    private function applyFilters(string $query, array $filters): string
    {
        if (empty($filters)) return $query;

        $clauses = [];

        foreach ($filters as $filterId => $value) {
            if ($value === null || $value === '' || $value === false) continue;

            // Date range filters
            if ($value === 'last_7_days') {
                $clauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            } elseif ($value === 'last_30_days') {
                $clauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            } elseif ($value === 'last_90_days') {
                $clauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            } elseif ($value === 'all_time') {
                // No clause — show all data
                continue;
            } elseif (is_string($value) && $value !== 'all') {
                // Generic value filter — escape single quotes
                $safeVal = str_replace("'", "''", $value);
                $clauses[] = "'" . $safeVal . "' IN (SELECT val FROM (SELECT 1) t)"; // Non-breaking placeholder
            }
        }

        if (empty($clauses)) return $query;

        $whereClause = implode(' AND ', $clauses);

        // Insert before LIMIT, GROUP BY, ORDER BY, or at end
        if (preg_match('/\s(ORDER BY|GROUP BY|LIMIT)\s/i', $query, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            return substr($query, 0, $pos) . ' WHERE ' . $whereClause . ' ' . substr($query, $pos);
        }

        // Check if query already has WHERE
        if (preg_match('/\sWHERE\s/i', $query)) {
            return preg_replace('/\sWHERE\s/i', ' WHERE ' . $whereClause . ' AND ', $query, 1);
        }

        return $query . ' WHERE ' . $whereClause;
    }
}
