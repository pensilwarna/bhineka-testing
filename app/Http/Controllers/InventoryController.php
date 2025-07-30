<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\TrackedAsset;
use App\Models\Warehouse;
use App\Models\AssetCategory;
use App\Models\TechnicianAssetDebt;
use App\Models\CustomerInstalledAsset;
use App\Models\AssetReceipt;
use App\Models\AssetReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;

class InventoryController extends Controller
{
    public function index()
    {
        $this->authorize('view-asset-inventory');
        
        $warehouses = Warehouse::orderBy('name')->get();
        $categories = AssetCategory::orderBy('name')->get();
        
        // Get comprehensive inventory statistics
        $stats = $this->getInventoryStatistics();
        
        return view('asset-management.inventory.index', compact('warehouses', 'categories', 'stats'));
    }

    public function getInventoryData(Request $request): JsonResponse
    {
        $this->authorize('view-asset-inventory');

        $query = Asset::with(['asset_category', 'warehouse'])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->category_id, fn($q) => $q->where('asset_category_id', $request->category_id))
            ->when($request->asset_type, fn($q) => $q->where('asset_type', $request->asset_type))
            ->when($request->tracking_type, function($q) use ($request) {
                if ($request->tracking_type === 'tracked') {
                    $q->where('requires_qr_tracking', true);
                } else {
                    $q->where('requires_qr_tracking', false);
                }
            })
            ->when($request->stock_status, function($q) use ($request) {
                switch ($request->stock_status) {
                    case 'out_of_stock':
                        $q->outOfStock();
                        break;
                    case 'low_stock':
                        $q->lowStock();
                        break;
                    case 'in_stock':
                        $q->available();
                        break;
                }
            });

        return DataTables::of($query)
            ->addColumn('category_name', fn($asset) => $asset->asset_category->name ?? '-')
            ->addColumn('warehouse_name', fn($asset) => $asset->warehouse->name ?? '-')
            ->addColumn('available_stock', function($asset) {
                $available = $this->getAvailableStock($asset);
                $color = $available == 0 ? 'danger' : ($available <= 5 ? 'warning' : 'success');
                return "<span class='badge bg-label-{$color}'>{$available}</span>";
            })
            ->addColumn('total_stock', function($asset) {
                $total = $this->getTotalStock($asset);
                return "<span class='badge bg-label-info'>{$total}</span>";
            })
            ->addColumn('in_use_stock', function($asset) {
                $inUse = $this->getInUseStock($asset);
                return $inUse > 0 ? "<span class='badge bg-label-warning'>{$inUse}</span>" : "<span class='text-muted'>0</span>";
            })
            ->addColumn('damaged_stock', function($asset) {
                $damaged = $this->getDamagedStock($asset);
                return $damaged > 0 ? "<span class='badge bg-label-danger'>{$damaged}</span>" : "<span class='text-muted'>0</span>";
            })
            ->addColumn('stock_status', function($asset) {
                $available = $this->getAvailableStock($asset);
                
                if ($available == 0) {
                    return '<span class="badge bg-label-danger"><i class="ti ti-ban me-1"></i>Habis</span>';
                } elseif ($available <= 5) {
                    return '<span class="badge bg-label-warning"><i class="ti ti-alert-triangle me-1"></i>Menipis</span>';
                } else {
                    return '<span class="badge bg-label-success"><i class="ti ti-check me-1"></i>Tersedia</span>';
                }
            })
            ->addColumn('tracking_type', function($asset) {
                if ($asset->requires_qr_tracking) {
                    $trackedCount = TrackedAsset::where('asset_id', $asset->id)->count();
                    $qrGenerated = TrackedAsset::where('asset_id', $asset->id)->where('qr_generated', true)->count();
                    
                    return "<span class='badge bg-label-primary'><i class='ti ti-qrcode me-1'></i>QR Tracked ({$qrGenerated}/{$trackedCount})</span>";
                } else {
                    return '<span class="badge bg-label-secondary"><i class="ti ti-package me-1"></i>Simple</span>';
                }
            })
            ->addColumn('length_summary', function($asset) {
                if ($asset->asset_sub_type && strpos($asset->asset_sub_type, 'cable') !== false) {
                    $totalLength = TrackedAsset::where('asset_id', $asset->id)
                        ->where('current_status', 'available')
                        ->sum('current_length');
                    
                    $totalRolls = TrackedAsset::where('asset_id', $asset->id)
                        ->where('current_status', 'available')
                        ->count();
                    
                    if ($totalLength > 0) {
                        return "
                            <div class='text-center'>
                                <div><strong>" . number_format($totalLength, 1) . "m</strong></div>
                                <small class='text-muted'>{$totalRolls} roll(s)</small>
                            </div>
                        ";
                    }
                }
                return '<span class="text-muted">-</span>';
            })
            ->addColumn('formatted_price', fn($asset) => 'Rp ' . number_format($asset->standard_price, 0, ',', '.'))
            ->addColumn('last_receipt', function($asset) {
                $lastReceipt = AssetReceiptItem::where('asset_id', $asset->id)
                    ->with('assetReceipt')
                    ->latest()
                    ->first();
                
                return $lastReceipt 
                    ? $lastReceipt->assetReceipt->receipt_date->format('d M Y')
                    : '<span class="text-muted">-</span>';
            })
            ->addColumn('actions', function($asset) {
                $actions = "
                    <div class='btn-group' role='group'>
                        <button type='button' class='btn btn-sm btn-outline-info view-asset-detail' 
                                data-id='{$asset->id}' title='Detail Stok'>
                            <i class='ti ti-eye'></i>
                        </button>";
                
                if ($asset->requires_qr_tracking) {
                    $actions .= "
                        <button type='button' class='btn btn-sm btn-outline-primary view-tracked-units' 
                                data-id='{$asset->id}' title='Unit Tracked'>
                            <i class='ti ti-qrcode'></i>
                        </button>";
                }
                
                // Special button for cable assets
                if ($asset->asset_sub_type && strpos($asset->asset_sub_type, 'cable') !== false) {
                    $actions .= "
                        <button type='button' class='btn btn-sm btn-outline-success view-cable-details' 
                                data-id='{$asset->id}' title='Detail Kabel'>
                            <i class='ti ti-cable'></i>
                        </button>";
                }
                
                $actions .= "
                        <div class='btn-group' role='group'>
                            <button type='button' class='btn btn-sm btn-outline-secondary dropdown-toggle' 
                                    data-bs-toggle='dropdown'><i class='ti ti-dots-vertical'></i></button>
                            <ul class='dropdown-menu'>
                                <li><a class='dropdown-item view-movement-history' href='#' data-id='{$asset->id}'>
                                    <i class='ti ti-history me-2'></i>History Pergerakan</a></li>";
                
                if ($asset->requires_qr_tracking) {
                    $actions .= "
                                <li><a class='dropdown-item generate-missing-qr' href='#' data-id='{$asset->id}'>
                                    <i class='ti ti-qrcode me-2'></i>Generate QR</a></li>
                                <li><a class='dropdown-item print-all-qr' href='#' data-id='{$asset->id}'>
                                    <i class='ti ti-printer me-2'></i>Print All QR</a></li>";
                }
                
                $actions .= "
                                <li><a class='dropdown-item export-asset-data' href='#' data-id='{$asset->id}'>
                                    <i class='ti ti-download me-2'></i>Export Data</a></li>";
                
                if (auth()->user()->can('manage-assets')) {
                    $actions .= "
                                <li><hr class='dropdown-divider'></li>
                                <li><a class='dropdown-item adjust-stock' href='#' data-id='{$asset->id}'>
                                    <i class='ti ti-adjustments me-2'></i>Adjust Stock</a></li>";
                }
                
                $actions .= "
                            </ul>
                        </div>
                    </div>";
                
                return $actions;
            })
            ->rawColumns(['available_stock', 'total_stock', 'in_use_stock', 'damaged_stock', 'stock_status', 'tracking_type', 'length_summary', 'last_receipt', 'actions'])
            ->make(true);
    }

    public function getTrackedUnitsData(Request $request): JsonResponse
    {
        $this->authorize('view-asset-inventory');
        
        $assetId = $request->asset_id;
        
        $query = TrackedAsset::with(['asset', 'currentWarehouse', 'assetReceiptItem.assetReceipt'])
            ->where('asset_id', $assetId)
            ->when($request->status, fn($q) => $q->where('current_status', $request->status))
            ->when($request->warehouse_id, fn($q) => $q->where('current_warehouse_id', $request->warehouse_id));

        return DataTables::of($query)
            ->addColumn('qr_code_display', function($tracked) {
                $qrIcon = $tracked->qr_generated ? 'ti-check text-success' : 'ti-clock text-warning';
                return "
                    <div class='d-flex align-items-center'>
                        <span class='me-2'>{$tracked->qr_code}</span>
                        <i class='ti {$qrIcon} me-2'></i>
                        <button class='btn btn-xs btn-outline-primary view-qr-code' data-id='{$tracked->id}' title='View QR'>
                            <i class='ti ti-qrcode'></i>
                        </button>
                    </div>";
            })
            ->addColumn('asset_name', fn($tracked) => $tracked->asset->name ?? '-')
            ->addColumn('warehouse_name', fn($tracked) => $tracked->currentWarehouse->name ?? '-')
            ->addColumn('status_badge', function($tracked) {
                $statusConfig = $this->getStatusConfig($tracked->current_status);
                return "<span class='badge bg-label-{$statusConfig['color']}'><i class='ti {$statusConfig['icon']} me-1'></i>{$statusConfig['label']}</span>";
            })
            ->addColumn('device_info', function($tracked) {
                $info = [];
                if ($tracked->serial_number) $info[] = "S/N: {$tracked->serial_number}";
                if ($tracked->mac_address) $info[] = "MAC: {$tracked->mac_address}";
                
                return !empty($info) ? implode('<br>', $info) : '<span class="text-muted">-</span>';
            })
            ->addColumn('length_info', function($tracked) {
                if ($tracked->initial_length) {
                    $initial = number_format($tracked->initial_length, 1);
                    $current = number_format($tracked->current_length, 1);
                    $used = $tracked->initial_length - $tracked->current_length;
                    $percentage = round(($tracked->current_length / $tracked->initial_length) * 100, 1);
                    
                    $color = $percentage > 80 ? 'success' : ($percentage > 50 ? 'warning' : 'danger');
                    
                    return "
                        <div>
                            <div class='d-flex justify-content-between'>
                                <span><strong>{$current}m</strong> / {$initial}m</span>
                                <small class='text-muted'>{$percentage}%</small>
                            </div>
                            <div class='progress mt-1' style='height: 4px;'>
                                <div class='progress-bar bg-{$color}' style='width: {$percentage}%'></div>
                            </div>
                            <small class='text-muted'>Used: " . number_format($used, 1) . "m</small>
                        </div>";
                }
                return '<span class="text-muted">-</span>';
            })
            ->addColumn('received_date', function($tracked) {
                return $tracked->assetReceiptItem?->assetReceipt?->receipt_date?->format('d M Y') ?? '-';
            })
            ->addColumn('current_usage', function($tracked) {
                $usage = [];
                
                // Check if with technician
                $technicianDebt = TechnicianAssetDebt::where('tracked_asset_id', $tracked->id)
                    ->where('status', 'active')
                    ->with('technician')
                    ->first();
                
                if ($technicianDebt) {
                    $usage[] = "<small class='text-warning'>With: {$technicianDebt->technician->name}</small>";
                }
                
                // Check if installed at customer
                $customerInstall = CustomerInstalledAsset::where('tracked_asset_id', $tracked->id)
                    ->where('status', 'installed')
                    ->with('customer')
                    ->first();
                
                if ($customerInstall) {
                    $usage[] = "<small class='text-info'>Customer: {$customerInstall->customer->name}</small>";
                }
                
                return !empty($usage) ? implode('<br>', $usage) : '<span class="text-muted">Available</span>';
            })
            ->addColumn('actions', function($tracked) {
                return "
                    <div class='btn-group btn-group-sm'>
                        <button type='button' class='btn btn-outline-info view-tracked-detail' 
                                data-id='{$tracked->id}' title='Detail'>
                            <i class='ti ti-eye'></i>
                        </button>
                        <button type='button' class='btn btn-outline-primary print-single-qr' 
                                data-id='{$tracked->id}' title='Print QR'>
                            <i class='ti ti-printer'></i>
                        </button>
                        <button type='button' class='btn btn-outline-success download-qr' 
                                data-id='{$tracked->id}' title='Download QR'>
                            <i class='ti ti-download'></i>
                        </button>
                    </div>";
            })
            ->rawColumns(['qr_code_display', 'status_badge', 'device_info', 'length_info', 'current_usage', 'actions'])
            ->make(true);
    }

    /**
     * Get detailed cable information for cable assets
     */
    public function getCableDetails(Request $request): JsonResponse
    {
        $this->authorize('view-asset-inventory');
        
        $assetId = $request->asset_id;
        $asset = Asset::findOrFail($assetId);
        
        // Ensure this is a cable asset
        if (!$asset->asset_sub_type || strpos($asset->asset_sub_type, 'cable') === false) {
            return response()->json([
                'success' => false,
                'message' => 'Asset ini bukan jenis kabel'
            ], 422);
        }
        
        $cableData = TrackedAsset::where('asset_id', $assetId)
            ->select([
                'id', 'qr_code', 'serial_number', 'initial_length', 'current_length',
                'current_status', 'current_warehouse_id', 'notes', 'created_at'
            ])
            ->with(['currentWarehouse:id,name'])
            ->get();
        
        $summary = [
            'total_rolls' => $cableData->count(),
            'available_rolls' => $cableData->where('current_status', 'available')->count(),
            'in_use_rolls' => $cableData->whereIn('current_status', ['loaned', 'installed'])->count(),
            'damaged_rolls' => $cableData->whereIn('current_status', ['damaged', 'in_repair'])->count(),
            'total_initial_length' => $cableData->sum('initial_length'),
            'total_current_length' => $cableData->sum('current_length'),
            'total_used_length' => $cableData->sum(function($item) {
                return $item->initial_length - $item->current_length;
            }),
            'available_length' => $cableData->where('current_status', 'available')->sum('current_length'),
            'in_use_length' => $cableData->whereIn('current_status', ['loaned', 'installed'])->sum('current_length'),
        ];
        
        $summary['usage_percentage'] = $summary['total_initial_length'] > 0 
            ? round(($summary['total_used_length'] / $summary['total_initial_length']) * 100, 1)
            : 0;
        
        // Group by warehouse
        $warehouseBreakdown = $cableData->groupBy('current_warehouse_id')->map(function($items) {
            return [
                'warehouse_name' => $items->first()->currentWarehouse->name ?? 'Unknown',
                'rolls_count' => $items->count(),
                'total_length' => $items->sum('current_length'),
                'available_length' => $items->where('current_status', 'available')->sum('current_length')
            ];
        })->values();
        
        // Get rolls that are running low (less than 20% remaining)
        $lowRolls = $cableData->filter(function($item) {
            return $item->initial_length > 0 && 
                   ($item->current_length / $item->initial_length) < 0.2;
        })->values();
        
        return response()->json([
            'success' => true,
            'asset' => $asset,
            'summary' => $summary,
            'warehouse_breakdown' => $warehouseBreakdown,
            'low_rolls' => $lowRolls,
            'cable_data' => $cableData
        ]);
    }

    public function getAssetDetail($id): JsonResponse
    {
        $this->authorize('view-asset-inventory');
        
        $asset = Asset::with(['asset_category', 'warehouse'])->findOrFail($id);
        
        // Get comprehensive stock information
        $stockInfo = $this->getDetailedStockInfo($asset);
        
        // Get recent movements
        $recentMovements = $this->getRecentMovements($id);
        
        // Get valuation
        $valuation = $this->getAssetValuation($asset);
        
        // Special handling for cable assets
        $cableInfo = null;
        if ($asset->asset_sub_type && strpos($asset->asset_sub_type, 'cable') !== false) {
            $cableInfo = $this->getCableStockSummary($asset);
        }
        
        return response()->json([
            'asset' => $asset,
            'stock_info' => $stockInfo,
            'cable_info' => $cableInfo,
            'recent_movements' => $recentMovements,
            'valuation' => $valuation
        ]);
    }

    public function getTrackedAssetDetail($id): JsonResponse
    {
        $this->authorize('view-asset-inventory');
        
        $trackedAsset = TrackedAsset::with([
            'asset.asset_category',
            'currentWarehouse',
            'assetReceiptItem.assetReceipt.supplier',
            'technicianAssetDebts' => function($query) {
                $query->where('status', 'active')->with('technician');
            },
            'customerInstalledAssets' => function($query) {
                $query->where('status', 'installed')->with(['customer', 'serviceLocation']);
            }
        ])->findOrFail($id);
        
        // Get usage history for cable assets
        $usageHistory = [];
        if ($trackedAsset->initial_length && $trackedAsset->initial_length != $trackedAsset->current_length) {
            // Get usage history from customer installed assets
            $usageHistory = CustomerInstalledAsset::where('tracked_asset_id', $id)
                ->with(['customer', 'serviceLocation', 'ticket'])
                ->get()
                ->map(function($install) {
                    return [
                        'type' => 'installation',
                        'date' => $install->installation_date,
                        'location' => $install->serviceLocation->address ?? 'Unknown',
                        'customer' => $install->customer->name,
                        'length_used' => $install->installed_length,
                        'ticket_id' => $install->ticket_id
                    ];
                });
        }
        
        return response()->json([
            'tracked_asset' => $trackedAsset,
            'usage_history' => $usageHistory
        ]);
    }

    // ... existing methods remain the same ...

    // Helper Methods
    private function getInventoryStatistics(): array
    {
        $totalAssetTypes = Asset::count();
        
        $totalTrackedUnits = TrackedAsset::count();
        $totalSimpleQuantity = Asset::where('requires_qr_tracking', false)->sum('total_quantity');
        $totalUnits = $totalTrackedUnits + $totalSimpleQuantity;
        
        $availableTracked = TrackedAsset::where('current_status', 'available')->count();
        $availableSimple = Asset::where('requires_qr_tracking', false)->sum('available_quantity');
        $totalAvailable = $availableTracked + $availableSimple;
        
        $withTechnicians = TechnicianAssetDebt::where('status', 'active')->sum('current_debt_quantity');
        
        $installedAtCustomers = CustomerInstalledAsset::where('status', 'installed')->sum('quantity_installed');
        
        $damagedAssets = TrackedAsset::whereIn('current_status', ['damaged', 'in_repair'])->count();
        
        $lowStockAssets = Asset::lowStock()->count();
        $outOfStockAssets = Asset::outOfStock()->count();
        
        // Cable-specific statistics
        $totalCableLength = TrackedAsset::whereHas('asset', function($q) {
            $q->where('asset_sub_type', 'like', '%cable%');
        })->sum('current_length');
        
        $availableCableLength = TrackedAsset::whereHas('asset', function($q) {
            $q->where('asset_sub_type', 'like', '%cable%');
        })->where('current_status', 'available')->sum('current_length');
        
        // Valuation
        $totalValue = Asset::sum(DB::raw('total_quantity * standard_price'));
        $availableValue = Asset::sum(DB::raw('available_quantity * standard_price'));
        
        return [
            'total_asset_types' => $totalAssetTypes,
            'total_units' => $totalUnits,
            'total_available' => $totalAvailable,
            'with_technicians' => $withTechnicians,
            'installed_at_customers' => $installedAtCustomers,
            'damaged_assets' => $damagedAssets,
            'low_stock_assets' => $lowStockAssets,
            'out_of_stock_assets' => $outOfStockAssets,
            'total_cable_length' => $totalCableLength,
            'available_cable_length' => $availableCableLength,
            'total_value' => $totalValue,
            'available_value' => $availableValue,
            'utilization_rate' => $totalUnits > 0 ? round((($totalUnits - $totalAvailable) / $totalUnits) * 100, 1) : 0
        ];
    }

    private function getCableStockSummary($asset): array
    {
        $cableData = TrackedAsset::where('asset_id', $asset->id)->get();
        
        return [
            'total_rolls' => $cableData->count(),
            'available_rolls' => $cableData->where('current_status', 'available')->count(),
            'total_length' => $cableData->sum('current_length'),
            'available_length' => $cableData->where('current_status', 'available')->sum('current_length'),
            'used_length' => $cableData->sum(function($item) {
                return $item->initial_length - $item->current_length;
            }),
            'average_roll_length' => $cableData->where('current_status', 'available')->avg('current_length'),
            'longest_roll' => $cableData->where('current_status', 'available')->max('current_length'),
            'shortest_roll' => $cableData->where('current_status', 'available')->min('current_length')
        ];
    }

    private function getAvailableStock($asset): int
    {
        if ($asset->requires_qr_tracking) {
            return TrackedAsset::where('asset_id', $asset->id)
                ->where('current_status', 'available')
                ->count();
        }
        return $asset->available_quantity;
    }

    private function getTotalStock($asset): int
    {
        if ($asset->requires_qr_tracking) {
            return TrackedAsset::where('asset_id', $asset->id)->count();
        }
        return $asset->total_quantity;
    }

    private function getInUseStock($asset): int
    {
        if ($asset->requires_qr_tracking) {
            return TrackedAsset::where('asset_id', $asset->id)
                ->whereIn('current_status', ['loaned', 'installed', 'in_transit'])
                ->count();
        } else {
            $debtQuantity = TechnicianAssetDebt::where('asset_id', $asset->id)
                ->where('status', 'active')
                ->sum('current_debt_quantity');
            
            $installedQuantity = CustomerInstalledAsset::where('asset_id', $asset->id)
                ->where('status', 'installed')
                ->sum('quantity_installed');
            
            return $debtQuantity + $installedQuantity;
        }
    }

    private function getDamagedStock($asset): int
    {
        if ($asset->requires_qr_tracking) {
            return TrackedAsset::where('asset_id', $asset->id)
                ->whereIn('current_status', ['damaged', 'in_repair'])
                ->count();
        }
        return 0;
    }

    private function getStatusConfig($status): array
    {
        $configs = [
            'available' => ['color' => 'success', 'icon' => 'ti-check', 'label' => 'Available'],
            'in_transit' => ['color' => 'info', 'icon' => 'ti-truck', 'label' => 'In Transit'],
            'loaned' => ['color' => 'warning', 'icon' => 'ti-user', 'label' => 'With Technician'],
            'installed' => ['color' => 'primary', 'icon' => 'ti-home', 'label' => 'Installed'],
            'damaged' => ['color' => 'danger', 'icon' => 'ti-alert-circle', 'label' => 'Damaged'],
            'in_repair' => ['color' => 'warning', 'icon' => 'ti-tools', 'label' => 'In Repair'],
            'lost' => ['color' => 'danger', 'icon' => 'ti-help', 'label' => 'Lost'],
            'written_off' => ['color' => 'dark', 'icon' => 'ti-ban', 'label' => 'Written Off']
        ];
        
        return $configs[$status] ?? ['color' => 'secondary', 'icon' => 'ti-question-mark', 'label' => ucfirst($status)];
    }

    private function getDetailedStockInfo($asset): array
    {
        return [
            'total_received' => AssetReceiptItem::where('asset_id', $asset->id)->sum('quantity_received'),
            'total_receipts' => AssetReceiptItem::where('asset_id', $asset->id)->count(),
            'average_price' => AssetReceiptItem::where('asset_id', $asset->id)->avg('actual_unit_price'),
            'last_receipt_date' => AssetReceiptItem::where('asset_id', $asset->id)
                ->with('assetReceipt')
                ->latest()
                ->first()?->assetReceipt?->receipt_date?->format('d M Y')
        ];
    }

    private function getRecentMovements($assetId, $limit = 10): array
    {
        // Implementation will be added later for movement tracking
        return [];
    }

    private function getAssetValuation($asset): array
    {
        if ($asset->requires_qr_tracking) {
            $trackedAssets = TrackedAsset::where('asset_id', $asset->id)->count();
            return [
                'total_value' => $trackedAssets * $asset->standard_price,
                'available_value' => TrackedAsset::where('asset_id', $asset->id)
                    ->where('current_status', 'available')
                    ->count() * $asset->standard_price
            ];
        } else {
            return [
                'total_value' => $asset->total_quantity * $asset->standard_price,
                'available_value' => $asset->available_quantity * $asset->standard_price
            ];
        }
    }
}