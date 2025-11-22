<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AddTestData extends Command
{
    protected $signature = 'test:add-data';
    protected $description = 'Add test data for n8n workflow testing';

    public function handle()
    {
        $this->info('Adding test data...');

        // Get or create student with proper student_id
        $student = DB::table('students')->where('student_id', 'STU-001')->first();
        if (!$student) {
            $studentId = DB::table('students')->insertGetId([
                'student_id' => 'STU-001',
                'name' => 'Test Student',
                'email' => 'teststudent@attendify.test',
                'year' => '1',
                'course' => 'Computer Science',
                'section' => 'A',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("Created student (ID: $studentId)");
        } else {
            $studentId = $student->id;
            $this->info("Using existing student (ID: $studentId)");
        }

        // Update student's user record if exists
        if (isset($student->user_id)) {
            DB::table('users')->where('id', $student->user_id)->update([
                'telegram_chat_id' => '123456789', // Your actual Telegram chat ID
                'notifications_enabled' => true,
            ]);
        }

        // Get or create teacher with proper structure
        $teacher = DB::table('teachers')->where('user_id', 27)->first();
        if (!$teacher) {
            $teacherId = DB::table('teachers')->insertGetId([
                'user_id' => 27, // Existing user ID from earlier
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("Created teacher (ID: $teacherId)");
        } else {
            $teacherId = $teacher->id;
            $this->info("Using existing teacher (ID: $teacherId)");
        }

        // Create test class
        $classId = DB::table('classes')->insertGetId([
            'name' => 'Mathematics 101',
            'room' => 'Room 301',
            'teacher_id' => $teacherId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Created class (ID: $classId)");

        // Enroll student in class
        DB::table('student_class')->insert([
            'student_id' => $studentId,
            'class_id' => $classId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Enrolled student in class");

        // Create another class
        $classId2 = DB::table('classes')->insertGetId([
            'name' => 'Physics 201',
            'room' => 'Lab 102',
            'teacher_id' => $teacherId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Created second class (ID: $classId2)");

        // Enroll student in second class
        DB::table('student_class')->insert([
            'student_id' => $studentId,
            'class_id' => $classId2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->info("Enrolled student in second class");

        $this->info('');
        $this->info('Test data created successfully!');
        $this->warn('IMPORTANT: Replace the telegram_chat_id values (123456789 and 987654321) with your actual Telegram chat IDs');
        $this->info('Now test the API: curl -H "Authorization: Bearer n8n_secure_token_attendify_2025" http://127.0.0.1:8080/api/n8n/upcoming-classes');
    }
}
