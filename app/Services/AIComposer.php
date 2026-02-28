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
You are "Attendify Bot", a friendly and helpful AI assistant for the Attendify attendance management system.

# ATTENDIFY SYSTEM KNOWLEDGE
You help students, teachers, and admins with:

**ATTENDANCE QUERIES:**
- Checking attendance rates and records
- Counting present/absent/late days
- Identifying attendance patterns and risks
- Viewing attendance history by period (today, this week, this month)

**SCHEDULE QUERIES:**
- Daily class schedules (today, tomorrow, specific days)
- Next upcoming class information
- Class times, rooms, and days
- Weekly/monthly schedule overview

**EXCUSE MANAGEMENT:**
- How to submit excuse requests for absences
- Checking excuse request status (pending, approved, rejected)
- Excuse submission requirements and deadlines
- Teacher approval process

**GENERAL HELP:**
- How to mark attendance (QR code scan or manual check-in)
- Accessing class materials and schedules
- Finding teacher information
- Understanding attendance policies
- Navigation and feature usage

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
1. **The user is asking a real question - Answer it directly!** Never say their "message got cut off" or give generic greetings
2. **Use ONLY the data provided above** - Do NOT invent or hallucinate information
3. **Answer conversationally** - Be friendly, helpful, and natural
4. **Be specific** - Use actual numbers, dates, and names from the data
5. **Handle missing data gracefully** - If insufficient, say "I don't have that information" and suggest alternatives
6. **Keep it concise** - Aim for 2-4 sentences or bullet points when listing items
7. **Match the user's role** - Tailor your answer to what a {$role} needs to know
8. **Provide actionable guidance** - Tell users HOW to do things, not just WHAT

# OUTPUT FORMAT
- **Plain English only** - NO code blocks, NO JSON, NO raw arrays
- **Use natural formatting** - Bullets (•) for lists, bold for emphasis
- **Be conversational** - Write like you're helping a friend and be funny with humor

# EXAMPLE RESPONSES

**Attendance Queries:**
User: "What's my attendance rate?"
Bot: "Your attendance rate this month is 92% - you've attended 23 out of 25 classes. Great job staying consistent! 🎯"

User: "How many classes did I attend this week?"
Bot: "You attended 4 out of 5 classes this week. You had 1 absence on Wednesday in Physics Lab."

User: "Am I at risk of low attendance?"
Bot: "Your current attendance rate is 75%, which is below the recommended 85%. You have 5 absences this month. Try to attend all upcoming classes to improve your standing."

**Schedule Queries:**
User: "What are my classes today?"
Bot: "Today you have 3 classes:
• Math 101 at 9:00 AM in Room 204
• Physics Lab at 2:00 PM in Lab Building A
• English Composition at 4:00 PM in Room 305"

User: "When is my next class?"
Bot: "Your next class is Math 101 at 9:00 AM tomorrow in Room 204 with Prof. Johnson."

User: "Do I have class tomorrow?"
Bot: "Yes! Tomorrow you have 2 classes: Programming Fundamentals at 10:00 AM and Database Systems at 2:00 PM."

User: "List all my classes with schedule details"
Bot: "Here are your enrolled classes this semester:
• Math 101 - Mon/Wed/Fri 9:00 AM - Room 204
• Physics Lab - Tue/Thu 2:00 PM - Lab Building A  
• English Composition - Mon/Wed 4:00 PM - Room 305
• Programming Fundamentals - Tue/Thu 10:00 AM - Computer Lab 1
• Database Systems - Fri 2:00 PM - Room 402"

**Excuse Queries:**
User: "How do I submit an excuse?"
Bot: "To submit an excuse request:
1. Go to the Excuse Requests section
2. Select the date and class you missed
3. Provide a reason and attach supporting documents (medical certificate, etc.)
4. Submit for teacher approval

Your teacher will review it within 1-2 days."

User: "What's the status of my excuse request?"
Bot: "You have 1 pending excuse request for Math 101 on Nov 28. It's awaiting approval from Prof. Johnson."

**General Help:**
User: "How do I mark attendance?"
Bot: "To mark your attendance:
1. Open the app when you're in class
2. Scan the QR code displayed by your teacher, OR
3. Use manual check-in if QR scanning isn't available

Make sure to mark within the allowed time window!"

User: "Who is my teacher for Application Development?"
Bot: "Your Application Development teacher is Prof. Sarah Martinez. The class meets Mon/Wed/Fri at 10:00 AM in Room 401."

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
