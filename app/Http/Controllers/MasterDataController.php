<?php
// File: app/Http/Controllers/MasterDataController.php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\AssetCategory;
use App\Models\Asset; // ADDED for Master Asset
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    // --- Office Management ---
    public function officesIndex()
    {
        $this->authorize('manage-master-data');
        return view('asset-management.master.offices.index');
    }

    public function getOfficesData(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $query = Office::query();
        return DataTables::of($query)
            ->addColumn('actions', function ($office) {
                return '<div class="d-flex"><a href="#" class="btn btn-sm btn-label-primary me-2 edit-office" data-id="'.$office->id.'">Edit</a><button type="button" class="btn btn-sm btn-label-danger delete-office" data-id="'.$office->id.'">Delete</button></div>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function storeOffice(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => 'required|string|max:255|unique:offices,name',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $office = Office::create($request->all());
        return response()->json(['success' => true, 'message' => 'Office created successfully.', 'office' => $office]);
    }

    public function editOffice(Office $office): JsonResponse
    {
        $this->authorize('manage-master-data');
        return response()->json($office);
    }

    public function updateOffice(Request $request, Office $office): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('offices')->ignore($office->id)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ]);

        $office->update($request->all());
        return response()->json(['success' => true, 'message' => 'Office updated successfully.', 'office' => $office]);
    }

    public function destroyOffice(Office $office): JsonResponse
    {
        $this->authorize('manage-master-data');
        // Check if any warehouses are linked to this office before deleting
        if ($office->warehouses()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete office. It has linked warehouses.'], 409);
        }
        $office->delete();
        return response()->json(['success' => true, 'message' => 'Office deleted successfully.']);
    }

    // --- Supplier Management ---
    public function suppliersIndex()
    {
        $this->authorize('manage-master-data');
        return view('asset-management.master.suppliers.index');
    }

    public function getSuppliersData(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $query = Supplier::query();
        return DataTables::of($query)
            ->addColumn('actions', function ($supplier) {
                return '<div class="d-flex"><a href="#" class="btn btn-sm btn-label-primary me-2 edit-supplier" data-id="'.$supplier->id.'">Edit</a><button type="button" class="btn btn-sm btn-label-danger delete-supplier" data-id="'.$supplier->id.'">Delete</button></div>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $supplier = Supplier::create($request->all());
        return response()->json(['success' => true, 'message' => 'Supplier created successfully.', 'supplier' => $supplier]);
    }

    public function editSupplier(Supplier $supplier): JsonResponse
    {
        $this->authorize('manage-master-data');
        return response()->json($supplier);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers')->ignore($supplier->id)],
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $supplier->update($request->all());
        return response()->json(['success' => true, 'message' => 'Supplier updated successfully.', 'supplier' => $supplier]);
    }

    public function destroySupplier(Supplier $supplier): JsonResponse
    {
        $this->authorize('manage-master-data');
        // Check if any asset receipts are linked to this supplier before deleting
        if ($supplier->assetReceipts()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete supplier. It has linked asset receipts.'], 409);
        }
        $supplier->delete();
        return response()->json(['success' => true, 'message' => 'Supplier deleted successfully.']);
    }

    // --- Warehouse Management ---
    public function warehousesIndex()
    {
        $this->authorize('manage-master-data');
        $offices = Office::all();
        return view('asset-management.master.warehouses.index', compact('offices'));
    }

    public function getWarehousesData(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $query = Warehouse::with('office');
        return DataTables::of($query)
            ->addColumn('office_name', function ($warehouse) {
                return $warehouse->office->name ?? '-';
            })
            ->addColumn('actions', function ($warehouse) {
                return '<div class="d-flex"><a href="#" class="btn btn-sm btn-label-primary me-2 edit-warehouse" data-id="'.$warehouse->id.'">Edit</a><button type="button" class="btn btn-sm btn-label-danger delete-warehouse" data-id="'.$warehouse->id.'">Delete</button></div>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => 'required|string|max:255|unique:warehouses,name',
            'location' => 'nullable|string',
            'office_id' => 'nullable|exists:offices,id',
        ]);

        $warehouse = Warehouse::create($request->all());
        return response()->json(['success' => true, 'message' => 'Warehouse created successfully.', 'warehouse' => $warehouse]);
    }

    public function editWarehouse(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('manage-master-data');
        return response()->json($warehouse->load('office'));
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('warehouses')->ignore($warehouse->id)],
            'location' => 'nullable|string',
            'office_id' => 'nullable|exists:offices,id',
        ]);

        $warehouse->update($request->all());
        return response()->json(['success' => true, 'message' => 'Warehouse updated successfully.', 'warehouse' => $warehouse]);
    }

    public function destroyWarehouse(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('manage-master-data');
        // Check if any assets or tracked assets are linked to this warehouse
        if ($warehouse->assets()->exists() || $warehouse->trackedAssets()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete warehouse. It has linked assets or tracked assets.'], 409);
        }
        $warehouse->delete();
        return response()->json(['success' => true, 'message' => 'Warehouse deleted successfully.']);
    }

    // --- Asset Category Management ---
    public function assetCategoriesIndex()
    {
        $this->authorize('manage-master-data');
        return view('asset-management.master.asset-categories.index');
    }

    public function getAssetCategoriesData(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $query = AssetCategory::query();
        return DataTables::of($query)
            ->addColumn('actions', function ($category) {
                return '
                    <div class="d-flex justify-content-sm-center align-items-sm-center gap-2">
                        <a href="#"
                            class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect edit-asset-category"
                            data-id="'.$category->id.'"
                            data-bs-toggle="tooltip"
                            title="Ubah">
                            <i class="ti ti-edit ti-sm"></i>
                        </a>
                        <button type="button"
                            class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect delete-asset-category"
                            data-id="'.$category->id.'"
                            data-bs-toggle="tooltip"
                            title="Hapus">
                            <i class="ti ti-trash ti-sm"></i>
                        </button>
                    </div>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function storeAssetCategory(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name',
            'unit' => 'required|string|max:50',
        ]);

        $category = AssetCategory::create($request->all());
        return response()->json(['success' => true, 'message' => 'Asset Category created successfully.', 'category' => $category]);
    }

    public function editAssetCategory(AssetCategory $assetCategory): JsonResponse
    {
        $this->authorize('manage-master-data');
        return response()->json($assetCategory);
    }

    public function updateAssetCategory(Request $request, AssetCategory $assetCategory): JsonResponse
    {
        $this->authorize('manage-master-data');
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('asset_categories')->ignore($assetCategory->id)],
            'unit' => 'required|string|max:50',
        ]);

        $assetCategory->update($request->all());
        return response()->json(['success' => true, 'message' => 'Asset Category updated successfully.', 'category' => $assetCategory]);
    }

    public function destroyAssetCategory(AssetCategory $assetCategory): JsonResponse
    {
        $this->authorize('manage-master-data');
        // Check if any assets are linked to this category
        if ($assetCategory->assets()->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete asset category. It has linked assets.'], 409);
        }
        $assetCategory->delete();
        return response()->json(['success' => true, 'message' => 'Asset Category deleted successfully.']);
    }

    // --- Master Assets (Jenis Aset) ---
    public function masterAssetsIndex()
    {
        $this->authorize('manage-master-data');
        $warehouses = Warehouse::all();
        $assetCategories = AssetCategory::all();
        
        // Get asset sub types for dropdown
        $assetSubTypes = [
            'cable_fiber' => 'Kabel Fiber Optik',
            'cable_copper' => 'Kabel Tembaga', 
            'cable_power' => 'Kabel Power',
            'router' => 'Router',
            'switch' => 'Switch',
            'ont' => 'ONT/Modem',
            'olt' => 'OLT',
            'access_point' => 'Access Point',
            'media_converter' => 'Media Converter',
            'odp' => 'ODP/Joint Box',
            'splitter' => 'Splitter',
            'patch_panel' => 'Patch Panel',
            'patch_core' => 'Patch Core',
            'connector' => 'Connector',
            'adapter' => 'Adapter',
            'splice_sleeve' => 'Splice Sleeve',
            'fusion_splicer' => 'Fusion Splicer',
            'otdr' => 'OTDR',
            'power_meter' => 'Power Meter',
            'cleaver' => 'Cleaver',
            'tools' => 'Tools/Equipment',
            'consumable' => 'Consumable/Other'
        ];
        
        return view('asset-management.master.assets.index', compact('warehouses', 'assetCategories', 'assetSubTypes'));
    }

    public function getMasterAssetsData(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        $query = Asset::with(['asset_category', 'warehouse']);
        
        return DataTables::of($query)
            ->addColumn('category_name', fn($asset) => $asset->asset_category->name ?? '-')
            ->addColumn('warehouse_name', fn($asset) => $asset->warehouse->name ?? '-')
            ->addColumn('asset_sub_type_display', function($asset) {
                if (!$asset->asset_sub_type) return '-';
                
                $subTypes = [
                    'cable_fiber' => 'Kabel Fiber',
                    'cable_copper' => 'Kabel Tembaga',
                    'router' => 'Router',
                    'switch' => 'Switch',
                    'ont' => 'ONT',
                    'olt' => 'OLT',
                    'patch_core' => 'Patch Core',
                    'connector' => 'Connector',
                    'tools' => 'Tools'
                ];
                
                return $subTypes[$asset->asset_sub_type] ?? ucfirst(str_replace('_', ' ', $asset->asset_sub_type));
            })
            ->addColumn('tracking_type', function($asset) {
                $badges = [];
                if ($asset->requires_qr_tracking) $badges[] = '<span class="badge bg-label-primary">QR</span>';
                if ($asset->requires_serial_number) $badges[] = '<span class="badge bg-label-info">SN</span>';
                if ($asset->requires_mac_address) $badges[] = '<span class="badge bg-label-warning">MAC</span>';
                if ($asset->isCableAsset()) $badges[] = '<span class="badge bg-label-success">Length</span>';
                
                return empty($badges) ? '<span class="badge bg-label-secondary">Simple</span>' : implode(' ', $badges);
            })
            ->addColumn('formatted_price', fn($asset) => 'Rp ' . number_format($asset->standard_price, 0, ',', '.'))
            ->addColumn('actions', function ($asset) {
                return '
                    <div class="d-flex justify-content-sm-center align-items-sm-center gap-1">
                        <button type="button"
                            class="btn btn-sm btn-icon btn-text-info rounded-pill waves-effect show-master-asset"
                            data-id="'.$asset->id.'"
                            data-bs-toggle="tooltip"
                            title="Lihat Detail">
                            <i class="ti ti-eye ti-sm"></i>
                        </button>
                        <button type="button"
                            class="btn btn-sm btn-icon btn-text-primary rounded-pill waves-effect edit-master-asset"
                            data-id="'.$asset->id.'"
                            data-bs-toggle="modal"
                            data-bs-target="#masterAssetModal"
                            title="Ubah">
                            <i class="ti ti-edit ti-sm"></i>
                        </button>
                        <button type="button"
                            class="btn btn-sm btn-icon btn-text-danger rounded-pill waves-effect delete-master-asset"
                            data-id="'.$asset->id.'"
                            data-bs-toggle="tooltip"
                            title="Hapus">
                            <i class="ti ti-trash ti-sm"></i>
                        </button>
                    </div>';
            })
            ->rawColumns(['tracking_type', 'actions'])
            ->make(true);
    }

    public function storeMasterAsset(Request $request): JsonResponse
    {
        $this->authorize('manage-master-data');
        
        $request->validate([
            'name' => 'required|string|max:255',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_type' => 'required|in:consumable,fixed',
            'asset_sub_type' => 'nullable|string|max:100',
            'asset_code' => 'required|string|max:255|unique:assets,asset_code',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'standard_length_per_roll' => 'nullable|numeric|min:0',
            'standard_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'tracking_instructions' => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $data = $request->all();
        
        // Handle boolean checkboxes
        $data['requires_qr_tracking'] = $request->has('requires_qr_tracking');
        $data['requires_serial_number'] = $request->has('requires_serial_number');
        $data['requires_mac_address'] = $request->has('requires_mac_address');
        
        // Auto-set tracking requirements based on asset sub type if not manually set
        if ($request->asset_sub_type && !$request->has('manual_tracking_override')) {
            $data = $this->autoSetTrackingRequirements($data);
        }

        $asset = Asset::create($data);
        return response()->json([
            'success' => true, 
            'message' => 'Master Asset created successfully.', 
            'asset' => $asset
        ]);
    }

    public function editMasterAsset(Asset $asset): JsonResponse
    {
        $this->authorize('manage-master-data');
        return response()->json($asset->load('asset_category', 'warehouse'));
    }

    public function updateMasterAsset(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('manage-master-data');
        
        $request->validate([
            'name' => 'required|string|max:255',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_type' => 'required|in:consumable,fixed',
            'asset_sub_type' => 'nullable|string|max:100',
            'asset_code' => ['required', 'string', 'max:255', Rule::unique('assets')->ignore($asset->id)],
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'standard_length_per_roll' => 'nullable|numeric|min:0',
            'standard_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'tracking_instructions' => 'nullable|string',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $data = $request->all();
        
        // Handle boolean checkboxes
        $data['requires_qr_tracking'] = $request->has('requires_qr_tracking');
        $data['requires_serial_number'] = $request->has('requires_serial_number');
        $data['requires_mac_address'] = $request->has('requires_mac_address');
        
        // Auto-set tracking requirements based on asset sub type if not manually overridden
        if ($request->asset_sub_type && !$request->has('manual_tracking_override')) {
            $data = $this->autoSetTrackingRequirements($data);
        }

        $asset->update($data);
        return response()->json([
            'success' => true, 
            'message' => 'Master Asset updated successfully.', 
            'asset' => $asset
        ]);
    }

    public function destroyMasterAsset(Asset $asset): JsonResponse
    {
        $this->authorize('manage-master-data');
        
        // Prevent deletion if there are any linked records
        if ($asset->trackedAssets()->exists() || 
            $asset->assetUsages()->exists() || 
            $asset->technicianAssetDebts()->exists() || 
            $asset->assetReceiptItems()->exists()) {
            return response()->json([
                'success' => false, 
                'message' => 'Cannot delete master asset. It has linked records (tracked units, usages, debts, or receipts).'
            ], 409);
        }
        
        $asset->delete();
        return response()->json([
            'success' => true, 
            'message' => 'Master Asset deleted successfully.'
        ]);
    }
































    private function autoSetTrackingRequirements(array $data): array
    {
        $subType = $data['asset_sub_type'] ?? '';
        
        switch ($subType) {
            case 'cable_fiber':
            case 'cable_copper':
                $data['requires_qr_tracking'] = true;
                $data['requires_serial_number'] = false;
                $data['requires_mac_address'] = false;
                break;
                
            case 'router':
            case 'switch':
            case 'ont':
            case 'olt':
            case 'access_point':
                $data['requires_qr_tracking'] = true;
                $data['requires_serial_number'] = true;
                $data['requires_mac_address'] = true;
                break;
                
            case 'media_converter':
            case 'odp':
            case 'splitter':
                $data['requires_qr_tracking'] = true;
                $data['requires_serial_number'] = true;
                $data['requires_mac_address'] = false;
                break;
                
            case 'fusion_splicer':
            case 'otdr':
            case 'power_meter':
            case 'tools':
                $data['requires_qr_tracking'] = true;
                $data['requires_serial_number'] = true;
                $data['requires_mac_address'] = false;
                break;
                
            case 'patch_core':
            case 'connector':
            case 'adapter':
            case 'splice_sleeve':
            case 'consumable':
            default:
                $data['requires_qr_tracking'] = false;
                $data['requires_serial_number'] = false;
                $data['requires_mac_address'] = false;
                break;
        }
        
        return $data;
    }

    /**
     * Get asset sub type configuration via AJAX
     */
    public function getAssetSubTypeConfig(Request $request): JsonResponse
    {
        $subType = $request->input('sub_type');
        $config = $this->autoSetTrackingRequirements(['asset_sub_type' => $subType]);
        
        return response()->json([
            'requires_qr_tracking' => $config['requires_qr_tracking'],
            'requires_serial_number' => $config['requires_serial_number'],
            'requires_mac_address' => $config['requires_mac_address'],
            'instructions' => $this->getSubTypeInstructions($subType)
        ]);
    }

    /**
     * Get instructions for asset sub type
     */
    private function getSubTypeInstructions(string $subType): string
    {
        $instructions = [
            'cable_fiber' => 'Setiap roll kabel harus dicatat panjangnya. QR Code untuk identifikasi roll.',
            'cable_copper' => 'Kabel tembaga/UTP. Catat panjang per roll untuk tracking inventory.',
            'router' => 'Serial Number dan MAC Address wajib untuk konfigurasi network.',
            'switch' => 'Serial Number dan MAC Address management interface wajib dicatat.',
            'ont' => 'Serial Number dan MAC Address untuk provisioning customer.',
            'olt' => 'Equipment critical dengan Serial Number dan MAC Address wajib.',
            'patch_core' => 'Consumable item, cukup tracking quantity saja.',
            'connector' => 'Consumable item, tidak memerlukan tracking individual.',
            'fusion_splicer' => 'Equipment mahal, perlu tracking untuk maintenance dan kalibrasi.',
            'otdr' => 'Testing equipment, tracking untuk maintenance dan kalibrasi.',
            'tools' => 'Tools/equipment perlu tracking untuk maintenance dan inventory.'
        ];
        
        return $instructions[$subType] ?? 'Masukkan detail sesuai kebutuhan tracking asset ini.';
    }
}