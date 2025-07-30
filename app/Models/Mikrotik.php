<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mikrotik extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip_address',
        'port',
        'username',
        'password',
        'is_enabled',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function mikrotikData(): HasMany
    {
        return $this->hasMany(MikrotikDatum::class);
    }
}