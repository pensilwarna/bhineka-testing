<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 

class Warehouse extends Model
{
    protected $fillable = [
        'name',
        'location', 
        'office_id'
    ];

    // Relationships
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function trackedAssets(): HasMany
    {
        return $this->hasMany(TrackedAsset::class, 'current_warehouse_id');
    }

    public function assetReceiptItems(): HasMany
    {
        return $this->hasMany(AssetReceiptItem::class);
    }

    public function technicianAssetDebts(): HasMany
    {
        return $this->hasMany(TechnicianAssetDebt::class);
    }

    // Helper methods
    public function getTotalAssetsCount(): int
    {
        $simpleAssets = $this->assets()->sum('total_quantity');
        $trackedAssets = $this->trackedAssets()->count();
        return $simpleAssets + $trackedAssets;
    }

    public function getAvailableAssetsCount(): int
    {
        $simpleAssets = $this->assets()->sum('available_quantity');
        $trackedAssets = $this->trackedAssets()->where('current_status', 'available')->count();
        return $simpleAssets + $trackedAssets;
    }
}