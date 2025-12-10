<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix students table sequence
        DB::statement("SELECT setval(pg_get_serial_sequence('students', 'id'), COALESCE((SELECT MAX(id) FROM students), 1), true)");
        
        // Fix teachers table sequence
        DB::statement("SELECT setval(pg_get_serial_sequence('teachers', 'id'), COALESCE((SELECT MAX(id) FROM teachers), 1), true)");
        
        // Fix users table sequence (just in case)
        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), COALESCE((SELECT MAX(id) FROM users), 1), true)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this operation
    }
};
