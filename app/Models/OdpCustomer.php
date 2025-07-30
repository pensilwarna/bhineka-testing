<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdpCustomer extends Model
{
    protected $fillable = [
        'odp_id',
        'customer_id',
        'port',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    public function serviceLocation()
    {
        return $this->belongsTo(ServiceLocation::class);
    }
}