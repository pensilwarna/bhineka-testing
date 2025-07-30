<?php
// File: app/Models/AssetReceiptItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetReceiptItem extends Model
{
    protected $fillable = [
        'asset_receipt_id',
        'asset_id',
        'warehouse_id',
        'quantity_received',
        'actual_unit_price',
        'total_price',
        'notes'
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'actual_unit_price' => 'decimal:2',
        'total_price' => 'decimal:2'
    ];

    // Relationships
    public function assetReceipt(): BelongsTo
    {
        return $this->belongsTo(AssetReceipt::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function trackedAssets(): HasMany
    {
        return $this->hasMany(TrackedAsset::class);
    }

    // Helper methods
    public function isFullyTracked(): bool
    {
        if (!$this->asset->requires_qr_tracking) {
            return true; // Simple quantity items are always "fully tracked"
        }
        
        return $this->trackedAssets()->count() >= $this->quantity_received;
    }
}