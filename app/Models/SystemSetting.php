<?php
// File: app/Models/SystemSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key_name', 'value', 'data_type', 'description', 'category', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'value' => 'string' // Will be cast based on data_type
    ];

    // Helper method untuk ambil value dengan cast yang benar
    public static function getValue($key, $default = null)
    {
        $setting = self::where('key_name', $key)->where('is_active', true)->first();
        
        if (!$setting) {
            return $default;
        }

        // Cast value berdasarkan data_type
        switch ($setting->data_type) {
            case 'integer':
                return (int) $setting->value;
            case 'decimal':
                return (float) $setting->value;
            case 'boolean':
                return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }

    // Helper method untuk set value
    public static function setValue($key, $value, $dataType = 'string')
    {
        // Convert value to string for storage
        if ($dataType === 'json') {
            $value = json_encode($value);
        } elseif ($dataType === 'boolean') {
            $value = $value ? 'true' : 'false';
        } else {
            $value = (string) $value;
        }

        return self::updateOrCreate(
            ['key_name' => $key],
            [
                'value' => $value,
                'data_type' => $dataType,
                'is_active' => true
            ]
        );
    }
}