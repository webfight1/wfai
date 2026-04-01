<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CRMService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.crm.url'), '/');
        $this->token = config('services.crm.token');
    }

    public function getTasks(array $params = []): array
    {
        try {
            // Always set a high limit to prevent API default limit of 3
            if (!isset($params['limit'])) {
                $params['limit'] = 100;
            }

            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/tasks", $this->filterParams($params));

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result;
            }

            Log::error('CRM API getTasks failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['error' => 'Failed to fetch tasks from CRM'];
        } catch (\Exception $e) {
            Log::error('CRM API getTasks exception', ['message' => $e->getMessage()]);
            return ['error' => 'CRM API connection failed'];
        }
    }

    public function getClients(array $params = []): array
    {
        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/clients", $this->filterParams($params));

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result;
            }

            Log::error('CRM API getClients failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['error' => 'Failed to fetch clients from CRM'];
        } catch (\Exception $e) {
            Log::error('CRM API getClients exception', ['message' => $e->getMessage()]);
            return ['error' => 'CRM API connection failed'];
        }
    }

    public function getDeals(array $params = []): array
    {
        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/deals", $this->filterParams($params));

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result;
            }

            Log::error('CRM API getDeals failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['error' => 'Failed to fetch deals from CRM'];
        } catch (\Exception $e) {
            Log::error('CRM API getDeals exception', ['message' => $e->getMessage()]);
            return ['error' => 'CRM API connection failed'];
        }
    }

    public function getTimeEntries(array $params = []): array
    {
        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/time-entries", $this->filterParams($params));

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result;
            }

            Log::error('CRM API getTimeEntries failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['error' => 'Failed to fetch time entries from CRM'];
        } catch (\Exception $e) {
            Log::error('CRM API getTimeEntries exception', ['message' => $e->getMessage()]);
            return ['error' => 'CRM API connection failed'];
        }
    }

    public function getQuotations(array $params = []): array
    {
        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/quotations", $this->filterParams($params));

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result;
            }

            Log::error('CRM API getQuotations failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['error' => 'Failed to fetch quotations from CRM'];
        } catch (\Exception $e) {
            Log::error('CRM API getQuotations exception', ['message' => $e->getMessage()]);
            return ['error' => 'CRM API connection failed'];
        }
    }

    public function getQuotationItems(array $params = []): array
    {
        try {
            $response = Http::withToken($this->token)
                ->get("{$this->baseUrl}/api/quotation-items", $this->filterParams($params));

            if ($response->successful()) {
                $result = $response->json();
                return $result['data'] ?? $result;
            }

            Log::error('CRM API getQuotationItems failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['error' => 'Failed to fetch quotation items from CRM'];
        } catch (\Exception $e) {
            Log::error('CRM API getQuotationItems exception', ['message' => $e->getMessage()]);
            return ['error' => 'CRM API connection failed'];
        }
    }

    public function getTaskTimeByName(string $taskName): array
    {
        try {
            // First, get all tasks
            $tasks = $this->getTasks([]);
            
            if (isset($tasks['error'])) {
                return $tasks;
            }
            
            // Find task by name (case-insensitive partial match with fuzzy matching)
            $foundTask = null;
            $normalizedSearchName = $this->normalizeTaskName($taskName);
            
            foreach ($tasks as $task) {
                $normalizedTaskTitle = $this->normalizeTaskName($task['title']);
                
                // Try exact partial match first
                if (stripos($task['title'], $taskName) !== false) {
                    $foundTask = $task;
                    break;
                }
                
                // Try normalized match (removes extra spaces, special chars)
                if (stripos($normalizedTaskTitle, $normalizedSearchName) !== false) {
                    $foundTask = $task;
                    break;
                }
            }
            
            if (!$foundTask) {
                return ['error' => "Task '{$taskName}' not found"];
            }
            
            // Get time entries for this task
            $timeEntries = $this->getTimeEntries(['task_id' => $foundTask['id']]);
            
            if (isset($timeEntries['error'])) {
                return $timeEntries;
            }
            
            return [
                'task' => $foundTask,
                'time_entries' => $timeEntries,
                'task_id' => $foundTask['id'],
                'task_title' => $foundTask['title']
            ];
            
        } catch (\Exception $e) {
            Log::error('CRM API getTaskTimeByName exception', ['message' => $e->getMessage()]);
            return ['error' => 'Failed to get task time'];
        }
    }

    public function getTaskMetadata(): array
    {
        return [
            'available_types' => [
                'call' => 'Helistamine',
                'email' => 'E-mail',
                'meeting' => 'Kohtumine',
                'follow_up' => 'Jätkamine',
                'development' => 'Arendus',
                'bug_fix' => 'Vea parandus',
                'content_creation' => 'Sisu loomine',
                'proposal_creation' => 'Pakkumise loomine',
                'testing' => 'Testimine',
                'other' => 'Muu'
            ],
            'available_statuses' => [
                'pending' => 'Ootel',
                'in_progress' => 'Töös',
                'completed' => 'Valmis',
                'cancelled' => 'Tühistatud'
            ],
            'available_priorities' => [
                'low' => 'Madal',
                'medium' => 'Keskmine',
                'high' => 'Kõrge',
                'urgent' => 'Kiire'
            ]
        ];
    }

    public function getTasksFiltered(array $filters): array
    {
        try {
            // Get all tasks from API
            $allTasks = $this->getTasks(['limit' => 100]);
            
            if (isset($allTasks['error'])) {
                return $allTasks;
            }
            
            $filtered = $allTasks;
            
            // Apply type filter
            if (!empty($filters['type'])) {
                $filtered = array_filter($filtered, fn($task) => 
                    ($task['type'] ?? '') === $filters['type']
                );
            }
            
            // Apply status filter
            if (!empty($filters['status'])) {
                $filtered = array_filter($filtered, fn($task) => 
                    ($task['status'] ?? '') === $filters['status']
                );
            }
            
            // Apply exclude_completed filter
            if (!empty($filters['exclude_completed'])) {
                $filtered = array_filter($filtered, fn($task) => 
                    !in_array($task['status'] ?? '', ['completed', 'cancelled'])
                );
            }
            
            // Apply is_quick_win filter
            if (isset($filters['is_quick_win'])) {
                $filtered = array_filter($filtered, fn($task) => 
                    ($task['is_quick_win'] ?? false) === $filters['is_quick_win']
                );
            }
            
            // Apply is_blocking filter
            if (isset($filters['is_blocking'])) {
                $filtered = array_filter($filtered, fn($task) => 
                    ($task['is_blocking'] ?? false) === $filters['is_blocking']
                );
            }
            
            // Apply work_type filter
            if (!empty($filters['work_type'])) {
                $filtered = array_filter($filtered, fn($task) => 
                    ($task['work_type'] ?? '') === $filters['work_type']
                );
            }
            
            // Apply risk_level filter
            if (!empty($filters['risk_level'])) {
                $filtered = array_filter($filtered, fn($task) => 
                    ($task['risk_level'] ?? '') === $filters['risk_level']
                );
            }
            
            // Apply limit
            if (!empty($filters['limit'])) {
                $filtered = array_slice($filtered, 0, (int)$filters['limit']);
            }
            
            return array_values($filtered);
            
        } catch (\Exception $e) {
            Log::error('CRM API getTasksFiltered exception', ['message' => $e->getMessage()]);
            return ['error' => 'Failed to filter tasks'];
        }
    }

    public function getClientsFiltered(array $filters): array
    {
        try {
            // Get all clients from API
            $allClients = $this->getClients(['limit' => 100]);
            
            if (isset($allClients['error'])) {
                return $allClients;
            }
            
            $filtered = $allClients;
            
            // Apply payment_behavior filter
            if (!empty($filters['payment_behavior'])) {
                $filtered = array_filter($filtered, fn($client) => 
                    ($client['payment_behavior'] ?? '') === $filters['payment_behavior']
                );
            }
            
            // Apply value_level filter
            if (!empty($filters['value_level'])) {
                $filtered = array_filter($filtered, fn($client) => 
                    ($client['value_level'] ?? '') === $filters['value_level']
                );
            }
            
            // Apply client_attribute filter
            if (!empty($filters['client_attribute'])) {
                $filtered = array_filter($filtered, fn($client) => 
                    ($client['client_attribute'] ?? '') === $filters['client_attribute']
                );
            }
            
            // Apply cooperation_level filter
            if (!empty($filters['cooperation_level'])) {
                $filtered = array_filter($filtered, fn($client) => 
                    ($client['cooperation_level'] ?? '') === $filters['cooperation_level']
                );
            }
            
            // Apply status filter
            if (!empty($filters['status'])) {
                $filtered = array_filter($filtered, fn($client) => 
                    ($client['status'] ?? '') === $filters['status']
                );
            }
            
            // Apply limit
            if (!empty($filters['limit'])) {
                $filtered = array_slice($filtered, 0, (int)$filters['limit']);
            }
            
            return array_values($filtered);
            
        } catch (\Exception $e) {
            Log::error('CRM API getClientsFiltered exception', ['message' => $e->getMessage()]);
            return ['error' => 'Failed to filter clients'];
        }
    }

    public function getDealsFiltered(array $filters): array
    {
        try {
            // Get all deals from API
            $allDeals = $this->getDeals(['limit' => 100]);
            
            if (isset($allDeals['error'])) {
                return $allDeals;
            }
            
            $filtered = $allDeals;
            
            // Apply stage filter
            if (!empty($filters['stage'])) {
                $filtered = array_filter($filtered, fn($deal) => 
                    ($deal['stage'] ?? '') === $filters['stage']
                );
            }
            
            // Apply is_fast_cash filter
            if (isset($filters['is_fast_cash'])) {
                $filtered = array_filter($filtered, fn($deal) => 
                    ($deal['is_fast_cash'] ?? false) === $filters['is_fast_cash']
                );
            }
            
            // Apply risk_level filter
            if (!empty($filters['risk_level'])) {
                $filtered = array_filter($filtered, fn($deal) => 
                    ($deal['risk_level'] ?? '') === $filters['risk_level']
                );
            }
            
            // Apply clarity_level filter
            if (!empty($filters['clarity_level'])) {
                $filtered = array_filter($filtered, fn($deal) => 
                    ($deal['clarity_level'] ?? '') === $filters['clarity_level']
                );
            }
            
            // Apply work_type filter
            if (!empty($filters['work_type'])) {
                $filtered = array_filter($filtered, fn($deal) => 
                    ($deal['work_type'] ?? '') === $filters['work_type']
                );
            }
            
            // Apply limit
            if (!empty($filters['limit'])) {
                $filtered = array_slice($filtered, 0, (int)$filters['limit']);
            }
            
            return array_values($filtered);
            
        } catch (\Exception $e) {
            Log::error('CRM API getDealsFiltered exception', ['message' => $e->getMessage()]);
            return ['error' => 'Failed to filter deals'];
        }
    }

    private function normalizeTaskName(string $name): string
    {
        // Remove extra spaces, convert to lowercase, remove special characters
        $normalized = mb_strtolower($name, 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $normalized); // Multiple spaces to single space
        $normalized = trim($normalized);
        return $normalized;
    }

    private function filterParams(array $params): array
    {
        return array_filter($params, fn($value) => $value !== null && $value !== '');
    }
}
