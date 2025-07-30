<?php
// File: app/Http/Controllers/AssetManagementController.php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\TechnicianAssetDebt;
use App\Models\TrackedAsset; // ADDED
use App\Models\CustomerInstalledAsset; // ADDED
use App\Services\QRCodeService; // Use the updated service
use App\Services\AssetManagementService; // Use the updated service
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class AssetManagementController extends Controller
{
    protected $qrService;
    protected $assetManagementService; // ADDED

    public function __construct(QRCodeService $qrService, AssetManagementService $assetManagementService) // MODIFIED
    {
        $this->qrService = $qrService;
        $this->assetManagementService = $assetManagementService; // ADDED
    }

    /**
     * Display asset management dashboard
     */
    public function index()
    {
        return view('asset-management.index');
    }

    /**
     * Get assets data untuk DataTables (MODIFIED for combined Assets & TrackedAssets)
     */
    public function getAssetsData(Request $request): JsonResponse
    {
        // Query untuk asset non-tracked
        $nonTrackedAssets = Asset::with(['asset_category', 'warehouse'])
            ->where('requires_qr_tracking', false)
            ->select('assets.*')
            ->get()
            ->map(function($asset) {
                $asset->display_id = 'A' . $asset->id; // Prefix for display ID
                $asset->qr_identifier = 'N/A'; // Non-tracked assets don't have individual QR
                $asset->is_qr_generated = true; // Always considered 'generated' for display
                $asset->stock_count = $asset->available_quantity;
                $asset->is_tracked_unit = false;
                $asset->current_asset_status = ($asset->available_quantity <= 0) ? 'out_of_stock' : 'available';
                return $asset;
            });

        // Query untuk tracked assets
        $trackedAssets = TrackedAsset::with(['asset.asset_category', 'currentWarehouse'])
            ->select(
                'tracked_assets.id',
                'tracked_assets.qr_code',
                'tracked_assets.serial_number',
                'tracked_assets.mac_address',
                'tracked_assets.current_status',
                'tracked_assets.qr_generated',
                'tracked_assets.current_warehouse_id',
                'assets.name',
                'assets.standard_price',
                'assets.asset_category_id'
            )
            ->join('assets', 'tracked_assets.asset_id', '=', 'assets.id')
            ->get()
            ->map(function($trackedAsset) {
                $trackedAsset->display_id = 'T' . $trackedAsset->id; // Prefix for display ID
                $trackedAsset->qr_identifier = $trackedAsset->qr_code ?? $trackedAsset->serial_number ?? $trackedAsset->mac_address ?? 'N/A';
                $trackedAsset->is_qr_generated = $trackedAsset->qr_generated;
                $trackedAsset->stock_count = ($trackedAsset->current_status == 'available' ? 1 : 0);
                $trackedAsset->is_tracked_unit = true;
                $trackedAsset->current_asset_status = $trackedAsset->current_status; // Use its actual status
                $trackedAsset->category_name = $trackedAsset->asset->asset_category->name ?? '-';
                $trackedAsset->warehouse_name = $trackedAsset->currentWarehouse->name ?? '-';
                $trackedAsset->formatted_price = 'Rp ' . number_format($trackedAsset->standard_price, 0, ',', '.');
                return $trackedAsset;
            });

        // Combine both collections
        $combinedAssets = $nonTrackedAssets->concat($trackedAssets);

        return DataTables::of($combinedAssets)
            ->addColumn('qr_code', function ($item) {
                return $item->qr_identifier; // Display QR/SN/MAC
            })
            ->addColumn('category_name', function ($item) {
                return $item->category_name ?? ($item->asset_category->name ?? '-');
            })
            ->addColumn('warehouse_name', function ($item) {
                return $item->warehouse_name ?? ($item->warehouse->name ?? '-');
            })
            ->addColumn('formatted_price', function ($item) {
                return $item->formatted_price ?? 'Rp ' . number_format($item->standard_price, 0, ',', '.');
            })
            ->addColumn('stock_display', function ($item) {
                return $item->stock_count; // Display available quantity / 1
            })
            ->addColumn('qr_status', function ($item) {
                if ($item->is_tracked_unit) {
                    return $item->is_qr_generated ? '<span class="badge bg-label-success"><i class="ti ti-check ti-xs"></i> Generated</span>' : '<span class="badge bg-label-warning"><i class="ti ti-x ti-xs"></i> Not Generated</span>';
                } else {
                    return '<span class="badge bg-label-info">N/A</span>';
                }
            })
            ->addColumn('stock_status', function ($item) {
                if ($item->is_tracked_unit) {
                    $statusClass = [
                        'available' => 'bg-label-success',
                        'loaned' => 'bg-label-info',
                        'in_transit' => 'bg-label-info',
                        'installed' => 'bg-label-primary',
                        'damaged' => 'bg-label-warning',
                        'in_repair' => 'bg-label-warning',
                        'awaiting_return_to_supplier' => 'bg-label-warning',
                        'written_off' => 'bg-label-danger',
                        'scrap' => 'bg-label-danger',
                        'returned_to_supplier' => 'bg-label-success', // Re-available
                        'lost' => 'bg-label-danger',
                    ];
                    return '<span class="badge ' . ($statusClass[$item->current_asset_status] ?? 'bg-label-secondary') . '">' . ucfirst(str_replace('_', ' ', $item->current_asset_status)) . '</span>';
                } else {
                    // For non-tracked assets, base on available_quantity
                    if ($item->available_quantity <= 0) {
                        return '<span class="badge bg-label-danger">Out of Stock</span>';
                    } elseif ($item->available_quantity <= 5) { // Threshold for low stock
                        return '<span class="badge bg-label-warning">Low Stock</span>';
                    } else {
                        return '<span class="badge bg-label-success">Available</span>';
                    }
                }
            })
            ->addColumn('actions', function ($item) {
                $actions = '<div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical ti-xs"></i>
                    </button>
                    <ul class="dropdown-menu">';
                
                if ($item->is_tracked_unit) {
                    // Actions for individual tracked assets
                    if (!$item->is_qr_generated) {
                        $actions .= '<li><a class="dropdown-item generate-qr" data-id="' . $item->id . '">
                            <i class="ti ti-qrcode me-2"></i>Generate QR
                        </a></li>';
                    }
                    $actions .= '<li><a class="dropdown-item view-qr" data-id="' . $item->id . '">
                            <i class="ti ti-eye me-2"></i>View QR
                        </a></li>';
                    // Example: Link to maintenance/edit for tracked asset
                    $actions .= '<li><a class="dropdown-item" href="'.route('asset-management.maintenance.index', ['tracked_asset_id' => $item->id]).'">
                            <i class="ti ti-tool me-2"></i>Manage State
                        </a></li>';
                } else {
                    // Actions for non-tracked asset (master record)
                    $actions .= '<li><a class="dropdown-item edit-master-asset" data-id="' . $item->id . '">
                            <i class="ti ti-edit me-2"></i>Edit Master
                        </a></li>';
                }
                $actions .= '</ul></div>';
                return $actions;
            })
            ->rawColumns(['qr_status', 'stock_status', 'actions'])
            ->make(true);
    }

    /**
     * Generate QR code untuk tracked asset (MODIFIED)
     */
    public function generateQR(Request $request): JsonResponse
    {
        try {
            // asset_id here refers to tracked_asset_id from the UI
            $trackedAssetId = $request->asset_id; 
            $qrContent = $this->qrService->generateForTrackedAsset($trackedAssetId);
            
            return response()->json([
                'success' => true,
                'message' => 'QR Code berhasil digenerate',
                'qr_code_content' => $qrContent // Send content back for display
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate QR Code: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate batch QR codes (MODIFIED)
     */
    public function generateBatchQR(Request $request): JsonResponse
    {
        $request->validate([
            'asset_ids' => 'required|array', // These are now tracked_asset_ids
            'asset_ids.*' => 'exists:tracked_assets,id'
        ]);

        try {
            $results = $this->qrService->generateBatchTrackedAssets($request->asset_ids);
            
            return response()->json([
                'success' => true,
                'message' => 'Batch QR generation completed',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate batch QR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print QR labels (MODIFIED)
     */
    public function printQRLabels(Request $request)
    {
        $request->validate([
            'asset_ids' => 'required|array', // These are now tracked_asset_ids
            'asset_ids.*' => 'exists:tracked_assets,id'
        ]);

        try {
            $pdfPath = $this->qrService->printLabelsForTrackedAssets($request->asset_ids);
            
            return response()->download(storage_path('app/' . $pdfPath))
                ->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal print QR labels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get asset by QR code (MODIFIED)
     */
    public function getAssetByQR(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string'
        ]);

        $trackedAsset = $this->qrService->getTrackedAssetByQR($request->qr_code); // New service method

        if (!$trackedAsset) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code/Serial Number/MAC Address tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'asset' => [
                'id' => $trackedAsset->id,
                'name' => $trackedAsset->asset->name,
                'category' => $trackedAsset->asset->asset_category->name ?? '-',
                'price' => $trackedAsset->asset->standard_price,
                'formatted_price' => 'Rp ' . number_format($trackedAsset->asset->standard_price, 0, ',', '.'),
                'current_status' => $trackedAsset->current_status,
                'qr_code' => $trackedAsset->qr_code,
                'serial_number' => $trackedAsset->serial_number,
                'mac_address' => $trackedAsset->mac_address,
                'warehouse' => $trackedAsset->currentWarehouse->name ?? '-',
                'initial_length' => $trackedAsset->initial_length,
                'current_length' => $trackedAsset->current_length,
                'unit_of_measure' => $trackedAsset->unit_of_measure,
            ]
        ]);
    }

    public function getStats(): JsonResponse
    {
        try {
            // Utilize the updated AssetManagementService for stats
            $stats = $this->assetManagementService->getDashboardStats();
            return response()->json([
                'total_assets' => ($stats['assets']['total'] ?? 0) + ($stats['tracked_assets_count'] ?? 0), // Adjust total assets calculation
                'qr_generated' => $stats['assets']['qr_generated'] ?? 0,
                'active_debts' => $stats['debts']['active_technicians'] ?? 0,
                'total_debt_value' => 'Rp ' . number_format($stats['debts']['total_debt_value'] ?? 0, 0, ',', '.'),
                // Add more stats here if needed by the dashboard blade
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load stats: ' . $e->getMessage()
            ], 500);
        }
    }
}