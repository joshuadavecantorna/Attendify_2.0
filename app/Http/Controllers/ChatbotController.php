<?php

namespace App\Http\Controllers;

use App\Services\UserContextBuilder;
use App\Services\RetrievalPlanner;
use App\Services\AIComposer;
use App\Services\OllamaService;
use App\Services\GroqService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotController extends Controller
{
    protected UserContextBuilder $contextBuilder;
    protected RetrievalPlanner $retrievalPlanner;
    protected AIComposer $composer;
    protected OllamaService $ollamaService;
    protected GroqService $groqService;

    public function __construct(
        UserContextBuilder $contextBuilder,
        RetrievalPlanner $retrievalPlanner,
        AIComposer $composer,
        OllamaService $ollamaService,
        GroqService $groqService
    ) {
        $this->contextBuilder = $contextBuilder;
        $this->retrievalPlanner = $retrievalPlanner;
        $this->composer = $composer;
        $this->ollamaService = $ollamaService;
        $this->groqService = $groqService;
    }

    /**
     * AI service health check
     */
    public function status()
    {
        try {
            // Try Groq first, fallback to Ollama
            $groqHealth = $this->groqService->healthCheck();
            if ($groqHealth['ok']) {
                return response()->json(['ai' => $groqHealth], 200);
            }

            $ollamaHealth = $this->ollamaService->healthCheck();
            return response()->json(['ai' => $ollamaHealth], $ollamaHealth['ok'] ? 200 : 503);
        } catch (\Exception $e) {
            Log::error('ChatbotController::status error', ['error' => $e->getMessage()]);
            return response()->json([
                'ai' => ['ok' => false, 'error' => 'Health check failed']
            ], 503);
        }
    }

    /**
     * Chatbot view (Inertia)
     */
    public function index(): Response
    {
        return Inertia::render('Chatbot/Index');
    }

    /**
     * Handle synchronous chat query
     */
    public function queryRequest(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $userQuery = trim($request->input('message'));

        try {
            // 1. Build cached user context (15-minute cache)
            $cachedContext = $this->contextBuilder->build($user->id);

            // 2. Validate role-based access
            $this->validateUserAccess($cachedContext);

            // 3. Plan and execute dynamic data retrieval
            $retrieval = $this->retrievalPlanner->planAndExecute($userQuery, $cachedContext);

            // 4. Check if we have actionable results
            if (isset($retrieval['results']['error'])) {
                // Retrieval failed - return a simple error message
                return response()->json([
                    'reply' => "I'm sorry, I couldn't retrieve the information you requested. Please try rephrasing your question or try again later.",
                    'retrieval' => null,
                    'fallback' => true,
                ]);
            }

            // 5. Compose full prompt with retrieval results
            $prompt = $this->composer->compose($cachedContext, $retrieval, $userQuery);

            // 6. Generate AI response (Try Groq first, fallback to Ollama)
            $output = $this->groqService->generate($prompt, false);
            
            if ($output === null) {
                // Fallback to Ollama if Groq fails
                $output = $this->ollamaService->generate($prompt, false);
            }

            // 7. Handle null response from AI
            if ($output === null || trim($output) === '') {
                return response()->json([
                    'reply' => "I'm having trouble generating a response right now. Please try again.",
                    'retrieval' => $retrieval['plan'] ?? null,
                ]);
            }

            return response()->json([
                'reply' => $output,
                'retrieval' => $retrieval['plan'] ?? null,
                'intent' => $retrieval['intent'] ?? 'general',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('ChatbotController::queryRequest error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id ?? null,
            ]);
            
            return response()->json([
                'error' => 'An unexpected error occurred. Please try again.',
                'debug' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Handle streaming chat query
     */
    public function streamChat(Request $request)
    {
        set_time_limit(120);
        
        $request->validate(['query' => 'required|string|max:2000']);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $userQuery = trim($request->input('query'));

        try {
            $cachedContext = $this->contextBuilder->build($user->id);
            $this->validateUserAccess($cachedContext);

            $retrieval = $this->retrievalPlanner->planAndExecute($userQuery, $cachedContext);
            $prompt = $this->composer->compose($cachedContext, $retrieval, $userQuery);

            // Try Groq streaming first, fallback to Ollama
            $streamSource = $this->groqService->streamGenerate($prompt);
            if ($streamSource === null) {
                $streamSource = $this->ollamaService->streamGenerate($prompt);
            }
            
            $generator = is_callable($streamSource) ? $streamSource() : $streamSource;

            $response = new StreamedResponse(function () use ($generator) {
                try {
                    foreach ($generator as $chunk) {
                        $out = is_string($chunk) ? $chunk : json_encode($chunk);
                        echo $out;
                        if (ob_get_level() > 0) {
                            @ob_flush();
                        }
                        @flush();
                    }
                } catch (\Throwable $e) {
                    Log::error('ChatbotController::streamChat iteration error', [
                        'error' => $e->getMessage()
                    ]);
                    echo json_encode(['error' => 'Streaming interrupted.']);
                }
            });

            $response->headers->set('Content-Type', 'text/event-stream');
            $response->headers->set('Cache-Control', 'no-cache');
            $response->headers->set('X-Accel-Buffering', 'no');
            
            return $response;

        } catch (\Exception $e) {
            Log::error('ChatbotController::streamChat error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return new StreamedResponse(function () use ($e) {
                echo json_encode([
                    'error' => 'Streaming failed.',
                    'debug' => app()->environment('local') ? $e->getMessage() : null,
                ]);
            });
        }
    }

    /**
     * Validate that user has proper access based on role
     */
    private function validateUserAccess(array $context): void
    {
        $role = $context['role'] ?? null;

        if ($role === 'student' && empty($context['student_id'])) {
            throw new \RuntimeException('Student context missing or access denied.');
        }

        if ($role === 'teacher' && empty($context['teacher_id'])) {
            throw new \RuntimeException('Teacher context missing or access denied.');
        }
    }
}
