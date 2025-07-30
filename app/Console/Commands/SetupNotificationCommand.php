<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\NotificationSetting;

class SetupNotificationCommand extends Command
{
    protected $signature = 'notification:setup';
    protected $description = 'Setup default notification preferences for existing users';

    public function handle()
    {
        $this->info('Setting up notification system...');
        
        // Setup default notification settings if not exists
        $this->setupDefaultSettings();
        
        // Setup user preferences
        $this->setupUserPreferences();
        
        $this->info('✅ Notification setup completed!');
    }

    private function setupDefaultSettings()
    {
        $this->info('Setting up default notification settings...');
        
        $settings = [
            [
                'channel' => 'whatsapp',
                'is_enabled' => true,
                'provider' => 'fonnte',
                'config' => [
                    'api_key' => config('services.fonnte.api_key'),
                    'base_url' => config('services.fonnte.base_url')
                ],
                'description' => 'WhatsApp notifications via Fonnte API'
            ],
            [
                'channel' => 'telegram',
                'is_enabled' => false,
                'provider' => 'telegram_bot',
                'config' => [
                    'bot_token' => config('services.telegram.bot_token'),
                    'base_url' => 'https://api.telegram.org'
                ],
                'description' => 'Telegram notifications via Bot API'
            ]
        ];

        foreach ($settings as $setting) {
            NotificationSetting::updateOrCreate(
                ['channel' => $setting['channel']],
                $setting
            );
        }
        
        $this->line('✅ Default settings created');
    }

    private function setupUserPreferences()
    {
        $this->info('Setting up user notification preferences...');
        
        $users = User::with('roles')->get();
        $createdCount = 0;
        
        foreach ($users as $user) {
            UserNotificationPreference::setDefaultPreferences($user);
            $createdCount++;
        }
        
        $this->line("✅ Preferences set for {$createdCount} users");
    }
}