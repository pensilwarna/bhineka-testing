<?php

// File: app/Console/Commands/TestNotificationCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramService;
use App\Services\NotificationManager;
use App\Models\User;
use App\Models\Ticket;

class TestNotificationCommand extends Command
{
    protected $signature = 'notification:test {type=telegram}';
    protected $description = 'Test notification system';

    public function handle()
    {
        $type = $this->argument('type');

        switch ($type) {
            case 'telegram':
                $this->testTelegram();
                break;
                
            case 'fonnte':
                $this->testFonnte();
                break;
                
            case 'ticket':
                $this->testTicketNotification();
                break;
                
            default:
                $this->error('Unknown test type. Use: telegram, fonnte, ticket');
        }
    }

    private function testTelegram()
    {
        $this->info('Testing Telegram Service...');
        
        try {
            $telegramService = app(TelegramService::class);
        } catch (\Exception $e) {
            $this->error('Failed to create TelegramService: ' . $e->getMessage());
            $this->line('Make sure TelegramService.php exists in app/Services/');
            return;
        }
        
        // Test configuration
        if (!$telegramService->isConfigured()) {
            $this->error('Telegram not configured. Please set TELEGRAM_BOT_TOKEN in .env');
            $this->line('');
            $this->line('Steps to setup:');
            $this->line('1. Go to @BotFather on Telegram');
            $this->line('2. Send /newbot');
            $this->line('3. Follow instructions to create bot');
            $this->line('4. Copy token to TELEGRAM_BOT_TOKEN in .env');
            return;
        }
        
        // Test connection
        $this->line('Testing bot connection...');
        $connectionTest = $telegramService->testConnection();
        
        if ($connectionTest['success']) {
            $this->info('✅ Telegram connection test: SUCCESS');
            $this->line('Bot Name: ' . ($connectionTest['data']['bot_name'] ?? 'Unknown'));
            $this->line('Username: @' . ($connectionTest['data']['username'] ?? 'Unknown'));
        } else {
            $this->error('❌ Telegram connection test: FAILED');
            $this->error('Error: ' . $connectionTest['error']);
            return;
        }
        
        // Test message sending (optional)
        if ($this->confirm('Do you want to send a test message?')) {
            $chatId = $this->ask('Enter your Telegram chat ID (numbers only):');
            
            if (!$chatId || !is_numeric($chatId)) {
                $this->error('Invalid chat ID. Should be numbers only (e.g., 123456789)');
                $this->line('');
                $this->line('To get your chat ID:');
                $this->line('1. Send a message to your bot');
                $this->line('2. Visit: https://api.telegram.org/bot[YOUR_TOKEN]/getUpdates');
                $this->line('3. Look for "chat":{"id":123456789}');
                return;
            }
            
            $message = $this->ask('Enter test message:', '🤖 Test message from notification system!');
            
            $this->line('Sending message...');
            $result = $telegramService->sendMessage($chatId, $message);
            
            if ($result['success']) {
                $this->info('✅ Test message sent successfully!');
                $this->line('Message ID: ' . ($result['data']['result']['message_id'] ?? 'N/A'));
            } else {
                $this->error('❌ Test message failed: ' . $result['error']);
                $this->line('');
                $this->line('Common issues:');
                $this->line('- Wrong chat ID');
                $this->line('- Bot is blocked by user');
                $this->line('- User never started conversation with bot');
            }
        }
    }

    private function testFonnte()
    {
        $this->info('Testing Fonnte WhatsApp Service...');
        
        try {
            // Check if FontteWhatsAppService exists
            if (!class_exists('\App\Services\FontteWhatsAppService')) {
                $this->error('FontteWhatsAppService not found. This will be implemented later.');
                return;
            }
            
            $fontteService = app(\App\Services\FontteWhatsAppService::class);
        } catch (\Exception $e) {
            $this->error('Failed to create FontteWhatsAppService: ' . $e->getMessage());
            return;
        }
        
        // Test configuration
        if (!$fontteService->isConfigured()) {
            $this->error('Fonnte not configured. Please set FONNTE_API_KEY in .env');
            return;
        }
        
        // Test connection
        $connectionTest = $fontteService->testConnection();
        
        if ($connectionTest['success']) {
            $this->info('✅ Fonnte connection test: SUCCESS');
            $this->line('Device: ' . ($connectionTest['data']['device'] ?? 'Unknown'));
            $this->line('Quota: ' . ($connectionTest['data']['quota'] ?? 'Unknown'));
        } else {
            $this->error('❌ Fonnte connection test: FAILED');
            $this->error('Error: ' . $connectionTest['error']);
            return;
        }
        
        // Test message sending (optional)
        if ($this->confirm('Do you want to send a test message?')) {
            $phoneNumber = $this->ask('Enter phone number (format: 628123456789):');
            $message = $this->ask('Enter test message:', 'Test message from notification system');
            
            $result = $fontteService->sendMessage($phoneNumber, $message);
            
            if ($result['success']) {
                $this->info('✅ Test message sent successfully!');
                $this->line('Message ID: ' . ($result['data']['id'] ?? 'N/A'));
            } else {
                $this->error('❌ Test message failed: ' . $result['error']);
            }
        }
    }

    private function testTicketNotification()
    {
        $this->info('Testing Ticket Notification...');
        
        try {
            // Check if NotificationManager exists
            if (!class_exists('\App\Services\NotificationManager')) {
                $this->error('NotificationManager not found. Please create it first.');
                return;
            }
            
            $notificationManager = app(\App\Services\NotificationManager::class);
        } catch (\Exception $e) {
            $this->error('Failed to create NotificationManager: ' . $e->getMessage());
            return;
        }
        
        // Find a ticket with technicians
        $ticket = Ticket::with(['technicians', 'customer', 'serviceLocation'])
                        ->whereHas('technicians')
                        ->first();
        
        if (!$ticket) {
            $this->error('No ticket with assigned technicians found. Please create a ticket first.');
            return;
        }
        
        $this->info("Using ticket: {$ticket->id}");
        $this->info("Customer: {$ticket->customer->name}");
        $this->info("Technicians: " . $ticket->technicians->pluck('name')->join(', '));
        
        if (!$this->confirm('Send test notifications to these technicians?')) {
            return;
        }
        
        $results = $notificationManager->notifyTicketAssigned($ticket);
        
        foreach ($results as $recipient => $result) {
            if ($result['success']) {
                $this->info("✅ {$recipient}: SUCCESS");
                
                if (isset($result['channels'])) {
                    foreach ($result['channels'] as $channel => $channelResult) {
                        $status = $channelResult['success'] ? '✅' : '❌';
                        $this->line("  {$channel}: {$status}");
                        if (!$channelResult['success']) {
                            $this->line("    Error: {$channelResult['error']}");
                        }
                    }
                }
            } else {
                $this->error("❌ {$recipient}: FAILED - {$result['error']}");
            }
        }
    }

    /**
     * Test user's telegram setup
     */
    private function testUserTelegram()
    {
        $this->info('Testing User Telegram Setup...');
        
        // Find users with telegram_chat_id
        $users = \App\Models\User::whereNotNull('telegram_chat_id')
                               ->where('telegram_chat_id', '!=', '')
                               ->get();
        
        if ($users->isEmpty()) {
            $this->warn('No users with telegram_chat_id found.');
            $this->line('');
            $this->line('To set telegram_chat_id for a user:');
            $this->line('1. Get user\'s chat ID (send message to bot, check getUpdates)');
            $this->line('2. Run: UPDATE users SET telegram_chat_id = "123456789" WHERE id = 1;');
            return;
        }
        
        $this->table(
            ['ID', 'Name', 'Telegram Chat ID', 'Notifications Enabled'],
            $users->map(function ($user) {
                return [
                    $user->id,
                    $user->name,
                    $user->telegram_chat_id,
                    $user->notifications_enabled ? 'Yes' : 'No'
                ];
            })->toArray()
        );
        
        if ($this->confirm('Send test message to these users?')) {
            $telegramService = app(TelegramService::class);
            $message = '🧪 Test notification from system!';
            
            foreach ($users as $user) {
                $result = $telegramService->sendMessage($user->telegram_chat_id, $message);
                
                if ($result['success']) {
                    $this->info("✅ Sent to {$user->name}");
                } else {
                    $this->error("❌ Failed to send to {$user->name}: " . $result['error']);
                }
            }
        }
    }
}