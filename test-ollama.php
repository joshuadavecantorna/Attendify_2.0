<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

use App\Services\OllamaService;

echo "=== Testing Ollama Integration ===\n\n";

// Test 1: Check if Ollama is available
echo "1. Checking Ollama availability...\n";
$ollama = new OllamaService();
$available = $ollama->isAvailable();
echo $available ? "✓ Ollama is running\n" : "✗ Ollama is not available\n";
echo "\n";

if (!$available) {
    echo "Please start Ollama first!\n";
    exit(1);
}

// Test 2: List available models
echo "2. Available models:\n";
$models = $ollama->listModels();
if ($models && isset($models['models'])) {
    foreach ($models['models'] as $model) {
        echo "   - {$model['name']} ({$model['size']})\n";
    }
} else {
    echo "   Could not retrieve models\n";
}
echo "\n";

// Test 3: Extract attendance query
echo "3. Testing query extraction...\n";
$testQuery = "for student Joshua Dave Cantorna, how many absent he have this month";
echo "   Query: \"{$testQuery}\"\n";
echo "   Extracting intent...\n";

$extracted = $ollama->extractAttendanceQuery($testQuery);
if ($extracted) {
    echo "   ✓ Successfully extracted:\n";
    echo "   " . json_encode($extracted, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "   ✗ Failed to extract query\n";
}
echo "\n";

// Test 4: Simple generation test
echo "4. Testing simple generation...\n";
$response = $ollama->generate("Say 'Hello, Attendify!' in a friendly way.");
if ($response && isset($response['response'])) {
    echo "   Response: " . $response['response'] . "\n";
} else {
    echo "   ✗ Failed to generate response\n";
}
echo "\n";

// Test 5: Format response test
echo "5. Testing response formatting...\n";
$mockData = [
    'type' => 'count',
    'count' => 3,
    'status' => 'absent',
    'student' => 'Joshua Dave Cantorna',
    'period' => 'this_month'
];
$formatted = $ollama->formatResponse($mockData, $testQuery);
if ($formatted) {
    echo "   Formatted: {$formatted}\n";
} else {
    echo "   ✗ Failed to format response\n";
}
echo "\n";

echo "=== Tests Complete ===\n";
