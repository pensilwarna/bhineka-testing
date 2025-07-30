<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetWriteOff extends Model
{
    protected $fillable = [
        'asset_id',
        'quantity',
        'reason',
        'write_off_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'write_off_date' => 'datetime',
    ];

    // Relasi ke aset
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}