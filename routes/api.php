<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\Api\N8NController;

// Auth user endpoint
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/files/metrics', [FilesController::class, 'metrics'])->name('api.files.metrics');

    // Telegram user management
    Route::prefix('telegram')->group(function () {
        Route::post('/generate-code', [TelegramWebhookController::class, 'generateVerificationCode']);
        Route::post('/unlink', [TelegramWebhookController::class, 'unlinkAccount']);
        Route::post('/toggle-notifications', [TelegramWebhookController::class, 'toggleNotifications']);
        Route::get('/status', [TelegramWebhookController::class, 'getStatus']);
    });
});


// Telegram webhook (public, authenticated by secret token)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'webhook']);
Route::get('/telegram/test', [TelegramWebhookController::class, 'test']);

// N8N Integration Routes
Route::prefix('n8n')->group(function () {
    
    // Health check
    Route::get('/health', [N8NController::class, 'healthCheck']);
    
    // Get all students with today's schedule (for 6am automation)
    Route::get('/schedules/today', [N8NController::class, 'getAllTodaySchedules']);
    
    // Get specific student's today schedule
    Route::get('/students/{studentId}/schedule/today', [N8NController::class, 'getStudentTodaySchedule']);
    
    // Update telegram chat ID
    Route::post('/students/telegram/update', [N8NController::class, 'updateTelegramChatId']);
});

// AI Chatbot Routes
Route::middleware(['web'])->group(function () {
    Route::post('/chatbot/query', [ChatbotController::class, 'queryRequest'])
        ->name('api.chatbot.query');
    
    Route::post('/chatbot/stream', [ChatbotController::class, 'streamChat'])
        ->name('api.chatbot.stream');
    
    Route::get('/chatbot/status', [ChatbotController::class, 'status'])
        ->name('api.chatbot.status');
});