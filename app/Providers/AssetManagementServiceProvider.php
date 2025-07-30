<?php
// File: app/Providers/AssetManagementServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\QRCodeService;
use App\Services\DebtService;
use App\Services\AssetManagementService;

class AssetManagementServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register QR Code Service
        $this->app->singleton(QRCodeService::class, function ($app) {
            return new QRCodeService();
        });

        // Register Debt Service
        $this->app->singleton(DebtService::class, function ($app) {
            return new DebtService();
        });

        // Register Asset Management Service
        $this->app->singleton(AssetManagementService::class, function ($app) {
            return new AssetManagementService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config file if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/asset-management.php' => config_path('asset-management.php'),
            ], 'asset-management-config');
        }

        // Register views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views/asset-management', 'asset-management');

        // Register custom validation rules if needed
        $this->registerValidationRules();
    }

    /**
     * Register custom validation rules
     */
    private function registerValidationRules(): void
    {
        // QR Code format validation
        \Validator::extend('qr_format', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^AST-[A-Z]{3}-\d{3}$/', $value);
        });

        \Validator::replacer('qr_format', function ($message, $attribute, $rule, $parameters) {
            return 'The ' . $attribute . ' must be in format AST-XXX-999';
        });

        // Asset availability validation
        \Validator::extend('asset_available', function ($attribute, $value, $parameters, $validator) {
            if (count($parameters) !== 2) {
                return false;
            }

            $assetId = $parameters[0];
            $requestedQuantity = $parameters[1];

            $asset = \App\Models\Asset::find($assetId);
            return $asset && $asset->available_quantity >= $requestedQuantity;
        });

        \Validator::replacer('asset_available', function ($message, $attribute, $rule, $parameters) {
            return 'The requested quantity exceeds available stock';
        });
    }
}