<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class TestOllama extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ollama:test {--query= : Test with a specific query}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Ollama AI integration';

    /**
     * Execute the console command.
     */
    public function handle(OllamaService $ollama)
    {
        $this->info('=== Testing Ollama Integration ===');
        $this->newLine();

        // Test 1: Check availability
        $this->info('1. Checking Ollama availability...');
        $available = $ollama->isAvailable();
        if ($available) {
            $this->info('   ✓ Ollama is running');
        } else {
            $this->error('   ✗ Ollama is not available');
            $this->error('   Please make sure Ollama is running!');
            return 1;
        }
        $this->newLine();

        // Test 2: List models
        $this->info('2. Available models:');
        $models = $ollama->listModels();
        if ($models && isset($models['models'])) {
            foreach ($models['models'] as $model) {
                $size = $model['size'] ?? 'unknown';
                $this->line("   - {$model['name']} ({$size})");
            }
        } else {
            $this->warn('   Could not retrieve models');
        }
        $this->newLine();

        // Test 3: Query extraction
        $testQuery = $this->option('query') ?? 'for student Joshua Dave Cantorna, how many absent he have this month';
        $this->info('3. Testing query extraction...');
        $this->line("   Query: \"{$testQuery}\"");
        $this->line('   Extracting intent... (this may take a few seconds)');
        
        $extracted = $ollama->extractAttendanceQuery($testQuery);
        if ($extracted) {
            $this->info('   ✓ Successfully extracted:');
            $this->line('   ' . json_encode($extracted, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->error('   ✗ Failed to extract query');
        }
        $this->newLine();

        // Test 4: Simple generation
        $this->info('4. Testing simple generation...');
        $response = $ollama->generate("Say 'Hello, Attendify!' in one short friendly sentence.");
        if ($response && isset($response['response'])) {
            $this->info('   Response: ' . $response['response']);
        } else {
            $this->error('   ✗ Failed to generate response');
        }
        $this->newLine();

        // Test 5: Response formatting
        $this->info('5. Testing response formatting...');
        $mockData = [
            'type' => 'count',
            'count' => 3,
            'status' => 'absent',
            'student' => 'Joshua Dave Cantorna',
            'period' => 'this_month'
        ];
        $formatted = $ollama->formatResponse($mockData, $testQuery);
        if ($formatted) {
            $this->info('   Formatted: ' . $formatted);
        } else {
            $this->error('   ✗ Failed to format response');
        }
        $this->newLine();

        $this->info('=== Tests Complete ===');
        return 0;
    }
}
