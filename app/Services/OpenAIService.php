<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $model;
    private CRMService $crmService;

    public function __construct(CRMService $crmService)
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o');
        $this->crmService = $crmService;
    }

        public function chat(string $userMessage): array
        {
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'You are a professional CRM assistant. You work with a structured CRM system that includes companies, customers, deals, quotations, tasks, and time_entries. You have access to tools that return REAL data from the CRM.

CRITICAL RULES:
1. You MUST use tools for ALL data-related queries.
2. You MUST NOT calculate, filter, or guess data manually.
3. You MUST NOT reduce results after receiving them.
4. The backend ALWAYS returns correct, filtered, and complete data.
5. NEVER modify counts or remove items yourself.

If backend returns 5 items → you show 5 items.

DEFAULT BEHAVIOR (backend handles these automatically):
- Tasks: By default exclude completed/cancelled. Active = pending, in_progress, needs_testing, needs_clarification
- Deals: By default exclude closed_won, closed_lost, cancelled. Active = lead, qualified, proposal, negotiation
- Clients: Can be filtered by payment_behavior (fast, normal, slow, risky), value_level (high, medium, low), status (active, inactive, prospect)

USER INTENT → TOOL PARAMETERS:
- "anna mulle 5 viimast taski" → use get_tasks with limit=5
- "aktiivsed taskid" → use get_tasks with exclude_completed=true
- "valmis taskid" → use get_tasks with status=completed
- "helistamise taskid" → use get_tasks with type=call
- "closed deals" → use get_deals with stage=closed_won
- "läbirääkimised" → use get_deals with stage=negotiation
- "risky kliendid" → use get_clients with payment_behavior=risky
- "kõrge väärtusega kliendid" → use get_clients with value_level=high

RESPONSE FORMAT:
- Keep responses SHORT and CLEAR
- Use bullet points (•)
- Show ONLY important fields: title, status/stage, type, price/value, deadline
- Always format prices: 40€ (not 40.00)
- Always show count: 📋 Ülesanded (5 näidatud)
- If backend returns fewer items than requested, show actual number

LINK FORMAT (always clickable):
Tasks: [Task name](http://45.93.139.96:8082/tasks/{id})
Deals: [Deal name](http://45.93.139.96:8082/deals/{id})
Clients: [Client name](http://45.93.139.96:8082/clients/{id})
Quotations: [Quotation title](http://45.93.139.96:8082/quotations/{id})

IMPORTANT:
You are NOT a database. You are NOT doing calculations. You are an interpreter of user intent, a caller of backend tools, a formatter of results. Backend = brain, You = interface.'
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage
                ]
            ];

            $tools = $this->getToolDefinitions();

            try {
                $response = $this->sendToOpenAI($messages, $tools);

                if (isset($response['error'])) {
                    return ['reply' => 'Vabandust, tekkis viga AI teenusega suhtlemisel.'];
                }

                $assistantMessage = $response['choices'][0]['message'] ?? null;

                if (!$assistantMessage) {
                    return ['reply' => 'Vabandust, ei saanud vastust.'];
                }

                if (isset($assistantMessage['tool_calls']) && !empty($assistantMessage['tool_calls'])) {
                    return $this->handleToolCalls($messages, $assistantMessage, $tools);
                }

                return ['reply' => $assistantMessage['content'] ?? 'Vabandust, ei saanud vastust.'];

            } catch (\Exception $e) {
                Log::error('OpenAI chat error', ['message' => $e->getMessage()]);
                return ['reply' => 'Vabandust, tekkis viga AI teenusega suhtlemisel.'];
            }
        }

        private function handleToolCalls(array $messages, array $assistantMessage, array $tools): array
        {
            $messages[] = $assistantMessage;

            foreach ($assistantMessage['tool_calls'] as $toolCall) {
                $functionName = $toolCall['function']['name'];
                $arguments = json_decode($toolCall['function']['arguments'], true);

                $result = $this->executeToolCall($functionName, $arguments);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall['id'],
                    'content' => json_encode($result)
                ];
            }

            try {
                $finalResponse = $this->sendToOpenAI($messages, $tools);

                if (isset($finalResponse['error'])) {
                    return ['reply' => 'Vabandust, tekkis viga andmete töötlemisel.'];
                }

                $finalMessage = $finalResponse['choices'][0]['message']['content'] ?? 'Vabandust, ei saanud vastust.';

                return ['reply' => $finalMessage];

            } catch (\Exception $e) {
                Log::error('OpenAI final response error', ['message' => $e->getMessage()]);
                return ['reply' => 'Vabandust, tekkis viga andmete töötlemisel.'];
            }
        }

        private function executeToolCall(string $functionName, array $arguments): array
        {
            // Check if we need to use filtered methods
            $taskFilters = ['type', 'exclude_completed', 'is_quick_win', 'is_blocking', 'work_type', 'risk_level'];
            $clientFilters = ['payment_behavior', 'value_level', 'client_attribute', 'cooperation_level'];
            $dealFilters = ['stage', 'is_fast_cash', 'risk_level', 'clarity_level', 'work_type'];
            
            $hasTaskFilter = !empty(array_intersect(array_keys($arguments), $taskFilters));
            $hasClientFilter = !empty(array_intersect(array_keys($arguments), $clientFilters));
            $hasDealFilter = !empty(array_intersect(array_keys($arguments), $dealFilters));
            
            return match ($functionName) {
                'get_tasks' => $hasTaskFilter 
                    ? $this->crmService->getTasksFiltered($arguments)
                    : $this->crmService->getTasks($arguments),
                'get_clients' => $hasClientFilter 
                    ? $this->crmService->getClientsFiltered($arguments)
                    : $this->crmService->getClients($arguments),
                'get_deals' => $hasDealFilter 
                    ? $this->crmService->getDealsFiltered($arguments)
                    : $this->crmService->getDeals($arguments),
                'get_time_entries' => $this->crmService->getTimeEntries($arguments),
                'get_quotations' => $this->crmService->getQuotations($arguments),
                'get_quotation_items' => $this->crmService->getQuotationItems($arguments),
                'get_task_time_by_name' => $this->crmService->getTaskTimeByName($arguments['task_name'] ?? ''),
                'get_task_metadata' => $this->crmService->getTaskMetadata(),
                default => ['error' => 'Unknown function']
            };
        }

        private function sendToOpenAI(array $messages, array $tools): array
        {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'tools' => $tools,
                    'tool_choice' => 'auto'
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('OpenAI API failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return ['error' => 'OpenAI API request failed'];
        }

        private function getToolDefinitions(): array
        {
            return [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_tasks',
                        'description' => 'Get tasks from CRM with optional filtering. Backend filters and returns only matching results.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'description' => 'Filter by task type: call, email, meeting, follow_up, development, bug_fix, content_creation, proposal_creation, testing, other'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => 'Filter by status: pending, in_progress, completed, cancelled, needs_testing, needs_clarification'
                                ],
                                'exclude_completed' => [
                                    'type' => 'boolean',
                                    'description' => 'If true, exclude completed and cancelled tasks. Use for "aktiivsed taskid" queries.'
                                ],
                                'is_quick_win' => [
                                    'type' => 'boolean',
                                    'description' => 'Filter by is_quick_win flag (true/false)'
                                ],
                                'is_blocking' => [
                                    'type' => 'boolean',
                                    'description' => 'Filter by is_blocking flag (true/false)'
                                ],
                                'work_type' => [
                                    'type' => 'string',
                                    'description' => 'Filter by work_type: technical, design, copywriting, marketing, ecommerce, website, project, maintenance, other'
                                ],
                                'risk_level' => [
                                    'type' => 'string',
                                    'description' => 'Filter by risk_level: low, medium, high'
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'description' => 'Maximum number of tasks to return (e.g., 5 for "5 viimast")'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_clients',
                        'description' => 'Get clients from CRM with optional filtering. Backend filters and returns only matching results.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'search' => [
                                    'type' => 'string',
                                    'description' => 'Search term to filter clients by name'
                                ],
                                'payment_behavior' => [
                                    'type' => 'string',
                                    'description' => 'Filter by payment behavior: fast, normal, slow, risky'
                                ],
                                'value_level' => [
                                    'type' => 'string',
                                    'description' => 'Filter by value level: high, medium, low'
                                ],
                                'client_attribute' => [
                                    'type' => 'string',
                                    'description' => 'Filter by client attribute: kuldklient, hõbe, tavaline'
                                ],
                                'cooperation_level' => [
                                    'type' => 'string',
                                    'description' => 'Filter by cooperation level: easy, normal, difficult'
                                ],
                                'status' => [
                                    'type' => 'string',
                                    'description' => 'Filter by status: active, inactive, prospect'
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'description' => 'Maximum number of clients to return'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_deals',
                        'description' => 'Get deals from CRM with optional filtering. Backend filters and returns only matching results.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'stage' => [
                                    'type' => 'string',
                                    'description' => 'Filter by stage: lead, qualified, proposal, negotiation, closed_won, closed_lost, cancelled, arveldatud, valmis'
                                ],
                                'is_fast_cash' => [
                                    'type' => 'boolean',
                                    'description' => 'Filter by is_fast_cash flag (true/false)'
                                ],
                                'risk_level' => [
                                    'type' => 'string',
                                    'description' => 'Filter by risk level: low, medium, high'
                                ],
                                'clarity_level' => [
                                    'type' => 'string',
                                    'description' => 'Filter by clarity level: clear, medium, vague'
                                ],
                                'work_type' => [
                                    'type' => 'string',
                                    'description' => 'Filter by work type: technical, design, copywriting, marketing, ecommerce, website, project, maintenance, other'
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'description' => 'Maximum number of deals to return'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_time_entries',
                        'description' => 'Get time entries from CRM. Returns array with fields: id, task_id, user_id, duration, date, description. Use this to see how much time was spent on tasks. Response includes total_duration field.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'task_id' => [
                                    'type' => 'number',
                                    'description' => 'Filter by specific task ID'
                                ],
                                'user_id' => [
                                    'type' => 'number',
                                    'description' => 'Filter by specific user ID'
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'description' => 'Maximum number of entries to return'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_quotations',
                        'description' => 'Get quotations (pakkumised) from CRM. Returns array with fields: id, customer_id, company_id, status (draft, sent, accepted, rejected, expired), total_amount, created_at. You MUST filter results yourself based on user query.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'customer_id' => [
                                    'type' => 'number',
                                    'description' => 'Filter by customer ID'
                                ],
                                'company_id' => [
                                    'type' => 'number',
                                    'description' => 'Filter by company ID'
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'description' => 'Maximum number of quotations to return'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_quotation_items',
                        'description' => 'Get quotation items (pakkumise read) from CRM. Returns array with fields: id, quotation_id, description, quantity, unit_price, total. Response includes total_amount field.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'quotation_id' => [
                                    'type' => 'number',
                                    'description' => 'Filter by specific quotation ID'
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'description' => 'Maximum number of items to return'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_task_time_by_name',
                        'description' => 'Find a task by its name and get all time entries for it in ONE call. Use this when user asks about time spent on a task BY NAME (not ID). Returns: task info, time_entries array, task_id, task_title.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'task_name' => [
                                    'type' => 'string',
                                    'description' => 'The name or partial name of the task to search for (case-insensitive)'
                                ]
                            ],
                            'required' => ['task_name']
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_task_metadata',
                        'description' => 'Get metadata about tasks: available types (call, email, meeting, follow_up, development, bug_fix, content_creation, proposal_creation, testing, other), available statuses, and priorities. Use this when user asks what task types are possible or available.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => (object)[]
                        ]
                    ]
                ]
            ];
        }
    }
