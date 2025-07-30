<?php
// File: routes/web/asset-management.php

use App\Http\Controllers\AssetManagementController;
use App\Http\Controllers\AssetCheckoutController; // Assuming you have this
use App\Http\Controllers\AssetDebtController;     // Assuming you have this
use App\Http\Controllers\AssetReportController;
use App\Http\Controllers\CustomerAssetController; // Assuming you have this
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\AssetReceiptController;
use App\Http\Controllers\AssetMaintenanceController;

use App\Http\Controllers\QRManagementController;
use App\Http\Controllers\SystemSettingController;
use Illuminate\Support\Facades\Route;

// Asset Management Routes Group
Route::prefix('asset-management')->name('asset-management.')->group(function () {
    
    // Main Asset Management Dashboard
    Route::get('/', [AssetManagementController::class, 'index'])->name('index');
    Route::get('/get-data', [AssetManagementController::class, 'getAssetsData'])->name('get-assets-data');
    Route::get('/get-stats', [AssetManagementController::class, 'getStats'])->name('get-stats');

    // Master Data Management
    Route::middleware(['role:Super-Admin|Owner|Manager|NOC|LAD|Warehouse'])->prefix('master')->name('master.')->group(function () {
        // Offices CRUD
        Route::get('/offices', [MasterDataController::class, 'officesIndex'])->name('offices.index');
        Route::get('/get-offices-data', [MasterDataController::class, 'getOfficesData'])->name('offices.get-data');
        Route::post('/offices', [MasterDataController::class, 'storeOffice'])->name('offices.store');
        Route::get('/offices/{office}', [MasterDataController::class, 'editOffice'])->name('offices.edit');
        Route::put('/offices/{office}', [MasterDataController::class, 'updateOffice'])->name('offices.update');
        Route::delete('/offices/{office}', [MasterDataController::class, 'destroyOffice'])->name('offices.destroy');

        // Suppliers CRUD
        Route::get('/suppliers', [MasterDataController::class, 'suppliersIndex'])->name('suppliers.index');
        Route::get('/get-suppliers-data', [MasterDataController::class, 'getSuppliersData'])->name('suppliers.get-data');
        Route::post('/suppliers', [MasterDataController::class, 'storeSupplier'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [MasterDataController::class, 'editSupplier'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [MasterDataController::class, 'updateSupplier'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [MasterDataController::class, 'destroySupplier'])->name('suppliers.destroy');

        // Warehouses CRUD
        Route::get('/warehouses', [MasterDataController::class, 'warehousesIndex'])->name('warehouses.index');
        Route::get('/get-warehouses-data', [MasterDataController::class, 'getWarehousesData'])->name('warehouses.get-data');
        Route::post('/warehouses', [MasterDataController::class, 'storeWarehouse'])->name('warehouses.store');
        Route::get('/warehouses/{warehouse}', [MasterDataController::class, 'editWarehouse'])->name('warehouses.edit');
        Route::put('/warehouses/{warehouse}', [MasterDataController::class, 'updateWarehouse'])->name('warehouses.update');
        Route::delete('/warehouses/{warehouse}', [MasterDataController::class, 'destroyWarehouse'])->name('warehouses.destroy');

        // Asset Categories CRUD
        Route::get('/asset-categories', [MasterDataController::class, 'assetCategoriesIndex'])->name('categories.index');
        Route::get('/get-asset-categories-data', [MasterDataController::class, 'getAssetCategoriesData'])->name('categories.get-data');
        Route::post('/asset-categories', [MasterDataController::class, 'storeAssetCategory'])->name('categories.store');
        Route::get('/asset-categories/{assetCategory}', [MasterDataController::class, 'editAssetCategory'])->name('categories.edit');
        Route::put('/asset-categories/{assetCategory}', [MasterDataController::class, 'updateAssetCategory'])->name('categories.update');
        Route::delete('/asset-categories/{assetCategory}', [MasterDataController::class, 'destroyAssetCategory'])->name('categories.destroy');
        
        // Master Assets CRUD
        Route::get('assets', [MasterDataController::class, 'masterAssetsIndex'])->name('assets.index');
        Route::get('assets/data', [MasterDataController::class, 'getMasterAssetsData'])->name('assets.get-data');
        Route::post('assets', [MasterDataController::class, 'storeMasterAsset'])->name('assets.store');
        Route::get('assets/sub-type-config', [MasterDataController::class, 'getAssetSubTypeConfig'])->name('assets.get-sub-type-config');
        Route::get('assets/{asset}', [MasterDataController::class, 'editMasterAsset'])->name('assets.edit');
        Route::put('assets/{asset}', [MasterDataController::class, 'updateMasterAsset'])->name('assets.update');
        Route::delete('assets/{asset}', [MasterDataController::class, 'destroyMasterAsset'])->name('assets.destroy');
    });

    // Enhanced Asset Receipts with QR Management
    Route::middleware(['role:Warehouse|NOC|Manager|Owner|Super-Admin'])->prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/', [AssetReceiptController::class, 'index'])->name('index');
        Route::get('/get-data', [AssetReceiptController::class, 'getReceiptsData'])->name('get-data');
        Route::get('/config', [AssetReceiptController::class, 'getAssetTrackingConfig'])->name('config');
        Route::post('/', [AssetReceiptController::class, 'store'])->name('store');
        Route::get('/{receipt}', [AssetReceiptController::class, 'show'])->name('show');
        Route::delete('/{receipt}', [AssetReceiptController::class, 'destroy'])->name('destroy');
        
        // Enhanced QR Management for Receipts
        Route::get('/{receipt}/qr-summary', [AssetReceiptController::class, 'getReceiptQRSummary'])->name('qr-summary');
        Route::post('/{receipt}/generate-qr', [AssetReceiptController::class, 'generateReceiptQR'])->name('generate-qr');
        Route::post('/{receipt}/print-qr-labels', [AssetReceiptController::class, 'printReceiptQRLabels'])->name('print-qr-labels');
        
        // Receipt Statistics
        Route::get('/statistics/overview', [AssetReceiptController::class, 'getReceiptStatistics'])->name('statistics');
    });

    // Asset Maintenance (Repair, Return, Write-off)
    Route::middleware(['role:Warehouse|NOC|Manager|Owner|Super-Admin|LAD'])->prefix('maintenance')->name('maintenance.')->group(function () {
        Route::get('/', [AssetMaintenanceController::class, 'index'])->name('index');
        Route::get('/get-damaged-assets', [AssetMaintenanceController::class, 'getDamagedAssetsData'])->name('get-damaged-assets-data');
        
        // Repair
        Route::post('/repair', [AssetMaintenanceController::class, 'storeRepair'])->name('repair.store');
        Route::post('/repair/complete', [AssetMaintenanceController::class, 'completeRepair'])->name('repair.complete');
        
        // Supplier Return
        Route::post('/supplier-return', [AssetMaintenanceController::class, 'storeSupplierReturn'])->name('supplier-return.store');
        Route::post('/supplier-return/receive-replacement', [AssetMaintenanceController::class, 'receiveReplacement'])->name('supplier-return.receive-replacement');
        Route::post('/supplier-return/mark-rejected', [AssetMaintenanceController::class, 'markReturnRejected'])->name('supplier-return.mark-rejected');

        // Write Off
        Route::post('/write-off', [AssetMaintenanceController::class, 'storeWriteOff'])->name('write-off.store');
    });

     // Enhanced QR Code Management
    Route::middleware(['role:Super-Admin|Owner|Manager|NOC|Warehouse|Technician'])->prefix('qr')->name('qr.')->group(function () {
        // QR Scanner Interface
        Route::get('/scanner', [QRManagementController::class, 'scanner'])->name('scanner');
        Route::get('/mobile-scanner', [QRManagementController::class, 'mobileScanner'])->name('mobile-scanner');
        
        // QR Generation
        Route::post('/generate/{id}', [InventoryController::class, 'generateQRCode'])->name('generate');
        Route::post('/generate-missing', [QRManagementController::class, 'generateMissingQRCodes'])->name('generate-missing');
        Route::post('/regenerate-batch', [QRManagementController::class, 'regenerateBatchQR'])->name('regenerate-batch');
        
        // QR Printing & Labels
        Route::post('/print-labels', [InventoryController::class, 'printQRLabels'])->name('print-labels');
        Route::get('/download-labels/{filename}', [InventoryController::class, 'downloadQRLabels'])->name('download-labels');
        Route::post('/print-single/{id}', [QRManagementController::class, 'printSingleQR'])->name('print-single');
        Route::post('/print-asset-qr/{assetId}', [QRManagementController::class, 'printAssetQR'])->name('print-asset-qr');
        
        // QR Scanning & Lookup
        Route::post('/lookup', [QRManagementController::class, 'lookupQRCode'])->name('lookup');
        Route::get('/asset-by-qr/{qr_code}', [QRManagementController::class, 'getAssetByQR'])->name('asset-by-qr');
        Route::post('/scan-log', [QRManagementController::class, 'logQRScan'])->name('scan-log');
        
        // QR Management & Updates
        Route::post('/update-status', [QRManagementController::class, 'updateAssetStatus'])->name('update-status');
        Route::get('/validate/{qr_code}', [QRManagementController::class, 'validateQRCode'])->name('validate');
        Route::post('/bulk-update-status', [QRManagementController::class, 'bulkUpdateStatus'])->name('bulk-update-status');
        
        // QR Analytics
        Route::get('/analytics', [QRManagementController::class, 'qrAnalytics'])->name('analytics');
        Route::get('/scan-history', [QRManagementController::class, 'scanHistory'])->name('scan-history');
    });

    // Asset Checkout System
    Route::middleware(['role:Warehouse|NOC|Manager|Owner|Super-Admin'])->prefix('checkout')->name('checkout.')->group(function () {
        Route::get('/', [AssetCheckoutController::class, 'index'])->name('index');
        Route::get('/get-data', [AssetCheckoutController::class, 'getCheckoutsData'])->name('get-data');
        Route::post('/process', [AssetCheckoutController::class, 'processCheckout'])->name('process');
        Route::post('/return', [AssetCheckoutController::class, 'processReturn'])->name('return');
        Route::get('/get-technician-active-debts/{technician}', [AssetCheckoutController::class, 'getTechnicianActiveDebts'])->name('get-technician-active-debts');
        
        // QR-based checkout
        Route::post('/qr-checkout', [AssetCheckoutController::class, 'qrBasedCheckout'])->name('qr-checkout');
        Route::post('/qr-return', [AssetCheckoutController::class, 'qrBasedReturn'])->name('qr-return');
    });

    // Debt Management
    Route::middleware(['role:Warehouse|NOC|Manager|Owner|Super-Admin|Kasir|LAD'])->prefix('debts')->name('debts.')->group(function () {
        Route::get('/', [AssetDebtController::class, 'index'])->name('index');
        Route::get('/get-debts-data', [AssetDebtController::class, 'getDebtsData'])->name('get-data');
        Route::get('/technician/{technician}', [AssetDebtController::class, 'showTechnicianDebts'])->name('technician.show');
        
        // Settlement Process
        Route::post('/settle', [AssetDebtController::class, 'settleDebt'])->name('settle');
        Route::get('/settlements', [AssetDebtController::class, 'settlementsIndex'])->name('settlements.index');
        Route::get('/get-settlements-data', [AssetDebtController::class, 'getSettlementsData'])->name('settlements.get-data');

        // Approval for exceeding debt limit
        Route::post('/approve-exceed-limit', [AssetDebtController::class, 'approveExceedLimit'])->name('approve-exceed-limit')->middleware('role:NOC|Manager|Owner|Super-Admin');
    });

   // Customer Asset Tracking
    Route::middleware(['role:Technician|NOC|Manager|Owner|Super-Admin|LAD'])->prefix('customer-assets')->name('customer-assets.')->group(function () {
        Route::get('/', [CustomerAssetController::class, 'index'])->name('index');
        Route::get('/get-data', [CustomerAssetController::class, 'getCustomerAssetsData'])->name('get-data');
        Route::get('/customer/{id}', [CustomerAssetController::class, 'showCustomerAssets'])->name('show-customer');
        Route::get('/get-customer-data/{id}', [CustomerAssetController::class, 'getCustomerAssetData'])->name('get-customer-data');
        
        // Installation & Removal (QR-enabled)
        Route::post('/install', [CustomerAssetController::class, 'markAsInstalled'])->name('install');
        Route::post('/qr-install', [CustomerAssetController::class, 'qrBasedInstall'])->name('qr-install');
        Route::post('/remove', [CustomerAssetController::class, 'markAsRemoved'])->name('remove');
        Route::post('/replace', [CustomerAssetController::class, 'replaceAsset'])->name('replace');
        
        // Cable length tracking
        Route::post('/update-cable-length', [CustomerAssetController::class, 'updateCableLength'])->name('update-cable-length');
        Route::get('/cable-usage-history/{id}', [CustomerAssetController::class, 'getCableUsageHistory'])->name('cable-usage-history');
        
        // Photos related to installation/removal
        Route::post('/upload-photos', [CustomerAssetController::class, 'uploadPhotos'])->name('upload-photos');
        Route::delete('/delete-photo/{id}', [CustomerAssetController::class, 'deletePhoto'])->name('delete-photo');

        // Audit (Manager, Super-Admin, Owner, LAD)
        Route::middleware(['role:Manager|Owner|Super-Admin|LAD'])->group(function () {
            Route::post('/audit-adjust', [CustomerAssetController::class, 'auditAdjust'])->name('audit-adjust');
            Route::get('/audit-report', [CustomerAssetController::class, 'auditReport'])->name('audit-report');
        });
    });

    // System Settings
    Route::middleware(['role:Manager|Owner|Super-Admin'])->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SystemSettingController::class, 'index'])->name('index');
        Route::post('/', [SystemSettingController::class, 'update'])->name('update');
        Route::get('/qr-settings', [SystemSettingController::class, 'qrSettings'])->name('qr-settings');
        Route::post('/qr-settings', [SystemSettingController::class, 'updateQRSettings'])->name('update-qr-settings');
    });

    // Enhanced Reports
    Route::middleware(['role:Manager|Owner|Super-Admin|LAD'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [AssetReportController::class, 'index'])->name('index');
        Route::get('/inventory', [AssetReportController::class, 'inventoryReport'])->name('inventory');
        Route::get('/debt', [AssetReportController::class, 'debtReport'])->name('debt');
        Route::get('/customer-installed', [AssetReportController::class, 'customerInstalledReport'])->name('customer-installed');
        Route::get('/usage', [AssetReportController::class, 'usageReport'])->name('usage');
        Route::get('/maintenance', [AssetReportController::class, 'maintenanceReport'])->name('maintenance');
        
        // Cable-specific reports
        Route::get('/cable-utilization', [AssetReportController::class, 'cableUtilizationReport'])->name('cable-utilization');
        Route::get('/cable-by-location', [AssetReportController::class, 'cableByLocationReport'])->name('cable-by-location');
        
        // QR Analytics Reports
        Route::get('/qr-analytics', [AssetReportController::class, 'qrAnalyticsReport'])->name('qr-analytics');
        Route::get('/scan-activity', [AssetReportController::class, 'scanActivityReport'])->name('scan-activity');
    });

    // API Routes for Mobile/AJAX
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/asset-info-by-qr/{qr_code}', [AssetManagementController::class, 'getAssetByQR'])->name('asset-info-by-qr');
        Route::post('/quick-qr-scan', [QRManagementController::class, 'quickQRScan'])->name('quick-qr-scan');
        Route::get('/technician-assets/{technicianId}', [AssetManagementController::class, 'getTechnicianAssets'])->name('technician-assets');
        Route::get('/warehouse-summary/{warehouseId}', [AssetManagementController::class, 'getWarehouseSummary'])->name('warehouse-summary');
        
        // Cable length tracking API
        Route::post('/update-cable-usage', [AssetManagementController::class, 'updateCableUsage'])->name('update-cable-usage');
        Route::get('/cable-roll-info/{qr_code}', [AssetManagementController::class, 'getCableRollInfo'])->name('cable-roll-info');
    });

    // inventory lama
    // Route::middleware(['role:Super-Admin|Owner|Manager|NOC|LAD|Warehouse|Technician'])->prefix('inventory')->name('inventory.')->group(function () {
    //     Route::get('/', [InventoryController::class, 'index'])->name('index');
    //     Route::get('/get-data', [InventoryController::class, 'getInventoryData'])->name('get-data');
    //     Route::get('/asset-detail/{id}', [InventoryController::class, 'getAssetDetail'])->name('asset-detail');
    //     Route::get('/get-tracked-units-data', [InventoryController::class, 'getTrackedUnitsData'])->name('get-tracked-units-data');
    //     Route::get('/tracked-asset-detail/{id}', [InventoryController::class, 'getTrackedAssetDetail'])->name('tracked-asset-detail');
        
    //     // Stock Management (restricted to managers and above)
    //     Route::middleware(['role:Manager|Owner|Super-Admin|NOC|Warehouse'])->group(function () {
    //         Route::post('/adjust-stock', [InventoryController::class, 'adjustStock'])->name('adjust-stock');
    //         Route::get('/movement-history/{id}', [InventoryController::class, 'getMovementHistory'])->name('movement-history');
    //         Route::post('/export', [InventoryController::class, 'exportInventory'])->name('export');
    //     });
    // });

    // Enhanced Inventory Management
    Route::middleware(['role:Super-Admin|Owner|Manager|NOC|LAD|Warehouse|Technician'])->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/get-data', [InventoryController::class, 'getInventoryData'])->name('get-data');
        Route::get('/asset-detail/{id}', [InventoryController::class, 'getAssetDetail'])->name('asset-detail');
        Route::get('/get-tracked-units-data', [InventoryController::class, 'getTrackedUnitsData'])->name('get-tracked-units-data');
        Route::get('/tracked-asset-detail/{id}', [InventoryController::class, 'getTrackedAssetDetail'])->name('tracked-asset-detail');
        
        // Cable-specific tracking
        Route::get('/get-cable-details', [InventoryController::class, 'getCableDetails'])->name('get-cable-details');
        Route::get('/cable-warehouse-breakdown/{id}', [InventoryController::class, 'getCableWarehouseBreakdown'])->name('cable-warehouse-breakdown');
        
        // Stock Management (restricted to managers and above)
        Route::middleware(['role:Manager|Owner|Super-Admin|NOC|Warehouse'])->group(function () {
            Route::post('/adjust-stock', [InventoryController::class, 'adjustStock'])->name('adjust-stock');
            Route::get('/movement-history/{id}', [InventoryController::class, 'getMovementHistory'])->name('movement-history');
            Route::post('/export', [InventoryController::class, 'exportInventory'])->name('export');
            Route::post('/bulk-stock-update', [InventoryController::class, 'bulkStockUpdate'])->name('bulk-stock-update');
        });
        
        // QR Integration
        Route::post('/generate-qr/{id}', [InventoryController::class, 'generateQRCode'])->name('generate-qr');
        Route::post('/print-qr-labels', [InventoryController::class, 'printQRLabels'])->name('print-qr-labels');
    });

    // Bulk Operations
    Route::middleware(['role:Manager|Owner|Super-Admin|NOC|Warehouse'])->prefix('bulk')->name('bulk.')->group(function () {
        Route::post('/generate-qr', [QRManagementController::class, 'bulkGenerateQR'])->name('generate-qr');
        Route::post('/print-qr', [QRManagementController::class, 'bulkPrintQR'])->name('print-qr');
        Route::post('/update-status', [QRManagementController::class, 'bulkUpdateStatus'])->name('update-status');
        Route::post('/move-assets', [InventoryController::class, 'bulkMoveAssets'])->name('move-assets');
    });






});