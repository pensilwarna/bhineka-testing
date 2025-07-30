<?php
namespace App\Http\Controllers;

use App\Models\TrackedAsset;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class QRManagementController extends Controller
{
    public function scanner()
    {
        $this->authorize('view-asset-inventory');
        
        return view('asset-management.qr.scanner');
    }

    public function generateMissingQRCodes(): JsonResponse
    {
        $this->authorize('manage-assets');
        
        try {
            // Find tracked assets without QR codes
            $assetsWithoutQR = TrackedAsset::where(function($query) {
                $query->whereNull('qr_code')
                      ->orWhere('qr_code', '')
                      ->orWhere('qr_generated', false);
            })->with('asset')->get();
            
            if ($assetsWithoutQR->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Semua tracked assets sudah memiliki QR code.',
                    'generated_count' => 0
                ]);
            }
            
            $generatedCount = 0;
            
            foreach ($assetsWithoutQR as $trackedAsset) {
                // Generate QR code if missing
                if (empty($trackedAsset->qr_code)) {
                    $qrCode = $this->generateUniqueQRCode($trackedAsset->asset);
                    $trackedAsset->qr_code = $qrCode;
                }
                
                // Generate QR image
                $qrData = $this->buildQRData($trackedAsset);
                $qrString = json_encode($qrData);
                
                $qrCodePng = QrCode::format('png')
                    ->size(300)
                    ->margin(2)
                    ->generate($qrString);
                
                // Save QR image
                $filename = "qr-codes/{$trackedAsset->qr_code}.png";
                Storage::disk('public')->put($filename, $qrCodePng);
                
                // Update tracked asset
                $trackedAsset->update(['qr_generated' => true]);
                $generatedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil generate {$generatedCount} QR codes yang hilang.",
                'generated_count' => $generatedCount
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Generate Missing QR Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate QR codes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function regenerateBatchQR(Request $request): JsonResponse
    {
        $this->authorize('manage-assets');
        
        $request->validate([
            'tracked_asset_ids' => 'required|array',
            'tracked_asset_ids.*' => 'exists:tracked_assets,id'
        ]);
        
        try {
            $trackedAssets = TrackedAsset::with('asset')
                ->whereIn('id', $request->tracked_asset_ids)
                ->get();
            
            $regeneratedCount = 0;
            
            foreach ($trackedAssets as $trackedAsset) {
                // Generate new QR data
                $qrData = $this->buildQRData($trackedAsset);
                $qrString = json_encode($qrData);
                
                $qrCodePng = QrCode::format('png')
                    ->size(300)
                    ->margin(2)
                    ->generate($qrString);
                
                // Save updated QR image
                $filename = "qr-codes/{$trackedAsset->qr_code}.png";
                Storage::disk('public')->put($filename, $qrCodePng);
                
                $trackedAsset->update(['qr_generated' => true]);
                $regeneratedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Berhasil regenerate {$regeneratedCount} QR codes.",
                'regenerated_count' => $regeneratedCount
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Regenerate Batch QR Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal regenerate QR codes: ' . $e->getMessage()
            ], 500);
        }
    }

    public function printSingleQR($id): JsonResponse
    {
        $this->authorize('manage-assets');
        
        try {
            $trackedAsset = TrackedAsset::with('asset')->findOrFail($id);
            
            // Generate QR if not exists
            if (!$trackedAsset->qr_generated || empty($trackedAsset->qr_code)) {
                $this->generateQRForAsset($trackedAsset);
            }
            
            // Create single label
            $labels = [$this->buildLabelData($trackedAsset)];
            
            $pdf = Pdf::loadView('asset-management.inventory.qr-labels', [
                'labels' => $labels,
                'label_size' => 'medium',
                'include_text' => true,
                'generated_at' => now(),
                'generated_by' => auth()->user()->name
            ]);
            
            $filename = 'qr-label-' . $trackedAsset->qr_code . '-' . now()->format('Y-m-d-H-i-s') . '.pdf';
            $pdfPath = storage_path("app/qr-labels/{$filename}");
            
            // Ensure directory exists
            if (!file_exists(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }
            
            $pdf->save($pdfPath);
            
            return response()->json([
                'success' => true,
                'download_url' => route('asset-management.qr.download-labels', ['filename' => $filename]),
                'filename' => $filename
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Print Single QR Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal print QR label: ' . $e->getMessage()
            ], 500);
        }
    }

    public function lookupQRCode(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string'
        ]);
        
        try {
            $trackedAsset = TrackedAsset::with([
                'asset.asset_category',
                'currentWarehouse',
                'assetReceiptItem.assetReceipt.supplier'
            ])->where('qr_code', $request->qr_code)->first();
            
            if (!$trackedAsset) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR Code tidak ditemukan dalam sistem.'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'tracked_asset' => $trackedAsset,
                'lookup_timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('QR Lookup Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal lookup QR code: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAssetByQR($qrCode): JsonResponse
    {
        try {
            $trackedAsset = TrackedAsset::with([
                'asset.asset_category',
                'currentWarehouse',
                'technicianAssetDebts' => function($query) {
                    $query->where('status', 'active')->with('technician');
                },
                'customerInstalledAssets' => function($query) {
                    $query->where('status', 'installed')->with(['customer', 'serviceLocation']);
                }
            ])->where('qr_code', $qrCode)->first();
            
            if (!$trackedAsset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset tidak ditemukan dengan QR code: ' . $qrCode
                ], 404);
            }
            
            // Log access untuk audit
            \Log::info("QR Code accessed: {$qrCode}", [
                'user_id' => auth()->id(),
                'tracked_asset_id' => $trackedAsset->id,
                'current_status' => $trackedAsset->current_status
            ]);
            
            return response()->json([
                'success' => true,
                'tracked_asset' => $trackedAsset,
                'scan_info' => [
                    'scanned_at' => now()->toISOString(),
                    'scanned_by' => auth()->user()->name ?? 'Unknown',
                    'current_location' => $trackedAsset->currentWarehouse->name ?? 'Unknown'
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Get Asset by QR Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data asset: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateAssetStatus(Request $request): JsonResponse
    {
        $this->authorize('manage-assets');
        
        $request->validate([
            'qr_code' => 'required|string',
            'new_status' => 'required|in:available,in_transit,loaned,installed,damaged,in_repair,lost,written_off',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'notes' => 'nullable|string|max:500'
        ]);
        
        try {
            $trackedAsset = TrackedAsset::where('qr_code', $request->qr_code)->first();
            
            if (!$trackedAsset) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asset dengan QR code tidak ditemukan.'
                ], 404);
            }
            
            $oldStatus = $trackedAsset->current_status;
            $oldWarehouse = $trackedAsset->current_warehouse_id;
            
            // Update status
            $updateData = [
                'current_status' => $request->new_status,
                'last_status_change_by_user_id' => auth()->id()
            ];
            
            if ($request->warehouse_id) {
                $updateData['current_warehouse_id'] = $request->warehouse_id;
            }
            
            if ($request->notes) {
                $updateData['notes'] = $request->notes;
            }
            
            $trackedAsset->update($updateData);
            
            // Log status change
            \Log::info("Asset status updated via QR", [
                'qr_code' => $request->qr_code,
                'old_status' => $oldStatus,
                'new_status' => $request->new_status,
                'old_warehouse_id' => $oldWarehouse,
                'new_warehouse_id' => $request->warehouse_id,
                'updated_by' => auth()->id(),
                'notes' => $request->notes
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Status asset berhasil diupdate dari '{$oldStatus}' ke '{$request->new_status}'.",
                'tracked_asset' => $trackedAsset->fresh()
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Update Asset Status Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal update status asset: ' . $e->getMessage()
            ], 500);
        }
    }

    public function validateQRCode($qrCode): JsonResponse
    {
        try {
            $trackedAsset = TrackedAsset::where('qr_code', $qrCode)->first();
            
            $isValid = !is_null($trackedAsset);
            
            return response()->json([
                'success' => true,
                'is_valid' => $isValid,
                'qr_code' => $qrCode,
                'validation_timestamp' => now()->toISOString(),
                'asset_exists' => $isValid,
                'basic_info' => $isValid ? [
                    'asset_name' => $trackedAsset->asset->name,
                    'current_status' => $trackedAsset->current_status,
                    'warehouse' => $trackedAsset->currentWarehouse->name ?? 'Unknown'
                ] : null
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Validate QR Code Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal validasi QR code: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper Methods
    private function generateUniqueQRCode($asset): string
    {
        do {
            $qrCode = 'QR-' . $asset->asset_code . '-' . date('ymd') . '-' . Str::upper(Str::random(4));
        } while (TrackedAsset::where('qr_code', $qrCode)->exists());
        
        return $qrCode;
    }

    private function buildQRData($trackedAsset): array
    {
        return [
            'type' => 'asset',
            'version' => '1.0',
            'qr_code' => $trackedAsset->qr_code,
            'asset_id' => $trackedAsset->asset_id,
            'tracked_asset_id' => $trackedAsset->id,
            'asset_name' => $trackedAsset->asset->name,
            'asset_code' => $trackedAsset->asset->asset_code,
            'serial_number' => $trackedAsset->serial_number,
            'mac_address' => $trackedAsset->mac_address,
            'current_status' => $trackedAsset->current_status,
            'warehouse_id' => $trackedAsset->current_warehouse_id,
            'generated_at' => now()->toISOString(),
            'company' => config('app.name', 'Asset Management System')
        ];
    }

    private function buildLabelData($trackedAsset): array
    {
        return [
            'qr_code' => $trackedAsset->qr_code,
            'asset_name' => $trackedAsset->asset->name,
            'asset_code' => $trackedAsset->asset->asset_code,
            'serial_number' => $trackedAsset->serial_number,
            'mac_address' => $trackedAsset->mac_address,
            'qr_image' => $this->generateQRImage($trackedAsset)
        ];
    }

    private function generateQRImage($trackedAsset): string
    {
        $qrData = $this->buildQRData($trackedAsset);
        $qrString = json_encode($qrData);
        
        $qrCodePng = QrCode::format('png')
            ->size(300)
            ->margin(2)
            ->generate($qrString);
        
        return 'data:image/png;base64,' . base64_encode($qrCodePng);
    }

    private function generateQRForAsset($trackedAsset): void
    {
        if (empty($trackedAsset->qr_code)) {
            $trackedAsset->qr_code = $this->generateUniqueQRCode($trackedAsset->asset);
        }
        
        $qrData = $this->buildQRData($trackedAsset);
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
}