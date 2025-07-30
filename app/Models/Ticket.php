<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Hashids\Hashids;


class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string'; // UUID
    public $incrementing = false;

    protected $fillable = [
        'id',
        'kode',
        'customer_id',
        'service_location_id',
        'supervisor_id',
        'odp_id',
        'installation_request_id',
        'title',
        'description',
        'suggestion',
        'handling',
        'handling_time',
        'handling_photo',
        'resolution',
        'resolution_time',
        'resolution_photo',
        'status',
        'priority',
        'failure_reason',
        'ticket_type',
        'created_by',
        'checkin_longitude',
        'checkin_latitude',
        'checkin_time',
        'checkin_distance',
        'checkin_validated',
        'customer_digital_signature',
        'technician_digital_signature',
        'contract_pdf',
        'contract_number',
        'contract_signed_at',
        'assigned_at',
        'acknowledged_at',
        'started_at',
        'completed_at',
        'work_duration_minutes',
        'before_photos',
        'after_photos',
        'notification_sent_at',
        'notification_log',
        'work_started_at',
        'work_completed_at',
        'customer_latitude',
        'customer_longitude',
        'checkin_distance_meters',
        'pppoe_username',
        'pppoe_password',
        'mikrotik_profile',
        'credentials_generated'
    ];

    protected $casts = [
        'before_photos' => 'array',
        'after_photos' => 'array',
        'notification_log' => 'array',
        'checkin_validated' => 'boolean',
        'credentials_generated' => 'boolean',
        'assigned_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'work_started_at' => 'datetime',
        'work_completed_at' => 'datetime',
        'checkin_time' => 'datetime',
        'resolution_time' => 'datetime',
        'handling_time' => 'datetime',
        'notification_sent_at' => 'datetime',
        'contract_signed_at' => 'datetime',
    ];

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_WAITING_SUPERVISOR = 'waiting_supervisor';
    const STATUS_SOLVED = 'solved';
    const STATUS_CLOSED = 'closed';
    const STATUS_FAILED = 'failed';

    protected static function booted(): void
    {
        static::created(function (Ticket $ticket) {
            $ticket->refresh();
            $hashids = new Hashids(config('app.key'), 8, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890');
            $hashedId = $hashids->encode($ticket->ticket_sequence);

            $ticket->kode = $hashedId;
            $ticket->saveQuietly();
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceLocation()
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_technician', 'ticket_id', 'user_id')->withTimestamps();
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function odp()
    {
        return $this->belongsTo(Odp::class);
    }

    public function installationRequest()
    {
        return $this->belongsTo(InstallationRequest::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assetUsages()
    {
        return $this->hasMany(AssetUsage::class);
    }

    public function logs()
    {
        return $this->hasMany(TicketLog::class);
    }
}