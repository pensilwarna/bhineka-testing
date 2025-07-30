<?php
// File: app/Models/AssetReceipt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetReceipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'purchase_order_number', 
        'receipt_date',
        'supplier_id',
        'received_by_user_id',
        'notes',
        'total_value'
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_value' => 'decimal:2'
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetReceiptItem::class);
    }

    // Helper methods
    public function getTotalItemsCount(): int
    {
        return $this->items()->sum('quantity_received');
    }

    public function hasTrackedItems(): bool
    {
        return $this->items()
            ->whereHas('asset', function($query) {
                $query->where('requires_qr_tracking', true);
            })
            ->exists();
    }
}