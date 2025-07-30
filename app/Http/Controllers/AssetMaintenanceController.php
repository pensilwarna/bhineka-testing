<?php
// File: app/Http/Controllers/AssetMaintenanceController.php

namespace App\Http\Controllers;

use App\Models\TrackedAsset;
use App\Models\AssetRepair;
use App\Models\AssetSupplierReturn;
use App\Models\AssetWriteOff;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class AssetMaintenanceController extends Controller
{
    /**
     * Display the asset maintenance dashboard.
     */
    public function index()
    {
        $this->authorize('manage-asset-maintenance'); // Authorize user with permission
        $suppliers = Supplier::all(); // Fetch suppliers for supplier return options
        return view('asset-management.maintenance.index', compact('suppliers')); // Pass suppliers to view
    }

    /**
     * Get damaged/maintenance assets data for DataTables.
     */
    public function getDamagedAssetsData(): JsonResponse
    {
        $this->authorize('manage-asset-maintenance');
        $query = TrackedAsset::with('asset', 'currentWarehouse')
                        ->whereIn('current_status', ['damaged', 'in_repair', 'awaiting_return_to_supplier', 'lost', 'scrap']); // Include more relevant statuses
        
        return DataTables::of($query)
            ->addColumn('asset_name', fn($asset) => $asset->asset->name ?? '-')
            ->addColumn('asset_identifier', fn($asset) => $asset->qr_code ?? $asset->serial_number ?? $asset->mac_address ?? '-')
            ->addColumn('warehouse_name', fn($asset) => $asset->currentWarehouse->name ?? '-')
            ->addColumn('status_label', function($asset) {
                $badge = '';
                switch ($asset->current_status) {
                    case 'damaged': $badge = 'danger'; break;
                    case 'in_repair': $badge = 'warning'; break;
                    case 'awaiting_return_to_supplier': $badge = 'info'; break;
                    case 'lost': $badge = 'dark'; break;
                    case 'scrap': $badge = 'secondary'; break;
                    default: $badge = 'light'; break;
                }
                return '<span class="badge bg-label-'.$badge.'">'.ucfirst(str_replace('_', ' ', $asset->current_status)).'</span>';
            })
            ->addColumn('actions', function ($asset) {
                $actions = '<div class="dropdown"><button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button><div class="dropdown-menu">';
                
                // Actions depend on current_status
                if ($asset->current_status == 'damaged' || $asset->current_status == 'lost' || $asset->current_status == 'scrap') { // Can initiate repair/return/write-off from these
                    $actions .= ' <a class="dropdown-item repair-asset" data-id="'.$asset->id.'"><i class="ti ti-tool me-1"></i> Repair</a>';
                    $actions .= ' <a class="dropdown-item return-to-supplier-asset" data-id="'.$asset->id.'"><i class="ti ti-truck me-1"></i> Return to Supplier</a>';
                    $actions .= ' <a class="dropdown-item write-off-asset" data-id="'.$asset->id.'"><i class="ti ti-trash me-1"></i> Write Off</a>';
                } 
                if ($asset->current_status == 'in_repair') {
                     $actions .= ' <a class="dropdown-item complete-repair" data-id="'.$asset->id.'"><i class="ti ti-check me-1"></i> Mark as Repaired</a>';
                     // Option to fail repair and set back to damaged/write_off
                     $actions .= ' <a class="dropdown-item fail-repair" data-id="'.$asset->id.'"><i class="ti ti-x me-1"></i> Fail Repair</a>';
                } 
                if ($asset->current_status == 'awaiting_return_to_supplier') {
                     $actions .= ' <a class="dropdown-item receive-replacement" data-id="'.$asset->id.'"><i class="ti ti-box me-1"></i> Receive Replacement</a>';
                     $actions .= ' <a class="dropdown-item mark-return-rejected" data-id="'.$asset->id.'"><i class="ti ti-x me-1"></i> Mark Return Rejected</a>';
                }
                $actions .= '</div></div>';
                return $actions;
            })
            ->rawColumns(['status_label', 'actions'])
            ->make(true);
    }

    /**
     * Store a new repair record and update asset status.
     */
    public function storeRepair(Request $request): JsonResponse
    {
        $this->authorize('repair-asset'); // Define this permission
        $request->validate([
            'tracked_asset_id' => 'required|exists:tracked_assets,id',
            'repair_date' => 'required|date',
            'repair_description' => 'required|string',
            'cost_of_repair' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string', // Added notes for repair record
        ]);

        $asset = TrackedAsset::findOrFail($request->tracked_asset_id);
        if (!in_array($asset->current_status, ['damaged', 'lost', 'scrap'])) { // Allow repair from damaged, lost, scrap
            return response()->json(['success' => false, 'message' => 'Asset must be in damaged, lost, or scrap status to initiate repair.'], 400);
        }

        DB::beginTransaction();
        try {
            AssetRepair::create([
                'tracked_asset_id' => $asset->id,
                'repair_date' => $request->repair_date,
                'repaired_by_user_id' => auth()->id(),
                'repair_description' => $request->repair_description,
                'cost_of_repair' => $request->cost_of_repair,
                'repair_status' => 'in_progress',
                'notes' => $request->notes,
            ]);

            $asset->update([
                'current_status' => 'in_repair',
                'last_status_change_by_user_id' => auth()->id(),
                'damage_notes' => $asset->damage_notes ? $asset->damage_notes . "\n-- Repair initiated: " . $request->repair_description : $request->repair_description, // Append description
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset marked for repair.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Store Repair Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to mark asset for repair: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark a repair as complete.
     */
    public function completeRepair(Request $request): JsonResponse
    {
        $this->authorize('repair-asset');
        $request->validate([
            'tracked_asset_id' => 'required|exists:tracked_assets,id',
            'repair_outcome_notes' => 'nullable|string', // Notes about repair outcome
        ]);

        $asset = TrackedAsset::findOrFail($request->tracked_asset_id);
        if ($asset->current_status != 'in_repair') {
            return response()->json(['success' => false, 'message' => 'Asset must be in repair status to complete repair.'], 400);
        }

        DB::beginTransaction();
        try {
            $lastRepair = $asset->assetRepairs()->where('repair_status', 'in_progress')->latest()->first();
            if ($lastRepair) {
                $lastRepair->update([
                    'repair_status' => 'completed',
                    'notes' => $lastRepair->notes ? $lastRepair->notes . "\n-- Completed: " . ($request->repair_outcome_notes ?? 'N/A') : ($request->repair_outcome_notes ?? 'N/A'), // Append outcome notes
                ]);
            }

            $asset->update([
                'current_status' => 'available', // Asset is now available
                'last_status_change_by_user_id' => auth()->id(),
                'damage_notes' => null, // Clear damage notes if fully repaired
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset repair completed. Status set to available.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Complete Repair Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to complete asset repair: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark a repair as failed.
     */
    public function failRepair(Request $request): JsonResponse
    {
        $this->authorize('repair-asset');
        $request->validate([
            'tracked_asset_id' => 'required|exists:tracked_assets,id',
            'failure_reason' => 'required|string',
            'new_status_after_failure' => 'required|in:damaged,written_off,scrap', // Status after failed repair
        ]);

        $asset = TrackedAsset::findOrFail($request->tracked_asset_id);
        if ($asset->current_status != 'in_repair') {
            return response()->json(['success' => false, 'message' => 'Asset must be in repair status to mark repair as failed.'], 400);
        }

        DB::beginTransaction();
        try {
            $lastRepair = $asset->assetRepairs()->where('repair_status', 'in_progress')->latest()->first();
            if ($lastRepair) {
                $lastRepair->update([
                    'repair_status' => 'failed',
                    'notes' => $lastRepair->notes ? $lastRepair->notes . "\n-- Failed: " . $request->failure_reason : $request->failure_reason,
                ]);
            }

            $asset->update([
                'current_status' => $request->new_status_after_failure,
                'last_status_change_by_user_id' => auth()->id(),
                'damage_notes' => $asset->damage_notes ? $asset->damage_notes . "\n-- Repair failed: " . $request->failure_reason : $request->failure_reason,
            ]);

            // If status is written_off, create a write-off record
            if ($request->new_status_after_failure === 'written_off') {
                AssetWriteOff::create([
                    'asset_id' => $asset->asset_id,
                    'tracked_asset_id' => $asset->id,
                    'quantity' => 1,
                    'reason' => 'Failed repair: ' . $request->failure_reason,
                    'user_id' => auth()->id(),
                    'written_off_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset repair marked as failed. Status set to ' . $request->new_status_after_failure . '.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Fail Repair Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to mark repair as failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store a new supplier return record and update asset status.
     */
    public function storeSupplierReturn(Request $request): JsonResponse
    {
        $this->authorize('return-asset-to-supplier'); // Define this permission
        $request->validate([
            'tracked_asset_id' => 'required|exists:tracked_assets,id',
            'return_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'return_reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $asset = TrackedAsset::findOrFail($request->tracked_asset_id);
        if (!in_array($asset->current_status, ['damaged', 'lost', 'scrap', 'available'])) { // Allow return from more statuses if policy permits
            return response()->json(['success' => false, 'message' => 'Asset must be in damaged, lost, scrap, or available status to return to supplier.'], 400);
        }

        DB::beginTransaction();
        try {
            AssetSupplierReturn::create([
                'tracked_asset_id' => $asset->id,
                'return_date' => $request->return_date,
                'supplier_id' => $request->supplier_id,
                'return_reason' => $request->return_reason,
                'return_status' => 'sent',
                'notes' => $request->notes,
            ]);

            $asset->update([
                'current_status' => 'awaiting_return_to_supplier',
                'last_status_change_by_user_id' => auth()->id(),
                'notes' => $asset->notes ? $asset->notes . "\n-- Sent to supplier: " . $request->return_reason : $request->return_reason, // Append notes
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset marked for return to supplier.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Store Supplier Return Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to process supplier return: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Receive a replacement asset from supplier or mark original as repaired.
     */
    public function receiveReplacement(Request $request): JsonResponse
    {
        $this->authorize('return-asset-to-supplier');
        $request->validate([
            'return_id' => 'required|exists:asset_supplier_returns,id',
            'new_tracked_asset_id' => 'nullable|exists:tracked_assets,id', // If supplier provides new asset
            'received_date' => 'required|date', // Date when replacement is received
            'notes' => 'nullable|string',
        ]);

        $assetReturn = AssetSupplierReturn::with('trackedAsset')->findOrFail($request->return_id);
        if ($assetReturn->return_status != 'sent') {
            return response()->json(['success' => false, 'message' => 'Supplier return must be in "sent" status to receive replacement.'], 400);
        }

        DB::beginTransaction();
        try {
            if ($request->filled('new_tracked_asset_id')) {
                // Scenario: Supplier sent a new asset as replacement
                $newAsset = TrackedAsset::findOrFail($request->new_tracked_asset_id);
                $newAsset->update([
                    'current_status' => 'available',
                    'current_warehouse_id' => $assetReturn->trackedAsset->current_warehouse_id, // Assume it returns to original warehouse
                    'last_status_change_by_user_id' => auth()->id(),
                    'notes' => $newAsset->notes ? $newAsset->notes . "\n-- Received as replacement for return ID " . $assetReturn->id : "Received as replacement for return ID " . $assetReturn->id,
                ]);

                // Original asset is now considered replaced and written off from active inventory
                $assetReturn->trackedAsset->update([
                    'current_status' => 'written_off',
                    'last_status_change_by_user_id' => auth()->id(),
                    'notes' => $assetReturn->trackedAsset->notes ? $assetReturn->trackedAsset->notes . "\n-- Replaced by " . ($newAsset->qr_code ?? $newAsset->id) : "Replaced by " . ($newAsset->qr_code ?? $newAsset->id),
                ]);
                // Also create a write-off record for the old asset
                AssetWriteOff::create([
                    'asset_id' => $assetReturn->trackedAsset->asset_id,
                    'tracked_asset_id' => $assetReturn->trackedAsset->id,
                    'quantity' => 1,
                    'reason' => 'Replaced by supplier for return ID ' . $assetReturn->id,
                    'user_id' => auth()->id(),
                    'written_off_at' => now(),
                ]);

                $assetReturn->update([
                    'return_status' => 'received_replacement',
                    'replacement_tracked_asset_id' => $newAsset->id,
                    'notes' => $assetReturn->notes ? $assetReturn->notes . "\n-- Replacement Received: " . ($request->notes ?? 'N/A') : ($request->notes ?? 'N/A'),
                ]);

            } else {
                // Scenario: Original asset was repaired by supplier and returned
                $assetReturn->trackedAsset->update([
                    'current_status' => 'available',
                    'last_status_change_by_user_id' => auth()->id(),
                    'notes' => $assetReturn->trackedAsset->notes ? $assetReturn->trackedAsset->notes . "\n-- Repaired and Returned: " . ($request->notes ?? 'N/A') : ($request->notes ?? 'N/A'),
                ]);
                $assetReturn->update([
                    'return_status' => 'received_replacement',
                    'notes' => $assetReturn->notes ? $assetReturn->notes . "\n-- Repaired and Returned: " . ($request->notes ?? 'N/A') : ($request->notes ?? 'N/A'),
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Replacement/repaired asset received.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Receive Replacement Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to receive replacement: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark a supplier return as rejected.
     */
    public function markReturnRejected(Request $request): JsonResponse
    {
        $this->authorize('return-asset-to-supplier');
        $request->validate([
            'return_id' => 'required|exists:asset_supplier_returns,id',
            'rejection_reason' => 'required|string',
            'new_status_after_rejection' => 'required|in:damaged,written_off,scrap', // Status after rejection
        ]);

        $assetReturn = AssetSupplierReturn::with('trackedAsset')->findOrFail($request->return_id);
        if ($assetReturn->return_status != 'sent') {
            return response()->json(['success' => false, 'message' => 'Supplier return must be in "sent" status to mark as rejected.'], 400);
        }

        DB::beginTransaction();
        try {
            $assetReturn->trackedAsset->update([
                'current_status' => $request->new_status_after_rejection,
                'last_status_change_by_user_id' => auth()->id(),
                'damage_notes' => $assetReturn->trackedAsset->damage_notes ? $assetReturn->trackedAsset->damage_notes . "\n-- Return rejected: " . $request->rejection_reason : $request->rejection_reason,
            ]);
            $assetReturn->update([
                'return_status' => 'rejected',
                'notes' => $assetReturn->notes ? $assetReturn->notes . "\n-- Rejected: " . $request->rejection_reason : $request->rejection_reason,
            ]);

            // If status is written_off, create a write-off record
            if ($request->new_status_after_rejection === 'written_off') {
                AssetWriteOff::create([
                    'asset_id' => $assetReturn->trackedAsset->asset_id,
                    'tracked_asset_id' => $assetReturn->trackedAsset->id,
                    'quantity' => 1,
                    'reason' => 'Rejected return from supplier: ' . $request->rejection_reason,
                    'user_id' => auth()->id(),
                    'written_off_at' => now(),
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Supplier return marked as rejected. Asset status updated.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Mark Return Rejected Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to mark return rejected: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Store an asset write-off record and update asset status.
     */
    public function storeWriteOff(Request $request): JsonResponse
    {
        $this->authorize('write-off-asset');
        $request->validate([
            'tracked_asset_id' => 'required|exists:tracked_assets,id',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $asset = TrackedAsset::findOrFail($request->tracked_asset_id);
        // Allow write-off from various problematic/non-active statuses
        if (!in_array($asset->current_status, ['available', 'damaged', 'lost', 'residual', 'scrap', 'in_repair', 'awaiting_return_to_supplier'])) { // Allow available to write off too if needed
            return response()->json(['success' => false, 'message' => 'Asset cannot be written off from its current status (' . $asset->current_status . ').'], 400);
        }

        DB::beginTransaction();
        try {
            AssetWriteOff::create([
                'asset_id' => $asset->asset_id,
                'tracked_asset_id' => $asset->id,
                'quantity' => 1, // Always 1 for tracked assets
                'reason' => $request->reason,
                'user_id' => auth()->id(),
                'written_off_at' => now(),
                'notes' => $request->notes,
            ]);

            $asset->update([
                'current_status' => 'written_off',
                'last_status_change_by_user_id' => auth()->id(),
                'notes' => $asset->notes ? $asset->notes . "\n-- Written off: " . $request->reason : $request->reason,
            ]);

            // Also consider updating related debts if any (e.g., if a loaned asset is written off while still with technician)
            $debt = $asset->technicianAssetDebts()->where('status', 'active')->first();
            if ($debt) {
                $debt->update([
                    'status' => 'written_off',
                    'current_debt_quantity' => 0,
                    'current_debt_value' => 0,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset successfully written off.']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Store Write Off Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to write off asset: ' . $e->getMessage()], 500);
        }
    }
}