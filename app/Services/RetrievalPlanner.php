<?php

namespace App\Services;  // ✅ FIXED: Changed from App\Services\AI

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RetrievalPlanner
{
    /**
     * Plan and execute retrieval for user query
     */
    public function planAndExecute(string $question, array $userContext): array
    {
        $q = Str::lower($question);
        $intent = $this->detectIntent($q);

        $plan = [
            'intent' => $intent,
            'tables' => [],
            'filters' => [],
            'role_constraints' => [],
        ];

        try {
            $results = $this->executeRetrieval($intent, $q, $userContext, $plan);

            return [
                'intent' => $intent,
                'plan' => $plan,
                'results' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('RetrievalPlanner::planAndExecute error', [
                'intent' => $intent,
                'question' => $question,
                'error' => $e->getMessage(),
            ]);

            return [
                'intent' => $intent,
                'plan' => $plan,
                'results' => ['error' => 'Failed to retrieve data: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Execute retrieval based on intent
     */
    private function executeRetrieval(string $intent, string $q, array $userContext, array &$plan)
    {
        switch ($intent) {
            case 'attendance':
                return $this->retrieveAttendance($q, $userContext, $plan);
            
            case 'schedule':
                return $this->retrieveSchedule($userContext, $plan);
            
            case 'teacher':
                return $this->retrieveTeachers($userContext, $plan);
            
            case 'subjects':
            case 'classes':
                return $this->retrieveClasses($userContext, $plan);
            
            default:
                // For general questions, allow AI to answer without specific data retrieval
                $plan['executed_query'] = 'general_question';
                return [
                    'type' => 'general',
                    'message' => 'No specific data retrieval needed - answering from general knowledge',
                ];
        }
    }

    /**
     * Retrieve attendance records
     */
    private function retrieveAttendance(string $q, array $userContext, array &$plan): array
    {
        $plan['tables'][] = 'attendance_records';

        // Extract date range
        [$start, $end] = $this->extractDateRange($q);
        if ($start && $end) {
            $plan['filters']['date_range'] = [$start->toDateTimeString(), $end->toDateTimeString()];
        }

        $role = $userContext['role'] ?? null;

        // Build query based on role
        $query = DB::table('attendance_records');

        if ($role === 'student' && isset($userContext['student_id'])) {
            $studentId = $userContext['student_id'];
            $plan['role_constraints']['student_id'] = $studentId;
            $query->where('attendance_records.student_id', $studentId);

        } elseif ($role === 'teacher' && isset($userContext['teacher'])) {
            $teacherUserId = $userContext['teacher']['user_id'] ?? null;
            $plan['role_constraints']['teacher_user_id'] = $teacherUserId;

            // Get student IDs in teacher's classes
            $studentIds = DB::table('class_student')
                ->join('class_models', 'class_student.class_model_id', '=', 'class_models.id')
                ->where('class_models.teacher_id', $teacherUserId)
                ->where('class_student.status', 'enrolled')
                ->pluck('class_student.student_id')
                ->unique()
                ->all();

            if (!empty($studentIds)) {
                $query->whereIn('attendance_records.student_id', $studentIds);
            } else {
                return ['message' => 'No students found in your classes.'];
            }

        } else {
            // Admin - no filtering, but limit results
            $plan['role_constraints']['role'] = 'admin';
        }

        // Apply date range filter
        if ($start && $end) {
            $query->whereBetween('marked_at', [$start, $end]);
        }

        // Join with students table for names
        $results = $query
            ->join('students', 'attendance_records.student_id', '=', 'students.id')
            ->select([
                'attendance_records.id',
                'attendance_records.student_id',
                'attendance_records.status',
                'attendance_records.marked_at',
                'attendance_records.notes',
                'students.name as student_name',
            ])
            ->orderBy('marked_at', 'desc')
            ->limit(100)
            ->get();

        $plan['executed_query'] = 'attendance_records joined with students';

        // Calculate summary stats
        $summary = [
            'total' => $results->count(),
            'present' => $results->where('status', 'present')->count(),
            'absent' => $results->where('status', 'absent')->count(),
            'late' => $results->where('status', 'late')->count(),
            'excused' => $results->where('status', 'excused')->count(),
        ];

        return [
            'records' => $results->toArray(),
            'summary' => $summary,
            'date_range' => $plan['filters']['date_range'] ?? 'all time',
        ];
    }

    /**
     * Retrieve schedule/classes
     */
    private function retrieveSchedule(array $userContext, array &$plan): array
    {
        $plan['tables'][] = 'class_models';
        $role = $userContext['role'] ?? null;

        if ($role === 'student' && isset($userContext['student_id'])) {
            $studentId = $userContext['student_id'];
            $plan['role_constraints']['student_id'] = $studentId;

            $classes = DB::table('class_models')
                ->join('class_student', 'class_models.id', '=', 'class_student.class_model_id')
                ->where('class_student.student_id', $studentId)
                ->where('class_student.status', 'enrolled')
                ->select([
                    'class_models.id',
                    'class_models.name',
                    'class_models.class_code',
                    'class_models.subject',
                    'class_models.schedule_time',
                    'class_models.schedule_days',
                    'class_models.room',
                ])
                ->orderBy('class_models.schedule_time')
                ->get();

        } elseif ($role === 'teacher' && isset($userContext['teacher'])) {
            $teacherUserId = $userContext['teacher']['user_id'] ?? null;
            $plan['role_constraints']['teacher_user_id'] = $teacherUserId;

            $classes = DB::table('class_models')
                ->where('teacher_id', $teacherUserId)
                ->select([
                    'id', 'name', 'class_code', 'subject',
                    'schedule_time', 'schedule_days', 'room'
                ])
                ->orderBy('schedule_time')
                ->get();

        } else {
            // Admin view
            $classes = DB::table('class_models')
                ->select([
                    'id', 'name', 'class_code', 'subject',
                    'schedule_time', 'schedule_days', 'room'
                ])
                ->whereRaw('COALESCE(is_active, true) = true')
                ->orderBy('name')
                ->limit(50)
                ->get();
        }

        $plan['executed_query'] = 'class_models with schedule fields';

        return [
            'classes' => $classes->toArray(),
            'count' => $classes->count(),
        ];
    }

    /**
     * Retrieve teacher information
     */
    private function retrieveTeachers(array $userContext, array &$plan): array
    {
        $plan['tables'][] = 'teachers';
        $role = $userContext['role'] ?? null;

        if ($role === 'student' && isset($userContext['student_id'])) {
            $studentId = $userContext['student_id'];
            $plan['role_constraints']['student_id'] = $studentId;

            // Find teachers for student's classes
            $teachers = DB::table('teachers')
                ->join('class_models', 'teachers.user_id', '=', 'class_models.teacher_id')
                ->join('class_student', 'class_models.id', '=', 'class_student.class_model_id')
                ->where('class_student.student_id', $studentId)
                ->where('class_student.status', 'enrolled')
                ->select([
                    'teachers.id',
                    'teachers.first_name',
                    'teachers.last_name',
                    'teachers.email',
                    'teachers.department',
                    'class_models.name as class_name',
                    'class_models.subject',
                ])
                ->distinct()
                ->get();

        } elseif ($role === 'teacher' && isset($userContext['teacher'])) {
            // Return their own info
            $teacherId = $userContext['teacher']['id'] ?? null;
            $teachers = DB::table('teachers')
                ->where('id', $teacherId)
                ->select(['id', 'first_name', 'last_name', 'email', 'department', 'position'])
                ->get();

            $plan['role_constraints']['teacher_id'] = $teacherId;

        } else {
            // Admin - all teachers
            $teachers = DB::table('teachers')
                ->select(['id', 'first_name', 'last_name', 'email', 'department', 'position'])
                ->limit(50)
                ->get();
        }

        $plan['executed_query'] = 'teachers table with role-based filtering';

        return [
            'teachers' => $teachers->toArray(),
            'count' => $teachers->count(),
        ];
    }

    /**
     * Retrieve classes/subjects
     */
    private function retrieveClasses(array $userContext, array &$plan): array
    {
        $plan['tables'][] = 'class_models';
        $plan['tables'][] = 'class_student';
        $role = $userContext['role'] ?? null;

        if ($role === 'student' && isset($userContext['student_id'])) {
            $studentId = $userContext['student_id'];
            $plan['role_constraints']['student_id'] = $studentId;

            $classes = DB::table('class_models')
                ->join('class_student', 'class_models.id', '=', 'class_student.class_model_id')
                ->where('class_student.student_id', $studentId)
                ->where('class_student.status', 'enrolled')
                ->select([
                    'class_models.id',
                    'class_models.name',
                    'class_models.subject',
                    'class_models.class_code',
                    'class_models.course',
                    'class_models.section',
                ])
                ->orderBy('class_models.name')
                ->get();

        } elseif ($role === 'teacher' && isset($userContext['teacher'])) {
            $teacherUserId = $userContext['teacher']['user_id'] ?? null;
            $plan['role_constraints']['teacher_user_id'] = $teacherUserId;

            $classes = DB::table('class_models')
                ->where('teacher_id', $teacherUserId)
                ->select(['id', 'name', 'subject', 'class_code', 'course', 'section'])
                ->orderBy('name')
                ->get();

        } else {
            // Admin
            $classes = DB::table('class_models')
                ->select(['id', 'name', 'subject', 'class_code', 'course', 'section'])
                ->whereRaw('COALESCE(is_active, true) = true')
                ->orderBy('name')
                ->limit(50)
                ->get();
        }

        $plan['executed_query'] = 'class_models with student/teacher filtering';

        return [
            'classes' => $classes->toArray(),
            'count' => $classes->count(),
        ];
    }

    /**
     * Detect user intent from question
     */
    protected function detectIntent(string $q): string
    {
        if (Str::contains($q, ['attendance', 'absent', 'present', 'late', 'attendance rate', 'how many times'])) {
            return 'attendance';
        }

        if (Str::contains($q, ['schedule', 'timetable', 'my schedule', 'class time', 'when is', 'what time'])) {
            return 'schedule';
        }

        if (Str::contains($q, ['my teacher', 'teacher', 'who teaches', 'advisor', 'adviser', 'instructor'])) {
            return 'teacher';
        }

        if (Str::contains($q, ['subject', 'subjects', 'my subjects', 'enrolled', 'classes', 'my classes', 'what classes'])) {
            return 'subjects';
        }

        return 'unknown';
    }

    /**
     * Extract date range from natural language
     */
    protected function extractDateRange(string $q): array
    {
        $now = Carbon::now();

        if (Str::contains($q, ['today', 'right now'])) {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        }

        if (Str::contains($q, ['yesterday'])) {
            return [
                $now->copy()->subDay()->startOfDay(),
                $now->copy()->subDay()->endOfDay()
            ];
        }

        if (Str::contains($q, ['this week', 'current week'])) {
            return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
        }

        if (Str::contains($q, ['last week', 'previous week'])) {
            return [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek()
            ];
        }

        if (Str::contains($q, ['this month', 'current month'])) {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        if (Str::contains($q, ['last month', 'previous month'])) {
            return [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth()
            ];
        }

        if (Str::contains($q, ['this semester', 'this year'])) {
            return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
        }

        // Default: no date filter
        return [null, null];
    }
}
