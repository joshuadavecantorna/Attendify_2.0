<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $host;
    protected string $model;
    protected int $timeout;
    protected bool $stream;

    public function __construct()
    {
        $this->host = config('ollama.host');
        $this->model = config('ollama.model');
        $this->timeout = config('ollama.timeout');
        $this->stream = config('ollama.stream');
    }

    /**
     * Generate a response from a single prompt
     *
     * @param string $prompt The prompt to send to the AI
     * @param string|null $model Optional: Override default model
     * @return array|null
     */
    public function generate(string $prompt, ?string $model = null): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->host}/api/generate", [
                    'model' => $model ?? $this->model,
                    'prompt' => $prompt,
                    'stream' => $this->stream,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Ollama generate request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Ollama generate exception', [
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Have a chat conversation with context
     *
     * @param array $messages Array of messages with 'role' and 'content'
     * @param string|null $model Optional: Override default model
     * @return array|null
     */
    public function chat(array $messages, ?string $model = null): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->host}/api/chat", [
                    'model' => $model ?? $this->model,
                    'messages' => $messages,
                    'stream' => $this->stream,
                    'options' => [
                        'temperature' => 0.3,
                        'num_predict' => 256,
                    ]
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Ollama chat request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Ollama chat exception', [
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Extract structured data from natural language query
     * Handles both attendance and general queries
     *
     * @param string $query The natural language query
     * @return array|null
     */
    public function extractAttendanceQuery(string $query): ?array
    {
        $systemPrompt = "Extract query data. Return ONLY JSON.

Query Types:
- Attendance: count_absences, count_present, count_late, list_dates, attendance_rate
- Classes: list_classes, class_info, count_students_in_class
- General: general_question

Format:
{\"query_category\":\"attendance|classes|general\",\"student_name\":\"NAME or null\",\"class_name\":\"CLASS NAME or null\",\"query_type\":\"TYPE\",\"status\":\"STATUS or null\",\"time_period\":\"PERIOD\",\"start_date\":null,\"end_date\":null}

Status: absent, present, late, excused
Period: today, this_week, this_month, last_month, this_year

Examples:
'John absent October' → {\"query_category\":\"attendance\",\"student_name\":\"John\",\"class_name\":null,\"query_type\":\"count_absences\",\"status\":\"absent\",\"time_period\":\"last_month\",\"start_date\":null,\"end_date\":null}

'list my enrolled classes' → {\"query_category\":\"classes\",\"student_name\":null,\"class_name\":null,\"query_type\":\"list_classes\",\"status\":null,\"time_period\":\"this_month\",\"start_date\":null,\"end_date\":null}

'how many students in Life and works of Rizal' → {\"query_category\":\"classes\",\"student_name\":null,\"class_name\":\"Life and works of Rizal\",\"query_type\":\"count_students_in_class\",\"status\":null,\"time_period\":\"this_month\",\"start_date\":null,\"end_date\":null}

'how many students I have in Application Development' → {\"query_category\":\"classes\",\"student_name\":null,\"class_name\":\"Application Development\",\"query_type\":\"count_students_in_class\",\"status\":null,\"time_period\":\"this_month\",\"start_date\":null,\"end_date\":null}

'how many students present today' → {\"query_category\":\"attendance\",\"student_name\":null,\"class_name\":null,\"query_type\":\"count_present\",\"status\":\"present\",\"time_period\":\"today\",\"start_date\":null,\"end_date\":null}

Extract:";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $query]
        ];

        $response = $this->chat($messages);

        if ($response && isset($response['message']['content'])) {
            $content = $response['message']['content'];
            
            // Extract JSON from response (in case model adds extra text)
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonString = $matches[0];
                $extracted = json_decode($jsonString, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $extracted;
                }
            }
        }

        return null;
    }

    /**
     * Format database results into a natural language response
     *
     * @param array $data The data from database query
     * @param string $originalQuery The original user query for context
     * @return string|null
     */
    public function formatResponse(array $data, string $originalQuery): ?string
    {
        $currentUser = $data['current_user'] ?? null;
        $userRole = $data['user_role'] ?? null;
        
        // Special handling for student count in class
        if (isset($data['type']) && $data['type'] === 'student_count') {
            $className = $data['class_name'] ?? 'the class';
            $count = $data['student_count'] ?? 0;
            
            if ($count === 0) {
                return "There are no students enrolled in {$className} yet.";
            }
            
            return "You have {$count} " . ($count === 1 ? 'student' : 'students') . " enrolled in {$className}.";
        }
        
        // Special handling for class lists
        if (isset($data['type']) && $data['type'] === 'classes_list') {
            $count = $data['count'] ?? 0;
            $classes = $data['classes'] ?? [];
            
            if ($count === 0) {
                return "You don't have any enrolled classes yet.";
            }
            
            $response = "You're enrolled in {$count} " . ($count === 1 ? 'class' : 'classes') . ":\n\n";
            foreach ($classes as $class) {
                $className = $class->class_name ?? $class->name ?? 'Unknown';
                $subject = $class->subject ?? '';
                $schedule = $class->schedule ?? '';
                $teacher = $class->teacher_name ?? '';
                
                $response .= "• {$className}";
                if ($subject) $response .= " ({$subject})";
                if ($schedule) $response .= " - {$schedule}";
                if ($teacher) $response .= "\n  Teacher: {$teacher}";
                $response .= "\n";
            }
            
            return trim($response);
        }
        
        // Handle general database query results
        if (isset($data['type']) && $data['type'] === 'database_query') {
            $results = $data['results'] ?? [];
            $count = $data['count'] ?? 0;
            
            if ($count === 0) {
                return "I couldn't find any matching data for your question.";
            }
            
            // Let AI format the database results naturally
            $systemPrompt = "Format database query results into a natural, friendly answer. Be concise (2-3 sentences max).";
            $userPrompt = "Question: \"{$data['query']}\"\n\nResults from database: " . json_encode($results) . "\n\nProvide a natural answer:";
            
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ];
            
            $response = $this->chat($messages);
            if ($response && isset($response['message']['content'])) {
                return trim($response['message']['content']);
            }
            
            return "I found {$count} result(s) but couldn't format the response properly.";
        }

        // Special: Count of excuse requests approved by the user
        if (isset($data['type']) && $data['type'] === 'excuse_count') {
            $count = $data['count'] ?? 0;
            if ($count === 0) {
                return "You haven't approved any excuse requests yet.";
            }
            return "You have approved {$count} excuse request" . ($count === 1 ? '' : 's') . ".";
        }
        
        $contextNote = $currentUser 
            ? "The person asking is {$currentUser} ({$userRole}). Use 'you/your' when referring to them, not their name." 
            : "";
        
        $systemPrompt = "Answer in 1-2 friendly sentences. Use the numbers from data. Be encouraging if zero. {$contextNote}";

        $dataJson = json_encode($data);
        $userPrompt = "Original question: \"{$originalQuery}\"\n\nDatabase result: {$dataJson}\n\nProvide a natural response:";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        $response = $this->chat($messages);

        if ($response && isset($response['message']['content'])) {
            return trim($response['message']['content']);
        }

        return null;
    }

    /**
     * Generate SQL query from natural language
     */
    public function generateSQLQuery(string $question, array $schema, $user): ?string
    {
        $userName = $user ? $user->name : null;
        $userRole = $user ? $user->role : null;
        $userId = $user ? $user->id : null;

        $schemaText = "Database Schema:\n";
        foreach ($schema as $table => $info) {
            $schemaText .= "- {$table}: {$info['description']}\n";
            $schemaText .= "  Columns: " . implode(', ', $info['columns']) . "\n";
        }

        $contextText = $userName ? "Current user: {$userName} (ID: {$userId}, Role: {$userRole})" : "No user logged in";

        $systemPrompt = "You are a PostgreSQL expert. Generate a READ-ONLY SELECT query.

RULES:
1. Only SELECT queries (no INSERT, UPDATE, DELETE, DROP)
2. Use proper JOINs when needed
3. Filter by current user when they ask about 'my' or 'I'
4. Return ONLY the SQL query, no explanations
5. Use ILIKE for case-insensitive text search
6. PostgreSQL syntax only

{$schemaText}

{$contextText}

Examples:
Q: 'how many students are enrolled'
SQL: SELECT COUNT(*) as total FROM students WHERE is_active = true

Q: 'list all teachers in computer science'
SQL: SELECT name, email FROM teachers WHERE department ILIKE '%computer science%'

Q: 'show my classes' (user_id: 5, role: student)
SQL: SELECT c.name, c.subject, t.name as teacher FROM classes c JOIN student_class sc ON c.id = sc.class_id JOIN teachers t ON c.teacher_id = t.id WHERE sc.student_id = 5

Generate SQL:";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $question]
        ];

        $response = $this->chat($messages);

        if ($response && isset($response['message']['content'])) {
            $sql = trim($response['message']['content']);
            
            // Extract SQL if wrapped in code blocks
            if (preg_match('/```(?:sql)?\s*(SELECT.*?)```/is', $sql, $matches)) {
                $sql = trim($matches[1]);
            } elseif (preg_match('/(SELECT.*?);?\s*$/is', $sql, $matches)) {
                $sql = trim($matches[1]);
            }
            
            // Security: ensure it's a SELECT query only
            if (!preg_match('/^\s*SELECT\s/i', $sql)) {
                return null;
            }
            
            // Remove any dangerous keywords
            $dangerous = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'GRANT', 'REVOKE'];
            foreach ($dangerous as $keyword) {
                if (stripos($sql, $keyword) !== false) {
                    return null;
                }
            }
            
            return $sql;
        }

        return null;
    }

    /**
     * Check if Ollama server is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->host);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get list of available models
     *
     * @return array|null
     */
    public function listModels(): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$this->host}/api/tags");
            
            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to list Ollama models', [
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }
}
