<?php 

namespace App\Services;

use App\Models\Asset;
use App\Models\TrackedAsset; // ADDED
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

// Using chillerlan/php-qrcode (Alternative to simplesoftwareio)
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QRCodeService
{
    protected $qrcode;
    protected $options;

    public function __construct()
    {
        // Configure QR code options
        $this->options = new QROptions([
            'version'    => 5,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_M,
            'scale'      => 6,
            'imageBase64' => false,
        ]);

        $this->qrcode = new QRCode($this->options);
    }

    /**
     * 🏷️ Generate QR code untuk satu tracked asset
     * Mengupdate tracked_assets.qr_code dan qr_generated
     */
    public function generateForTrackedAsset($trackedAssetId): string
    {
        $trackedAsset = TrackedAsset::with('asset.asset_category')->findOrFail($trackedAssetId);
        
        // Generate QR content format: AST-[CATEGORY_PREFIX]-[TRACKED_ASSET_ID]
        $categoryPrefix = $trackedAsset->asset->asset_category ? 
            strtoupper(substr($trackedAsset->asset->asset_category->name, 0, 3)) : 'GEN';
        $qrContent = "AST-{$categoryPrefix}-" . str_pad($trackedAssetId, 5, '0', STR_PAD_LEFT); // Lebih panjang ID untuk tracked_asset
        
        // Check if QR already exists and is generated
        if ($trackedAsset->qr_code && $trackedAsset->qr_generated) {
            return $trackedAsset->qr_code;
        }
        
        // Generate QR code image
        $qrCodeImage = $this->qrcode->render($qrContent);
        
        // Save to storage
        $fileName = "tracked_asset_{$trackedAssetId}.png";
        $path = "qrcodes/{$fileName}";
        Storage::put($path, $qrCodeImage);
        
        // Update tracked asset record
        $trackedAsset->update([
            'qr_code' => $qrContent,
            'qr_generated' => true
        ]);
        
        return $qrContent;
    }

    /**
     * 🏷️ Generate QR codes untuk multiple tracked assets (batch)
     */
    public function generateBatchTrackedAssets(array $trackedAssetIds): array
    {
        $results = [];
        
        foreach ($trackedAssetIds as $trackedAssetId) {
            try {
                $qrCode = $this->generateForTrackedAsset($trackedAssetId);
                $results[$trackedAssetId] = [
                    'success' => true,
                    'qr_code' => $qrCode
                ];
            } catch (\Exception $e) {
                $results[$trackedAssetId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * 🖨️ Print QR labels sebagai PDF untuk tracked assets
     */
    public function printLabelsForTrackedAssets(array $trackedAssetIds): string
    {
        $trackedAssets = TrackedAsset::whereIn('id', $trackedAssetIds)
            ->with('asset.asset_category') // Load asset details
            ->get();
        
        // Ensure all assets have QR codes
        foreach ($trackedAssets as $trackedAsset) {
            if (!$trackedAsset->qr_generated) {
                $this->generateForTrackedAsset($trackedAsset->id);
                $trackedAsset->refresh(); // Reload to get updated QR code
            }
        }
        
        // Generate PDF
        // Make sure you have a view: resources/views/asset-management/qr-labels.blade.php that expects 'trackedAssets'
        $pdf = Pdf::loadView('asset-management.qr-labels', compact('trackedAssets'));
        $pdf->setPaper('a4', 'portrait');
        
        // Save temporary file
        $fileName = 'qr-labels-' . date('Y-m-d-H-i-s') . '-' . Str::random(8) . '.pdf';
        $path = 'temp/' . $fileName;
        
        Storage::put($path, $pdf->output());
        
        return $path;
    }

    /**
     * 🔍 Get tracked asset by QR code, serial number, or MAC address
     */
    public function getTrackedAssetByQR(string $identifier): ?TrackedAsset
    {
        return TrackedAsset::where('qr_code', $identifier)
            ->orWhere('serial_number', $identifier)
            ->orWhere('mac_address', $identifier)
            ->with(['asset.asset_category', 'currentWarehouse'])
            ->first();
    }

    /**
     * ✅ Validate QR code format (for tracked assets)
     */
    public function validateQRFormat(string $qrCode): bool
    {
        // Format should be: AST-[CATEGORY_PREFIX]-99999 (e.g., AST-ROU-00123)
        return preg_match('/^AST-[A-Z]{3}-\d{5}$/', $qrCode);
    }

    /**
     * 📊 Get QR Generation Stats (MODIFIED to count tracked assets)
     */
    public function getQRStats(): array
    {
        $totalTrackedAssets = TrackedAsset::count();
        $generatedQR = TrackedAsset::where('qr_generated', true)->count();
        $pendingQR = $totalTrackedAssets - $generatedQR;
        
        return [
            'total_tracked_assets' => $totalTrackedAssets,
            'generated_qr' => $generatedQR,
            'pending_qr' => $pendingQR,
            'generation_percentage' => $totalTrackedAssets > 0 ? ($generatedQR / $totalTrackedAssets) * 100 : 0
        ];
    }

    /**
     * 🎨 Generate QR dengan base64 (untuk blade template)
     */
    public function generateBase64($content, $size = 200): string
    {
        $options = new QROptions([
            'version'    => 5,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_M,
            'scale'      => $size / 25, // Approximate scale for size
            'imageBase64' => true,
        ]);

        $qrcode = new QRCode($options);
        return $qrcode->render($content);
    }

    /**
     * 🎯 Generate QR for Blade (Helper method)
     */
    public static function generateForBlade($content, $size = 100): string
    {
        $options = new QROptions([
            'version'    => 5,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_M,
            'scale'      => $size / 25,
            'imageBase64' => false,
        ]);

        $qrcode = new QRCode($options);
        return base64_encode($qrcode->render($content));
    }
}