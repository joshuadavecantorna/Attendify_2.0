<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class N8nApiController extends BaseController
{
    /**
     * Authenticate n8n requests
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $token = $request->bearerToken();
            
            if ($token !== config('app.n8n_api_token', env('N8N_API_TOKEN'))) {
                Log::warning('N8n API unauthorized access attempt', [
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            return $next($request);
        });
    }

    /**
     * Get upcoming classes for notifications (classes starting in 30-35 minutes)
     */
    public function upcomingClasses(Request $request)
    {
        $reminderMinutes = config('telegram.reminder_minutes', 30);
        $now = Carbon::now();
        
        // Calculate time window (30-35 minutes from now)
        $startWindow = $now->copy()->addMinutes($reminderMinutes);
        $endWindow = $now->copy()->addMinutes($reminderMinutes + 5);

        // Get current day of week (1 = Monday, 7 = Sunday)
        $dayOfWeek = $now->dayOfWeekIso;

        Log::info('N8n upcoming classes check', [
            'current_time' => $now->format('H:i:s'),
            'window_start' => $startWindow->format('H:i:s'),
            'window_end' => $endWindow->format('H:i:s'),
            'day_of_week' => $dayOfWeek,
        ]);

        // Get all classes (without filtering by day/time since columns don't exist)
        // TODO: Add proper day_of_week and start_time columns to classes table
        $classes = DB::table('class_models')->get();

        $students = [];
        $teachers = [];

        foreach ($classes as $class) {
            // Get teacher info
            $teacher = DB::table('teachers')
                ->join('users', 'teachers.user_id', '=', 'users.id')
                ->where('teachers.id', $class->teacher_id)
                ->whereNotNull('users.telegram_chat_id')
                ->where('users.notifications_enabled', true)
                ->select(
                    'users.id as user_id',
                    'users.name',
                    'users.telegram_chat_id',
                    'users.telegram_username',
                    'teachers.id as teacher_id'
                )
                ->first();

            // Get enrolled students count
            $enrolledCount = DB::table('class_student')
                ->where('class_model_id', $class->id)
                ->count();

            // Add teacher notification
            if ($teacher) {
                $teachers[] = [
                    'user_id' => $teacher->user_id,
                    'teacher_id' => $teacher->teacher_id,
                    'telegram_chat_id' => $teacher->telegram_chat_id,
                    'telegram_username' => $teacher->telegram_username,
                    'name' => $teacher->name,
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'room' => ($class->course ?? '') . ' ' . ($class->section ?? ''),
                    'enrolled_count' => $enrolledCount,
                    'minutes' => $reminderMinutes,
                ];
            }

            // Get enrolled students with Telegram enabled
            $enrolledStudents = DB::table('class_student')
                ->join('students', 'class_student.student_id', '=', 'students.id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('class_student.class_model_id', $class->id)
                ->whereNotNull('users.telegram_chat_id')
                ->where('users.notifications_enabled', true)
                ->select(
                    'users.id as user_id',
                    'users.name',
                    'users.telegram_chat_id',
                    'users.telegram_username',
                    'students.id as student_id'
                )
                ->get();

            // Add student notifications
            foreach ($enrolledStudents as $student) {
                $students[] = [
                    'user_id' => $student->user_id,
                    'student_id' => $student->student_id,
                    'telegram_chat_id' => $student->telegram_chat_id,
                    'telegram_username' => $student->telegram_username,
                    'name' => $student->name,
                    'class_id' => $class->id,
                    'class_name' => $class->name,
                    'room' => ($class->course ?? '') . ' ' . ($class->section ?? ''),
                    'teacher_name' => $teacher ? $teacher->name : 'TBA',
                    'minutes' => $reminderMinutes,
                ];
            }
        }

        $response = [
            'success' => true,
            'timestamp' => $now->toIso8601String(),
            'window' => [
                'start' => $startWindow->format('H:i:s'),
                'end' => $endWindow->format('H:i:s'),
            ],
            'students' => $students,
            'teachers' => $teachers,
            'summary' => [
                'total_classes' => count($classes),
                'total_students' => count($students),
                'total_teachers' => count($teachers),
            ],
        ];

        Log::info('N8n upcoming classes response', [
            'classes_found' => count($classes),
            'students_to_notify' => count($students),
            'teachers_to_notify' => count($teachers),
        ]);

        return response()->json($response);
    }

    /**
     * Get all classes schedule (for debugging)
     */
    public function allClasses(Request $request)
    {
        $classes = DB::table('classes')
            ->leftJoin('teachers', 'classes.teacher_id', '=', 'teachers.id')
            ->leftJoin('users', 'teachers.user_id', '=', 'users.id')
            ->select(
                'classes.*',
                'users.name as teacher_name'
            )
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'room' => $class->room ?? null,
                    'teacher_name' => $class->teacher_name,
                ];
            });

        return response()->json([
            'success' => true,
            'classes' => $classes,
            'total' => $classes->count(),
        ]);
    }

    /**
     * Get users with Telegram enabled
     */
    public function telegramUsers(Request $request)
    {
        $users = User::whereNotNull('telegram_chat_id')
            ->select('id', 'name', 'email', 'telegram_username', 'notifications_enabled')
            ->get();

        return response()->json([
            'success' => true,
            'users' => $users,
            'total' => $users->count(),
        ]);
    }

    /**
     * Health check endpoint
     */
    public function health(Request $request)
    {
        return response()->json([
            'success' => true,
            'status' => 'ok',
            'timestamp' => Carbon::now()->toIso8601String(),
        ]);
    }
}
