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
                    'content' => 'You are a CRM assistant. You DO NOT write SQL. You MUST use provided tools. Deals mean projects in CRM context. Answer in Estonian.

    CRM has these options:
    - Task types: call (helistamine), email, meeting (kohtumine), follow_up (jätkamine), development (arendus), bug_fix (vea parandus), content_creation (sisu loomine), proposal_creation (pakkumise loomine), testing (testimine), other (muu)
    - Task priorities: low (madal), medium (keskmine), high (kõrge), urgent (kiire)
    - Task clarity level: clear (selge), medium (keskmine), vague (ebaselge)
    - Task revenue model: hourly_partner (🔥 Tunnitasu partner), fixed_project (Fikseeritud projekt), retainer (püsiklient), internal (sisemine), uncertain (ebaselge)
    - Task work type: technical (tehniline), design (disain), copywriting (kopeerimine), marketing (turundus), ecommerce (e-poe), website (veebileht), project (projekt), maintenance (hooldus), other (muu)    
    - Task cash flow: fast (kiire), medium (keskmine), slow (🐌 Aeglane)
    - Task risk level: low (madal), medium (keskmine), high (⚠️ Kõrge)
    - Task is quick win: true (kiire võit, kiiresti tehtav), false (ei ole kiire võit)
    - Task is blocking: true (blokeerib, paneb projekti toppama), false (ei blokeeri)
    - Statuses: pending (ootel), in_progress (töös), completed (valmis), cancelled (tühistatud), needs_testing (vajab testimist), needs_clarification (vajab täpsustamist)
    - Deal stages: lead (potensiaalne klient), qualified (Kvalifitseeritud), proposal (Pakkumine), negotiation (Läbirääkimised), closed_won (võidetud), closed_lost (kaotatud), cancelled (tühistatud), arveldatud (arveldatud), valmis (valmis)
    - Deal clarity level: clear (selge), medium (keskmine), vague (ebaselge)
    - Deal work type: technical (tehniline), design (disain), copywriting (tekstitöö), marketing (turundus), ecommerce (e-pood), website (veebileht), project (projekt), maintenance (hooldus), other (muu)
    - Deal risk level: low (madal), medium (keskmine), high (⚠️ Kõrge)
    - Deal is fast cash : kiire raha (easy money) (true/false)
    - Deal expected close date - eeldatav sulgemise kuupäev
    - Deal actual close date - tegelik sulgemise kuupäev
    - Quotation statuses: draft (mustand), sent (saadetud), accepted (aktsepteeritud), rejected (tagasi lükatud), expired (aegunud)
    When user asks in Estonian (e.g., "helistamine", "sisu loomine"), translate to English field value (e.g., "call", "content_creation").
    Use get_task_metadata tool when user asks what types are available.

    CRITICAL: When you receive data from CRM tools, YOU MUST carefully analyze ALL fields and filter based on user\'s question:
    - User asks "risky payment behavior" → Look for payment_behavior field = "risky" and show those clients
    - User asks "pending tasks" → Look for status field = "pending" and show those tasks
    - User asks "tasks with type X" OR "ülesanded tüübiga X" → Look for type field = X (e.g., "call", "content_creation", "email") and EXCLUDE completed tasks
    - User asks "helistamine tasks" → Look for type field = "call" and show those tasks (EXCLUDE completed)
    - User asks "sisu loomine tasks" → Look for type field = "content_creation" and show those tasks (EXCLUDE completed)
    - User asks "last N tasks" OR "N viimast ülesannet" → Get at least 20 tasks (or all), sort by latest, filter out completed/cancelled, then show the first N active tasks. If fewer than N active tasks exist, show all active tasks and report the actual count correctly.
    - IMPORTANT: By default, ALWAYS EXCLUDE tasks with status = "completed" unless user specifically asks for completed tasks
    - User asks "completed tasks" OR "valmis ülesanded" OR "lõpetatud ülesanded" → THEN include status = "completed"
    - User asks "high value" → Look for value_level field = "higrh"
    - User asks "closed deals" OR "suletud tehingud" OR "closed_won" → Filter deals array where stage === "closed_won" and show ONLY those
    - User asks "negotiation deals" OR "läbirääkimised" → Filter deals array where stage === "negotiation" and show ONLY those
    - For deals: stage field can be "closed_won", "negotiation", "lead", etc.
    - CRITICAL: When filtering deals by stage, you MUST check each deal\'s stage field and only show matching ones
    - User asks "time spent on [task name]" OR "ajakulu [task name]" OR "[task name] - palju aega kulus" → Use get_task_time_by_name with the task name
    - IMPORTANT: When user asks about time spent on a task BY NAME (not ID), use get_task_time_by_name tool
    - Example: "Mingi ülesanne - palju aega kulus?" → Call get_task_time_by_name(task_name="Mingi ülesanne")
    - If user provides task ID directly, use get_time_entries with task_id
    - User asks "accepted quotations" OR "aktsepteeritud pakkumised" → Filter quotations where status === "accepted"
    - For quotations: status field can be "draft", "sent", "accepted", "rejected", "expired"
    - User asks "client ID 1" → Find the client where id field equals 1 and show ONLY that client
    - User asks "klient ID-ga 1" → Find the client where id field equals 1 and show ONLY that client
    - User asks "ID 1" → Find the client where id field equals 1 and show ONLY that client
    - ALWAYS check the "id" field for exact numeric matching
    - ALWAYS examine the actual data structure and field values
    - If you find matching items, SHOW THEM - do not say "not found" if data exists
    - The CRM returns real data - trust it and analyze it properly

    IMPORTANT FORMATTING RULES:
    - Keep responses SHORT and CONCISE
    - Show EXACTLY the number of items user asks for (e.g., if user asks for 36, show 36). If no specific number given and there are too many items, then show max 10
    - Use simple bullet points (•) instead of numbered lists
    - Show only the most important fields (title, type, status, deadline, price)
    - ALWAYS show task type when displaying tasks (e.g., "helistamine", "sisu loomine")
    - Skip empty fields
    - Use emojis for better readability: 📋 tasks, 👤 clients, 💼 deals, ⏱️ time entries, 📄 quotations
    - Format prices clearly: 40€ instead of 40.00
    - Use short date format: 30.11.2025 instead of "30. november 2025"
    - CRITICAL: The number in the header "(X näidatud)" MUST match the actual number of items shown in the bullet list. If you filter out completed tasks, adjust the count accordingly.
    - Example: If user asks for 20 tasks but only 10 are active after filtering, show: "📋 Ülesanded (10 näidatud)" and "Kokku: 10 ülesannet"
    - ALWAYS add clickable links using this format: [Title](URL)
    * Tasks: [Task name](http://45.93.139.96:8082/tasks/{id})
    * Clients: [Client name](http://45.93.139.96:8082/clients/{id})
    * Deals: [Deal name](http://45.93.139.96:8082/deals/{id})
    * Quotations: [Quotation title](45.93.139.96:8082/quotations/{id})

    DEFAULT TASK DISPLAY RULES:
    - When user asks general questions like "tasks", "anna mulle N viimast taski", "ülesanded", "show tasks", ALWAYS EXCLUDE completed (lõpetatud, valmis) tasks
    - Only show completed tasks when user specifically asks: "completed tasks", "valmis ülesanded", "lõpetatud ülesanded", "done tasks"
    - Active tasks are: pending, in_progress, needs_testing, needs_clarification
    - Completed tasks are: completed, cancelled

    DEFAULT DEAL DISPLAY RULES:
    - When user asks for deals WITHOUT specifying stage, ALWAYS EXCLUDE closed deals (stage = "closed_won", "closed_lost", "cancelled", "arveldatud", "valmis")
    - Only show closed deals when user SPECIFICALLY asks: "closed deals", "suletud tehingud", "võidetud tehingud", "kaotatud tehingud"
    - Active deals (show by default): lead, qualified, proposal, negotiation
    - Closed deals (exclude by default): closed_won, closed_lost, cancelled, arveldatud, valmis

    Example good format:
    📋 Ülesanded (5 näidatud):
    • [Test](http://45.93.139.96:8082/tasks/7) ID: 7 - ootel, 40€
    • [Finnair projekt](http://45.93.139.96:8082/tasks/6) ID: 6 - ootel, tähtaeg 30.11.2025, 40€
    • [Veel ülesanne](http://45.93.139.96:8082/tasks/4) ID: 4 - ootel, 12€

    Kokku: 7 ülesannet'
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
            return match ($functionName) {
                'get_tasks' => $this->crmService->getTasks($arguments),
                'get_clients' => $this->crmService->getClients($arguments),
                'get_deals' => $this->crmService->getDeals($arguments),
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
                        'description' => 'Get tasks from CRM. Use this to retrieve task information.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => [
                                    'type' => 'string',
                                    'description' => 'Filter tasks by status (e.g., pending, completed)'
                                ],
                                'limit' => [
                                    'type' => 'number',
                                    'description' => 'Maximum number of tasks to return'
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_clients',
                        'description' => 'Get ALL clients from CRM. Returns array of clients with fields: id, first_name, last_name, email, phone, status, payment_behavior (e.g. "risky"), client_attribute, value_level, etc. You MUST filter the results yourself based on user query after receiving the data.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'search' => [
                                    'type' => 'string',
                                    'description' => 'Search term to filter clients by name'
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
                        'description' => 'Get ALL deals from CRM. Returns array of deals with fields: id, title, value, stage (e.g. "closed_won", "negotiation", "lead"), expected_close_date, customer_id, etc. You MUST filter the results yourself based on user query after receiving the data.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
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
