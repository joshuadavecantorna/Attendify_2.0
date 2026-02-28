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
            
            case 'excuse':
                return $this->retrieveExcuses($userContext, $plan);
            
            case 'teacher':
                return $this->retrieveTeachers($userContext, $plan);
            
            case 'subjects':
            case 'classes':
                return $this->retrieveClasses($userContext, $plan);
            
            case 'help':
                return $this->retrieveHelpInfo($q, $userContext, $plan);
            
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
        $plan['tables'][] = 'attendance_sessions';

        // Extract date range
        [$start, $end] = $this->extractDateRange($q);
        if ($start && $end) {
            $plan['filters']['date_range'] = [$start->toDateTimeString(), $end->toDateTimeString()];
        }

        // Detect specific status query
        $specificStatus = null;
        if (Str::contains($q, ['absent', 'absence', 'absences', 'missed'])) {
            $specificStatus = 'absent';
        } elseif (Str::contains($q, ['late', 'tardy'])) {
            $specificStatus = 'late';
        } elseif (Str::contains($q, ['present', 'attended'])) {
            $specificStatus = 'present';
        } elseif (Str::contains($q, ['excused'])) {
            $specificStatus = 'excused';
        }

        $role = $userContext['role'] ?? null;

        // Build query based on role
        $query = DB::table('attendance_records')
            ->join('attendance_sessions', 'attendance_records.attendance_session_id', '=', 'attendance_sessions.id')
            ->join('class_models', 'attendance_sessions.class_id', '=', 'class_models.id');

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
                return [
                    'message' => 'No students found in your classes.',
                    'summary' => [
                        'total' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'excused' => 0,
                    ],
                ];
            }

        } else {
            // Admin - no filtering, but limit results
            $plan['role_constraints']['role'] = 'admin';
        }

        // Apply date range filter
        if ($start && $end) {
            $query->whereBetween('attendance_records.marked_at', [$start, $end]);
        }

        // Apply specific status filter if detected
        if ($specificStatus) {
            $query->where('attendance_records.status', $specificStatus);
            $plan['filters']['status'] = $specificStatus;
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
                'class_models.name as class_name',
                'class_models.subject',
                'attendance_sessions.session_name',
                'attendance_sessions.session_date',
            ])
            ->orderBy('attendance_records.marked_at', 'desc')
            ->limit(100)
            ->get();

        $plan['executed_query'] = 'attendance_records joined with sessions, classes, and students';

        // Calculate summary stats
        $summary = [
            'total' => $results->count(),
            'present' => $results->where('status', 'present')->count(),
            'absent' => $results->where('status', 'absent')->count(),
            'late' => $results->where('status', 'late')->count(),
            'excused' => $results->where('status', 'excused')->count(),
        ];

        // Calculate attendance rate
        $totalRecords = $summary['total'];
        $attendanceRate = $totalRecords > 0 
            ? round((($summary['present'] + $summary['late']) / $totalRecords) * 100, 1) 
            : 0;

        // Determine risk level for students
        $riskLevel = 'good';
        if ($attendanceRate < 75) {
            $riskLevel = 'critical';
        } elseif ($attendanceRate < 85) {
            $riskLevel = 'at_risk';
        } elseif ($attendanceRate < 90) {
            $riskLevel = 'warning';
        }

        return [
            'records' => $results->toArray(),
            'summary' => $summary,
            'attendance_rate' => $attendanceRate,
            'risk_level' => $riskLevel,
            'date_range' => $plan['filters']['date_range'] ?? 'all time',
            'filtered_by_status' => $specificStatus ?? 'all',
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
     * Retrieve excuse requests
     */
    private function retrieveExcuses(array $userContext, array &$plan): array
    {
        $plan['tables'][] = 'excuse_requests';
        $role = $userContext['role'] ?? null;

        if ($role === 'student' && isset($userContext['student_id'])) {
            $studentId = $userContext['student_id'];
            $plan['role_constraints']['student_id'] = $studentId;

            $excuses = DB::table('excuse_requests')
                ->join('attendance_sessions', 'excuse_requests.attendance_session_id', '=', 'attendance_sessions.id')
                ->join('class_models', 'attendance_sessions.class_id', '=', 'class_models.id')
                ->where('excuse_requests.student_id', $studentId)
                ->select([
                    'excuse_requests.id',
                    'excuse_requests.status',
                    'excuse_requests.reason',
                    'excuse_requests.submitted_at',
                    'excuse_requests.created_at',
                    'excuse_requests.reviewed_at',
                    'excuse_requests.review_notes',
                    'attendance_sessions.session_date',
                    'class_models.name as class_name',
                    'class_models.subject',
                ])
                ->orderBy('excuse_requests.created_at', 'desc')
                ->limit(20)
                ->get();

            // Calculate summary
            $summary = [
                'total' => $excuses->count(),
                'pending' => $excuses->where('status', 'pending')->count(),
                'approved' => $excuses->where('status', 'approved')->count(),
                'rejected' => $excuses->where('status', 'rejected')->count(),
            ];

        } elseif ($role === 'teacher' && isset($userContext['teacher'])) {
            $teacherUserId = $userContext['teacher']['user_id'] ?? null;
            $plan['role_constraints']['teacher_user_id'] = $teacherUserId;

            // Get excuses for students in teacher's classes
            $excuses = DB::table('excuse_requests')
                ->join('attendance_sessions', 'excuse_requests.attendance_session_id', '=', 'attendance_sessions.id')
                ->join('class_models', 'attendance_sessions.class_id', '=', 'class_models.id')
                ->join('students', 'excuse_requests.student_id', '=', 'students.id')
                ->where('class_models.teacher_id', $teacherUserId)
                ->select([
                    'excuse_requests.id',
                    'excuse_requests.status',
                    'excuse_requests.reason',
                    'excuse_requests.submitted_at',
                    'excuse_requests.created_at',
                    'attendance_sessions.session_date',
                    'students.name as student_name',
                    'class_models.name as class_name',
                ])
                ->orderBy('excuse_requests.created_at', 'desc')
                ->limit(50)
                ->get();

            $summary = [
                'total' => $excuses->count(),
                'pending' => $excuses->where('status', 'pending')->count(),
                'approved' => $excuses->where('status', 'approved')->count(),
                'rejected' => $excuses->where('status', 'rejected')->count(),
            ];

        } else {
            // Admin - all excuses
            $excuses = DB::table('excuse_requests')
                ->join('students', 'excuse_requests.student_id', '=', 'students.id')
                ->join('attendance_sessions', 'excuse_requests.attendance_session_id', '=', 'attendance_sessions.id')
                ->leftJoin('class_models', 'attendance_sessions.class_id', '=', 'class_models.id')
                ->select([
                    'excuse_requests.id',
                    'excuse_requests.status',
                    'excuse_requests.submitted_at',
                    'attendance_sessions.session_date',
                    'students.name as student_name',
                    'class_models.name as class_name',
                ])
                ->orderBy('excuse_requests.created_at', 'desc')
                ->limit(100)
                ->get();

            $summary = [
                'total' => $excuses->count(),
                'pending' => $excuses->where('status', 'pending')->count(),
                'approved' => $excuses->where('status', 'approved')->count(),
                'rejected' => $excuses->where('status', 'rejected')->count(),
            ];
        }

        $plan['executed_query'] = 'excuse_requests with role-based filtering';

        return [
            'excuses' => $excuses->toArray(),
            'summary' => $summary,
            'count' => $excuses->count(),
        ];
    }
    
    /**
     * Retrieve help information
     */
    private function retrieveHelpInfo(string $q, array $userContext, array &$plan): array
    {
        $plan['tables'][] = 'none - providing system help';
        $role = $userContext['role'] ?? 'user';

        // Provide contextual help based on the question and role
        $helpTopics = [
            'marking_attendance' => [
                'title' => 'How to Mark Attendance',
                'content' => 'To mark your attendance: 1) Open the app when in class, 2) Scan the QR code shown by your teacher, OR use manual check-in, 3) Ensure you mark within the allowed time window.',
                'keywords' => ['mark', 'check in', 'qr', 'scan', 'attendance'],
            ],
            'viewing_schedule' => [
                'title' => 'Viewing Your Schedule',
                'content' => 'Access your class schedule from the main dashboard. You can see daily schedules, upcoming classes, and class times with room locations.',
                'keywords' => ['schedule', 'timetable', 'classes today'],
            ],
            'excuse_submission' => [
                'title' => 'Submitting Excuse Requests',
                'content' => 'To submit an excuse: 1) Go to Excuse Requests section, 2) Select the date and class you missed, 3) Provide a reason and attach documents (medical certificate, etc.), 4) Submit for teacher approval.',
                'keywords' => ['excuse', 'submit', 'how to excuse'],
            ],
            'finding_teachers' => [
                'title' => 'Finding Teacher Information',
                'content' => 'View your teachers from the Classes section. Each class shows the teacher name, email, and office hours if available.',
                'keywords' => ['teacher', 'instructor', 'professor', 'who teaches'],
            ],
        ];

        // Find relevant help topics
        $relevantTopics = [];
        foreach ($helpTopics as $key => $topic) {
            foreach ($topic['keywords'] as $keyword) {
                if (Str::contains($q, $keyword)) {
                    $relevantTopics[] = $topic;
                    break;
                }
            }
        }

        $plan['executed_query'] = 'contextual_help_lookup';

        return [
            'help_topics' => $relevantTopics,
            'role' => $role,
            'system_info' => [
                'name' => 'Attendify',
                'version' => '2.0',
                'features' => ['Attendance Tracking', 'QR Code Check-in', 'Schedule Management', 'Excuse Requests', 'Reports & Analytics'],
            ],
        ];
    }
    
    /**
     * Detect user intent from question
     */
    protected function detectIntent(string $q): string
    {
        // Attendance-related queries
        if (Str::contains($q, [
            'attendance', 'absent', 'present', 'late', 'attendance rate', 
            'how many times', 'how many classes', 'attendance percentage',
            'at risk', 'low attendance', 'attendance warning', 'missed class',
            'skip', 'cut class'
        ])) {
            return 'attendance';
        }

        // Schedule-related queries
        if (Str::contains($q, [
            'schedule', 'timetable', 'my schedule', 'class time', 'when is', 
            'what time', 'classes today', 'classes tomorrow', 'next class',
            'do i have class', 'class schedule', 'when do i have', 'what day'
        ])) {
            return 'schedule';
        }

        // Excuse-related queries
        if (Str::contains($q, [
            'excuse', 'excuse request', 'submit excuse', 'excuse status',
            'excuse approved', 'excuse pending', 'excuse rejected',
            'how to submit', 'medical certificate', 'absence excuse'
        ])) {
            return 'excuse';
        }

        // Teacher-related queries
        if (Str::contains($q, [
            'my teacher', 'teacher', 'who teaches', 'advisor', 'adviser', 
            'instructor', 'professor', 'prof', 'teacher for', 'who is teaching'
        ])) {
            return 'teacher';
        }

        // Class/Subject queries
        if (Str::contains($q, [
            'subject', 'subjects', 'my subjects', 'enrolled', 'classes', 
            'my classes', 'what classes', 'class list', 'course', 'courses'
        ])) {
            return 'subjects';
        }

        // General help queries
        if (Str::contains($q, [
            'how do i', 'how to', 'help', 'mark attendance', 'check in',
            'qr code', 'scan', 'manual check-in', 'what is', 'explain'
        ])) {
            return 'help';
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
