<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Odp extends Model
{
    protected $fillable = [
        'name',
        'status',
        'longitude',
        'latitude',
        'meta_data',
    ];

    protected $casts = [
       'meta_data' => 'json', 
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    // Relasi ke ODP Customer
    public function odpCustomers(): HasMany
    {
        return $this->hasMany(OdpCustomer::class);
    }

    // Relasi ke tiket
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function getLatitudeAttribute($value)
    {
        return $value;
    }

    public function getLongitudeAttribute($value)
    {
        return $value;
    }
}