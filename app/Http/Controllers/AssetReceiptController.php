<?php
namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetReceipt;
use App\Models\AssetReceiptItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\AssetTrackingConfig;
use App\Models\TrackedAsset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class AssetReceiptController extends Controller
{
    public function index()
    {
        $this->authorize('view-asset-receipts');
        $suppliers = Supplier::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();
        $assets = Asset::with('asset_category')->orderBy('name')->get();
        
        return view('asset-management.receipts.index', compact('suppliers', 'warehouses', 'assets'));
    }

    public function getReceiptsData(Request $request): JsonResponse
    {
        $this->authorize('view-asset-receipts');

        $query = AssetReceipt::with(['supplier', 'receivedBy'])
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->start_date && $request->end_date, fn($q) => $q->whereDate('receipt_date', '>=', $request->start_date)
                ->whereDate('receipt_date', '<=', $request->end_date));

        return DataTables::of($query)
            ->editColumn('receipt_date', fn($r) => Carbon::parse($r->receipt_date)->format('d M Y'))
            ->editColumn('total_value', fn($r) => 'Rp ' . number_format($r->total_value, 0, ',', '.'))
            ->addColumn('supplier_name', fn($r) => $r->supplier->name ?? '-')
            ->addColumn('received_by_name', fn($r) => $r->receivedBy->name ?? '-')
            ->addColumn('items_count', fn($r) => $r->items()->count() . ' item(s)')
            ->addColumn('actions', function($receipt) {
                $actions = '';
                $today = Carbon::now()->format('Y-m-d');
                $receiptDate = Carbon::parse($receipt->created_at)->format('Y-m-d');
                $canDelete = ($receiptDate === $today); // Only allow delete on same day
                
                // Check if user has delete permission (optional)
                $userCanDelete = auth()->user()->can('create-asset-receipt'); // Or create specific permission
                
                $actions .= "
                    <div class='dropdown'>
                        <button type='button' class='btn btn-sm btn-icon btn-text-secondary rounded-pill' data-bs-toggle='dropdown' aria-expanded='false'>
                            <i class='ti ti-dots-vertical'></i>
                        </button>
                        <div class='dropdown-menu dropdown-menu-end'>
                            <h6 class='dropdown-header'>Aksi</h6>
                            
                            <a class='dropdown-item show-receipt' href='#' data-id='{$receipt->id}'>
                                <i class='ti ti-eye me-2'></i>Lihat Detail
                            </a>
                            
                            <a class='dropdown-item print-receipt' href='#' data-id='{$receipt->id}'>
                                <i class='ti ti-printer me-2'></i>Print Receipt
                            </a>
                            
                            <div class='dropdown-divider'></div>";
                
                if ($canDelete && $userCanDelete) {
                    $actions .= "
                            <a class='dropdown-item text-danger delete-receipt' href='#' data-id='{$receipt->id}' data-receipt-number='{$receipt->receipt_number}'>
                                <i class='ti ti-trash me-2'></i>Hapus Receipt
                            </a>";
                } else {
                    $deleteReason = !$canDelete ? 'Hanya bisa dihapus pada hari yang sama' : 'Tidak ada izin menghapus';
                    $actions .= "
                            <span class='dropdown-item text-muted disabled' title='{$deleteReason}'>
                                <i class='ti ti-trash me-2'></i>Hapus Receipt
                                <small class='d-block text-muted' style='font-size: 0.7rem;'>{$deleteReason}</small>
                            </span>";
                }
                
                $actions .= "
                        </div>
                    </div>";
                
                return $actions;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create-asset-receipt');

        // Enhanced validation
        $request->validate([
            'receipt_number' => 'required|string|max:255|unique:asset_receipts,receipt_number',
            'receipt_date' => 'required|date|before_or_equal:today',
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_order_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.asset_id' => 'required|exists:assets,id',
            'items.*.warehouse_id' => 'required|exists:warehouses,id',
            'items.*.quantity_received' => 'required|numeric|min:0.001',
            'items.*.actual_unit_price' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'items.*.tracked_data' => 'nullable|string', // JSON string from frontend
        ]);

        DB::beginTransaction();
        try {
            $totalValue = 0;

            // Create main receipt
            $receipt = AssetReceipt::create([
                'receipt_number' => $request->receipt_number,
                'purchase_order_number' => $request->purchase_order_number,
                'receipt_date' => $request->receipt_date,
                'supplier_id' => $request->supplier_id,
                'received_by_user_id' => Auth::id(),
                'notes' => $request->notes,
            ]);

            // Process each item
            foreach ($request->items as $itemData) {
                $asset = Asset::with('asset_category')->findOrFail($itemData['asset_id']);
                $quantity = (float) $itemData['quantity_received'];
                $unitPrice = (float) ($itemData['actual_unit_price'] ?? $asset->standard_price);
                $totalPrice = $quantity * $unitPrice;
                $totalValue += $totalPrice;

                // Create receipt item
                $receiptItem = AssetReceiptItem::create([
                    'asset_receipt_id' => $receipt->id,
                    'asset_id' => $asset->id,
                    'warehouse_id' => $itemData['warehouse_id'],
                    'quantity_received' => $quantity,
                    'actual_unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // Handle tracking based on asset requirements
                if ($asset->requires_qr_tracking) {
                    $this->createTrackedAssets($asset, $itemData, $receiptItem);
                } else {
                    // Simple quantity tracking
                    $asset->increment('total_quantity', $quantity);
                    $asset->increment('available_quantity', $quantity);
                }
            }

            // Update receipt total
            $receipt->update(['total_value' => $totalValue]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Penerimaan aset berhasil disimpan.',
                'receipt_id' => $receipt->id,
                'receipt' => $receipt->load('supplier', 'receivedBy', 'items.asset')
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('AssetReceipt Store Error: ' . $e->getMessage());
            \Log::error('Stack Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan penerimaan aset: ' . $e->getMessage()
            ], 500);
        }
    }

    private function createTrackedAssets($asset, $itemData, $receiptItem)
    {
        $quantity = (int) $itemData['quantity_received'];
        
        // ✅ FIXED: Parse JSON string to array
        $trackedDataRaw = $itemData['tracked_data'] ?? '[]';
        
        // Handle both string and array cases
        if (is_string($trackedDataRaw)) {
            $trackedData = json_decode($trackedDataRaw, true) ?? [];
        } else {
            $trackedData = is_array($trackedDataRaw) ? $trackedDataRaw : [];
        }

        // Debug log untuk troubleshoot
        \Log::info('Tracking Data Debug', [
            'asset_id' => $asset->id,
            'quantity' => $quantity,
            'tracked_data_raw' => $trackedDataRaw,
            'tracked_data_decoded' => $trackedData,
            'tracked_data_count' => count($trackedData)
        ]);

        // Validate tracked data count matches quantity for tracked assets
        if (count($trackedData) !== $quantity) {
            throw ValidationException::withMessages([
                'tracked_data' => "Jumlah data tracking untuk {$asset->name} harus sesuai dengan quantity ($quantity unit). Ditemukan " . count($trackedData) . " data tracking."
            ]);
        }

        // Get tracking configuration
        $config = AssetTrackingConfig::where('asset_sub_type', $asset->asset_sub_type)->first();

        foreach ($trackedData as $index => $data) {
            $this->validateTrackingData($data, $config, $asset, $index + 1);

            // Generate QR Code
            $qrCode = $this->generateQRCode($asset);

            // Ensure uniqueness
            while (TrackedAsset::where('qr_code', $qrCode)->exists()) {
                $qrCode = $this->generateQRCode($asset);
            }

            TrackedAsset::create([
                'asset_id' => $asset->id,
                'asset_receipt_item_id' => $receiptItem->id,
                'qr_code' => $qrCode,
                'qr_generated' => true,
                'serial_number' => $data['serial_number'] ?? null,
                'mac_address' => $data['mac_address'] ?? null,
                'initial_length' => $data['initial_length'] ?? null,
                'current_length' => $data['initial_length'] ?? null, // Set current = initial at receipt
                'unit_of_measure' => $this->getUnitOfMeasure($asset),
                'current_warehouse_id' => $itemData['warehouse_id'],
                'current_status' => 'available',
                'last_status_change_by_user_id' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);
        }
    }

    private function validateTrackingData($data, $config, $asset, $unitNumber)
    {
        if (!$config) {
            // If no config, check asset requirements directly
            $requiredFields = [];
            if ($asset->requires_serial_number) $requiredFields[] = 'serial_number';
            if ($asset->requires_mac_address) $requiredFields[] = 'mac_address';
            if ($asset->isCableAsset()) $requiredFields[] = 'initial_length';
        } else {
            $requiredFields = $config->required_fields ?? [];
        }

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw ValidationException::withMessages([
                    'tracked_data' => "Field '$field' wajib diisi untuk {$asset->name} unit ke-$unitNumber."
                ]);
            }
        }

        // Validate serial number uniqueness if provided
        if (!empty($data['serial_number'])) {
            if (TrackedAsset::where('serial_number', $data['serial_number'])->exists()) {
                throw ValidationException::withMessages([
                    'tracked_data' => "Serial Number '{$data['serial_number']}' sudah ada dalam sistem."
                ]);
            }
        }

        // Validate MAC address uniqueness if provided
        if (!empty($data['mac_address'])) {
            if (TrackedAsset::where('mac_address', $data['mac_address'])->exists()) {
                throw ValidationException::withMessages([
                    'tracked_data' => "MAC Address '{$data['mac_address']}' sudah ada dalam sistem."
                ]);
            }
        }

        // Validate MAC address format if provided
        if (!empty($data['mac_address']) && !preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $data['mac_address'])) {
            throw ValidationException::withMessages([
                'tracked_data' => "Format MAC Address '{$data['mac_address']}' tidak valid. Gunakan format AA:BB:CC:DD:EE:FF atau AA-BB-CC-DD-EE-FF."
            ]);
        }
    }

    private function generateQRCode($asset): string
    {
        return 'QR-' . $asset->asset_code . '-' . date('ymd') . '-' . Str::upper(Str::random(4));
    }

    private function getUnitOfMeasure($asset): string
    {
        if (in_array($asset->asset_sub_type, ['cable_fiber', 'cable_copper', 'cable_power'])) {
            return 'meter';
        }
        return 'unit';
    }

    public function show($id): JsonResponse
    {
        $this->authorize('view-asset-receipts');

        $receipt = AssetReceipt::with([
            'supplier',
            'receivedBy',
            'items.asset.asset_category',
            'items.warehouse',
            'items.trackedAssets' => function($query) {
                $query->select(['id', 'asset_receipt_item_id', 'qr_code', 'serial_number', 'mac_address', 'initial_length', 'current_status']);
            }
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'receipt' => $receipt
        ]);
    }

    public function destroy(AssetReceipt $receipt): JsonResponse
    {
        $this->authorize('create-asset-receipt'); // Atau buat permission khusus 'delete-asset-receipt'
        
        // Cek syarat: Tanggal pembuatan harus sama dengan tanggal delete
        if (Carbon::parse($receipt->created_at)->format('Y-m-d') !== Carbon::now()->format('Y-m-d')) {
            return response()->json([
                'success' => false,
                'message' => 'Penerimaan aset hanya bisa dihapus pada tanggal dibuatnya (' . Carbon::parse($receipt->created_at)->format('d M Y') . ').'
            ], 403);
        }
        
        // Cek apakah ada tracked assets yang sudah digunakan (tidak available)
        $usedTrackedAssets = [];
        foreach ($receipt->items as $item) {
            if ($item->asset->requires_qr_tracking) {
                $usedAssets = $item->trackedAssets()->where('current_status', '!=', 'available')->get();
                if ($usedAssets->isNotEmpty()) {
                    foreach ($usedAssets as $tracked) {
                        $usedTrackedAssets[] = $tracked->qr_code . ' (' . $tracked->current_status . ')';
                    }
                }
            }
        }
        
        if (!empty($usedTrackedAssets)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus penerimaan. Ada aset yang sudah digunakan: ' . implode(', ', $usedTrackedAssets)
            ], 409);
        }

        DB::beginTransaction();
        try {
            $receiptNumber = $receipt->receipt_number;
            $totalValue = $receipt->total_value;
            
            // 1. Proses rollback untuk setiap item
            foreach ($receipt->items as $item) {
                $asset = $item->asset;
                
                if ($asset->requires_qr_tracking) {
                    // Hapus TrackedAssets yang terkait (hanya yang available)
                    $trackedAssetsCount = $item->trackedAssets()->count();
                    \Log::info("Deleting {$trackedAssetsCount} tracked assets for item {$item->id}");
                    
                    $item->trackedAssets()->delete();
                } else {
                    // Kurangi quantity dari master aset untuk simple tracking
                    $quantityToDeduct = $item->quantity_received;
                    
                    \Log::info("Rolling back quantity for asset {$asset->id}: -{$quantityToDeduct}");
                    
                    // Pastikan tidak minus
                    $newTotal = max(0, $asset->total_quantity - $quantityToDeduct);
                    $newAvailable = max(0, $asset->available_quantity - $quantityToDeduct);
                    
                    $asset->update([
                        'total_quantity' => $newTotal,
                        'available_quantity' => $newAvailable
                    ]);
                }
            }

            // 2. Hapus Item Penerimaan
            $receipt->items()->delete();

            // 3. Hapus Penerimaan Utama
            $receipt->delete();

            DB::commit();
            
            \Log::info("AssetReceipt deleted successfully: {$receiptNumber}");

            return response()->json([
                'success' => true,
                'message' => "Penerimaan aset {$receiptNumber} berhasil dihapus. Stok telah dikembalikan."
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('AssetReceipt Destroy Error: ' . $e->getMessage());
            \Log::error('Stack Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus penerimaan aset: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAssetTrackingConfig(Request $request): JsonResponse
    {
        $assetId = $request->input('asset_id');
        $asset = Asset::findOrFail($assetId);
        
        $config = AssetTrackingConfig::where('asset_sub_type', $asset->asset_sub_type)->first();

        if ($config) {
            return response()->json([
                'requires_qr_tracking' => $config->requires_qr_tracking,
                'requires_serial_number' => $config->requires_serial_number,
                'requires_mac_address' => $config->requires_mac_address,
                'requires_length_tracking' => $config->requires_length_tracking,
                'required_fields' => $config->required_fields,
                'optional_fields' => $config->optional_fields,
                'instructions' => $config->tracking_instructions,
                'asset_sub_type' => $asset->asset_sub_type,
                'asset_name' => $asset->name,
            ]);
        }

        return response()->json([
            'requires_qr_tracking' => $asset->requires_qr_tracking,
            'requires_serial_number' => $asset->requires_serial_number,
            'requires_mac_address' => $asset->requires_mac_address,
            'requires_length_tracking' => in_array($asset->asset_sub_type, ['cable_fiber', 'cable_copper', 'cable_power']),
            'required_fields' => $this->getDefaultRequiredFields($asset),
            'optional_fields' => ['notes'],
            'instructions' => 'Tidak ada konfigurasi tracking khusus.',
            'asset_sub_type' => $asset->asset_sub_type,
            'asset_name' => $asset->name,
        ]);
    }

    private function getDefaultRequiredFields($asset): array
    {
        $fields = [];
        
        if ($asset->requires_serial_number) {
            $fields[] = 'serial_number';
        }
        
        if ($asset->requires_mac_address) {
            $fields[] = 'mac_address';
        }
        
        if ($asset->isCableAsset()) {
            $fields[] = 'initial_length';
        }
        
        return $fields;
    }

    public function getAssetsBySupplier(Request $request): JsonResponse
    {
        $supplierId = $request->input('supplier_id');
        
        // Get recent assets from this supplier (last 3 months)
        $recentAssets = AssetReceipt::where('supplier_id', $supplierId)
            ->where('receipt_date', '>=', now()->subMonths(3))
            ->with('items.asset')
            ->get()
            ->pluck('items')
            ->flatten()
            ->pluck('asset')
            ->unique('id')
            ->values();

        return response()->json([
            'recent_assets' => $recentAssets
        ]);
    }

    /**
     * Generate QR codes untuk semua tracked assets dalam receipt
     */
    public function generateReceiptQR(AssetReceipt $receipt): JsonResponse
    {
        $this->authorize('create-asset-receipt');
        
        try {
            $generatedCount = 0;
            $skippedCount = 0;
            $trackedAssets = [];
            
            foreach ($receipt->items as $item) {
                if ($item->asset->requires_qr_tracking) {
                    foreach ($item->trackedAssets as $tracked) {
                        if (!$tracked->qr_generated || empty($tracked->qr_code)) {
                            $this->generateQRForTrackedAsset($tracked);
                            $generatedCount++;
                            $trackedAssets[] = $tracked;
                        } else {
                            $skippedCount++;
                        }
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => "QR codes generated successfully. Generated: {$generatedCount}, Skipped: {$skippedCount}",
                'generated_count' => $generatedCount,
                'skipped_count' => $skippedCount,
                'tracked_assets' => $trackedAssets
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Receipt QR Generation Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate QR codes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print QR labels untuk semua tracked assets dalam receipt
     */
    public function printReceiptQRLabels(Request $request, AssetReceipt $receipt): JsonResponse
    {
        $this->authorize('create-asset-receipt');
        
        $request->validate([
            'label_size' => 'in:small,medium,large',
            'include_text' => 'boolean',
            'group_by_asset' => 'boolean'
        ]);
        
        try {
            $labelSize = $request->label_size ?? 'medium';
            $includeText = $request->include_text ?? true;
            $groupByAsset = $request->group_by_asset ?? false;
            
            $labels = [];
            $totalTrackedAssets = 0;
            
            foreach ($receipt->items as $item) {
                if ($item->asset->requires_qr_tracking) {
                    $itemLabels = [];
                    
                    foreach ($item->trackedAssets as $tracked) {
                        // Ensure QR is generated
                        if (!$tracked->qr_generated || empty($tracked->qr_code)) {
                            $this->generateQRForTrackedAsset($tracked);
                            $tracked->refresh();
                        }
                        
                        $labelData = [
                            'qr_code' => $tracked->qr_code,
                            'asset_name' => $tracked->asset->name,
                            'asset_code' => $tracked->asset->asset_code,
                            'serial_number' => $tracked->serial_number,
                            'mac_address' => $tracked->mac_address,
                            'initial_length' => $tracked->initial_length,
                            'qr_image' => $this->generateQRImageData($tracked),
                            'receipt_number' => $receipt->receipt_number,
                            'received_date' => $receipt->receipt_date->format('d M Y')
                        ];
                        
                        if ($groupByAsset) {
                            $itemLabels[] = $labelData;
                        } else {
                            $labels[] = $labelData;
                        }
                        
                        $totalTrackedAssets++;
                    }
                    
                    if ($groupByAsset && !empty($itemLabels)) {
                        $labels[] = [
                            'asset_group' => $item->asset->name,
                            'labels' => $itemLabels
                        ];
                    }
                }
            }
            
            if (empty($labels)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada tracked assets untuk di-print QR labels.'
                ], 422);
            }
            
            // Generate PDF
            $pdf = Pdf::loadView('asset-management.receipts.qr-labels', [
                'receipt' => $receipt,
                'labels' => $labels,
                'label_size' => $labelSize,
                'include_text' => $includeText,
                'group_by_asset' => $groupByAsset,
                'generated_at' => now(),
                'generated_by' => auth()->user()->name
            ]);
            
            $filename = 'receipt-qr-labels-' . $receipt->receipt_number . '-' . now()->format('Y-m-d-H-i-s') . '.pdf';
            $pdfPath = storage_path("app/qr-labels/{$filename}");
            
            // Ensure directory exists
            if (!file_exists(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }
            
            $pdf->save($pdfPath);
            
            return response()->json([
                'success' => true,
                'message' => "QR labels berhasil di-generate untuk {$totalTrackedAssets} assets.",
                'download_url' => route('asset-management.qr.download-labels', ['filename' => $filename]),
                'filename' => $filename,
                'total_labels' => $totalTrackedAssets
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Receipt QR Labels Print Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal print QR labels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get receipt summary untuk QR generation
     */
    public function getReceiptQRSummary(AssetReceipt $receipt): JsonResponse
    {
        $this->authorize('view-asset-receipts');
        
        try {
            $summary = [
                'receipt_number' => $receipt->receipt_number,
                'receipt_date' => $receipt->receipt_date->format('d M Y'),
                'supplier' => $receipt->supplier->name,
                'total_items' => $receipt->items->count(),
                'tracked_items' => 0,
                'total_tracked_assets' => 0,
                'qr_generated_count' => 0,
                'qr_pending_count' => 0,
                'items_detail' => []
            ];
            
            foreach ($receipt->items as $item) {
                $itemDetail = [
                    'asset_name' => $item->asset->name,
                    'asset_code' => $item->asset->asset_code,
                    'quantity' => $item->quantity_received,
                    'requires_tracking' => $item->asset->requires_qr_tracking,
                    'tracked_assets_count' => 0,
                    'qr_generated' => 0,
                    'qr_pending' => 0
                ];
                
                if ($item->asset->requires_qr_tracking) {
                    $summary['tracked_items']++;
                    $trackedAssets = $item->trackedAssets;
                    $itemDetail['tracked_assets_count'] = $trackedAssets->count();
                    $summary['total_tracked_assets'] += $trackedAssets->count();
                    
                    $generated = $trackedAssets->where('qr_generated', true)->count();
                    $pending = $trackedAssets->where('qr_generated', false)->count();
                    
                    $itemDetail['qr_generated'] = $generated;
                    $itemDetail['qr_pending'] = $pending;
                    
                    $summary['qr_generated_count'] += $generated;
                    $summary['qr_pending_count'] += $pending;
                }
                
                $summary['items_detail'][] = $itemDetail;
            }
            
            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Receipt QR Summary Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal load QR summary: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods untuk QR generation
    private function generateQRForTrackedAsset($trackedAsset): void
    {
        if (empty($trackedAsset->qr_code)) {
            $trackedAsset->qr_code = $this->generateUniqueQRCode($trackedAsset->asset);
        }
        
        $qrData = [
            'type' => 'asset',
            'version' => '1.0',
            'qr_code' => $trackedAsset->qr_code,
            'asset_id' => $trackedAsset->asset_id,
            'tracked_asset_id' => $trackedAsset->id,
            'asset_name' => $trackedAsset->asset->name,
            'asset_code' => $trackedAsset->asset->asset_code,
            'serial_number' => $trackedAsset->serial_number,
            'mac_address' => $trackedAsset->mac_address,
            'initial_length' => $trackedAsset->initial_length,
            'current_status' => $trackedAsset->current_status,
            'warehouse_id' => $trackedAsset->current_warehouse_id,
            'generated_at' => now()->toISOString(),
            'company' => config('app.name', 'Asset Management System')
        ];
        
        $qrString = json_encode($qrData);
        
        $qrCodePng = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($qrString);
        
        // Save QR image
        $filename = "qr-codes/{$trackedAsset->qr_code}.png";
        Storage::disk('public')->put($filename, $qrCodePng);
        
        $trackedAsset->update(['qr_generated' => true]);
    }

    private function generateUniqueQRCode($asset): string
    {
        do {
            $qrCode = 'QR-' . $asset->asset_code . '-' . date('ymd') . '-' . Str::upper(Str::random(4));
        } while (TrackedAsset::where('qr_code', $qrCode)->exists());
        
        return $qrCode;
    }

    private function generateQRImageData($trackedAsset): string
    {
        $qrData = [
            'type' => 'asset',
            'qr_code' => $trackedAsset->qr_code,
            'asset_id' => $trackedAsset->asset_id,
            'tracked_asset_id' => $trackedAsset->id,
            'generated_at' => now()->toISOString()
        ];
        
        $qrString = json_encode($qrData);
        
        $qrCodePng = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($qrString);
        
        return 'data:image/png;base64,' . base64_encode($qrCodePng);
    }




















}