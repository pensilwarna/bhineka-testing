<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'service_location_id',
        'notification_type',
        'channel',
        'status',
        'message_content',
        'api_response',
        'external_id',
        'sent_at',
        'error_message'
    ];

    protected $casts = [
        'api_response' => 'array',
        'sent_at' => 'datetime'
    ];

    /**
     * Polymorphic relationship to notifiable (User or Customer)
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Relationship to Service Location
     */
    public function serviceLocation()
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    /**
     * Scope for successful notifications
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get delivery statistics
     */
    public static function getDeliveryStats(string $period = 'today')
    {
        $query = self::query();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month);
                break;
        }

        return [
            'total' => $query->count(),
            'sent' => $query->where('status', 'sent')->count(),
            'failed' => $query->where('status', 'failed')->count(),
            'pending' => $query->where('status', 'pending')->count()
        ];
    }
}