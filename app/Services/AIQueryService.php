<?php

namespace App\Services;

use App\Services\OllamaService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AIQueryService
{
    protected OllamaService $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    public function handleQuery(string $query, $user): array
    {
        // Replace pronouns for user-specific queries
        $modifiedQuery = $query;
        if ($user) {
            $userName = $user->name;
            $modifiedQuery = preg_replace('/\b(my|me|i|i\'m|i\'ve|i have)\b/i', $userName, $modifiedQuery);
        }

        $queryLower = strtolower($query);

        // Special-case handling for excuse/approved queries
        if (str_contains($queryLower, 'excuse') && str_contains($queryLower, 'approve')) {
            $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
            $ids = [$user->id];
            if ($teacher) $ids[] = $teacher->id;
            $count = DB::table('excuse_requests')
                ->where('status', 'approved')
                ->whereIn('reviewed_by', $ids)
                ->count();
            return [
                'type' => 'excuse_count',
                'count' => $count
            ];
        }

        // Extract
        $extracted = $this->ollama->extractAttendanceQuery($modifiedQuery);
        if (!$extracted) {
            // Fallback - do a generic SQL generation
            $sql = $this->ollama->generateSQLQuery($query, $this->getSchema(), $user);
            if ($sql) {
                // Limit results to prevent huge payloads
                $limitQuery = $sql;
                if (!preg_match('/\blimit\b/i', $sql)) {
                    $limitQuery = rtrim($sql, ';') . ' LIMIT 200';
                }
                try {
                    $results = DB::select($limitQuery);
                } catch (\Exception $e) {
                    return ['type' => 'error', 'error' => $e->getMessage()];
                }
                return [
                    'type' => 'database_query',
                    'results' => $results,
                    'count' => count($results),
                    'query' => $query,
                ];
            }
            return [
                'type' => 'unknown',
                'error' => 'Could not parse the question',
            ];
        }

        $category = $extracted['query_category'] ?? 'general';

        if ($category === 'attendance') {
            try {
                return $this->executeAttendanceQuery($extracted);
            } catch (\Exception $e) {
                return ['type' => 'error', 'error' => $e->getMessage()];
            }
        }

        if ($category === 'classes') {
            try {
                return $this->executeClassQuery($extracted, $user);
            } catch (\Exception $e) {
                return ['type' => 'error', 'error' => $e->getMessage()];
            }
        }

        // For general queries, attempt generateSQLQuery
        if ($category === 'general') {
            $sql = $this->ollama->generateSQLQuery($query, $this->getSchema(), $user);
            if (!$sql) {
                return ['type' => 'unknown', 'error' => 'Could not generate SQL'];
            }
            // Limit results to prevent large payloads
            $limitQuery = $sql;
            if (!preg_match('/\blimit\b/i', $sql)) {
                $limitQuery = rtrim($sql, ';') . ' LIMIT 200';
            }
            try {
                $results = DB::select($limitQuery);
            } catch (\Exception $e) {
                return ['type' => 'error', 'error' => $e->getMessage()];
            }
            $tooLarge = false;
            // If more than 200 results exist, we won't fetch full count — just note limitation
            if (count($results) >= 200) {
                $tooLarge = true;
            }
            return [
                'type' => 'database_query',
                'results' => $results,
                'count' => count($results),
                'too_large' => $tooLarge,
                'query' => $sql,
            ];
        }

        return ['type' => 'unknown', 'error' => 'Unhandled category'];
    }

    protected function executeAttendanceQuery(array $extracted): array
    {
        $studentName = $extracted['student_name'] ?? null;
        $queryType = $extracted['query_type'] ?? 'count_absences';
        $status = $extracted['status'] ?? null;
        $timePeriod = $extracted['time_period'] ?? 'this_month';

        [$startDate, $endDate] = $this->getDateRange($timePeriod);

        $query = DB::table('attendance_records')
            ->join('students', 'attendance_records.student_id', '=', 'students.id');

        if ($studentName) {
            $query->where('students.name', 'ILIKE', "%{$studentName}%");
        }

        if ($status) {
            $query->where('attendance_records.status', $status);
        }

        if ($startDate) $query->whereDate('attendance_records.created_at', '>=', $startDate);
        if ($endDate) $query->whereDate('attendance_records.created_at', '<=', $endDate);

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
        }

        return ['type' => 'unknown', 'error' => 'Query type not supported'];
    }

    protected function executeClassQuery(array $extracted, $user): array
    {
        $queryType = $extracted['query_type'] ?? 'list_classes';
        $className = $extracted['class_name'] ?? null;

        if ($queryType === 'count_students_in_class' && $className) {
            $class = DB::table('class_models')->where('name', 'ILIKE', "%{$className}%")->first();
            if (!$class) {
                return ['type' => 'student_count', 'student_count' => 0, 'class_name' => $className];
            }
            $count = DB::table('student_class')->where('class_id', $class->id)->count();
            return ['type' => 'student_count', 'student_count' => $count, 'class_name' => $className];
        }

        if ($queryType === 'list_classes') {
            // List classes that this user is enrolled in (student) or teaches (teacher)
            if ($user && $user->role === 'student') {
                $classes = DB::table('class_models')
                    ->join('student_class', 'class_models.id', '=', 'student_class.class_id')
                    ->where('student_class.student_id', $user->student_id)
                    ->select('class_models.*')
                    ->get();
                return ['type' => 'classes_list', 'count' => $classes->count(), 'classes' => $classes];
            }

            if ($user && $user->role === 'teacher') {
                $teacher = DB::table('teachers')->where('user_id', $user->id)->first();
                if ($teacher) {
                    $classes = DB::table('class_models')->where('teacher_id', $teacher->id)->get();
                    return ['type' => 'classes_list', 'count' => $classes->count(), 'classes' => $classes];
                }
            }
        }

        return ['type' => 'unknown', 'error' => 'Class query not handled'];
    }

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
                return [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()];
        }
    }

    protected function getSchema(): array
    {
        // Provide minimal schema info for SQL generation
        return [
            'students' => ['description' => 'student records', 'columns' => ['id', 'name', 'user_id', 'class_code']],
            'teachers' => ['description' => 'teacher records', 'columns' => ['id', 'user_id', 'first_name', 'last_name']],
            'class_models' => ['description' => 'class models', 'columns' => ['id', 'name', 'subject', 'teacher_id']],
            'attendance_records' => ['description' => 'attendance records', 'columns' => ['id', 'student_id', 'status', 'created_at']],
            'excuse_requests' => ['description' => 'excuse requests', 'columns' => ['id', 'student_id', 'status', 'reviewed_by', 'reviewed_at']],
        ];
    }
}
