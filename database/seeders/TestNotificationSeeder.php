<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TestNotificationSeeder extends Seeder
{
    public function run()
    {
        // Create test users/students
        $studentId = DB::table('users')->insertGetId([
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'telegram_chat_id' => '123456789', // Replace with your actual Telegram chat ID
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test teacher
        $teacherId = DB::table('users')->insertGetId([
            'name' => 'Test Teacher',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'telegram_chat_id' => '987654321', // Replace with your actual Telegram chat ID
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create test class (50 minutes from now)
        $classStartTime = Carbon::now()->addMinutes(50);
        
        $classId = DB::table('classes')->insertGetId([
            'class_name' => 'Mathematics 101',
            'room' => 'Room 301',
            'teacher_id' => $teacherId,
            'class_time' => $classStartTime,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Enroll student in class
        DB::table('enrollments')->insert([
            'user_id' => $studentId,
            'class_id' => $classId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create another class (20 minutes from now)
        $classStartTime2 = Carbon::now()->addMinutes(20);
        
        $classId2 = DB::table('classes')->insertGetId([
            'class_name' => 'Physics 201',
            'room' => 'Lab 102',
            'teacher_id' => $teacherId,
            'class_time' => $classStartTime2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Enroll student in second class
        DB::table('enrollments')->insert([
            'user_id' => $studentId,
            'class_id' => $classId2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Test data created successfully!');
        $this->command->info('Student ID: ' . $studentId);
        $this->command->info('Teacher ID: ' . $teacherId);
        $this->command->info('Classes created with times: ' . $classStartTime . ' and ' . $classStartTime2);
    }
}
