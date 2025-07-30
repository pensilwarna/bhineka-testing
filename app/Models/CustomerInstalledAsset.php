<?php
// File: app/Models/CustomerInstalledAsset.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInstalledAsset extends Model
{
    protected $fillable = [
        'customer_id', 'service_location_id', 'ticket_id', 'technician_id', 'asset_id', 'debt_id',
        'quantity_installed', 'unit_value', 'total_asset_value',
        'installation_date', 'installation_photos', 'installation_notes',
        'gps_latitude', 'gps_longitude',
        'status', 'removed_date', 'removed_by', 'removal_photos', 'removal_notes'
    ];

    protected $casts = [
        'installation_date' => 'date',
        'removed_date' => 'date',
        'installation_photos' => 'array',
        'removal_photos' => 'array',
        'quantity_installed' => 'decimal:3',
        'unit_value' => 'decimal:2',
        'total_asset_value' => 'decimal:2',
        'gps_latitude' => 'decimal:8',
        'gps_longitude' => 'decimal:8',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceLocation(): BelongsTo
    {
        return $this->belongsTo(ServiceLocation::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(TechnicianAssetDebt::class, 'debt_id');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    // Scopes
    public function scopeInstalled($query)
    {
        return $query->where('status', 'installed');
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    // Helper methods
    public function getFormattedValueAttribute(): string
    {
        return 'Rp ' . number_format($this->total_asset_value, 0, ',', '.');
    }

    public function hasPhotos(): bool
    {
        return !empty($this->installation_photos);
    }

    public function getPhotosCount(): int
    {
        return count($this->installation_photos ?? []);
    }
}