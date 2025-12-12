<?php

namespace App\Services;

class AIComposer
{
    /**
     * Compose the final prompt sent to the AI model
     */
    public function compose(array $cachedContext, array $retrieval, string $question, int $truncateChars = 4000): string
    {
        $role = $cachedContext['role'] ?? 'user';
        $userName = $cachedContext['user']['name'] ?? 'User';
        
        // Sanitize and limit context size
        $maxItems = 10;
        $sanitizedCached = $this->sanitizeContext($cachedContext, $maxItems);
        $plan = $retrieval['plan'] ?? [];
        $results = $this->prepareResults($retrieval['results'] ?? null, $maxItems);

        // ✅ FIX: Pre-convert to JSON strings BEFORE heredoc
        $cachedJson = $this->toJson($this->extractUiContext($sanitizedCached));
        $resultsJson = $this->toJson($results);
        $intent = $retrieval['intent'] ?? 'unknown';

        // Check if this is a general question or specific data query
        $isGeneralQuestion = ($intent === 'unknown' || (isset($results['type']) && $results['type'] === 'general'));
        
        if ($isGeneralQuestion) {
            // For general questions, use a more flexible prompt
            $prompt = <<<PROMPT
# SYSTEM ROLE
You are "Attendify Bot", a friendly and helpful AI assistant for Attendify - an attendance management system for schools.

# USER CONTEXT
- Name: {$userName}
- Role: {$role}

# USER QUESTION
"{$question}"

# INSTRUCTIONS
1. **Answer helpfully** - Provide useful information to the best of your ability
2. **Be conversational** - Be friendly, natural, and engaging
3. **Be relevant** - If the question relates to Attendify (attendance, schedules, classes), explain what you can help with
4. **Be honest** - If you don't have specific user data, say so and offer general guidance
5. **Keep it concise** - Aim for 2-4 sentences unless more detail is requested

# OUTPUT FORMAT
- **Plain English only** - NO code blocks, NO JSON, NO raw arrays
- **Use natural formatting** - Bullets (•) for lists if needed
- **Be conversational** - Write like you're helping a friend

# YOUR ANSWER
PROMPT;
        } else {
            // For specific data queries, use structured prompt
            $prompt = <<<PROMPT
# SYSTEM ROLE
You are "Attendify Bot", a friendly and helpful AI assistant for Attendify.

# USER CONTEXT
- Name: {$userName}
- Role: {$role}
- Cached Profile Data: {$cachedJson}

# DYNAMIC DATA RETRIEVAL
Intent: {$intent}
Retrieved Results: {$resultsJson}

# USER QUESTION
"{$question}"

# INSTRUCTIONS
1. **Use only the data provided above** - Do NOT invent or hallucinate information
2. **Answer conversationally** - Be friendly, helpful, and natural
3. **Be specific** - Use actual numbers, dates, and names from the data
4. **Handle missing data gracefully** - If insufficient, say "I don't have that information" and suggest alternatives
5. **Keep it concise** - Aim for 2-4 sentences or bullet points when listing items
6. **Match the user's role** - Tailor your answer to what a {$role} needs to know

# OUTPUT FORMAT
- **Plain English only** - NO code blocks, NO JSON, NO raw arrays
- **Use natural formatting** - Bullets (•) for lists, bold for emphasis
- **Be conversational** - Write like you're texting a friend

# EXAMPLES OF GOOD RESPONSES
User: "How many absences do I have?"
Bot: "You have 3 absences this month in your Math 101 class. Try to maintain good attendance to stay on track!"

User: "What's my schedule for today?"
Bot: "Today you have:
• Math 101 at 9:00 AM in Room 204
• Physics Lab at 2:00 PM in Lab Building A"

User: "Show me my classes"
Bot: "You're enrolled in 5 classes this semester: Math 101, Physics 202, Chemistry 150, English Composition, and Programming Fundamentals."

# YOUR ANSWER
PROMPT;
        }

        // Truncate if too long
        if (strlen($prompt) > $truncateChars) {
            $prompt = substr($prompt, 0, $truncateChars - 100) . "\n\n[PROMPT TRUNCATED]";
        }

        return $prompt;
    }

    /**
     * Sanitize context to reduce size
     */
    protected function sanitizeContext(array $ctx, int $maxItems): array
    {
        $san = $ctx;

        // Limit arrays
        if (isset($san['student']['classes'])) {
            $san['student']['classes'] = array_slice($san['student']['classes'], 0, $maxItems);
        }
        if (isset($san['student']['recent_attendance'])) {
            $san['student']['recent_attendance'] = array_slice($san['student']['recent_attendance'], 0, $maxItems);
        }
        if (isset($san['teacher']['classes'])) {
            $san['teacher']['classes'] = array_slice($san['teacher']['classes'], 0, $maxItems);
        }

        // Remove sensitive data
        if (isset($san['user']['password'])) {
            unset($san['user']['password']);
        }

        return $san;
    }

    /**
     * Prepare results for inclusion in prompt
     */
    protected function prepareResults($resultsRaw, int $maxItems)
    {
        if (is_object($resultsRaw) && method_exists($resultsRaw, 'toArray')) {
            $arr = $resultsRaw->toArray();
        } elseif (is_array($resultsRaw)) {
            $arr = $resultsRaw;
        } else {
            return $resultsRaw;
        }

        // Limit list size
        if ($this->isList($arr)) {
            $arr = array_slice($arr, 0, $maxItems);
        }

        return $arr;
    }

    /**
     * Check if array is a list (numeric keys)
     */
    protected function isList(array $arr): bool
    {
        if (empty($arr)) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * Extract minimal UI context for the prompt
     */
    protected function extractUiContext(array $ctx): array
    {
        $ui = [];

        if (isset($ctx['user'])) {
            $ui['user'] = [
                'id' => $ctx['user']['id'] ?? null,
                'name' => $ctx['user']['name'] ?? null,
                'role' => $ctx['user']['role'] ?? null,
            ];
        }

        if (isset($ctx['student'])) {
            $s = $ctx['student'];
            $ui['student'] = [
                'name' => $s['name'] ?? null,
                'year' => $s['year'] ?? null,
                'course' => $s['course'] ?? null,
                'section' => $s['section'] ?? null,
                'classes_count' => count($s['classes'] ?? []),
                'recent_attendance_count' => count($s['recent_attendance'] ?? []),
            ];
        }

        if (isset($ctx['teacher'])) {
            $t = $ctx['teacher'];
            $ui['teacher'] = [
                'name' => trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')),
                'department' => $t['department'] ?? null,
                'classes_count' => count($t['classes'] ?? []),
            ];
        }

        return $ui;
    }

    /**
     * Convert data to clean JSON
     */
    protected function toJson($data): string
    {
        try {
            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return '[unserializable]';
        }
    }
}
