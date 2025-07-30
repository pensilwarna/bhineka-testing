<?php
// File: app/Models/TrackedAsset.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TrackedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'asset_receipt_item_id',
        'qr_code',
        'qr_generated',
        'serial_number',
        'mac_address',
        'initial_length',
        'current_length',
        'unit_of_measure',
        'current_warehouse_id',
        'current_status',
        'damage_notes',
        'last_status_change_by_user_id',
        'notes'
    ];

    protected $casts = [
        'qr_generated' => 'boolean',
        'initial_length' => 'decimal:3',
        'current_length' => 'decimal:3',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'qr_image_url',
        'length_percentage',
        'used_length',
        'status_display',
        'age_in_days'
    ];

    // Constants for statuses
    const STATUS_AVAILABLE = 'available';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_LOANED = 'loaned';
    const STATUS_INSTALLED = 'installed';
    const STATUS_DAMAGED = 'damaged';
    const STATUS_IN_REPAIR = 'in_repair';
    const STATUS_AWAITING_RETURN = 'awaiting_return_to_supplier';
    const STATUS_WRITTEN_OFF = 'written_off';
    const STATUS_SCRAP = 'scrap';
    const STATUS_RETURNED = 'returned_to_supplier';
    const STATUS_LOST = 'lost';

    // Relationships
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetReceiptItem(): BelongsTo
    {
        return $this->belongsTo(AssetReceiptItem::class);
    }

    public function currentWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'current_warehouse_id');
    }

    public function lastStatusChangeBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_status_change_by_user_id');
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

    public function assetRepairs(): HasMany
    {
        return $this->hasMany(AssetRepair::class);
    }

    public function assetSupplierReturns(): HasMany
    {
        return $this->hasMany(AssetSupplierReturn::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TrackedAssetStatusLog::class)->latest();
    }

    public function qrScanLogs(): HasMany
    {
        return $this->hasMany(QRScanLog::class)->latest();
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('current_status', self::STATUS_AVAILABLE);
    }

    public function scopeInUse($query)
    {
        return $query->whereIn('current_status', [
            self::STATUS_LOANED,
            self::STATUS_INSTALLED,
            self::STATUS_IN_TRANSIT
        ]);
    }

    public function scopeDamaged($query)
    {
        return $query->whereIn('current_status', [
            self::STATUS_DAMAGED,
            self::STATUS_IN_REPAIR
        ]);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('current_warehouse_id', $warehouseId);
    }

    public function scopeByAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeCableAssets($query)
    {
        return $query->whereHas('asset', function($q) {
            $q->where('asset_sub_type', 'like', '%cable%');
        });
    }

    public function scopeNetworkDevices($query)
    {
        return $query->whereHas('asset', function($q) {
            $q->whereIn('asset_sub_type', ['router', 'switch', 'ont', 'olt', 'modem']);
        });
    }

    public function scopeWithQR($query)
    {
        return $query->where('qr_generated', true)
                     ->whereNotNull('qr_code');
    }

    public function scopeWithoutQR($query)
    {
        return $query->where('qr_generated', false)
                     ->orWhereNull('qr_code');
    }

    public function scopeLowCableLength($query, $percentage = 20)
    {
        return $query->whereNotNull('initial_length')
                     ->whereNotNull('current_length')
                     ->whereRaw('(current_length / initial_length) * 100 <= ?', [$percentage]);
    }

    public function scopeRecentlyReceived($query, $days = 7)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    // Accessor Methods
    public function getQrImageUrlAttribute(): ?string
    {
        if (!$this->qr_generated || !$this->qr_code) {
            return null;
        }

        $imagePath = "qr-codes/{$this->qr_code}.png";
        
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::url($imagePath);
        }
        
        return null;
    }

    public function getLengthPercentageAttribute(): ?float
    {
        if (!$this->initial_length || !$this->current_length) {
            return null;
        }
        
        return round(($this->current_length / $this->initial_length) * 100, 1);
    }

    public function getUsedLengthAttribute(): ?float
    {
        if (!$this->initial_length || !$this->current_length) {
            return null;
        }
        
        return $this->initial_length - $this->current_length;
    }

    public function getStatusDisplayAttribute(): array
    {
        return $this->getStatusConfig($this->current_status);
    }

    public function getAgeInDaysAttribute(): int
    {
        return $this->created_at->diffInDays(now());
    }

    public function getIsNewAttribute(): bool
    {
        return $this->age_in_days <= 7;
    }

    public function getIsCableAssetAttribute(): bool
    {
        return $this->initial_length !== null && $this->current_length !== null;
    }

    public function getIsNetworkDeviceAttribute(): bool
    {
        return !empty($this->serial_number) || !empty($this->mac_address);
    }

    public function getFormattedLengthAttribute(): ?string
    {
        if (!$this->is_cable_asset) {
            return null;
        }

        return "{$this->current_length}m / {$this->initial_length}m ({$this->length_percentage}%)";
    }

    // Status Management Methods
    public function updateStatus(string $newStatus, ?int $userId = null, ?string $notes = null, ?int $warehouseId = null): bool
    {
        $oldStatus = $this->current_status;
        
        // Validate status
        if (!$this->isValidStatus($newStatus)) {
            throw new \InvalidArgumentException("Invalid status: {$newStatus}");
        }
        
        // Log status change
        $this->logStatusChange($oldStatus, $newStatus, $userId, $notes);
        
        // Update the asset
        $updateData = [
            'current_status' => $newStatus,
            'last_status_change_by_user_id' => $userId ?? auth()->id(),
        ];
        
        if ($warehouseId) {
            $updateData['current_warehouse_id'] = $warehouseId;
        }
        
        if ($notes) {
            $updateData['notes'] = $notes;
        }
        
        $this->update($updateData);
        
        // Trigger status change event
        event(new TrackedAssetStatusChanged($this, $oldStatus, $newStatus, $userId));
        
        return true;
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, [
            self::STATUS_AVAILABLE,
            self::STATUS_IN_TRANSIT,
            self::STATUS_LOANED,
            self::STATUS_INSTALLED,
            self::STATUS_DAMAGED,
            self::STATUS_IN_REPAIR,
            self::STATUS_AWAITING_RETURN,
            self::STATUS_WRITTEN_OFF,
            self::STATUS_SCRAP,
            self::STATUS_RETURNED,
            self::STATUS_LOST,
        ]);
    }

    public function canBeCheckedOut(): bool
    {
        return $this->current_status === self::STATUS_AVAILABLE;
    }

    public function canBeReturned(): bool
    {
        return in_array($this->current_status, [
            self::STATUS_LOANED,
            self::STATUS_IN_TRANSIT
        ]);
    }

    public function canBeInstalled(): bool
    {
        return in_array($this->current_status, [
            self::STATUS_AVAILABLE,
            self::STATUS_LOANED,
            self::STATUS_IN_TRANSIT
        ]);
    }

    public function canBeRepaired(): bool
    {
        return $this->current_status === self::STATUS_DAMAGED;
    }

    // Cable Length Management
    public function updateCableLength(float $newLength, ?string $reason = null, ?int $userId = null): bool
    {
        if (!$this->is_cable_asset) {
            throw new \InvalidArgumentException('This asset is not a cable asset');
        }
        
        if ($newLength < 0 || $newLength > $this->initial_length) {
            throw new \InvalidArgumentException('Invalid cable length');
        }
        
        $oldLength = $this->current_length;
        $usedLength = $oldLength - $newLength;
        
        // Log cable usage
        $this->logCableUsage($oldLength, $newLength, $usedLength, $reason, $userId);
        
        $this->update(['current_length' => $newLength]);
        
        // Trigger cable usage event
        event(new CableLengthUpdated($this, $oldLength, $newLength, $usedLength));
        
        return true;
    }

    public function getCableUsageHistory(): array
    {
        if (!$this->is_cable_asset) {
            return [];
        }

        return $this->customerInstalledAssets()
            ->where('status', 'installed')
            ->with(['customer', 'serviceLocation', 'ticket'])
            ->get()
            ->map(function($install) {
                return [
                    'type' => 'installation',
                    'date' => $install->installation_date,
                    'customer' => $install->customer->name,
                    'location' => $install->serviceLocation->address ?? 'Unknown',
                    'length_used' => $install->installed_length,
                    'ticket_id' => $install->ticket_id,
                    'notes' => $install->installation_notes
                ];
            })
            ->toArray();
    }

    // QR Code Management
    public function generateQRCode(): bool
    {
        if (empty($this->qr_code)) {
            $this->qr_code = $this->generateUniqueQRCode();
        }
        
        $qrData = $this->buildQRData();
        $qrString = json_encode($qrData);
        
        // Generate QR image using SimpleQRCode or similar
        $qrCodePng = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($qrString);
        
        // Save QR image
        $filename = "qr-codes/{$this->qr_code}.png";
        Storage::disk('public')->put($filename, $qrCodePng);
        
        $this->update(['qr_generated' => true]);
        
        return true;
    }

    public function buildQRData(): array
    {
        return [
            'type' => 'asset',
            'version' => '1.0',
            'qr_code' => $this->qr_code,
            'asset_id' => $this->asset_id,
            'tracked_asset_id' => $this->id,
            'asset_name' => $this->asset->name,
            'asset_code' => $this->asset->asset_code,
            'serial_number' => $this->serial_number,
            'mac_address' => $this->mac_address,
            'initial_length' => $this->initial_length,
            'current_length' => $this->current_length,
            'current_status' => $this->current_status,
            'warehouse_id' => $this->current_warehouse_id,
            'generated_at' => now()->toISOString(),
            'company' => config('app.name', 'Asset Management System')
        ];
    }

    private function generateUniqueQRCode(): string
    {
        do {
            $qrCode = 'QR-' . $this->asset->asset_code . '-' . date('ymd') . '-' . strtoupper(\Str::random(4));
        } while (self::where('qr_code', $qrCode)->exists());
        
        return $qrCode;
    }

    public function logQRScan(?int $userId = null, ?string $location = null, array $metadata = []): void
    {
        QRScanLog::create([
            'tracked_asset_id' => $this->id,
            'user_id' => $userId ?? auth()->id(),
            'scan_location' => $location,
            'scan_metadata' => $metadata,
            'scanned_at' => now()
        ]);
    }

    // Utility Methods
    public function getStatusConfig(string $status): array
    {
        $configs = [
            self::STATUS_AVAILABLE => ['color' => 'success', 'icon' => 'ti-check', 'label' => 'Available'],
            self::STATUS_IN_TRANSIT => ['color' => 'info', 'icon' => 'ti-truck', 'label' => 'In Transit'],
            self::STATUS_LOANED => ['color' => 'warning', 'icon' => 'ti-user', 'label' => 'With Technician'],
            self::STATUS_INSTALLED => ['color' => 'primary', 'icon' => 'ti-home', 'label' => 'Installed'],
            self::STATUS_DAMAGED => ['color' => 'danger', 'icon' => 'ti-alert-circle', 'label' => 'Damaged'],
            self::STATUS_IN_REPAIR => ['color' => 'warning', 'icon' => 'ti-tools', 'label' => 'In Repair'],
            self::STATUS_LOST => ['color' => 'danger', 'icon' => 'ti-help', 'label' => 'Lost'],
            self::STATUS_WRITTEN_OFF => ['color' => 'dark', 'icon' => 'ti-ban', 'label' => 'Written Off']
        ];
        
        return $configs[$status] ?? ['color' => 'secondary', 'icon' => 'ti-question-mark', 'label' => ucfirst($status)];
    }

    public function getCurrentLocation(): array
    {
        $location = [
            'type' => 'warehouse',
            'name' => $this->currentWarehouse->name ?? 'Unknown',
            'id' => $this->current_warehouse_id
        ];

        // Check if with technician
        $activeDebt = $this->technicianAssetDebts()
            ->where('status', 'active')
            ->with('technician')
            ->first();
        
        if ($activeDebt) {
            $location = [
                'type' => 'technician',
                'name' => $activeDebt->technician->name,
                'id' => $activeDebt->technician_id,
                'phone' => $activeDebt->technician->phone_number
            ];
        }

        // Check if installed at customer
        $installation = $this->customerInstalledAssets()
            ->where('status', 'installed')
            ->with(['customer', 'serviceLocation'])
            ->first();
        
        if ($installation) {
            $location = [
                'type' => 'customer',
                'name' => $installation->customer->name,
                'address' => $installation->serviceLocation->address ?? 'Unknown',
                'id' => $installation->customer_id
            ];
        }

        return $location;
    }

    public function getEstimatedValue(): float
    {
        $baseValue = $this->asset->standard_price;
        
        // Depreciate based on age and condition
        $depreciationRate = 0.1; // 10% per year
        $ageInYears = $this->age_in_days / 365;
        $depreciation = $baseValue * $depreciationRate * $ageInYears;
        
        // Additional depreciation for damaged items
        if (in_array($this->current_status, [self::STATUS_DAMAGED, self::STATUS_IN_REPAIR])) {
            $depreciation += $baseValue * 0.3; // 30% additional
        }
        
        // Cable depreciation based on usage
        if ($this->is_cable_asset && $this->length_percentage < 100) {
            $usageDepreciation = $baseValue * (1 - ($this->length_percentage / 100));
            $depreciation += $usageDepreciation;
        }
        
        return max(0, $baseValue - $depreciation);
    }

    // Logging Methods
    private function logStatusChange(string $oldStatus, string $newStatus, ?int $userId, ?string $notes): void
    {
        TrackedAssetStatusLog::create([
            'tracked_asset_id' => $this->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by_user_id' => $userId ?? auth()->id(),
            'notes' => $notes,
            'changed_at' => now()
        ]);
    }

    private function logCableUsage(float $oldLength, float $newLength, float $usedLength, ?string $reason, ?int $userId): void
    {
        CableUsageLog::create([
            'tracked_asset_id' => $this->id,
            'old_length' => $oldLength,
            'new_length' => $newLength,
            'used_length' => $usedLength,
            'reason' => $reason,
            'user_id' => $userId ?? auth()->id(),
            'used_at' => now()
        ]);
    }

    // Static Methods
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_IN_TRANSIT => 'In Transit',
            self::STATUS_LOANED => 'With Technician',
            self::STATUS_INSTALLED => 'Installed',
            self::STATUS_DAMAGED => 'Damaged',
            self::STATUS_IN_REPAIR => 'In Repair',
            self::STATUS_AWAITING_RETURN => 'Awaiting Return',
            self::STATUS_WRITTEN_OFF => 'Written Off',
            self::STATUS_SCRAP => 'Scrap',
            self::STATUS_RETURNED => 'Returned to Supplier',
            self::STATUS_LOST => 'Lost',
        ];
    }

    public static function getCableAssetsLowOnLength($percentage = 20)
    {
        return self::cableAssets()
            ->lowCableLength($percentage)
            ->with(['asset', 'currentWarehouse'])
            ->get();
    }

    public static function getAssetsByStatus(string $status)
    {
        return self::where('current_status', $status)
            ->with(['asset', 'currentWarehouse'])
            ->get();
    }

    public static function getRecentQRScans($days = 7)
    {
        return self::whereHas('qrScanLogs', function($query) use ($days) {
            $query->where('scanned_at', '>=', Carbon::now()->subDays($days));
        })
        ->with(['asset', 'qrScanLogs' => function($query) use ($days) {
            $query->where('scanned_at', '>=', Carbon::now()->subDays($days))
                  ->with('user')
                  ->latest();
        }])
        ->get();
    }
}