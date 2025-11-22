<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Handle incoming Telegram webhook
     */
    public function webhook(Request $request)
    {
        // Verify webhook secret
        $secretToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($secretToken !== config('telegram.webhook_secret')) {
            Log::warning('Telegram webhook unauthorized', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $update = $request->all();
        Log::info('Telegram webhook received', $update);

        // Handle message
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle incoming message
     */
    protected function handleMessage(array $message)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? null;

        // Handle /start command
        if ($text === '/start') {
            $this->handleStart($chatId);
            return;
        }

        // Check if user is linked
        $user = User::where('telegram_chat_id', $chatId)->first();

        if ($user) {
            // User is linked, handle as a query
            $this->handleQuery($chatId, $text, $user);
        } else {
            // User not linked, try verification code
            $this->handleVerificationCode($chatId, $text, $username);
        }
    }

    /**
     * Handle user query using chatbot
     */
    protected function handleQuery(string $chatId, string $query, User $user)
    {
        Log::info('Telegram query received', [
            'user_id' => $user->id,
            'query' => $query,
        ]);

        try {
            // Use the existing ChatbotController logic
            $chatbotController = app(\App\Http\Controllers\ChatbotController::class);
            
            // Create a fake request with user context
            $request = \Illuminate\Http\Request::create('/api/chatbot/query', 'POST', [
                'message' => $query,
            ]);
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            // Get response from chatbot
            $response = $chatbotController->query($request);
            $responseData = $response->getData();

            if (isset($responseData->response)) {
                // Send the chatbot response back to Telegram
                $this->telegram->sendMessage($chatId, $responseData->response);
            } else {
                $this->telegram->sendMessage($chatId, "Sorry, I couldn't process your request. Please try again.");
            }

        } catch (\Exception $e) {
            Log::error('Telegram query failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            $this->telegram->sendMessage(
                $chatId,
                "❌ Sorry, something went wrong while processing your request. Please try again later."
            );
        }
    }

    /**
     * Handle /start command
     */
    protected function handleStart(string $chatId)
    {
        // Check if already linked
        $user = User::where('telegram_chat_id', $chatId)->first();
        
        if ($user) {
            $this->telegram->sendAccountAlreadyLinked($chatId);
        } else {
            $this->telegram->sendWelcome($chatId);
        }
    }

    /**
     * Handle verification code
     */
    protected function handleVerificationCode(string $chatId, string $code, ?string $username)
    {
        // Clean up the code
        $code = strtoupper(trim($code));

        // Find user with this verification code
        $user = User::where('verification_code', $code)->first();

        if (!$user) {
            $this->telegram->sendVerificationFailed($chatId);
            return;
        }

        // Check if already linked to another account
        $existingUser = User::where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->first();

        if ($existingUser) {
            $this->telegram->sendMessage(
                $chatId,
                "❌ This Telegram account is already linked to another Attendify account.\n\nPlease use a different Telegram account or unlink the existing one first."
            );
            return;
        }

        // Link the account
        $user->telegram_chat_id = $chatId;
        $user->telegram_username = $username;
        $user->notifications_enabled = true;
        $user->verification_code = null; // Clear verification code after use
        $user->save();

        Log::info('Telegram account linked', [
            'user_id' => $user->id,
            'chat_id' => $chatId,
            'username' => $username,
        ]);

        $this->telegram->sendVerificationSuccess($chatId);
    }

    /**
     * Generate and store verification code for user
     */
    public function generateVerificationCode(Request $request)
    {
        $user = $request->user();

        // Check if already linked
        if ($user->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'Telegram account already linked',
                'linked' => true,
            ]);
        }

        // Generate unique verification code
        do {
            $code = 'ATT-' . $user->id . '-' . strtoupper(Str::random(5));
        } while (User::where('verification_code', $code)->exists());

        $user->verification_code = $code;
        $user->save();

        Log::info('Verification code generated', [
            'user_id' => $user->id,
            'code' => $code,
        ]);

        return response()->json([
            'success' => true,
            'verification_code' => $code,
            'bot_username' => $this->getBotUsername(),
            'bot_link' => $this->getBotLink(),
        ]);
    }

    /**
     * Unlink Telegram account
     */
    public function unlinkAccount(Request $request)
    {
        $user = $request->user();

        if (!$user->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram account linked',
            ]);
        }

        // Send notification before unlinking
        $this->telegram->sendMessage(
            $user->telegram_chat_id,
            "🔗 Your Telegram account has been unlinked from Attendify.\n\nYou will no longer receive class reminders."
        );

        $user->telegram_chat_id = null;
        $user->telegram_username = null;
        $user->notifications_enabled = false;
        $user->save();

        Log::info('Telegram account unlinked', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Telegram account unlinked successfully',
        ]);
    }

    /**
     * Toggle notifications
     */
    public function toggleNotifications(Request $request)
    {
        $user = $request->user();

        if (!$user->telegram_chat_id) {
            return response()->json([
                'success' => false,
                'message' => 'No Telegram account linked',
            ]);
        }

        $user->notifications_enabled = !$user->notifications_enabled;
        $user->save();

        $status = $user->notifications_enabled ? 'enabled' : 'disabled';
        $emoji = $user->notifications_enabled ? '🔔' : '🔕';

        // Send notification
        $this->telegram->sendMessage(
            $user->telegram_chat_id,
            "{$emoji} Notifications have been *{$status}*."
        );

        Log::info('Notifications toggled', [
            'user_id' => $user->id,
            'enabled' => $user->notifications_enabled,
        ]);

        return response()->json([
            'success' => true,
            'notifications_enabled' => $user->notifications_enabled,
        ]);
    }

    /**
     * Get Telegram connection status
     */
    public function getStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'linked' => (bool) $user->telegram_chat_id,
            'telegram_username' => $user->telegram_username,
            'notifications_enabled' => $user->notifications_enabled,
            'verification_code' => $user->verification_code,
        ]);
    }

    /**
     * Get bot username
     */
    protected function getBotUsername(): string
    {
        $botInfo = $this->telegram->getMe();
        return $botInfo['result']['username'] ?? 'unknown';
    }

    /**
     * Get bot link
     */
    protected function getBotLink(): string
    {
        $username = $this->getBotUsername();
        return "https://t.me/{$username}";
    }

    /**
     * Test webhook (for debugging)
     */
    public function test()
    {
        $info = $this->telegram->getWebhookInfo();
        $botInfo = $this->telegram->getMe();

        return response()->json([
            'bot' => $botInfo,
            'webhook' => $info,
        ]);
    }
}
