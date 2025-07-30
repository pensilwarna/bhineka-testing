<?php
// File: app/Models/AssetCheckout.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetCheckout extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'technician_id',
        'warehouse_staff_id',
        'warehouse_id',
        'checkout_date',
        'total_items',
        'total_value',
        'exceed_limit',
        'approved_by',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'checkout_date' => 'date',
        'total_value' => 'decimal:2',
        'exceed_limit' => 'boolean',
    ];

    /**
     * Get the technician who checked out the assets.
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get the warehouse staff who processed the checkout.
     */
    public function warehouseStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warehouse_staff_id');
    }

    /**
     * Get the warehouse from which the assets were checked out.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user who approved the checkout (if it exceeded limits).
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

}