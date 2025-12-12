<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ClassModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class N8NController extends Controller
{
    /**
     * Get all students with their today's class schedule
     * Based on schedule_days in class_models table
     * For 6am daily automation
     */
    public function getAllTodaySchedules()
    {
        try {
            $today = Carbon::now();
            $todayName = strtolower($today->format('l')); // "monday", "tuesday", etc.
            
            Log::info('Fetching schedules', [
                'date' => $today->toDateString(),
                'day_name' => $todayName
            ]);

            // Get all active classes that have today in their schedule_days
            $todayClasses = ClassModel::with(['teacher'])
                ->where('is_active', true)
                ->whereNotNull('schedule_days')
                ->whereNotNull('schedule_time')
                ->get()
                ->filter(function($class) use ($todayName) {
                    // schedule_days is stored as JSON array like ["monday","wednesday","friday"]
                    if (is_array($class->schedule_days)) {
                        // Convert all to lowercase for comparison
                        $scheduleDays = array_map('strtolower', $class->schedule_days);
                        return in_array($todayName, $scheduleDays);
                    }
                    return false;
                });

            Log::info('Classes found for today', [
                'count' => $todayClasses->count()
            ]);

            // Group classes by student
            $studentsSchedule = [];

            foreach ($todayClasses as $class) {
                // Get enrolled students for this class
                $enrolledStudents = DB::table('class_student')
                    ->join('students', 'students.id', '=', 'class_student.student_id')
                    ->join('users', 'users.id', '=', 'students.user_id')
                    ->where('class_student.class_model_id', $class->id)
                    ->where('class_student.status', 'enrolled') // Check enrollment status
                    ->whereNotNull('students.telegram_chat_id')
                    ->where('students.notification_enabled', true)
                    ->select(
                        'students.id as student_id',
                        'students.telegram_chat_id',
                        'students.telegram_username',
                        'students.preferred_language',
                        'students.notification_enabled',
                        'users.name as student_name'
                    )
                    ->get();

                if ($enrolledStudents->isEmpty()) {
                    continue;
                }

                foreach ($enrolledStudents as $student) {
                    // Initialize student data if not exists
                    if (!isset($studentsSchedule[$student->student_id])) {
                        $studentsSchedule[$student->student_id] = [
                            'student_id' => $student->student_id,
                            'student_name' => $student->student_name ?? 'Unknown',
                            'telegram_chat_id' => $student->telegram_chat_id,
                            'telegram_username' => $student->telegram_username,
                            'preferred_language' => $student->preferred_language ?? 'en',
                            'date' => $today->format('l, F j, Y'),
                            'day_of_week' => $today->format('l'),
                            'classes' => []
                        ];
                    }

                    // Get class name (fallback to name if class_name doesn't exist)
                    $className = $class->class_name ?? $class->name ?? 'Unnamed Class';

                    // Get teacher name
                    $teacherName = 'TBA';
                    if ($class->teacher) {
                        $teacherName = $class->teacher->name ?? 'TBA';
                    }

                    // Parse schedule time
                    $scheduleTime = '00:00';
                    $scheduleTime24h = '00:00';
                    if ($class->schedule_time) {
                        try {
                            $time = Carbon::parse($class->schedule_time);
                            $scheduleTime = $time->format('g:i A');
                            $scheduleTime24h = $time->format('H:i');
                        } catch (\Exception $e) {
                            Log::warning('Failed to parse schedule_time', [
                                'class_id' => $class->id,
                                'schedule_time' => $class->schedule_time
                            ]);
                        }
                    }

                    // Format schedule days (e.g., "Mon, Wed, Fri")
                    $scheduleDaysFormatted = '';
                    if (is_array($class->schedule_days)) {
                        $scheduleDaysFormatted = implode(', ', array_map('ucfirst', $class->schedule_days));
                    }

                    // Add class to student's schedule
                    $studentsSchedule[$student->student_id]['classes'][] = [
                        'class_id' => $class->id,
                        'time' => $scheduleTime,
                        'time_24h' => $scheduleTime24h,
                        'class_name' => $className,
                        'class_code' => $class->class_code ?? 'N/A',
                        'teacher_name' => $teacherName,
                        'schedule_days' => $scheduleDaysFormatted,
                        'location' => $class->room ?? 'TBA',
                        'subject' => $class->subject ?? '',
                        'description' => $class->description ?? '',
                    ];
                }
            }

            // Sort classes by time for each student
            foreach ($studentsSchedule as &$studentData) {
                usort($studentData['classes'], function($a, $b) {
                    return strcmp($a['time_24h'], $b['time_24h']);
                });
                
                // Add total classes count
                $studentData['total_classes'] = count($studentData['classes']);
                
                // Calculate first and last class times
                if (!empty($studentData['classes'])) {
                    $studentData['first_class_time'] = $studentData['classes'][0]['time'];
                    $studentData['last_class_time'] = end($studentData['classes'])['time'];
                }
            }

            Log::info('Processed student schedules', [
                'total_students' => count($studentsSchedule)
            ]);

            // ==================================================
            // FETCH TEACHERS' SCHEDULES
            // ==================================================
            $teachersSchedule = [];

            // Get all teachers with Telegram enabled
            $teachers = Teacher::with('user')
                ->whereNotNull('telegram_chat_id')
                ->where('notification_enabled', true)
                ->where('is_active', true)
                ->get();

            Log::info('Teachers with notifications enabled', [
                'count' => $teachers->count()
            ]);

            foreach ($teachers as $teacher) {
                // Get classes taught by this teacher that are scheduled today
                $teacherTodayClasses = ClassModel::where('teacher_id', $teacher->id)
                    ->where('is_active', true)
                    ->whereNotNull('schedule_days')
                    ->whereNotNull('schedule_time')
                    ->get()
                    ->filter(function($class) use ($todayName) {
                        if (is_array($class->schedule_days)) {
                            $scheduleDays = array_map('strtolower', $class->schedule_days);
                            return in_array($todayName, $scheduleDays);
                        }
                        return false;
                    });

                if ($teacherTodayClasses->isEmpty()) {
                    continue; // Skip teachers with no classes today
                }

                $teacherClasses = [];
                foreach ($teacherTodayClasses as $class) {
                    $className = $class->class_name ?? $class->name ?? 'Unnamed Class';

                    // Parse schedule time
                    $scheduleTime = '00:00';
                    $scheduleTime24h = '00:00';
                    if ($class->schedule_time) {
                        try {
                            $time = Carbon::parse($class->schedule_time);
                            $scheduleTime = $time->format('g:i A');
                            $scheduleTime24h = $time->format('H:i');
                        } catch (\Exception $e) {
                            Log::warning('Failed to parse schedule_time', [
                                'class_id' => $class->id,
                                'schedule_time' => $class->schedule_time
                            ]);
                        }
                    }

                    // Format schedule days
                    $scheduleDaysFormatted = '';
                    if (is_array($class->schedule_days)) {
                        $scheduleDaysFormatted = implode(', ', array_map('ucfirst', $class->schedule_days));
                    }

                    // Get student count for this class
                    $studentCount = DB::table('class_student')
                        ->where('class_model_id', $class->id)
                        ->where('status', 'enrolled')
                        ->count();

                    $teacherClasses[] = [
                        'class_id' => $class->id,
                        'time' => $scheduleTime,
                        'time_24h' => $scheduleTime24h,
                        'class_name' => $className,
                        'class_code' => $class->class_code ?? 'N/A',
                        'schedule_days' => $scheduleDaysFormatted,
                        'location' => $class->room ?? 'TBA',
                        'subject' => $class->subject ?? '',
                        'student_count' => $studentCount,
                    ];
                }

                // Sort classes by time
                usort($teacherClasses, function($a, $b) {
                    return strcmp($a['time_24h'], $b['time_24h']);
                });

                $teachersSchedule[] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->user->name ?? $teacher->first_name . ' ' . $teacher->last_name,
                    'telegram_chat_id' => $teacher->telegram_chat_id,
                    'telegram_username' => $teacher->telegram_username,
                    'preferred_language' => $teacher->preferred_language ?? 'en',
                    'date' => $today->format('l, F j, Y'),
                    'day_of_week' => $today->format('l'),
                    'total_classes' => count($teacherClasses),
                    'first_class_time' => !empty($teacherClasses) ? $teacherClasses[0]['time'] : null,
                    'last_class_time' => !empty($teacherClasses) ? end($teacherClasses)['time'] : null,
                    'classes' => $teacherClasses,
                    'user_type' => 'teacher' // Added to differentiate in n8n
                ];
            }

            Log::info('Processed teacher schedules', [
                'total_teachers' => count($teachersSchedule)
            ]);

            // Combine students and teachers into one array
            $allUsersSchedule = array_merge(
                array_map(function($student) {
                    $student['user_type'] = 'student';
                    return $student;
                }, array_values($studentsSchedule)),
                $teachersSchedule
            );

            return response()->json([
                'success' => true,
                'date' => $today->format('Y-m-d'),
                'date_formatted' => $today->format('l, F j, Y'),
                'day_of_week' => $today->format('l'),
                'total_students_with_classes' => count($studentsSchedule),
                'total_teachers_with_classes' => count($teachersSchedule),
                'total_users' => count($allUsersSchedule),
                'students' => array_values($studentsSchedule),
                'teachers' => $teachersSchedule,
                'all_users' => $allUsersSchedule // Combined array for easy n8n processing
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching schedules', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to fetch schedules',
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Get specific student's today schedule
     * Based on schedule_days in class_models table
     * For on-demand queries
     */
    public function getStudentTodaySchedule($studentId)
    {
        try {
            $student = Student::with('user')->findOrFail($studentId);
            $today = Carbon::now();
            $todayName = strtolower($today->format('l'));

            // Get student's enrolled class IDs
            $enrolledClassIds = DB::table('class_student')
                ->where('student_id', $studentId)
                ->where('status', 'enrolled')
                ->pluck('class_model_id');

            // Get classes that have today in schedule_days
            $todayClasses = ClassModel::with('teacher')
                ->whereIn('id', $enrolledClassIds)
                ->where('is_active', true)
                ->whereNotNull('schedule_days')
                ->whereNotNull('schedule_time')
                ->get()
                ->filter(function($class) use ($todayName) {
                    if (is_array($class->schedule_days)) {
                        $scheduleDays = array_map('strtolower', $class->schedule_days);
                        return in_array($todayName, $scheduleDays);
                    }
                    return false;
                });

            $classes = $todayClasses->map(function($class) {
                // Get class name
                $className = $class->class_name ?? $class->name ?? 'Unnamed Class';

                // Get teacher name
                $teacherName = 'TBA';
                if ($class->teacher) {
                    $teacherName = $class->teacher->name ?? 'TBA';
                }

                // Parse schedule time
                $scheduleTime = '00:00';
                $scheduleTime24h = '00:00';
                if ($class->schedule_time) {
                    try {
                        $time = Carbon::parse($class->schedule_time);
                        $scheduleTime = $time->format('g:i A');
                        $scheduleTime24h = $time->format('H:i');
                    } catch (\Exception $e) {
                        // Keep defaults
                    }
                }

                // Format schedule days
                $scheduleDaysFormatted = '';
                if (is_array($class->schedule_days)) {
                    $scheduleDaysFormatted = implode(', ', array_map('ucfirst', $class->schedule_days));
                }

                return [
                    'class_id' => $class->id,
                    'time' => $scheduleTime,
                    'time_24h' => $scheduleTime24h,
                    'class_name' => $className,
                    'class_code' => $class->class_code ?? 'N/A',
                    'teacher_name' => $teacherName,
                    'schedule_days' => $scheduleDaysFormatted,
                    'location' => $class->room ?? 'TBA',
                ];
            })->sortBy('time_24h')->values()->toArray();

            return response()->json([
                'success' => true,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->user->name,
                    'telegram_chat_id' => $student->telegram_chat_id,
                ],
                'date' => $today->format('l, F j, Y'),
                'day_of_week' => $today->format('l'),
                'total_classes' => count($classes),
                'classes' => $classes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update student's telegram chat ID
     * Called when student first interacts with bot
     */
    public function updateTelegramChatId(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'telegram_chat_id' => 'required|string',
            'telegram_username' => 'nullable|string'
        ]);

        try {
            $student = Student::findOrFail($request->student_id);
            $student->update([
                'telegram_chat_id' => $request->telegram_chat_id,
                'telegram_username' => $request->telegram_username,
                'notification_enabled' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Telegram chat ID updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check endpoint
     */
    public function healthCheck()
    {
        return response()->json([
            'success' => true,
            'message' => 'N8N API is working',
            'timestamp' => now()->toDateTimeString(),
            'server_time' => now()->toDateTimeString(),
            'timezone' => config('app.timezone'),
            'current_day' => now()->format('l')
        ]);
    }

    /**
     * Check if user exists by telegram_chat_id
     * For /start command - check if user is already registered
     */
    public function checkUser(Request $request)
    {
        $telegramChatId = $request->query('telegram_chat_id');

        if (!$telegramChatId) {
            return response()->json([
                'success' => false,
                'message' => 'telegram_chat_id is required'
            ], 400);
        }

        try {
            // Check students first
            $student = Student::with('user')
                ->where('telegram_chat_id', $telegramChatId)
                ->first();

            if ($student) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'user' => [
                        'type' => 'student',
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'name' => $student->user->name ?? $student->name ?? 'Student',
                        'notification_enabled' => $student->notification_enabled ?? false
                    ]
                ]);
            }

            // Check teachers
            $teacher = Teacher::with('user')
                ->where('telegram_chat_id', $telegramChatId)
                ->first();

            if ($teacher) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'user' => [
                        'type' => 'teacher',
                        'id' => $teacher->id,
                        'teacher_id' => $teacher->teacher_id,
                        'name' => $teacher->user->name ?? ($teacher->first_name . ' ' . $teacher->last_name),
                        'notification_enabled' => $teacher->notification_enabled ?? false
                    ]
                ]);
            }

            // User not found
            return response()->json([
                'success' => true,
                'exists' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking user', [
                'error' => $e->getMessage(),
                'telegram_chat_id' => $telegramChatId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error checking user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register user with Telegram
     * Searches by student_id or teacher_id
     * Prevents duplicate registrations
     */
    public function registerTelegram(Request $request)
    {
        $request->validate([
            'id' => 'required|string',
            'telegram_chat_id' => 'required|string',
            'telegram_username' => 'nullable|string'
        ]);

        $id = trim($request->id);
        $telegramChatId = $request->telegram_chat_id;
        $telegramUsername = $request->telegram_username;

        try {
            // Check if this telegram_chat_id is already registered
            $existingStudent = Student::where('telegram_chat_id', $telegramChatId)->first();
            $existingTeacher = Teacher::where('telegram_chat_id', $telegramChatId)->first();

            if ($existingStudent || $existingTeacher) {
                $existingUser = $existingStudent ?: $existingTeacher;
                $existingId = $existingStudent ? $existingStudent->student_id : $existingTeacher->teacher_id;
                
                return response()->json([
                    'success' => false,
                    'message' => 'Your Telegram account is already registered',
                    'registered_id' => $existingId
                ]);
            }

            // Try to find student by student_id
            $student = Student::with('user')->where('student_id', $id)->first();

            if ($student) {
                // Check if this student_id is already linked to another telegram account
                if ($student->telegram_chat_id && $student->telegram_chat_id !== $telegramChatId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This ID is already registered with another Telegram account',
                        'existing_username' => $student->telegram_username ?? 'Unknown'
                    ]);
                }

                // Update student's telegram info
                $student->update([
                    'telegram_chat_id' => $telegramChatId,
                    'telegram_username' => $telegramUsername,
                    'notification_enabled' => true
                ]);

                Log::info('Student registered with Telegram', [
                    'student_id' => $student->student_id,
                    'telegram_chat_id' => $telegramChatId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful',
                    'user' => [
                        'type' => 'student',
                        'id' => $student->id,
                        'student_id' => $student->student_id,
                        'name' => $student->user->name ?? $student->name ?? 'Student',
                        'notification_enabled' => true
                    ]
                ]);
            }

            // Try to find teacher by teacher_id
            $teacher = Teacher::with('user')->where('teacher_id', $id)->first();

            if ($teacher) {
                // Check if this teacher_id is already linked to another telegram account
                if ($teacher->telegram_chat_id && $teacher->telegram_chat_id !== $telegramChatId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This ID is already registered with another Telegram account',
                        'existing_username' => $teacher->telegram_username ?? 'Unknown'
                    ]);
                }

                // Update teacher's telegram info
                $teacher->update([
                    'telegram_chat_id' => $telegramChatId,
                    'telegram_username' => $telegramUsername,
                    'notification_enabled' => true
                ]);

                Log::info('Teacher registered with Telegram', [
                    'teacher_id' => $teacher->teacher_id,
                    'telegram_chat_id' => $telegramChatId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful',
                    'user' => [
                        'type' => 'teacher',
                        'id' => $teacher->id,
                        'teacher_id' => $teacher->teacher_id,
                        'name' => $teacher->user->name ?? ($teacher->first_name . ' ' . $teacher->last_name),
                        'notification_enabled' => true
                    ]
                ]);
            }

            // ID not found in either table
            return response()->json([
                'success' => false,
                'message' => 'ID not found in our system. Please check your Student ID or Teacher ID and try again.'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error registering telegram', [
                'error' => $e->getMessage(),
                'id' => $id,
                'telegram_chat_id' => $telegramChatId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error during registration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's schedule for a user by telegram_chat_id
     * For /today command
     */
    public function getTodayScheduleByChat(Request $request)
    {
        $telegramChatId = $request->query('telegram_chat_id');

        if (!$telegramChatId) {
            return response()->json([
                'success' => false,
                'message' => 'telegram_chat_id is required'
            ], 400);
        }

        try {
            $today = Carbon::now();
            $todayName = strtolower($today->format('l'));

            // Check if user is a student
            $student = Student::with('user')->where('telegram_chat_id', $telegramChatId)->first();

            if ($student) {
                // Get student's enrolled class IDs
                $enrolledClassIds = DB::table('class_student')
                    ->where('student_id', $student->id)
                    ->where('status', 'enrolled')
                    ->pluck('class_model_id');

                // Get classes scheduled for today
                $todayClasses = ClassModel::with('teacher')
                    ->whereIn('id', $enrolledClassIds)
                    ->where('is_active', true)
                    ->whereNotNull('schedule_days')
                    ->whereNotNull('schedule_time')
                    ->get()
                    ->filter(function($class) use ($todayName) {
                        if (is_array($class->schedule_days)) {
                            $scheduleDays = array_map('strtolower', $class->schedule_days);
                            return in_array($todayName, $scheduleDays);
                        }
                        return false;
                    });

                $classes = $todayClasses->map(function($class) {
                    $time = Carbon::parse($class->schedule_time);
                    return [
                        'class_id' => $class->id,
                        'time' => $time->format('g:i A'),
                        'time_24h' => $time->format('H:i'),
                        'class_name' => $class->class_name ?? $class->name ?? 'Unnamed Class',
                        'class_code' => $class->class_code ?? 'N/A',
                        'teacher_name' => $class->teacher ? ($class->teacher->user->name ?? 'TBA') : 'TBA',
                        'location' => $class->room ?? 'TBA',
                    ];
                })->sortBy('time_24h')->values()->toArray();

                return response()->json([
                    'success' => true,
                    'user' => [
                        'type' => 'student',
                        'name' => $student->user->name ?? $student->name ?? 'Student',
                        'student_id' => $student->student_id
                    ],
                    'date' => $today->format('l, F j, Y'),
                    'day_of_week' => $today->format('l'),
                    'total_classes' => count($classes),
                    'classes' => $classes
                ]);
            }

            // Check if user is a teacher
            $teacher = Teacher::with('user')->where('telegram_chat_id', $telegramChatId)->first();

            if ($teacher) {
                // Get classes taught by this teacher today
                $todayClasses = ClassModel::where('teacher_id', $teacher->id)
                    ->where('is_active', true)
                    ->whereNotNull('schedule_days')
                    ->whereNotNull('schedule_time')
                    ->get()
                    ->filter(function($class) use ($todayName) {
                        if (is_array($class->schedule_days)) {
                            $scheduleDays = array_map('strtolower', $class->schedule_days);
                            return in_array($todayName, $scheduleDays);
                        }
                        return false;
                    });

                $classes = $todayClasses->map(function($class) {
                    $time = Carbon::parse($class->schedule_time);
                    $studentCount = DB::table('class_student')
                        ->where('class_model_id', $class->id)
                        ->where('status', 'enrolled')
                        ->count();

                    return [
                        'class_id' => $class->id,
                        'time' => $time->format('g:i A'),
                        'time_24h' => $time->format('H:i'),
                        'class_name' => $class->class_name ?? $class->name ?? 'Unnamed Class',
                        'class_code' => $class->class_code ?? 'N/A',
                        'location' => $class->room ?? 'TBA',
                        'student_count' => $studentCount
                    ];
                })->sortBy('time_24h')->values()->toArray();

                return response()->json([
                    'success' => true,
                    'user' => [
                        'type' => 'teacher',
                        'name' => $teacher->user->name ?? ($teacher->first_name . ' ' . $teacher->last_name),
                        'teacher_id' => $teacher->teacher_id
                    ],
                    'date' => $today->format('l, F j, Y'),
                    'day_of_week' => $today->format('l'),
                    'total_classes' => count($classes),
                    'classes' => $classes
                ]);
            }

            // User not found
            return response()->json([
                'success' => false,
                'message' => 'User not registered. Please send /start to register first.'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error getting today schedule', [
                'error' => $e->getMessage(),
                'telegram_chat_id' => $telegramChatId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable notifications for a user
     * For /stop command
     */
    public function disableNotifications(Request $request)
    {
        $telegramChatId = $request->input('telegram_chat_id');

        if (!$telegramChatId) {
            return response()->json([
                'success' => false,
                'message' => 'telegram_chat_id is required'
            ], 400);
        }

        try {
            // Check students
            $student = Student::where('telegram_chat_id', $telegramChatId)->first();
            if ($student) {
                $student->update(['notification_enabled' => false]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Notifications disabled successfully',
                    'user_type' => 'student'
                ]);
            }

            // Check teachers
            $teacher = Teacher::where('telegram_chat_id', $telegramChatId)->first();
            if ($teacher) {
                $teacher->update(['notification_enabled' => false]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Notifications disabled successfully',
                    'user_type' => 'teacher'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error disabling notifications', [
                'error' => $e->getMessage(),
                'telegram_chat_id' => $telegramChatId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error disabling notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable notifications for a user
     * For /resume command
     */
    public function enableNotifications(Request $request)
    {
        $telegramChatId = $request->input('telegram_chat_id');

        if (!$telegramChatId) {
            return response()->json([
                'success' => false,
                'message' => 'telegram_chat_id is required'
            ], 400);
        }

        try {
            // Check students
            $student = Student::where('telegram_chat_id', $telegramChatId)->first();
            if ($student) {
                $student->update(['notification_enabled' => true]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Notifications enabled successfully',
                    'user_type' => 'student'
                ]);
            }

            // Check teachers
            $teacher = Teacher::where('telegram_chat_id', $telegramChatId)->first();
            if ($teacher) {
                $teacher->update(['notification_enabled' => true]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Notifications enabled successfully',
                    'user_type' => 'teacher'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error enabling notifications', [
                'error' => $e->getMessage(),
                'telegram_chat_id' => $telegramChatId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error enabling notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
