<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExcuseRequest;
use Illuminate\Support\Facades\Auth;
use App\Services\OllamaService;
use App\Services\AIQueryService;

class AIAnalysisController extends Controller
{
    public function analyzeUsers(Request $request, OllamaService $ollamaService, AIQueryService $aiQueryService)
    {
        // Get current user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get the question from the request (GET or POST)
        $question = $request->input('question') ?? $request->query('question');
        if (!$question) {
            return response()->json(['error' => 'No question provided.'], 400);
        }

        // Simple pattern match for known question
        if (preg_match('/how many (excuse|excused) request(s)? (did )?(i|I) approve(d)?/i', $question)) {
            // Try both user_id and teacher_id for reviewed_by
            $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
            $ids = [$user->id];
            if ($teacher) {
                $ids[] = $teacher->id;
            }
            $count = ExcuseRequest::where('status', 'approved')
                ->whereIn('reviewed_by', $ids)
                ->count();
            return response()->json([
                'message' => "You have approved {$count} excuse request(s).",
                'count' => $count,
            ]);
        }

        // Use AIQueryService to get DB-level result
        $result = $aiQueryService->handleQuery($question, $user);
        $result['current_user'] = $user->name;
        $result['user_role'] = $user->role;

        // Format the response naturally with Ollama
        $response = $ollamaService->formatResponse($result, $question);
        if (!$response) {
            return response()->json(['error' => 'Failed to get AI response'], 500);
        }

        return response()->json([
            'ai_response' => $response,
            'data' => $result,
        ]);
    }
}
