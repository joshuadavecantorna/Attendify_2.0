<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('csv');
        
        // Import in correct order (respecting foreign keys)
        $tables = [
            'users',
            'teachers',
            'students',
            'class_models',
            'classes',
            'class_student',
            'class_files',
            'attendance_sessions',
            'attendance_records',
            'excuse_requests',
            'sessions',
            'notification_logs',
            'migrations',
        ];
        
        foreach ($tables as $table) {
            $this->importCSV("$csvPath/$table.csv", $table);
        }
        
        $this->command->info('? All data imported successfully!');
    }
    
    private function importCSV($filePath, $tableName)
    {
        if (!File::exists($filePath)) {
            $this->command->warn("??  Skipping $tableName (file not found)");
            return;
        }
        
        $this->command->info("?? Importing $tableName...");
        
        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file); // Read header row
        
        $count = 0;
        while (($row = fgetcsv($file)) !== false) {
            try {
                $data = array_combine($headers, $row);
                
                // Convert empty strings to NULL
                $data = array_map(function($value) {
                    if ($value === '' || $value === 'NULL') {
                        return null;
                    }
                    return $value;
                }, $data);
                
                DB::table($tableName)->insert($data);
                $count++;
            } catch (\Exception $e) {
                $this->command->error("? Error in $tableName row $count: " . $e->getMessage());
            }
        }
        
        fclose($file);
        $this->command->info("? Imported $count rows into $tableName");
    }
}
