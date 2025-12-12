<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
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

            Log::info('Processed schedules', [
                'total_students' => count($studentsSchedule)
            ]);

            return response()->json([
                'success' => true,
                'date' => $today->format('Y-m-d'),
                'date_formatted' => $today->format('l, F j, Y'),
                'day_of_week' => $today->format('l'),
                'total_students_with_classes' => count($studentsSchedule),
                'students' => array_values($studentsSchedule)
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
     * Get all registered users with their roles
     * For N8N automation
     */
    public function getAllUsers()
    {
        try {
            $users = DB::table('users')
                ->select([
                    'id',
                    'name',
                    'email',
                    'role',
                    'created_at',
                    'updated_at'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            // Get additional details for each role
            $usersWithDetails = $users->map(function($user) {
                $userData = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];

                // Add role-specific details
                if ($user->role === 'student') {
                    $student = DB::table('students')
                        ->where('user_id', $user->id)
                        ->first();
                    
                    if ($student) {
                        $userData['student_id'] = $student->id;
                        $userData['student_number'] = $student->student_number ?? null;
                        $userData['telegram_chat_id'] = $student->telegram_chat_id ?? null;
                        $userData['telegram_username'] = $student->telegram_username ?? null;
                        $userData['notification_enabled'] = $student->notification_enabled ?? false;
                        $userData['preferred_language'] = $student->preferred_language ?? 'en';
                    }
                } elseif ($user->role === 'teacher') {
                    $teacher = DB::table('teachers')
                        ->where('user_id', $user->id)
                        ->first();
                    
                    if ($teacher) {
                        $userData['teacher_id'] = $teacher->id;
                        $userData['department'] = $teacher->department ?? null;
                        $userData['position'] = $teacher->position ?? null;
                    }
                }

                return $userData;
            });

            return response()->json([
                'success' => true,
                'total_users' => $users->count(),
                'timestamp' => now()->toDateTimeString(),
                'users' => $usersWithDetails
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all users', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
     * Get all users with notifications enabled for daily 6 AM reminders
     * Returns students and teachers with telegram_chat_id and notification_enabled
     */
    public function getAllUsersForDailyNotifications()
    {
        try {
            $users = [];

            // Get all students with notifications enabled
            $students = Student::whereNotNull('telegram_chat_id')
                ->where('notification_enabled', true)
                ->get(['student_id', 'name', 'telegram_chat_id', 'telegram_username'])
                ->map(function($student) {
                    return [
                        'id' => $student->student_id,
                        'name' => $student->name,
                        'type' => 'student',
                        'telegram_chat_id' => $student->telegram_chat_id,
                        'telegram_username' => $student->telegram_username,
                    ];
                });

            // Get all teachers with notifications enabled
            $teachers = \App\Models\Teacher::whereNotNull('telegram_chat_id')
                ->where('notification_enabled', true)
                ->get(['teacher_id', 'first_name', 'last_name', 'telegram_chat_id', 'telegram_username'])
                ->map(function($teacher) {
                    return [
                        'id' => $teacher->teacher_id,
                        'name' => trim($teacher->first_name . ' ' . $teacher->last_name),
                        'type' => 'teacher',
                        'telegram_chat_id' => $teacher->telegram_chat_id,
                        'telegram_username' => $teacher->telegram_username,
                    ];
                });

            // Combine both
            $users = $students->concat($teachers)->values();

            return response()->json([
                'success' => true,
                'total_users' => $users->count(),
                'students_count' => $students->count(),
                'teachers_count' => $teachers->count(),
                'users' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting users for daily notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user exists by telegram_chat_id
     */
    public function checkUser(Request $request)
    {
        try {
            $telegram_chat_id = $request->input('telegram_chat_id');

            if (!$telegram_chat_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'telegram_chat_id is required'
                ], 400);
            }

            // Check in students
            $student = Student::where('telegram_chat_id', $telegram_chat_id)->first();
            if ($student) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'user_type' => 'student',
                    'user_id' => $student->student_id,
                    'user_name' => $student->name
                ]);
            }

            // Check in teachers
            $teacher = \App\Models\Teacher::where('telegram_chat_id', $telegram_chat_id)->first();
            if ($teacher) {
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'user_type' => 'teacher',
                    'user_id' => $teacher->teacher_id,
                    'user_name' => trim($teacher->first_name . ' ' . $teacher->last_name)
                ]);
            }

            return response()->json([
                'success' => true,
                'exists' => false
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Register user with Telegram
     */
    public function registerTelegram(Request $request)
    {
        try {
            $id = $request->input('id');
            $telegram_chat_id = $request->input('telegram_chat_id');
            $telegram_username = $request->input('telegram_username');

            if (!$id || !$telegram_chat_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID and telegram_chat_id are required'
                ], 400);
            }

            // Check if telegram_chat_id is already used
            $existingStudent = Student::where('telegram_chat_id', $telegram_chat_id)->first();
            $existingTeacher = \App\Models\Teacher::where('telegram_chat_id', $telegram_chat_id)->first();

            if ($existingStudent || $existingTeacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'This Telegram account is already linked to another user'
                ], 400);
            }

            // Try to find student
            $student = Student::where('student_id', $id)->first();
            if ($student) {
                // Check if already linked to different telegram
                if ($student->telegram_chat_id && $student->telegram_chat_id !== $telegram_chat_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This Student ID is already linked to another Telegram account'
                    ], 400);
                }

                $student->telegram_chat_id = $telegram_chat_id;
                $student->telegram_username = $telegram_username;
                $student->notification_enabled = true;
                $student->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful',
                    'user' => [
                        'type' => 'student',
                        'id' => $student->student_id,
                        'name' => $student->name
                    ]
                ]);
            }

            // Try to find teacher
            $teacher = \App\Models\Teacher::where('teacher_id', $id)->first();
            if ($teacher) {
                // Check if already linked to different telegram
                if ($teacher->telegram_chat_id && $teacher->telegram_chat_id !== $telegram_chat_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This Teacher ID is already linked to another Telegram account'
                    ], 400);
                }

                $teacher->telegram_chat_id = $telegram_chat_id;
                $teacher->telegram_username = $telegram_username;
                $teacher->notification_enabled = true;
                $teacher->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Registration successful',
                    'user' => [
                        'type' => 'teacher',
                        'id' => $teacher->teacher_id,
                        'name' => trim($teacher->first_name . ' ' . $teacher->last_name)
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not found. Invalid Student ID or Teacher ID'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error during registration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's schedule by telegram_chat_id
     */
    public function getTodayScheduleByChat(Request $request)
    {
        try {
            $telegram_chat_id = $request->input('telegram_chat_id');

            if (!$telegram_chat_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'telegram_chat_id is required'
                ], 400);
            }

            // Find user
            $student = Student::where('telegram_chat_id', $telegram_chat_id)->first();
            $teacher = null;
            $userType = 'student';

            if (!$student) {
                $teacher = \App\Models\Teacher::where('telegram_chat_id', $telegram_chat_id)->first();
                $userType = 'teacher';
            }

            if (!$student && !$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not registered. Please send /start to register first.'
                ], 404);
            }

            $today = Carbon::now()->format('l');
            $date = Carbon::now()->format('F d, Y');

            if ($userType === 'student') {
                // Get student's classes
                $classes = DB::table('class_student')
                    ->join('class_models', 'class_models.id', '=', 'class_student.class_model_id')
                    ->join('teachers', 'teachers.id', '=', 'class_models.teacher_id')
                    ->where('class_student.student_id', $student->id)
                    ->where('class_models.day', $today)
                    ->select(
                        'class_models.class_name',
                        'class_models.class_code',
                        'class_models.start_time',
                        'class_models.end_time',
                        'class_models.location',
                        'teachers.first_name as teacher_first_name',
                        'teachers.last_name as teacher_last_name'
                    )
                    ->orderBy('class_models.start_time')
                    ->get()
                    ->map(function ($class) {
                        return [
                            'class_name' => $class->class_name,
                            'class_code' => $class->class_code,
                            'time' => Carbon::parse($class->start_time)->format('g:i A') . ' - ' . 
                                     Carbon::parse($class->end_time)->format('g:i A'),
                            'location' => $class->location,
                            'teacher_name' => trim($class->teacher_first_name . ' ' . $class->teacher_last_name)
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'date' => $date,
                    'day' => $today,
                    'total_classes' => $classes->count(),
                    'user' => [
                        'type' => 'student',
                        'name' => $student->name,
                        'id' => $student->student_id
                    ],
                    'classes' => $classes
                ]);
            } else {
                // Get teacher's classes
                $classes = DB::table('class_models')
                    ->leftJoin('class_student', 'class_models.id', '=', 'class_student.class_model_id')
                    ->where('class_models.teacher_id', $teacher->id)
                    ->where('class_models.day', $today)
                    ->select(
                        'class_models.class_name',
                        'class_models.class_code',
                        'class_models.start_time',
                        'class_models.end_time',
                        'class_models.location',
                        DB::raw('COUNT(DISTINCT class_student.student_id) as student_count')
                    )
                    ->groupBy('class_models.id', 'class_models.class_name', 'class_models.class_code', 
                             'class_models.start_time', 'class_models.end_time', 'class_models.location')
                    ->orderBy('class_models.start_time')
                    ->get()
                    ->map(function ($class) {
                        return [
                            'class_name' => $class->class_name,
                            'class_code' => $class->class_code,
                            'time' => Carbon::parse($class->start_time)->format('g:i A') . ' - ' . 
                                     Carbon::parse($class->end_time)->format('g:i A'),
                            'location' => $class->location,
                            'student_count' => $class->student_count
                        ];
                    });

                return response()->json([
                    'success' => true,
                    'date' => $date,
                    'day' => $today,
                    'total_classes' => $classes->count(),
                    'user' => [
                        'type' => 'teacher',
                        'name' => trim($teacher->first_name . ' ' . $teacher->last_name),
                        'id' => $teacher->teacher_id
                    ],
                    'classes' => $classes
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable notifications for user
     */
    public function disableNotifications(Request $request)
    {
        try {
            $telegram_chat_id = $request->input('telegram_chat_id');

            if (!$telegram_chat_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'telegram_chat_id is required'
                ], 400);
            }

            $student = Student::where('telegram_chat_id', $telegram_chat_id)->first();
            if ($student) {
                $student->notification_enabled = false;
                $student->save();
                return response()->json(['success' => true, 'message' => 'Notifications disabled']);
            }

            $teacher = \App\Models\Teacher::where('telegram_chat_id', $telegram_chat_id)->first();
            if ($teacher) {
                $teacher->notification_enabled = false;
                $teacher->save();
                return response()->json(['success' => true, 'message' => 'Notifications disabled']);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error disabling notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable notifications for user
     */
    public function enableNotifications(Request $request)
    {
        try {
            $telegram_chat_id = $request->input('telegram_chat_id');

            if (!$telegram_chat_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'telegram_chat_id is required'
                ], 400);
            }

            $student = Student::where('telegram_chat_id', $telegram_chat_id)->first();
            if ($student) {
                $student->notification_enabled = true;
                $student->save();
                return response()->json(['success' => true, 'message' => 'Notifications enabled']);
            }

            $teacher = \App\Models\Teacher::where('telegram_chat_id', $telegram_chat_id)->first();
            if ($teacher) {
                $teacher->notification_enabled = true;
                $teacher->save();
                return response()->json(['success' => true, 'message' => 'Notifications enabled']);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error enabling notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get today's schedule by telegram_chat_id
     * Used by /today command and daily scheduler
     */
    public function getTodayScheduleByChat(Request $request)
    {
        try {
            $telegram_chat_id = $request->telegram_chat_id;

            if (!$telegram_chat_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'telegram_chat_id is required'
                ], 400);
            }

            // Check if student
            $student = Student::where('telegram_chat_id', $telegram_chat_id)->first();
            
            if ($student) {
                $today = Carbon::now();
                $todayName = strtolower($today->format('l'));

                // Get student's enrolled class IDs
                $enrolledClassIds = DB::table('class_student')
                    ->where('student_id', $student->id)
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
                    $className = $class->class_name ?? $class->name ?? 'Unnamed Class';
                    $teacherName = 'TBA';
                    if ($class->teacher) {
                        $teacherName = trim(($class->teacher->first_name ?? '') . ' ' . ($class->teacher->last_name ?? ''));
                    }

                    $scheduleTime = 'TBA';
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

                    return [
                        'time' => $scheduleTime,
                        'time_24h' => $scheduleTime24h,
                        'class_name' => $className,
                        'class_code' => $class->class_code ?? 'N/A',
                        'teacher_name' => $teacherName,
                        'location' => $class->room ?? 'TBA',
                    ];
                })->sortBy('time_24h')->values();

                return response()->json([
                    'success' => true,
                    'user' => [
                        'id' => $student->student_id,
                        'name' => $student->name,
                        'type' => 'student'
                    ],
                    'date' => $today->format('l, F j, Y'),
                    'total_classes' => $classes->count(),
                    'classes' => $classes
                ]);
            }

            // Check if teacher
            $teacher = \App\Models\Teacher::where('telegram_chat_id', $telegram_chat_id)->first();
            
            if ($teacher) {
                $today = Carbon::now();
                $todayName = strtolower($today->format('l'));

                // Get classes taught by teacher today
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
                    $className = $class->class_name ?? $class->name ?? 'Unnamed Class';
                    
                    $scheduleTime = 'TBA';
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

                    $studentCount = DB::table('class_student')
                        ->where('class_model_id', $class->id)
                        ->where('status', 'enrolled')
                        ->count();

                    return [
                        'time' => $scheduleTime,
                        'time_24h' => $scheduleTime24h,
                        'class_name' => $className,
                        'class_code' => $class->class_code ?? 'N/A',
                        'location' => $class->room ?? 'TBA',
                        'student_count' => $studentCount
                    ];
                })->sortBy('time_24h')->values();

                return response()->json([
                    'success' => true,
                    'user' => [
                        'id' => $teacher->teacher_id,
                        'name' => trim($teacher->first_name . ' ' . $teacher->last_name),
                        'type' => 'teacher'
                    ],
                    'date' => $today->format('l, F j, Y'),
                    'total_classes' => $classes->count(),
                    'classes' => $classes
                ]);
            }

            // User not found
            return response()->json([
                'success' => false,
                'message' => 'User not registered. Please send /start to register first.'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error getting schedule by chat ID', [
                'telegram_chat_id' => $request->telegram_chat_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
