<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    protected $fillable = [
        'name',
        'unit',
    ];

    // Relasi ke aset
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}