<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:setup {action=info : info|webhook|delete}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup and test Telegram bot integration';

    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        parent::__construct();
        $this->telegram = $telegram;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        match ($action) {
            'info' => $this->showInfo(),
            'webhook' => $this->setWebhook(),
            'delete' => $this->deleteWebhook(),
            default => $this->error('Invalid action. Use: info, webhook, or delete'),
        };
    }

    protected function showInfo()
    {
        $this->info('🤖 Telegram Bot Information');
        $this->newLine();

        // Get bot info
        $botInfo = $this->telegram->getMe();
        
        if ($botInfo['ok'] ?? false) {
            $bot = $botInfo['result'];
            $this->info("✅ Bot Name: {$bot['first_name']}");
            $this->info("✅ Bot Username: @{$bot['username']}");
            $this->info("✅ Bot ID: {$bot['id']}");
            $this->info("✅ Bot Link: https://t.me/{$bot['username']}");
        } else {
            $this->error('❌ Failed to get bot info');
            $this->error('Error: ' . ($botInfo['error'] ?? 'Unknown error'));
            return 1;
        }

        $this->newLine();

        // Get webhook info
        $webhookInfo = $this->telegram->getWebhookInfo();
        
        if ($webhookInfo['ok'] ?? false) {
            $webhook = $webhookInfo['result'];
            
            if ($webhook['url']) {
                $this->info('📡 Webhook Status:');
                $this->info("   URL: {$webhook['url']}");
                $this->info("   Pending Updates: {$webhook['pending_update_count']}");
                
                if (isset($webhook['last_error_message'])) {
                    $this->warn("   Last Error: {$webhook['last_error_message']}");
                    $this->warn("   Last Error Date: " . date('Y-m-d H:i:s', $webhook['last_error_date']));
                }
            } else {
                $this->warn('⚠️  No webhook configured');
                $this->info('   Run: php artisan telegram:setup webhook');
            }
        }

        $this->newLine();
        $this->info('💡 Configuration:');
        $this->info('   Bot Token: ' . substr(config('telegram.bot_token'), 0, 10) . '...');
        $this->info('   Webhook Secret: ' . (config('telegram.webhook_secret') ? '✅ Set' : '❌ Not set'));

        return 0;
    }

    protected function setWebhook()
    {
        $this->info('🔗 Setting up webhook...');
        $this->newLine();

        $url = config('app.url') . '/api/telegram/webhook';
        $secret = config('telegram.webhook_secret');

        $this->info("Webhook URL: {$url}");
        
        if (!$this->confirm('Continue with this URL?', true)) {
            $this->warn('Cancelled.');
            return 1;
        }

        $result = $this->telegram->setWebhook($url, $secret);

        if ($result['ok'] ?? false) {
            $this->info('✅ Webhook set successfully!');
            $this->info($result['description'] ?? 'Webhook is active');
            
            $this->newLine();
            $this->info('📝 Next steps:');
            $this->info('1. Open your bot: https://t.me/' . $this->getBotUsername());
            $this->info('2. Send /start to test');
            $this->info('3. Check webhook status: php artisan telegram:setup info');
        } else {
            $this->error('❌ Failed to set webhook');
            $this->error('Error: ' . ($result['description'] ?? $result['error'] ?? 'Unknown error'));
            return 1;
        }

        return 0;
    }

    protected function deleteWebhook()
    {
        if (!$this->confirm('Are you sure you want to delete the webhook?')) {
            $this->warn('Cancelled.');
            return 1;
        }

        $this->info('🗑️  Deleting webhook...');
        
        $result = $this->telegram->deleteWebhook();

        if ($result['ok'] ?? false) {
            $this->info('✅ Webhook deleted successfully!');
        } else {
            $this->error('❌ Failed to delete webhook');
            $this->error('Error: ' . ($result['description'] ?? 'Unknown error'));
            return 1;
        }

        return 0;
    }

    protected function getBotUsername(): string
    {
        $botInfo = $this->telegram->getMe();
        return $botInfo['result']['username'] ?? 'unknown';
    }
}
