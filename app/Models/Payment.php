<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'service_location_id', 'package_id', 'amount', 'payment_date',
        'period_start', 'period_end', 'status', 'transaction_id'
    ];

    public function serviceLocation()
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}