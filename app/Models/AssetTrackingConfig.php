<?php
// File: app/Models/AssetTrackingConfig.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetTrackingConfig extends Model
{
    protected $fillable = [
        'asset_sub_type',
        'display_name',
        'requires_qr_tracking',
        'requires_serial_number',
        'requires_mac_address',
        'requires_length_tracking',
        'tracking_instructions',
        'required_fields',
        'optional_fields',
        'is_active',
    ];

    protected $casts = [
        'required_fields' => 'array',
        'optional_fields' => 'array',
        'is_active' => 'boolean',
        'requires_qr_tracking' => 'boolean',
        'requires_serial_number' => 'boolean',
        'requires_mac_address' => 'boolean',
        'requires_length_tracking' => 'boolean',
    ];
    /**
     * Get active configurations only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get configuration by sub type
     */
    public function scopeBySubType($query, $subType)
    {
        return $query->where('asset_sub_type', $subType);
    }

    /**
     * Get all required fields for this asset sub type
     */
    public function getAllRequiredFields(): array
    {
        $fields = $this->required_fields ?? [];
        
        if ($this->requires_qr_tracking && !in_array('qr_code', $fields)) {
            $fields[] = 'qr_code';
        }
        
        if ($this->requires_serial_number && !in_array('serial_number', $fields)) {
            $fields[] = 'serial_number';
        }
        
        if ($this->requires_mac_address && !in_array('mac_address', $fields)) {
            $fields[] = 'mac_address';
        }
        
        if ($this->requires_length_tracking && !in_array('length', $fields)) {
            $fields[] = 'length';
        }
        
        return array_unique($fields);
    }

    /**
     * Get all optional fields for this asset sub type
     */
    public function getAllOptionalFields(): array
    {
        $optional = $this->optional_fields ?? [];
        $required = $this->getAllRequiredFields();
        
        // Add standard optional fields if not already required
        $standardOptional = ['qr_code', 'serial_number', 'mac_address', 'notes'];
        
        foreach ($standardOptional as $field) {
            if (!in_array($field, $required) && !in_array($field, $optional)) {
                $optional[] = $field;
            }
        }
        
        return array_unique($optional);
    }

    /**
     * Validate tracking data against this configuration
     */
    public function validateTrackingData(array $data): array
    {
        $errors = [];
        $requiredFields = $this->getAllRequiredFields();
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $fieldLabel = $this->getFieldLabel($field);
                $errors[] = "{$fieldLabel} wajib diisi untuk {$this->display_name}";
            }
        }
        
        // Special validation for length
        if ($this->requires_length_tracking && isset($data['length'])) {
            if (!is_numeric($data['length']) || $data['length'] <= 0) {
                $errors[] = "Panjang harus berupa angka positif untuk {$this->display_name}";
            }
        }
        
        return $errors;
    }

    /**
     * Get user-friendly field label
     */
    public function getFieldLabel(string $field): string
    {
        $labels = [
            'qr_code' => 'QR Code',
            'serial_number' => 'Serial Number',
            'mac_address' => 'MAC Address',
            'length' => 'Panjang',
            'notes' => 'Catatan',
        ];
        
        return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Get field input type for forms
     */
    public function getFieldInputType(string $field): string
    {
        $types = [
            'length' => 'number',
            'qr_code' => 'text',
            'serial_number' => 'text',
            'mac_address' => 'text',
            'notes' => 'textarea',
        ];
        
        return $types[$field] ?? 'text';
    }

    /**
     * Get field placeholder for forms
     */
    public function getFieldPlaceholder(string $field): string
    {
        $placeholders = [
            'length' => 'Contoh: 1000',
            'qr_code' => 'Contoh: KF1C-R001',
            'serial_number' => 'Serial Number',
            'mac_address' => 'XX:XX:XX:XX:XX:XX',
            'notes' => 'Keterangan tambahan',
        ];
        
        return $placeholders[$field] ?? '';
    }

    /**
     * Check if this configuration requires at least one identifier
     */
    public function requiresIdentifier(): bool
    {
        return $this->requires_qr_tracking || 
               $this->requires_serial_number || 
               $this->requires_mac_address;
    }

    /**
     * Get tracking form instructions for this asset type
     */
    public function getFormInstructions(): string
    {
        if (!empty($this->tracking_instructions)) {
            return $this->tracking_instructions;
        }

        $instructions = "Untuk {$this->display_name}: ";
        $required = [];
        
        if ($this->requires_length_tracking) {
            $required[] = "panjang wajib diisi";
        }
        
        if ($this->requires_serial_number) {
            $required[] = "Serial Number wajib";
        }
        
        if ($this->requires_mac_address) {
            $required[] = "MAC Address wajib";
        }
        
        if ($this->requires_qr_tracking && !$this->requires_serial_number && !$this->requires_mac_address) {
            $required[] = "QR Code wajib";
        }
        
        if (empty($required)) {
            $instructions .= "masukkan minimal satu identifikasi.";
        } else {
            $instructions .= implode(', ', $required) . ".";
        }
        
        return $instructions;
    }

    /**
     * Create or update asset tracking configuration
     */
    public static function updateOrCreateConfig(string $subType, array $attributes): self
    {
        return static::updateOrCreate(
            ['asset_sub_type' => $subType],
            $attributes
        );
    }

    /**
     * Get configuration for asset or create default
     */
    public static function getConfigForAsset(Asset $asset): ?self
    {
        if ($asset->asset_sub_type) {
            return static::active()->bySubType($asset->asset_sub_type)->first();
        }

        // Try to detect based on asset properties
        if ($asset->isCableAsset()) {
            return static::active()->bySubType('cable_fiber')->first();
        } elseif ($asset->isDeviceAsset()) {
            return static::active()->bySubType('router')->first();
        }

        return null;
    }
}