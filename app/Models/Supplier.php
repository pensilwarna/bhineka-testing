<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'notes'
    ];

    // Relationships
    public function assetReceipts(): HasMany
    {
        return $this->hasMany(AssetReceipt::class);
    }

    public function assetSupplierReturns(): HasMany
    {
        return $this->hasMany(AssetSupplierReturn::class);
    }

    // Helper methods
    public function getTotalPurchaseValue(): float
    {
        return $this->assetReceipts()->sum('total_value');
    }

    public function getRecentAssets($months = 3)
    {
        return $this->assetReceipts()
            ->with('items.asset')
            ->where('receipt_date', '>=', now()->subMonths($months))
            ->get()
            ->pluck('items')
            ->flatten()
            ->pluck('asset')
            ->unique('id');
    }
}