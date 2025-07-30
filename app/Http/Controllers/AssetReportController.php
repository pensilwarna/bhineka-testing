<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\TrackedAsset;
use App\Models\TechnicianAssetDebt;
use App\Models\CustomerInstalledAsset;
use App\Models\AssetUsage;
use App\Models\AssetRepair;
use App\Models\AssetSupplierReturn;
use App\Models\AssetWriteOff;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Illuminate\Support\Str;

class AssetReportController extends Controller
{
    /**
     * Base method to display report index or generate reports based on type.
     */
    public function index()
    {
        $this->authorize('view-asset-reports'); // Define this permission for Manager, Owner, Super-Admin, LAD
        return view('asset-management.reports.index');
    }

    /**
     * Generate Inventory Report.
     */
    public function inventoryReport(Request $request)
    {
        $this->authorize('view-asset-reports');
        $format = $request->query('format', 'html'); // html, xlsx, csv, pdf

        $queryNonTracked = Asset::with('asset_category', 'warehouse')
            ->where('requires_qr_tracking', false)
            ->select('id', 'name', 'asset_code', 'brand', 'model', 'total_quantity', 'available_quantity', 'standard_price', 'asset_category_id', 'warehouse_id');

        $queryTracked = TrackedAsset::with('asset.asset_category', 'currentWarehouse')
            ->select('id', 'asset_id', 'qr_code', 'serial_number', 'mac_address', 'current_status', 'current_warehouse_id', 'initial_length', 'current_length', 'unit_of_measure');
        
        $nonTrackedAssets = $queryNonTracked->get()->map(function($asset) {
            return [
                'ID' => 'A-' . $asset->id,
                'Asset Name' => $asset->name,
                'Asset Code' => $asset->asset_code,
                'Category' => $asset->asset_category->name ?? '-',
                'Brand' => $asset->brand ?? '-',
                'Model' => $asset->model ?? '-',
                'Warehouse' => $asset->warehouse->name ?? '-',
                'Status' => ($asset->available_quantity <= 0 ? 'Out of Stock' : ($asset->available_quantity <= 5 ? 'Low Stock' : 'Available')),
                'Total Quantity' => $asset->total_quantity,
                'Available Quantity' => $asset->available_quantity,
                'Unit' => $asset->asset_category->unit ?? '-',
                'Standard Price' => $asset->standard_price,
                'Requires QR' => 'No',
                'QR Code/SN/MAC' => '-',
                'Initial Length' => '-',
                'Current Length' => '-',
                'Physical Status' => '-',
            ];
        });

        $trackedAssets = $queryTracked->get()->map(function($tracked) {
            return [
                'ID' => 'T-' . $tracked->id,
                'Asset Name' => $tracked->asset->name ?? '-',
                'Asset Code' => $tracked->asset->asset_code ?? '-',
                'Category' => $tracked->asset->asset_category->name ?? '-',
                'Brand' => $tracked->asset->brand ?? '-',
                'Model' => $tracked->asset->model ?? '-',
                'Warehouse' => $tracked->currentWarehouse->name ?? '-',
                'Status' => ucfirst(str_replace('_', ' ', $tracked->current_status)),
                'Total Quantity' => 1, // Always 1 for a tracked unit
                'Available Quantity' => ($tracked->current_status === 'available' ? 1 : 0),
                'Unit' => $tracked->unit_of_measure ?? ($tracked->asset->asset_category->unit ?? '-'),
                'Standard Price' => $tracked->asset->standard_price ?? 0,
                'Requires QR' => 'Yes',
                'QR Code/SN/MAC' => $tracked->qr_code ?? $tracked->serial_number ?? $tracked->mac_address ?? '-',
                'Initial Length' => $tracked->initial_length ?? '-',
                'Current Length' => $tracked->current_length ?? '-',
                'Physical Status' => ucfirst(str_replace('_', ' ', $tracked->current_status)),
            ];
        });

        $data = $nonTrackedAssets->concat($trackedAssets)->toArray();

        if ($format === 'html') {
            return view('asset-management.reports.inventory', compact('data'));
        } elseif ($format === 'xlsx') {
            return $this->exportToXLSX($data, 'Inventory_Report');
        } elseif ($format === 'csv') {
            return $this->exportToCSV($data, 'Inventory_Report');
        } elseif ($format === 'pdf') {
            return $this->exportToPDF($data, 'asset-management.reports.inventory-pdf', 'Inventory_Report');
        }

        return response('Invalid format', 400);
    }

    /**
     * Generate Debt Report.
     */
    public function debtReport(Request $request)
    {
        $this->authorize('view-asset-reports');
        $format = $request->query('format', 'html');

        $debts = TechnicianAssetDebt::with(['technician', 'asset.asset_category', 'trackedAsset', 'warehouse'])
            ->when($request->filled('technician_id'), function($q) use ($request) {
                $q->where('technician_id', $request->technician_id);
            })
            ->when($request->filled('status'), function($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->get()
            ->map(function($debt) {
                return [
                    'ID' => $debt->id,
                    'Technician' => $debt->technician->name ?? '-',
                    'Asset Name' => $debt->asset->name ?? '-',
                    'Asset Identifier' => $debt->trackedAsset ? ($debt->trackedAsset->qr_code ?? $debt->trackedAsset->serial_number ?? $debt->trackedAsset->mac_address) : '-',
                    'Category' => $debt->asset->asset_category->name ?? '-',
                    'Warehouse Checkout' => $debt->warehouse->name ?? '-',
                    'Checkout Date' => $debt->checkout_date->format('Y-m-d'),
                    'Quantity Taken' => $debt->quantity_taken,
                    'Current Debt Quantity' => $debt->current_debt_quantity,
                    'Unit Price' => $debt->unit_price,
                    'Total Debt Value' => $debt->total_debt_value,
                    'Current Debt Value' => $debt->current_debt_value,
                    'Status' => ucfirst(str_replace('_', ' ', $debt->status)),
                ];
            })->toArray();
        
        if ($format === 'html') {
            return view('asset-management.reports.debt', compact('debts'));
        } elseif ($format === 'xlsx') {
            return $this->exportToXLSX($debts, 'Debt_Report');
        } elseif ($format === 'csv') {
            return $this->exportToCSV($debts, 'Debt_Report');
        } elseif ($format === 'pdf') {
            return $this->exportToPDF($debts, 'asset-management.reports.debt-pdf', 'Debt_Report');
        }
        return response('Invalid format', 400);
    }

    /**
     * Generate Customer Installed Asset Report.
     */
    public function customerInstalledReport(Request $request)
    {
        $this->authorize('view-asset-reports');
        $format = $request->query('format', 'html');

        $installedAssets = CustomerInstalledAsset::with(['customer', 'serviceLocation', 'technician', 'asset.asset_category', 'trackedAsset'])
            ->when($request->filled('customer_id'), function($q) use ($request) {
                $q->where('customer_id', $request->customer_id);
            })
            ->when($request->filled('status'), function($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->get()
            ->map(function($cia) {
                return [
                    'ID' => $cia->id,
                    'Customer' => $cia->customer->name ?? '-',
                    'Service Location' => $cia->serviceLocation->address ?? '-',
                    'Asset Name' => $cia->asset->name ?? '-',
                    'Asset Identifier' => $cia->trackedAsset ? ($cia->trackedAsset->qr_code ?? $cia->trackedAsset->serial_number ?? $cia->trackedAsset->mac_address) : '-',
                    'Installation Date' => $cia->installation_date->format('Y-m-d'),
                    'Quantity Installed' => $cia->quantity_installed,
                    'Installed Length' => $cia->installed_length ?? '-',
                    'Current Length (Audit)' => $cia->current_length ?? '-',
                    'Unit Value' => $cia->unit_value,
                    'Total Asset Value' => $cia->total_asset_value,
                    'Status' => ucfirst($cia->status),
                    'Technician' => $cia->technician->name ?? '-',
                ];
            })->toArray();

        if ($format === 'html') {
            return view('asset-management.reports.customer-installed', compact('installedAssets'));
        } elseif ($format === 'xlsx') {
            return $this->exportToXLSX($installedAssets, 'Customer_Installed_Assets_Report');
        } elseif ($format === 'csv') {
            return $this->exportToCSV($installedAssets, 'Customer_Installed_Assets_Report');
        } elseif ($format === 'pdf') {
            return $this->exportToPDF($installedAssets, 'asset-management.reports.customer-installed-pdf', 'Customer_Installed_Assets_Report');
        }
        return response('Invalid format', 400);
    }

    /**
     * Generate Asset Usage Report.
     */
    public function usageReport(Request $request)
    {
        $this->authorize('view-asset-reports');
        $format = $request->query('format', 'html');

        $usages = AssetUsage::with(['ticket', 'asset.asset_category', 'trackedAsset', 'user'])
            ->when($request->filled('ticket_id'), function($q) use ($request) {
                $q->where('ticket_id', $request->ticket_id);
            })
            ->when($request->filled('user_id'), function($q) use ($request) {
                $q->where('user_id', $request->user_id);
            })
            ->get()
            ->map(function($usage) {
                return [
                    'ID' => $usage->id,
                    'Ticket Code' => $usage->ticket->kode ?? '-',
                    'Asset Name' => $usage->asset->name ?? '-',
                    'Asset Identifier' => $usage->trackedAsset ? ($usage->trackedAsset->qr_code ?? $usage->trackedAsset->serial_number ?? $usage->trackedAsset->mac_address) : '-',
                    'User (Technician)' => $usage->user->name ?? '-',
                    'Quantity Used' => $usage->quantity_used,
                    'Usage Purpose' => $usage->usage_purpose ?? '-',
                    'Used At' => $usage->used_at->format('Y-m-d H:i:s'),
                ];
            })->toArray();

        if ($format === 'html') {
            return view('asset-management.reports.usage', compact('usages'));
        } elseif ($format === 'xlsx') {
            return $this->exportToXLSX($usages, 'Asset_Usage_Report');
        } elseif ($format === 'csv') {
            return $this->exportToCSV($usages, 'Asset_Usage_Report');
        } elseif ($format === 'pdf') {
            return $this->exportToPDF($usages, 'asset-management.reports.usage-pdf', 'Asset_Usage_Report');
        }
        return response('Invalid format', 400);
    }

    /**
     * Generate Maintenance Report (Repairs, Returns, Write-offs).
     */
    public function maintenanceReport(Request $request)
    {
        $this->authorize('view-asset-reports');
        $format = $request->query('format', 'html');

        $repairs = AssetRepair::with(['trackedAsset.asset.asset_category', 'repairedBy'])->get()->map(function($repair) {
            return [
                'Type' => 'Repair',
                'ID' => $repair->id,
                'Asset Name' => $repair->trackedAsset->asset->name ?? '-',
                'Asset Identifier' => $repair->trackedAsset->qr_code ?? $repair->trackedAsset->serial_number ?? $repair->trackedAsset->mac_address ?? '-',
                'Date' => $repair->repair_date->format('Y-m-d'),
                'Details' => $repair->repair_description,
                'Cost' => $repair->cost_of_repair,
                'Status' => ucfirst($repair->repair_status),
                'User' => $repair->repairedBy->name ?? '-',
                'Reason/Notes' => $repair->notes ?? '-',
            ];
        });

        $supplierReturns = AssetSupplierReturn::with(['trackedAsset.asset.asset_category', 'supplier'])->get()->map(function($return) {
            return [
                'Type' => 'Supplier Return',
                'ID' => $return->id,
                'Asset Name' => $return->trackedAsset->asset->name ?? '-',
                'Asset Identifier' => $return->trackedAsset->qr_code ?? $return->trackedAsset->serial_number ?? $return->trackedAsset->mac_address ?? '-',
                'Date' => $return->return_date->format('Y-m-d'),
                'Details' => $return->return_reason,
                'Cost' => '-', // N/A for returns usually
                'Status' => ucfirst($return->return_status),
                'User' => $return->supplier->name ?? '-', // Supplier acts as 'user' here
                'Reason/Notes' => $return->notes ?? '-',
            ];
        });

        $writeOffs = AssetWriteOff::with(['asset.asset_category', 'trackedAsset', 'user'])->get()->map(function($writeOff) {
            return [
                'Type' => 'Write Off',
                'ID' => $writeOff->id,
                'Asset Name' => $writeOff->asset->name ?? '-',
                'Asset Identifier' => $writeOff->trackedAsset ? ($writeOff->trackedAsset->qr_code ?? $writeOff->trackedAsset->serial_number ?? $writeOff->trackedAsset->mac_address) : 'N/A (Non-tracked)',
                'Date' => $writeOff->written_off_at->format('Y-m-d H:i:s'),
                'Details' => $writeOff->reason,
                'Cost' => '-',
                'Status' => 'Written Off',
                'User' => $writeOff->user->name ?? '-',
                'Reason/Notes' => $writeOff->notes ?? '-',
            ];
        });

        $data = $repairs->concat($supplierReturns)->concat($writeOffs)->sortByDesc('Date')->toArray();

        if ($format === 'html') {
            return view('asset-management.reports.maintenance', compact('data'));
        } elseif ($format === 'xlsx') {
            return $this->exportToXLSX($data, 'Asset_Maintenance_Report');
        } elseif ($format === 'csv') {
            return $this->exportToCSV($data, 'Asset_Maintenance_Report');
        } elseif ($format === 'pdf') {
            return $this->exportToPDF($data, 'asset-management.reports.maintenance-pdf', 'Asset_Maintenance_Report');
        }
        return response('Invalid format', 400);
    }

    /**
     * Export data to XLSX.
     */
    protected function exportToXLSX(array $data, string $fileName): Response
    {
        if (empty($data)) {
            return response('No data to export.', 200);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add headers
        $headers = array_keys($data[0]);
        $sheet->fromArray([$headers], null, 'A1');

        // Add data
        $sheet->fromArray($data, null, 'A2');

        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName . '.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Export data to CSV.
     */
    protected function exportToCSV(array $data, string $fileName): Response
    {
        if (empty($data)) {
            return response('No data to export.', 200);
        }

        $csvContent = '';
        $headers = array_keys($data[0]);
        $csvContent .= implode(',', $headers) . "\n";

        foreach ($data as $row) {
            $csvContent .= implode(',', array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"'; // Escape quotes
            }, $row)) . "\n";
        }

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '.csv"');
    }

    /**
     * Export data to PDF.
     * Needs a Blade view for PDF generation.
     */
    protected function exportToPDF(array $data, string $viewName, string $fileName): Response
    {
        if (empty($data)) {
            return response('No data to export.', 200);
        }

        $pdf = Pdf::loadView($viewName, compact('data'));
        return $pdf->download($fileName . '.pdf');
    }
}