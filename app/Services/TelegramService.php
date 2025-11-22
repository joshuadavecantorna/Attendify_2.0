<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $botToken;
    protected string $apiUrl;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiUrl = config('telegram.api_url') . '/bot' . $this->botToken;
    }

    /**
     * Send a text message to a Telegram chat
     */
    public function sendMessage(string $chatId, string $message, array $options = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ], $options);

        try {
            $response = Http::post($this->apiUrl . '/sendMessage', $params);

            if ($response->successful()) {
                Log::info('Telegram message sent', [
                    'chat_id' => $chatId,
                    'message' => $message,
                ]);
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('Telegram message failed', [
                'chat_id' => $chatId,
                'response' => $response->json(),
            ]);
            return ['success' => false, 'error' => $response->json()];

        } catch (\Exception $e) {
            Log::error('Telegram API exception', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Set webhook URL for receiving messages
     */
    public function setWebhook(string $url, ?string $secretToken = null): array
    {
        $params = [
            'url' => $url,
        ];

        if ($secretToken) {
            $params['secret_token'] = $secretToken;
        }

        try {
            $response = Http::post($this->apiUrl . '/setWebhook', $params);
            
            Log::info('Telegram webhook set', [
                'url' => $url,
                'response' => $response->json(),
            ]);

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to set Telegram webhook', [
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): array
    {
        try {
            $response = Http::get($this->apiUrl . '/getWebhookInfo');
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Failed to get webhook info', [
                'error' => $e->getMessage(),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(): array
    {
        try {
            $response = Http::post($this->apiUrl . '/deleteWebhook');
            return $response->json();
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send student class reminder
     */
    public function sendStudentReminder(string $chatId, array $data): array
    {
        $template = config('telegram.templates.student_reminder');
        
        $message = strtr($template, [
            '{name}' => $data['name'],
            '{class_name}' => $data['class_name'],
            '{start_time}' => $data['start_time'],
            '{minutes}' => $data['minutes'] ?? 30,
            '{room}' => $data['room'] ?? 'TBA',
            '{teacher_name}' => $data['teacher_name'] ?? 'TBA',
        ]);

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Send teacher class reminder
     */
    public function sendTeacherReminder(string $chatId, array $data): array
    {
        $template = config('telegram.templates.teacher_reminder');
        
        $message = strtr($template, [
            '{name}' => $data['name'],
            '{class_name}' => $data['class_name'],
            '{start_time}' => $data['start_time'],
            '{minutes}' => $data['minutes'] ?? 30,
            '{room}' => $data['room'] ?? 'TBA',
            '{enrolled_count}' => $data['enrolled_count'] ?? 0,
        ]);

        return $this->sendMessage($chatId, $message);
    }

    /**
     * Send welcome message
     */
    public function sendWelcome(string $chatId): array
    {
        $message = config('telegram.templates.welcome');
        return $this->sendMessage($chatId, $message);
    }

    /**
     * Send verification success message
     */
    public function sendVerificationSuccess(string $chatId): array
    {
        $message = config('telegram.templates.verification_success');
        return $this->sendMessage($chatId, $message);
    }

    /**
     * Send verification failed message
     */
    public function sendVerificationFailed(string $chatId): array
    {
        $message = config('telegram.templates.verification_failed');
        return $this->sendMessage($chatId, $message);
    }

    /**
     * Send account already linked message
     */
    public function sendAccountAlreadyLinked(string $chatId): array
    {
        $message = config('telegram.templates.account_already_linked');
        return $this->sendMessage($chatId, $message);
    }

    /**
     * Get bot info
     */
    public function getMe(): array
    {
        try {
            $response = Http::get($this->apiUrl . '/getMe');
            return $response->json();
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
