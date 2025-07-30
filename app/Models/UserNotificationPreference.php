<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notification_type',
        'channels',
        'is_active'
    ];

    protected $casts = [
        'channels' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Relationship to User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get user preferences for specific notification type
     */
    public static function getUserPreference(int $userId, string $notificationType)
    {
        return self::where('user_id', $userId)
                   ->where('notification_type', $notificationType)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Set default preferences for new user based on role
     */
    public static function setDefaultPreferences(User $user)
    {
        $role = $user->roles->first();
        
        if (!$role) {
            return;
        }

        $defaultPreferences = [];

        switch ($role->name) {
            case 'NOC':
                $defaultPreferences = [
                    'ticket_assigned' => ['whatsapp'],
                    'system_maintenance' => ['whatsapp'],
                    'ticket_completed' => ['whatsapp']
                ];
                break;
                
            case 'Technician':
                $defaultPreferences = [
                    'ticket_assigned' => ['whatsapp'],
                    'system_maintenance' => ['whatsapp']
                ];
                break;
                
            case 'Owner':
            case 'Super-Admin':
                $defaultPreferences = [
                    'system_maintenance' => ['whatsapp'],
                    'daily_report' => ['whatsapp']
                ];
                break;
        }

        foreach ($defaultPreferences as $type => $channels) {
            self::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $type
                ],
                [
                    'channels' => $channels,
                    'is_active' => true
                ]
            );
        }
    }
}