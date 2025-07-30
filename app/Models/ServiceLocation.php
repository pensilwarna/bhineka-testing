<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceLocation extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';
    
    protected $fillable = [
        'customer_id', 'company_id', 'mikrotik_id', 'address', 'longitude', 'latitude', 'label',
        'package_id', 'sales_id', 'status', 'subscription_start', 'subscription_end',
        'mikrotik_profile', 'pppoe_username'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function mikrotik()
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function odpCustomers()
    {
        return $this->hasMany(OdpCustomer::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function installationRequests()
    {
        return $this->hasMany(InstallationRequest::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}