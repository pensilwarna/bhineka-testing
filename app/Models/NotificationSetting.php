<?php

// File: app/Models/NotificationSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = [
        'channel',
        'is_enabled',
        'provider',
        'config',
        'description'
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config' => 'array'
    ];

    /**
     * Check if specific channel is enabled
     */
    public static function isChannelEnabled(string $channel): bool
    {
        $setting = self::where('channel', $channel)->first();
        return $setting ? $setting->is_enabled : false;
    }

    /**
     * Get channel configuration
     */
    public static function getChannelConfig(string $channel): ?array
    {
        $setting = self::where('channel', $channel)->first();
        return $setting ? $setting->config : null;
    }
}