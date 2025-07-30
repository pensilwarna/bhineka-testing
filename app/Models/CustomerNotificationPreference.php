<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNotificationPreference extends Model
{
    protected $fillable = [
        'customer_id',
        'service_location_id',
        'notification_type',
        'channels',
        'is_active'
    ];

    protected $casts = [
        'channels' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Relationship to Customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship to Service Location
     */
    public function serviceLocation()
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    /**
     * Get customer preference for specific service location and notification type
     */
    public static function getCustomerPreference(int $customerId, int $serviceLocationId, string $notificationType)
    {
        return self::where('customer_id', $customerId)
                   ->where('service_location_id', $serviceLocationId)
                   ->where('notification_type', $notificationType)
                   ->where('is_active', true)
                   ->first();
    }

    /**
     * Enable notification for customer at specific service location
     */
    public static function enableNotification(int $customerId, int $serviceLocationId, string $notificationType, array $channels = ['whatsapp'])
    {
        return self::updateOrCreate(
            [
                'customer_id' => $customerId,
                'service_location_id' => $serviceLocationId,
                'notification_type' => $notificationType
            ],
            [
                'channels' => $channels,
                'is_active' => true
            ]
        );
    }
}