<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddTestClasses extends Command
{
    protected $signature = 'test:add-classes';
    protected $description = 'Add test classes for n8n workflow testing';

    public function handle()
    {
        $this->info('Adding test classes...');

        // Get first student
        $student = DB::table('students')->first();
        if (!$student) {
            $this->error('No students found. Please add at least one student first.');
            return;
        }
        $this->info("Using student: {$student->name} (ID: {$student->id})");

        // Check if student has Telegram enabled
        if ($student->user_id) {
            $studentUser = DB::table('users')->where('id', $student->user_id)->first();
            if ($studentUser && $studentUser->telegram_chat_id) {
                DB::table('users')->where('id', $student->user_id)->update(['notifications_enabled' => true]);
                $this->info("Student Telegram: {$studentUser->telegram_chat_id}");
            } else {
                $this->warn("Student has no Telegram chat ID set");
            }
        }

        // Get first teacher
        $teacher = DB::table('teachers')->first();
        if (!$teacher) {
            $this->error('No teachers found. Please add at least one teacher first.');
            return;
        }
        $this->info("Using teacher: {$teacher->first_name} {$teacher->last_name} (ID: {$teacher->id})");

        // Check if teacher has Telegram enabled
        if ($teacher->user_id) {
            $teacherUser = DB::table('users')->where('id', $teacher->user_id)->first();
            if ($teacherUser && $teacherUser->telegram_chat_id) {
                DB::table('users')->where('id', $teacher->user_id)->update(['notifications_enabled' => true]);
                $this->info("Teacher Telegram: {$teacherUser->telegram_chat_id}");
            } else {
                $this->warn("Teacher has no Telegram chat ID set");
            }
        }

        // Create test class 1
        $class1 = DB::table('class_models')->insertGetId([
            'class_code' => 'MATH-101',
            'name' => 'Mathematics 101',
            'course' => 'BSCS',
            'section' => 'A',
            'year' => '3rd Year',
            'teacher_id' => $teacher->user_id,
            'schedule_time' => '10:00:00',
            'schedule_days' => json_encode(['monday', 'wednesday', 'friday']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Created class: Mathematics 101 (ID: $class1)");

        // Create test class 2
        $class2 = DB::table('class_models')->insertGetId([
            'class_code' => 'PHYS-201',
            'name' => 'Physics 201',
            'course' => 'BSCS',
            'section' => 'B',
            'year' => '2nd Year',
            'teacher_id' => $teacher->user_id,
            'schedule_time' => '14:00:00',
            'schedule_days' => json_encode(['tuesday', 'thursday']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Created class: Physics 201 (ID: $class2)");

        // Enroll student in both classes
        DB::table('class_student')->insert([
            'student_id' => $student->id,
            'class_model_id' => $class1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Enrolled {$student->name} in Mathematics 101");

        DB::table('class_student')->insert([
            'student_id' => $student->id,
            'class_model_id' => $class2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Enrolled {$student->name} in Physics 201");

        $this->info('');
        $this->info('✅ Test classes created successfully!');
        $this->warn('⚠️  IMPORTANT: Replace the telegram_chat_id values (123456789 and 987654321) with your actual Telegram chat IDs!');
        $this->info('To get your Telegram chat ID, message your bot and check: https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates');
        $this->info('');
        $this->info('Now test the API:');
        $this->line('curl -H "Authorization: Bearer n8n_secure_token_attendify_2025" http://127.0.0.1:8080/api/n8n/upcoming-classes');
    }
}
