<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    protected $fillable = ['company_id', 'name', 'email', 'phone', 'identity_number', 'address', 'status', 'meta_data'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceLocations()
    {
        return $this->hasMany(ServiceLocation::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function installationRequests()
    {
        return $this->hasMany(InstallationRequest::class);
    }

    
    /**
     * Relationship to notification preferences
     */
    public function notificationPreferences()
    {
        return $this->hasMany(CustomerNotificationPreference::class);
    }

    /**
     * Relationship to notification logs
     */
    public function notificationLogs()
    {
        return $this->morphMany(NotificationLog::class, 'notifiable');
    }

    /**
     * Check if customer wants notification for specific service location and type
     */
    public function wantsNotification(int $serviceLocationId, string $notificationType): bool
    {
        if (!$this->notifications_enabled) {
            return false;
        }

        $preference = $this->notificationPreferences()
                        ->where('service_location_id', $serviceLocationId)
                        ->where('notification_type', $notificationType)
                        ->where('is_active', true)
                        ->first();

        return $preference !== null;
    }

    /**
     * Enable notifications for service location
     */
    public function enableNotifications(int $serviceLocationId, array $notificationTypes = ['technician_assigned', 'payment_reminder'], array $channels = ['whatsapp'])
    {
        foreach ($notificationTypes as $type) {
            CustomerNotificationPreference::enableNotification(
                $this->id,
                $serviceLocationId,
                $type,
                $channels
            );
        }
    }
    
}