<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FilesController;
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
});

// AI Chatbot routes (accessible to all authenticated users)
Route::post('/chatbot/query', [ChatbotController::class, 'query'])->name('api.chatbot.query');
Route::get('/chatbot/status', [ChatbotController::class, 'status'])->name('api.chatbot.status');