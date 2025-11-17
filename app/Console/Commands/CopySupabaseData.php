<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CopySupabaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:copy-from-supabase {--tables=* : Specific tables to copy}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy data from Supabase to local PostgreSQL database';

    /**
     * Supabase connection config
     */
    protected array $supabaseConfig = [
        'host' => 'aws-1-ap-southeast-1.pooler.supabase.com',
        'port' => '5432',
        'database' => 'postgres',
        'username' => 'postgres.pogqouxdsshsjaqwtwha',
        'password' => 'attendify-App-11-09-25',
    ];

    /**
     * Tables to copy in order (respecting foreign key constraints)
     */
    protected array $tablesToCopy = [
        'users',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'students',
        'teachers',
        'class_models',
        'class_student',
        'attendance_sessions',
        'attendance_records',
        'excuse_requests',
        'class_files',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Copying Data from Supabase to Local PostgreSQL ===');
        $this->newLine();

        // Configure temporary Supabase connection
        $this->configureSupabaseConnection();

        // Get tables to copy
        $tables = $this->option('tables') ?: $this->tablesToCopy;

        // Confirm before proceeding
        if (!$this->confirm('This will copy data from Supabase to your local database. Continue?', true)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->newLine();

        // Disable foreign key checks temporarily
        DB::connection('pgsql')->statement('SET session_replication_role = replica;');

        $totalCopied = 0;

        foreach ($tables as $table) {
            if (!$this->tableExists($table)) {
                $this->warn("Table '{$table}' does not exist locally. Skipping...");
                continue;
            }

            $this->info("Copying table: {$table}");

            try {
                $count = $this->copyTable($table);
                $totalCopied += $count;
                $this->line("  ✓ Copied {$count} rows");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: " . $e->getMessage());
            }
        }

        // Re-enable foreign key checks
        DB::connection('pgsql')->statement('SET session_replication_role = DEFAULT;');

        $this->newLine();
        $this->info("=== Copy Complete ===");
        $this->info("Total rows copied: {$totalCopied}");

        return 0;
    }

    /**
     * Configure temporary Supabase connection
     */
    protected function configureSupabaseConnection()
    {
        config([
            'database.connections.supabase' => [
                'driver' => 'pgsql',
                'host' => $this->supabaseConfig['host'],
                'port' => $this->supabaseConfig['port'],
                'database' => $this->supabaseConfig['database'],
                'username' => $this->supabaseConfig['username'],
                'password' => $this->supabaseConfig['password'],
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'require',
            ]
        ]);
    }

    /**
     * Check if table exists in local database
     */
    protected function tableExists(string $table): bool
    {
        return Schema::connection('pgsql')->hasTable($table);
    }

    /**
     * Copy all data from a table
     */
    protected function copyTable(string $table): int
    {
        // Get data from Supabase
        $data = DB::connection('supabase')->table($table)->get();

        if ($data->isEmpty()) {
            return 0;
        }

        // Clear existing data in local table
        DB::connection('pgsql')->table($table)->truncate();

        // Convert data to arrays and handle JSON fields
        $dataArray = $data->map(function ($row) {
            $array = (array) $row;
            
            // Convert any objects to JSON strings
            foreach ($array as $key => $value) {
                if (is_object($value) || is_array($value)) {
                    $array[$key] = json_encode($value);
                }
            }
            
            return $array;
        });

        // Insert data in chunks to avoid memory issues
        $chunks = $dataArray->chunk(500);
        $count = 0;

        foreach ($chunks as $chunk) {
            DB::connection('pgsql')->table($table)->insert($chunk->toArray());
            $count += $chunk->count();

            // Show progress for large tables
            if ($data->count() > 1000) {
                $this->line("  → {$count}/{$data->count()} rows...");
            }
        }

        // Reset sequences for auto-increment columns
        $this->resetSequences($table);

        return $count;
    }

    /**
     * Reset PostgreSQL sequences after copying data
     */
    protected function resetSequences(string $table)
    {
        try {
            // Get the primary key column
            $columns = DB::connection('pgsql')
                ->select("SELECT column_name FROM information_schema.columns 
                         WHERE table_name = ? AND column_default LIKE 'nextval%'", [$table]);

            foreach ($columns as $column) {
                $columnName = $column->column_name;
                $sequenceName = "{$table}_{$columnName}_seq";

                // Reset sequence to max value
                DB::connection('pgsql')->statement(
                    "SELECT setval('{$sequenceName}', COALESCE((SELECT MAX({$columnName}) FROM {$table}), 1), true)"
                );
            }
        } catch (\Exception $e) {
            // Ignore if sequence doesn't exist
        }
    }
}
