<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OllamaService
{
    protected string $model;
    protected string $baseUrl;
    protected bool $stream;
    protected int $timeout;

    public function __construct()
    {
        $this->model = config('ollama.model', 'qwen2.5:7b');
        $this->baseUrl = config('ollama.host', 'http://localhost:11434');
        $this->timeout = config('ollama.timeout', 60);
        $this->stream = config('ollama.stream', false);
    }

    /**
     * Quick health-check for Ollama service
     */
    public function healthCheck(): array
    {
        $ok = $this->isServiceReachable();
        return [
            'ok' => $ok,
            'baseUrl' => $this->baseUrl,
            'model' => $this->model,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    private function isServiceReachable(): bool
    {
        try {
            $resp = Http::withOptions(['connect_timeout' => 2])
                ->timeout(3)
                ->get($this->baseUrl);
            return $resp->successful();
        } catch (\Exception $e) {
            Log::warning('OllamaService::isServiceReachable failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a non-streaming response
     */
    public function generate(string $prompt, bool $stream = false): ?string
    {
        if (!$this->isServiceReachable()) {
            Log::error('OllamaService::generate - service unreachable', ['baseUrl' => $this->baseUrl]);
            return null;
        }

        $response = $this->sendRequest('generate', [
            'prompt' => $prompt,
            'stream' => false,
        ]);

        return $this->normalizeResponse($response);
    }

    /**
     * Stream a response (returns a generator function)
     */
    public function streamGenerate(string $prompt)
    {
        if (!$this->isServiceReachable()) {
            return function () {
                yield json_encode(['error' => 'AI service unreachable']);
            };
        }

        $data = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => true,
            'options' => $this->getModelOptions(),
        ];

        return function () use ($data) {
            try {
                if (function_exists('set_time_limit')) {
                    set_time_limit(120);
                }

                $response = Http::withOptions(['stream' => true, 'connect_timeout' => 5])
                    ->timeout(115)
                    ->post("{$this->baseUrl}/api/generate", $data);

                if ($response->failed()) {
                    Log::error("Ollama stream request failed", ['status' => $response->status()]);
                    yield json_encode(['error' => 'Failed to connect to AI service.']);
                    return;
                }

                $stream = $response->toPsrResponse()->getBody();
                while (!$stream->eof()) {
                    $line = stream_get_line($stream, 1024, "\n");
                    if (trim($line) !== '') {
                        $decoded = json_decode($line, true);
                        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['response'])) {
                            yield $decoded['response'];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Ollama stream error: ' . $e->getMessage());
                yield json_encode(['error' => 'Streaming error occurred.']);
            }
        };
    }

    /**
     * Send request with retry logic and exponential backoff
     */
    private function sendRequest(string $endpoint, array $data): mixed
    {
        $data['model'] = $this->model;
        $data['options'] = $this->getModelOptions();
        
        // CRITICAL FIX: Don't force JSON format - let model output natural language
        // Only use JSON format for structured extraction tasks
        if (!isset($data['format'])) {
            // Default to natural language output
            unset($data['format']);
        }

        try {
            if (function_exists('set_time_limit')) {
                set_time_limit(65);
            }

            // Retry logic with exponential backoff
            $attempts = 0;
            $maxAttempts = 3;
            $response = null;
            $lastException = null;

            while ($attempts < $maxAttempts) {
                try {
                    $attempts++;
                    $response = Http::withOptions(['connect_timeout' => 5])
                        ->timeout($this->timeout)
                        ->post("{$this->baseUrl}/api/{$endpoint}", $data);
                    
                    // Success - break retry loop
                    if ($response->successful()) {
                        break;
                    }
                    
                    // Log non-successful response but don't throw
                    Log::warning("Ollama request attempt {$attempts} returned non-200", [
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 500)
                    ]);
                    
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    $lastException = $e;
                    Log::warning("Ollama connection attempt {$attempts} failed: " . $e->getMessage());
                } catch (\Exception $e) {
                    $lastException = $e;
                    Log::warning("Ollama request attempt {$attempts} failed: " . $e->getMessage());
                }

                // Exponential backoff: 200ms, 500ms, 1000ms
                if ($attempts < $maxAttempts) {
                    $backoffs = [200, 500, 1000];
                    $delay = $backoffs[min($attempts - 1, count($backoffs) - 1)];
                    usleep($delay * 1000);
                }
            }

            // All retries exhausted
            if ($response === null || $response->failed()) {
                if ($lastException) {
                    Log::error('Ollama: all retry attempts failed', ['error' => $lastException->getMessage()]);
                } else {
                    Log::error('Ollama: request failed after retries', [
                        'status' => $response?->status(),
                        'body' => $response?->body()
                    ]);
                }
                return null;
            }

            return $response->body();

        } catch (\Exception $e) {
            Log::error('Ollama unexpected error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalize various response formats into a clean string
     */
    private function normalizeResponse($response): ?string
    {
        if ($response === null) {
            return null;
        }

        // Handle string response (most common from generate endpoint)
        if (is_string($response)) {
            return $this->extractTextFromNDJSON($response);
        }

        // Handle array response
        if (is_array($response)) {
            return $this->extractTextFromArray($response);
        }

        // Handle PSR stream
        if ($response instanceof \Psr\Http\Message\StreamInterface) {
            $body = (string)$response;
            return $this->extractTextFromNDJSON($body);
        }

        // Fallback: stringify
        return is_object($response) && method_exists($response, '__toString')
            ? (string)$response
            : json_encode($response);
    }

    /**
     * Extract text from newline-delimited JSON (NDJSON) format
     */
    private function extractTextFromNDJSON(string $body): string
    {
        $lines = array_filter(array_map('trim', explode("\n", $body)));
        $fullText = '';

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Extract response text from each chunk
                $fullText .= $decoded['response'] ?? '';
            }
        }

        return trim($fullText) ?: $body; // Fallback to raw body if extraction fails
    }

    /**
     * Extract text from array response format
     */
    private function extractTextFromArray(array $response): string
    {
        $text = '';
        foreach ($response as $item) {
            if (is_array($item) && isset($item['response'])) {
                $text .= $item['response'];
            } elseif (is_string($item)) {
                $text .= $item;
            }
        }
        return trim($text) ?: json_encode($response);
    }

    /**
     * Get optimized model options
     */
    private function getModelOptions(): array
    {
        return [
            'temperature' => 0.2,  // Lower for more consistent, factual responses
            'top_p' => 0.9,
            'num_predict' => 2048,  // Increased for complete, comprehensive responses
            'stop' => ["\n\n\n"], // Stop on triple newlines to prevent rambling
        ];
    }

    /**
     * Format retrieval results into natural language
     * This replaces the old formatResponse, formatAttendanceResponse, etc.
     */
    public function formatRetrievalResults(string $intent, $results, string $userQuery, array $userContext): string
    {
        // Build a concise summary of the retrieval results
        $summary = $this->summarizeResults($intent, $results);
        
        $role = $userContext['role'] ?? 'user';
        $userName = $userContext['user']['name'] ?? 'there';

        $prompt = <<<PROMPT
You are "Attendify Bot", a friendly and helpful AI assistant for the Attendify attendance management system.

You help with:
- Attendance queries (rates, records, patterns, risks)
- Schedule information (today's classes, upcoming classes, class times)
- Excuse management (status, submission process)
- Teacher information (who teaches what, contact details)
- General help (how to use features, system navigation)

User: {$userName} (Role: {$role})
Question: "{$userQuery}"

Retrieved Data Summary:
{$summary}

Task: Answer the user's question using ONLY the retrieved data above. 
- Be conversational, friendly, and concise
- Use bullet points for lists
- Include specific numbers/dates when available
- If data is empty or insufficient, say so politely and suggest what the user can do
- DO NOT make up information
- Aim for 200-400 words for comprehensive responses, but remain concise
- Provide actionable guidance when relevant

Answer:
PROMPT;

        $response = $this->generate($prompt, false);
        
        // Fallback if generation fails
        if (empty($response)) {
            return $this->buildFallbackResponse($intent, $results);
        }

        return $response;
    }

    /**
     * Summarize results for the formatting prompt
     */
    private function summarizeResults(string $intent, $results): string
    {
        if (empty($results)) {
            return "No data found.";
        }

        if (isset($results['error'])) {
            return "Error: " . $results['error'];
        }

        // Convert to JSON for model consumption
        $json = json_encode($results, JSON_PRETTY_PRINT);
        
        // Truncate if too long (keep within token limits)
        if (strlen($json) > 2000) {
            $json = substr($json, 0, 2000) . "\n... (truncated)";
        }

        return $json;
    }

    /**
     * Simple fallback response when AI formatting fails
     */
    private function buildFallbackResponse(string $intent, $results): string
    {
        if (empty($results) || isset($results['error'])) {
            return "I couldn't find any data for your request. Please try rephrasing your question.";
        }

        switch ($intent) {
            case 'attendance':
                $count = is_countable($results) ? count($results) : 0;
                return "I found {$count} attendance record(s). The data is available but I'm having trouble formatting it nicely right now.";
            
            case 'schedule':
            case 'classes':
                $count = isset($results) && is_countable($results) ? count($results) : 0;
                return "I found {$count} class(es) for you.";
            
            default:
                return "I found some data for your request, but I'm having trouble formatting it. Please try again.";
        }
    }
}
