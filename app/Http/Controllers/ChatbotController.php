<?php

namespace App\Http\Controllers;

use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChatbotController extends Controller
{
    protected OllamaService $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    /**
     * Handle chatbot queries about attendance
     */
    public function query(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'conversation_history' => 'nullable|array'
        ]);

        $query = $request->input('message');
        $history = $request->input('conversation_history', []);
        
        // Get current logged-in user info
        $user = $request->user();
        $userName = null;
        $userRole = null;
        
        if ($user) {
            $userName = $user->name;
            $userRole = $user->role;
            
            // If asking about "my" or "I", replace with actual user name
            $personalPronouns = ['my', 'me', 'i ', 'i\'m', 'i\'ve', 'i have'];
            $queryLower = strtolower($query);
            
            foreach ($personalPronouns as $pronoun) {
                if (strpos($queryLower, $pronoun) !== false) {
                    $query = preg_replace('/\b(my|me|i|i\'m|i\'ve|i have)\b/i', $userName, $query);
                    break;
                }
            }
        }

        // Check if Ollama is available
        if (!$this->ollama->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => 'AI service is currently unavailable. Please try again later.'
            ], 503);
        }

        // Extract structured query from natural language
        $extracted = $this->ollama->extractAttendanceQuery($query);

        if (!$extracted) {
            return response()->json([
                'success' => false,
                'error' => 'I could not understand your question. Please try asking differently.'
            ], 400);
        }

        // Execute query based on category
        $category = $extracted['query_category'] ?? 'general';
        
        if ($category === 'classes') {
            $result = $this->executeClassQuery($extracted, $user);
        } elseif ($category === 'attendance') {
            $result = $this->executeAttendanceQuery($extracted);
        } else {
            // General question - let AI query the database
            $result = $this->executeGeneralQuery($query, $user);
        }
        
        // Add user context to result
        $result['current_user'] = $userName;
        $result['user_role'] = $userRole;

        // Format the response naturally
        $response = $this->ollama->formatResponse($result, $query);

        if (!$response) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate response. Please try again.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'response' => $response,
            'data' => $result,
            'debug' => [
                'extracted_query' => $extracted
            ]
        ]);
    }

    /**
     * Execute database query based on extracted intent
     */
    protected function executeAttendanceQuery(array $extracted): array
    {
        $studentName = $extracted['student_name'] ?? null;
        $queryType = $extracted['query_type'] ?? 'count_absences';
        $status = $extracted['status'] ?? null;
        $timePeriod = $extracted['time_period'] ?? 'this_month';

        // Get date range based on time period
        [$startDate, $endDate] = $this->getDateRange($timePeriod);

        // Build base query
        $query = DB::table('attendance_records')
            ->join('students', 'attendance_records.student_id', '=', 'students.id');

        // Filter by student name if provided
        if ($studentName) {
            $query->where('students.name', 'ILIKE', "%{$studentName}%");
        }

        // Filter by status if provided
        if ($status) {
            $query->where('attendance_records.status', $status);
        }

        // Filter by date range
        if ($startDate) {
            $query->whereDate('attendance_records.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('attendance_records.created_at', '<=', $endDate);
        }

        // Execute appropriate query type
        switch ($queryType) {
            case 'count_absences':
            case 'count_present':
            case 'count_late':
                $count = $query->count();
                return [
                    'type' => 'count',
                    'count' => $count,
                    'status' => $status ?? 'all',
                    'student' => $studentName,
                    'period' => $timePeriod,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ];

            case 'list_dates':
                $dates = $query->select('attendance_records.created_at', 'attendance_records.status')
                    ->orderBy('attendance_records.created_at', 'desc')
                    ->limit(20)
                    ->get();
                
                return [
                    'type' => 'list',
                    'records' => $dates,
                    'count' => $dates->count(),
                    'student' => $studentName,
                    'period' => $timePeriod
                ];

            case 'attendance_rate':
                $total = DB::table('attendance_records')
                    ->join('students', 'attendance_records.student_id', '=', 'students.id')
                    ->when($studentName, function($q) use ($studentName) {
                        return $q->where('students.name', 'ILIKE', "%{$studentName}%");
                    })
                    ->whereDate('attendance_records.created_at', '>=', $startDate)
                    ->whereDate('attendance_records.created_at', '<=', $endDate)
                    ->count();

                $present = DB::table('attendance_records')
                    ->join('students', 'attendance_records.student_id', '=', 'students.id')
                    ->when($studentName, function($q) use ($studentName) {
                        return $q->where('students.name', 'ILIKE', "%{$studentName}%");
                    })
                    ->where('attendance_records.status', 'present')
                    ->whereDate('attendance_records.created_at', '>=', $startDate)
                    ->whereDate('attendance_records.created_at', '<=', $endDate)
                    ->count();

                $rate = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                return [
                    'type' => 'rate',
                    'total_records' => $total,
                    'present_count' => $present,
                    'attendance_rate' => $rate,
                    'student' => $studentName,
                    'period' => $timePeriod
                ];

            default:
                return [
                    'type' => 'unknown',
                    'error' => 'Query type not supported'
                ];
        }
    }

    /**
     * Get date range based on time period
     */
    protected function getDateRange(string $period): array
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'today':
                return [$now->toDateString(), $now->toDateString()];
            
            case 'this_week':
                return [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()];
            
            case 'this_month':
                return [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()];
            
            case 'last_month':
                $lastMonth = $now->copy()->subMonth();
                return [$lastMonth->startOfMonth()->toDateString(), $lastMonth->endOfMonth()->toDateString()];
            
            case 'this_year':
                return [$now->startOfYear()->toDateString(), $now->endOfYear()->toDateString()];
            
            default:
                // Default to this month
                return [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()];
        }
    }

    /**
     * Execute class-related queries
     */
    protected function executeClassQuery(array $extracted, $user): array
    {
        $queryType = $extracted['query_type'] ?? 'list_classes';

        switch ($queryType) {
            case 'list_classes':
                if (!$user) {
                    return [
                        'type' => 'error',
                        'message' => 'You need to be logged in to view your classes.'
                    ];
                }

                // Get enrolled classes based on user role
                if ($user->role === 'student') {
                    $classes = DB::table('student_class')
                        ->join('classes', 'student_class.class_id', '=', 'classes.id')
                        ->join('teachers', 'classes.teacher_id', '=', 'teachers.id')
                        ->where('student_class.student_id', $user->id)
                        ->select(
                            'classes.name as class_name',
                            'classes.subject',
                            'classes.schedule',
                            'teachers.name as teacher_name'
                        )
                        ->get();

                    return [
                        'type' => 'classes_list',
                        'classes' => $classes->toArray(),
                        'count' => $classes->count()
                    ];
                } elseif ($user->role === 'teacher') {
                    $classes = DB::table('classes')
                        ->where('teacher_id', $user->id)
                        ->select('name', 'subject', 'schedule', 'section')
                        ->get();

                    return [
                        'type' => 'classes_list',
                        'classes' => $classes->toArray(),
                        'count' => $classes->count()
                    ];
                }

                return [
                    'type' => 'classes_list',
                    'classes' => [],
                    'count' => 0
                ];

            default:
                return [
                    'type' => 'unknown',
                    'error' => 'Query type not supported'
                ];
        }
    }

    /**
     * Execute general database queries using AI
     */
    protected function executeGeneralQuery(string $query, $user): array
    {
        // Get database schema information
        $schema = $this->getDatabaseSchema();
        
        // Let AI generate SQL query based on the question and schema
        $sqlQuery = $this->ollama->generateSQLQuery($query, $schema, $user);
        
        if (!$sqlQuery) {
            return [
                'type' => 'general',
                'answer' => "I couldn't understand your question. Try asking about students, teachers, classes, or attendance."
            ];
        }

        try {
            // Execute the AI-generated query safely (read-only)
            $results = DB::select($sqlQuery);
            
            return [
                'type' => 'database_query',
                'query' => $query,
                'sql' => $sqlQuery,
                'results' => $results,
                'count' => count($results)
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'error',
                'message' => 'I had trouble finding that information. Try rephrasing your question.',
                'error_detail' => $e->getMessage()
            ];
        }
    }

    /**
     * Get database schema for AI context
     */
    protected function getDatabaseSchema(): array
    {
        return [
            'students' => [
                'description' => 'Student information',
                'columns' => ['id', 'student_id', 'name', 'email', 'year', 'course', 'section', 'is_active']
            ],
            'teachers' => [
                'description' => 'Teacher information',
                'columns' => ['id', 'teacher_id', 'name', 'email', 'department', 'is_active']
            ],
            'classes' => [
                'description' => 'Class/subject information',
                'columns' => ['id', 'teacher_id', 'name', 'subject', 'section', 'schedule', 'room']
            ],
            'attendance_records' => [
                'description' => 'Student attendance records',
                'columns' => ['id', 'student_id', 'class_id', 'status', 'date', 'remarks', 'created_at']
            ],
            'student_class' => [
                'description' => 'Student enrollment in classes',
                'columns' => ['id', 'student_id', 'class_id', 'enrolled_at']
            ]
        ];
    }

    /**
     * Check if AI service is available
     */
    public function status()
    {
        $available = $this->ollama->isAvailable();
        $models = $available ? $this->ollama->listModels() : null;

        return response()->json([
            'available' => $available,
            'host' => config('ollama.host'),
            'model' => config('ollama.model'),
            'models' => $models
        ]);
    }
}
