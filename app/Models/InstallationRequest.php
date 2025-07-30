<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationRequest extends Model
{
    protected $table = 'installation_requests';

    protected $fillable = [
        'customer_id',
        'service_location_id',
        'sales_id',
        'package_id',
        'proposed_installation_date',
        'notes',
        'status',
        'ticket_type',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'status' => 'string',
        'ticket_type' => 'string',
        'proposed_installation_date' => 'date',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const TICKET_TYPES = [
        'new',
        'repair',
        'reactivation',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceLocation()
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}