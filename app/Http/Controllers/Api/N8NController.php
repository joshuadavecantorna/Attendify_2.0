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
}
