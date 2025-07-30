<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'name',
        'title',
        'content',
        'channel',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get active template by name
     */
    public static function getTemplate(string $name): ?self
    {
        return self::where('name', $name)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Get templates by channel
     */
    public static function getByChannel(string $channel)
    {
        return self::where('channel', $channel)
                   ->orWhere('channel', 'all')
                   ->where('is_active', true)
                   ->get();
    }
}
