<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPostgreSQLSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:fix-sequences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix PostgreSQL sequences for all tables to prevent duplicate key violations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing PostgreSQL sequences...');

        $tables = [
            'users',
            'teachers',
            'students',
            'class_models',
            'attendance_sessions',
            'attendance_records',
            'excuse_requests',
            'class_files',
            'class_student',
        ];

        foreach ($tables as $table) {
            try {
                $this->fixSequence($table);
                $this->info("✓ Fixed sequence for: $table");
            } catch (\Exception $e) {
                $this->error("✗ Failed to fix sequence for: $table - " . $e->getMessage());
            }
        }

        $this->info('');
        $this->info('All sequences have been fixed!');

        return 0;
    }

    /**
     * Fix the sequence for a specific table
     */
    private function fixSequence($tableName, $columnName = 'id')
    {
        // Get the sequence name
        $result = DB::select("SELECT pg_get_serial_sequence('$tableName', '$columnName') as sequence");
        
        if (empty($result) || !$result[0]->sequence) {
            $this->warn("  No sequence found for $tableName.$columnName");
            return;
        }

        $sequenceName = $result[0]->sequence;

        // Get the max ID from the table
        $maxId = DB::table($tableName)->max($columnName) ?? 0;

        // Set the sequence to max + 1
        DB::statement("SELECT setval('$sequenceName', $maxId + 1, false)");
        
        $this->line("  Sequence: $sequenceName, Max ID: $maxId");
    }
}
