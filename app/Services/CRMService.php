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
