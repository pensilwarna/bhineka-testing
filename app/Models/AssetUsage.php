<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetUsage extends Model
{
    protected $fillable = [
        'ticket_id',
        'asset_id',
        'user_id',
        'quantity_used',
        'usage_purpose',
        'used_at',
    ];

    protected $casts = [
        'quantity_used' => 'integer',
        'used_at' => 'datetime',
    ];

    // Relasi ke tiket
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // Relasi ke aset
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    // Relasi ke pengguna (teknisi)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}