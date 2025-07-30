<?php
// File: app/Models/TechnicianAssetDebt.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianAssetDebt extends Model
{
    protected $fillable = [
        'technician_id', 'asset_id', 'warehouse_id', 'checkout_by_user_id',
        'quantity_taken', 'unit_price', 'total_debt_value',
        'quantity_returned', 'quantity_installed', 'current_debt_quantity', 'current_debt_value',
        'exceed_limit_approved_by', 'exceed_limit_approved_at', 'approval_reason',
        'checkout_date', 'notes', 'status'
    ];

    protected $casts = [
        'checkout_date' => 'date',
        'exceed_limit_approved_at' => 'datetime',
        'quantity_taken' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_debt_value' => 'decimal:2',
        'quantity_returned' => 'decimal:3',
        'quantity_installed' => 'decimal:3',
        'current_debt_quantity' => 'decimal:3',
        'current_debt_value' => 'decimal:2',
    ];

    // Relationships
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function checkoutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checkout_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exceed_limit_approved_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTechnician($query, $technicianId)
    {
        return $query->where('technician_id', $technicianId);
    }

    // Helper methods
    public function isFullySettled(): bool
    {
        return $this->current_debt_quantity <= 0;
    }

    public function getFormattedDebtValueAttribute(): string
    {
        return 'Rp ' . number_format($this->current_debt_value, 0, ',', '.');
    }
}