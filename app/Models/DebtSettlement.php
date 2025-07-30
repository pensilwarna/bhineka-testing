<?php
// File: app/Models/DebtSettlement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DebtSettlement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'technician_id',
        'settlement_period',
        'settlement_type',
        'total_debt_amount',
        'salary_deduction',
        'cash_payment',
        'remaining_debt',
        'settlement_date',
        'processed_by_user_id',
        'notes',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_debt_amount' => 'decimal:2',
        'salary_deduction' => 'decimal:2',
        'cash_payment' => 'decimal:2',
        'remaining_debt' => 'decimal:2',
        'settlement_date' => 'date',
    ];

    /**
     * Get the technician who made the settlement.
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get the user who processed the settlement.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    /**
     * Get the settlement items for the debt settlement.
     */
    public function items(): HasMany
    {
        return $this->hasMany(DebtSettlementItem::class, 'settlement_id');
    }
}