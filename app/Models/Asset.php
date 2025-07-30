<?php
// File: app/Models/Asset.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'asset_category_id',
        'asset_type',
        'asset_code',
        'name',
        'brand',
        'model',
        'total_quantity',
        'available_quantity',
        'description',
        'status',
        'standard_price',
        'requires_qr_tracking',
        'asset_sub_type',
        'standard_length_per_roll',
        'requires_serial_number',
        'requires_mac_address',
        'tracking_instructions'
    ];

    protected $casts = [
        'standard_price' => 'decimal:2',
        'total_quantity' => 'integer',
        'available_quantity' => 'integer',
        'standard_length_per_roll' => 'decimal:3',
        'requires_qr_tracking' => 'boolean',
        'requires_serial_number' => 'boolean',
        'requires_mac_address' => 'boolean',
    ];

    // Relationships
    public function asset_category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function trackedAssets(): HasMany
    {
        return $this->hasMany(TrackedAsset::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(AssetReceiptItem::class);
    }

    public function technicianAssetDebts(): HasMany
    {
        return $this->hasMany(TechnicianAssetDebt::class);
    }

    public function customerInstalledAssets(): HasMany
    {
        return $this->hasMany(CustomerInstalledAsset::class);
    }

    public function assetUsages(): HasMany
    {
        return $this->hasMany(AssetUsage::class);
    }

    public function assetWriteOffs(): HasMany
    {
        return $this->hasMany(AssetWriteOff::class);
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where(function($q) {
            $q->where('requires_qr_tracking', false)
              ->where('available_quantity', '>', 0)
              ->orWhere('requires_qr_tracking', true)
              ->whereHas('trackedAssets', function($subq) {
                  $subq->where('current_status', 'available');
              });
        });
    }

    public function scopeOutOfStock($query)
    {
        return $query->where(function($q) {
            $q->where('requires_qr_tracking', false)
              ->where('available_quantity', '<=', 0)
              ->orWhere('requires_qr_tracking', true)
              ->whereDoesntHave('trackedAssets', function($subq) {
                  $subq->where('current_status', 'available');
              });
        });
    }

    public function scopeLowStock($query, $threshold = 5)
    {
        return $query->where(function($q) use ($threshold) {
            $q->where('requires_qr_tracking', false)
              ->where('available_quantity', '>', 0)
              ->where('available_quantity', '<=', $threshold)
              ->orWhere('requires_qr_tracking', true)
              ->whereHas('trackedAssets', function($subq) {
                  $subq->where('current_status', 'available');
              }, '<=', $threshold);
        });
    }

    public function scopeCableAssets($query)
    {
        return $query->where('asset_sub_type', 'like', '%cable%');
    }

    public function scopeNetworkDevices($query)
    {
        return $query->whereIn('asset_sub_type', [
            'router', 'switch', 'ont', 'olt', 'modem', 'access_point'
        ]);
    }

    public function scopeConsumables($query)
    {
        return $query->where('asset_type', 'consumable');
    }

    public function scopeFixedAssets($query)
    {
        return $query->where('asset_type', 'fixed');
    }

    public function scopeRequiringQR($query)
    {
        return $query->where('requires_qr_tracking', true);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('asset_category_id', $categoryId);
    }

    // Accessor and Mutator methods
    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->standard_price, 0, ',', '.');
    }

    public function getAvailableStockAttribute(): int
    {
        if ($this->requires_qr_tracking) {
            return $this->trackedAssets()->where('current_status', 'available')->count();
        }
        return $this->available_quantity;
    }

    public function getTotalStockAttribute(): int
    {
        if ($this->requires_qr_tracking) {
            return $this->trackedAssets()->count();
        }
        return $this->total_quantity;
    }

    public function getInUseStockAttribute(): int
    {
        if ($this->requires_qr_tracking) {
            return $this->trackedAssets()
                ->whereIn('current_status', ['loaned', 'installed', 'in_transit'])
                ->count();
        } else {
            $debtQuantity = $this->technicianAssetDebts()
                ->where('status', 'active')
                ->sum('current_debt_quantity');
            
            $installedQuantity = $this->customerInstalledAssets()
                ->where('status', 'installed')
                ->sum('quantity_installed');
            
            return $debtQuantity + $installedQuantity;
        }
    }

    public function getDamagedStockAttribute(): int
    {
        if ($this->requires_qr_tracking) {
            return $this->trackedAssets()
                ->whereIn('current_status', ['damaged', 'in_repair'])
                ->count();
        }
        return 0;
    }

    public function getStockStatusAttribute(): string
    {
        $available = $this->available_stock;
        
        if ($available == 0) {
            return 'out_of_stock';
        } elseif ($available <= 5) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    public function getStockStatusColorAttribute(): string
    {
        switch ($this->stock_status) {
            case 'out_of_stock':
                return 'danger';
            case 'low_stock':
                return 'warning';
            default:
                return 'success';
        }
    }

    // Helper methods
    public function isCableAsset(): bool
    {
        return $this->asset_sub_type && str_contains(strtolower($this->asset_sub_type), 'cable');
    }

    public function isNetworkDevice(): bool
    {
        return in_array($this->asset_sub_type, [
            'router', 'switch', 'ont', 'olt', 'modem', 'access_point'
        ]);
    }

    public function isConsumable(): bool
    {
        return $this->asset_type === 'consumable';
    }

    public function requiresIndividualTracking(): bool
    {
        return $this->requires_qr_tracking;
    }

    public function getTotalValue(): float
    {
        if ($this->requires_qr_tracking) {
            return $this->trackedAssets()->count() * $this->standard_price;
        }
        return $this->total_quantity * $this->standard_price;
    }

    public function getAvailableValue(): float
    {
        if ($this->requires_qr_tracking) {
            return $this->trackedAssets()
                ->where('current_status', 'available')
                ->count() * $this->standard_price;
        }
        return $this->available_quantity * $this->standard_price;
    }

    /**
     * Get cable length statistics for cable assets
     */
    public function getCableLengthStats(): array
    {
        if (!$this->isCableAsset() || !$this->requires_qr_tracking) {
            return [];
        }

        $trackedAssets = $this->trackedAssets;
        
        return [
            'total_rolls' => $trackedAssets->count(),
            'available_rolls' => $trackedAssets->where('current_status', 'available')->count(),
            'total_initial_length' => $trackedAssets->sum('initial_length'),
            'total_current_length' => $trackedAssets->sum('current_length'),
            'total_used_length' => $trackedAssets->sum(function($item) {
                return $item->initial_length - $item->current_length;
            }),
            'available_length' => $trackedAssets->where('current_status', 'available')->sum('current_length'),
            'usage_percentage' => $trackedAssets->sum('initial_length') > 0 
                ? round(($trackedAssets->sum(function($item) {
                    return $item->initial_length - $item->current_length;
                }) / $trackedAssets->sum('initial_length')) * 100, 1)
                : 0,
            'average_roll_length' => $trackedAssets->where('current_status', 'available')->avg('current_length'),
            'longest_roll' => $trackedAssets->where('current_status', 'available')->max('current_length'),
            'shortest_roll' => $trackedAssets->where('current_status', 'available')->min('current_length'),
        ];
    }

    /**
     * Get QR tracking statistics
     */
    public function getQRTrackingStats(): array
    {
        if (!$this->requires_qr_tracking) {
            return ['total' => 0, 'generated' => 0, 'pending' => 0];
        }

        $trackedAssets = $this->trackedAssets;
        
        return [
            'total' => $trackedAssets->count(),
            'generated' => $trackedAssets->where('qr_generated', true)->count(),
            'pending' => $trackedAssets->where('qr_generated', false)->count(),
        ];
    }

    /**
     * Get recent receipt information
     */
    public function getLastReceiptInfo(): ?array
    {
        $lastReceipt = $this->receiptItems()
            ->with('assetReceipt')
            ->latest()
            ->first();
        
        if (!$lastReceipt) {
            return null;
        }

        return [
            'receipt_number' => $lastReceipt->assetReceipt->receipt_number,
            'receipt_date' => $lastReceipt->assetReceipt->receipt_date,
            'quantity_received' => $lastReceipt->quantity_received,
            'unit_price' => $lastReceipt->actual_unit_price,
            'supplier' => $lastReceipt->assetReceipt->supplier->name ?? 'Unknown',
        ];
    }

    /**
     * Get warehouse distribution for tracked assets
     */
    public function getWarehouseDistribution(): array
    {
        if (!$this->requires_qr_tracking) {
            return [];
        }

        return $this->trackedAssets()
            ->with('currentWarehouse')
            ->get()
            ->groupBy('current_warehouse_id')
            ->map(function($items) {
                $warehouse = $items->first()->currentWarehouse;
                return [
                    'warehouse_name' => $warehouse->name ?? 'Unknown',
                    'total_count' => $items->count(),
                    'available_count' => $items->where('current_status', 'available')->count(),
                    'in_use_count' => $items->whereIn('current_status', ['loaned', 'installed', 'in_transit'])->count(),
                    'damaged_count' => $items->whereIn('current_status', ['damaged', 'in_repair'])->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Check if asset can be checked out
     */
    public function canBeCheckedOut(int $quantity = 1): bool
    {
        return $this->available_stock >= $quantity;
    }

    /**
     * Get tracking configuration
     */
    public function getTrackingConfig(): array
    {
        return [
            'requires_qr_tracking' => $this->requires_qr_tracking,
            'requires_serial_number' => $this->requires_serial_number,
            'requires_mac_address' => $this->requires_mac_address,
            'requires_length_tracking' => $this->isCableAsset(),
            'asset_sub_type' => $this->asset_sub_type,
            'tracking_instructions' => $this->tracking_instructions,
        ];
    }

    /**
     * Update stock quantities (for non-tracked assets)
     */
    public function updateStock(int $quantityChange, string $reason = null): bool
    {
        if ($this->requires_qr_tracking) {
            return false; // Use tracked assets for QR-enabled assets
        }

        $newTotal = max(0, $this->total_quantity + $quantityChange);
        $newAvailable = max(0, $this->available_quantity + $quantityChange);

        $this->update([
            'total_quantity' => $newTotal,
            'available_quantity' => $newAvailable,
        ]);

        // Log the stock change
        \Log::info("Stock updated for asset {$this->id}: {$quantityChange} ({$reason})");

        return true;
    }

    /**
     * Get asset utilization rate
     */
    public function getUtilizationRate(): float
    {
        $total = $this->total_stock;
        $available = $this->available_stock;
        
        if ($total == 0) {
            return 0;
        }

        return round((($total - $available) / $total) * 100, 1);
    }
}