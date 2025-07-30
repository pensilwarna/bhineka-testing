<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'is_banned',
        'banned_at',
        'phone_number',
        'notifications_enabled',
        'telegram_chat_id',
        'whatsapp_number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'banned_at' => 'datetime',
    ];

    /**
     * Get the employee associated with the user.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the history records created by the user.
     */
    public function employeeHistoryChanges() // Tambahkan ini
    {
        return $this->hasMany(EmployeeHistory::class, 'changed_by_user_id');
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'sales_id');
    }

    public function assignedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_technician', 'user_id', 'ticket_id')->withTimestamps();
    }

    public function supervisedTickets()
    {
        return $this->hasMany(Ticket::class, 'supervisor_id');
    }

    public function installationRequests()
    {
        return $this->hasMany(InstallationRequest::class, 'sales_id');
    }

    public function createdTickets()
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function approvedInstallationRequests()
    {
        return $this->hasMany(InstallationRequest::class, 'approved_by');
    }

    /**
     * Relationship to notification preferences
     */
    public function notificationPreferences()
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    /**
     * Relationship to notification logs
     */
    public function notificationLogs()
    {
        return $this->morphMany(NotificationLog::class, 'notifiable');
    }

    /**
     * Check if user wants notification for specific type
     */
    public function wantsNotification(string $notificationType): bool
    {
        if (!$this->notifications_enabled) {
            return false;
        }

        $preference = $this->notificationPreferences()
                        ->where('notification_type', $notificationType)
                        ->where('is_active', true)
                        ->first();

        return $preference !== null;
    }

    /**
     * Get notification channels for specific type
     */
    public function getNotificationChannels(string $notificationType): array
    {
        $preference = $this->notificationPreferences()
                        ->where('notification_type', $notificationType)
                        ->where('is_active', true)
                        ->first();

        return $preference ? $preference->channels : [];
    }
}
