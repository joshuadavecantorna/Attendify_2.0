<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\N8nApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/files/metrics', [FilesController::class, 'metrics'])->name('api.files.metrics');
    
    // Telegram user management routes
    Route::prefix('telegram')->group(function () {
        Route::post('/generate-code', [TelegramWebhookController::class, 'generateVerificationCode']);
        Route::post('/unlink', [TelegramWebhookController::class, 'unlinkAccount']);
        Route::post('/toggle-notifications', [TelegramWebhookController::class, 'toggleNotifications']);
        Route::get('/status', [TelegramWebhookController::class, 'getStatus']);
    });
});

// AI Chatbot routes (accessible to all, gets auth context when available)
Route::post('/chatbot/query', [ChatbotController::class, 'query'])->middleware('web')->name('api.chatbot.query');
Route::get('/chatbot/status', [ChatbotController::class, 'status'])->middleware('web')->name('api.chatbot.status');

// Telegram webhook (public, authenticated by secret token)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'webhook']);
Route::get('/telegram/test', [TelegramWebhookController::class, 'test']); // For debugging

// n8n API endpoints (authenticated by bearer token)
Route::prefix('n8n')->group(function () {
    Route::get('/upcoming-classes', [N8nApiController::class, 'upcomingClasses']);
    Route::get('/all-classes', [N8nApiController::class, 'allClasses']);
    Route::get('/telegram-users', [N8nApiController::class, 'telegramUsers']);
    Route::get('/health', [N8nApiController::class, 'health']);
});