<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key', '');
        $this->model = config('services.groq.model', 'llama-3.3-70b-versatile');
        $this->baseUrl = 'https://api.groq.com/openai/v1';
        $this->timeout = config('services.groq.timeout', 60);
    }

    /**
     * Health check for Groq API
     */
    public function healthCheck(): array
    {
        $ok = !empty($this->apiKey);
        return [
            'ok' => $ok,
            'service' => 'Groq API',
            'model' => $this->model,
            'has_api_key' => $ok,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate a response using Groq API
     */
    public function generate(string $prompt, bool $stream = false): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('GroqService: API key not configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1024,
                'stream' => false,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }

            Log::error('GroqService: API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('GroqService: Exception occurred', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Stream generate (for future implementation)
     */
    public function streamGenerate(string $prompt)
    {
        if (empty($this->apiKey)) {
            return function () {
                yield json_encode(['error' => 'API key not configured']);
            };
        }

        // For now, return non-streaming response
        $response = $this->generate($prompt);
        
        return function () use ($response) {
            if ($response) {
                // Split response into words for streaming effect
                $words = explode(' ', $response);
                foreach ($words as $word) {
                    yield $word . ' ';
                }
            } else {
                yield json_encode(['error' => 'Failed to generate response']);
            }
        };
    }

    /**
     * Chat completion with message history
     */
    public function chat(array $messages): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('GroqService: API key not configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['choices'][0]['message']['content'] ?? null;
            }

            Log::error('GroqService: Chat request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('GroqService: Chat exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
