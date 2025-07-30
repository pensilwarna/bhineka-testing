<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCheckout;
use App\Models\TechnicianAssetDebt;
use App\Models\TrackedAsset;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DebtService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class AssetCheckoutController extends Controller
{
    protected $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Display the asset checkout dashboard.
     */
    public function index()
    {
        $this->authorize('checkout-asset'); // Define this permission for Warehouse, NOC, Manager, Owner, Super-Admin
        $technicians = User::role('Technician')->get();
        $warehouses = Warehouse::all();
        $assets = Asset::with('asset_category')->get(); // For master asset list (both tracked and non-tracked)
        return view('asset-management.checkout.index', compact('technicians', 'warehouses', 'assets'));
    }

    /**
     * Get active checkouts data for DataTables.
     */
    public function getCheckoutsData(Request $request): JsonResponse
    {
        $this->authorize('checkout-asset');
        $query = AssetCheckout::with(['technician', 'warehouseStaff', 'warehouse']);

        return DataTables::of($query)
            ->addColumn('technician_name', fn($checkout) => $checkout->technician->name ?? '-')
            ->addColumn('warehouse_name', fn($checkout) => $checkout->warehouse->name ?? '-')
            ->addColumn('staff_name', fn($checkout) => $checkout->warehouseStaff->name ?? '-')
            ->addColumn('formatted_total_value', fn($checkout) => 'Rp ' . number_format($checkout->total_value, 0, ',', '.'))
            ->addColumn('exceed_limit_status', function($checkout) {
                return $checkout->exceed_limit ? '<span class="badge bg-label-warning">Exceeded</span>' : '<span class="badge bg-label-success">OK</span>';
            })
            ->rawColumns(['exceed_limit_status'])
            ->make(true);
    }

    /**
     * Show checkout form. (Can be merged with index view)
     */
    public function create()
    {
        $this->authorize('checkout-asset');
        $technicians = User::role('Technician')->get();
        $warehouses = Warehouse::all();
        $assets = Asset::with('asset_category')->get();
        return view('asset-management.checkout.create', compact('technicians', 'warehouses', 'assets'));
    }

    /**
     * Process asset checkout.
     * This method handles creating TechnicianAssetDebt records.
     */
    public function processCheckout(Request $request): JsonResponse
    {
        $this->authorize('checkout-asset');

        $request->validate([
            'technician_id' => 'required|exists:users,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.asset_id' => 'required|exists:assets,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.tracked_asset_id' => 'nullable|exists:tracked_assets,id', // For tracked assets
            'items.*.is_tracked' => 'boolean', // Frontend flag
        ]);

        DB::beginTransaction();
        try {
            $totalCheckoutValue = 0;
            $totalItems = 0;
            $exceedLimit = false;

            $technician = User::findOrFail($request->technician_id);

            // First pass: Calculate potential total debt value and check limit
            $tempDebts = [];
            foreach ($request->items as $itemData) {
                $asset = Asset::find($itemData['asset_id']);
                $unitPrice = $asset->standard_price;

                if ($itemData['is_tracked']) {
                    $trackedAsset = TrackedAsset::findOrFail($itemData['tracked_asset_id']);
                    if ($trackedAsset->current_status !== 'available' || $trackedAsset->current_warehouse_id != $request->warehouse_id) {
                        throw ValidationException::withMessages([
                            'items' => ['Tracked asset ' . ($trackedAsset->qr_code ?? $trackedAsset->serial_number) . ' is not available in the selected warehouse.']
                        ]);
                    }
                    $tempDebts[] = [
                        'asset' => $asset,
                        'tracked_asset' => $trackedAsset,
                        'quantity' => 1,
                        'unit_price' => $unitPrice,
                        'total_item_value' => $unitPrice,
                    ];
                    $totalCheckoutValue += $unitPrice;
                    $totalItems += 1;
                } else {
                    if ($asset->available_quantity < $itemData['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => ['Insufficient stock for asset ' . $asset->name . '. Available: ' . $asset->available_quantity . ', Requested: ' . $itemData['quantity'] . '.']
                        ]);
                    }
                    $tempDebts[] = [
                        'asset' => $asset,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $unitPrice,
                        'total_item_value' => $unitPrice * $itemData['quantity'],
                    ];
                    $totalCheckoutValue += $unitPrice * $itemData['quantity'];
                    $totalItems += $itemData['quantity'];
                }
            }

            $debtCheckResult = $this->debtService->checkDebtLimit($request->technician_id, $totalCheckoutValue);
            if ($this->debtService->requiresNOCApproval($debtCheckResult)) {
                $exceedLimit = true;
                // For now, we allow creation but mark as needing approval.
                // In a real system, you might throw an error and require explicit approval before saving.
                // For simplicity here, we'll save and indicate approval needed.
            }

            // Create AssetCheckout record (summary of the transaction)
            $assetCheckout = AssetCheckout::create([
                'technician_id' => $request->technician_id,
                'warehouse_staff_id' => auth()->id(),
                'warehouse_id' => $request->warehouse_id,
                'checkout_date' => now()->toDateString(),
                'total_items' => $totalItems,
                'total_value' => $totalCheckoutValue,
                'exceed_limit' => $exceedLimit,
                'notes' => $request->notes,
            ]);

            // Second pass: Create individual TechnicianAssetDebt records and update asset quantities
            foreach ($tempDebts as $debtData) {
                if ($debtData['asset']->requires_qr_tracking) {
                    // For tracked assets
                    $debt = TechnicianAssetDebt::create([
                        'technician_id' => $request->technician_id,
                        'asset_id' => $debtData['asset']->id,
                        'tracked_asset_id' => $debtData['tracked_asset']->id,
                        'warehouse_id' => $request->warehouse_id,
                        'checkout_by_user_id' => auth()->id(),
                        'quantity_taken' => 1, // Always 1 for tracked assets
                        'unit_price' => $debtData['unit_price'],
                        'total_debt_value' => $debtData['total_item_value'],
                        'current_debt_quantity' => 1,
                        'current_debt_value' => $debtData['total_item_value'],
                        'checkout_date' => now()->toDateString(),
                        'notes' => 'Checkout for ticket related activity.',
                        'status' => 'active',
                        'exceed_limit_approved_by' => $exceedLimit ? null : null, // Set by approval process
                        'exceed_limit_approved_at' => $exceedLimit ? null : null, // Set by approval process
                        'approval_reason' => $exceedLimit ? 'Awaiting NOC approval' : null,
                    ]);
                    
                    // Update TrackedAsset status
                    $debtData['tracked_asset']->update([
                        'current_status' => 'loaned', // Status when with technician
                        'current_warehouse_id' => null, // No longer in a warehouse
                        'last_status_change_by_user_id' => auth()->id(),
                        'notes' => 'Loaned to Technician ' . $technician->name . ' for Asset Checkout ID: ' . $assetCheckout->id
                    ]);

                } else {
                    // For non-tracked assets
                    $debt = TechnicianAssetDebt::create([
                        'technician_id' => $request->technician_id,
                        'asset_id' => $debtData['asset']->id,
                        'warehouse_id' => $request->warehouse_id,
                        'checkout_by_user_id' => auth()->id(),
                        'quantity_taken' => $debtData['quantity'],
                        'unit_price' => $debtData['unit_price'],
                        'total_debt_value' => $debtData['total_item_value'],
                        'current_debt_quantity' => $debtData['quantity'],
                        'current_debt_value' => $debtData['total_item_value'],
                        'checkout_date' => now()->toDateString(),
                        'notes' => 'Checkout for ticket related activity.',
                        'status' => 'active',
                        'exceed_limit_approved_by' => $exceedLimit ? null : null,
                        'exceed_limit_approved_at' => $exceedLimit ? null : null,
                        'approval_reason' => $exceedLimit ? 'Awaiting NOC approval' : null,
                    ]);

                    // Update Asset quantities
                    $debtData['asset']->decrement('available_quantity', $debtData['quantity']);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Assets checked out successfully. Total value: Rp ' . number_format($totalCheckoutValue, 0, ',', '.'),
                'exceed_limit_warning' => $exceedLimit,
                'debt_check' => $debtCheckResult
            ]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Asset Checkout Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to process checkout: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Process asset return from technician.
     * This updates TechnicianAssetDebt records.
     */
    public function processReturn(Request $request): JsonResponse
    {
        $this->authorize('checkout-asset'); // Warehouse staff handles return

        $request->validate([
            'technician_id' => 'required|exists:users,id',
            'returned_to_warehouse_id' => 'required|exists:warehouses,id',
            'returned_items' => 'required|array|min:1',
            'returned_items.*.debt_id' => 'required|exists:technician_asset_debts,id',
            'returned_items.*.quantity_returned' => 'required|numeric|min:0.001',
            'returned_items.*.is_tracked' => 'boolean', // Frontend flag
            'returned_items.*.tracked_asset_status_on_return' => 'nullable|string|in:available,damaged,scrap,lost', // Status after return
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->returned_items as $itemData) {
                $debt = TechnicianAssetDebt::with('asset', 'trackedAsset')->findOrFail($itemData['debt_id']);

                if ($debt->technician_id != $request->technician_id) {
                    throw ValidationException::withMessages(['returned_items' => ['Debt ID ' . $itemData['debt_id'] . ' does not belong to the selected technician.']]);
                }
                if ($debt->status !== 'active') {
                    throw ValidationException::withMessages(['returned_items' => ['Debt ID ' . $itemData['debt_id'] . ' is not active and cannot be returned.']]);
                }

                $quantityToReturn = $itemData['quantity_returned'];
                if ($quantityToReturn > $debt->current_debt_quantity) {
                    throw ValidationException::withMessages(['returned_items' => ['Quantity to return for debt ' . $itemData['debt_id'] . ' exceeds current debt quantity.']]);
                }

                $debt->quantity_returned += $quantityToReturn;
                $debt->current_debt_quantity -= $quantityToReturn;
                $debt->current_debt_value -= ($debt->unit_price * $quantityToReturn);

                if ($debt->current_debt_quantity <= 0.001) { // Use small epsilon for float comparison
                    $debt->status = 'fully_settled';
                    $debt->current_debt_quantity = 0;
                    $debt->current_debt_value = 0;
                } else {
                    $debt->status = 'partially_returned';
                }
                $debt->save();

                if ($itemData['is_tracked'] && $debt->trackedAsset) {
                    // Update TrackedAsset status based on return condition
                    $trackedStatus = $itemData['tracked_asset_status_on_return'] ?? 'available';
                    $debt->trackedAsset->update([
                        'current_status' => $trackedStatus,
                        'current_warehouse_id' => $request->returned_to_warehouse_id,
                        'last_status_change_by_user_id' => auth()->id(),
                        'notes' => 'Returned by Technician ' . $debt->technician->name . '. Status: ' . $trackedStatus
                    ]);

                    // If it was a cable and partially returned, update its current_length
                    if ($debt->trackedAsset->asset->asset_category->unit && 
                        str_contains(strtolower($debt->trackedAsset->asset->asset_category->unit), 'meter') && 
                        $debt->trackedAsset->current_length !== null) {
                        // This assumes processReturn only for items that are *returned* not used.
                        // For cables, usage should modify current_length, not return.
                        // If a cable roll is returned with a different length than initial_length, it means it was partially used.
                        // For simplicity, we assume 'quantity_returned' for tracked cables means the *whole roll* is returned
                        // and its current_length reflects the remaining length.
                        // More complex logic might be needed if user needs to specify remaining length on return.
                    }

                } elseif (!$itemData['is_tracked'] && $debt->asset) {
                    // For non-tracked assets, increment available stock in Asset model
                    $debt->asset->increment('available_quantity', $quantityToReturn);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Assets returned successfully.']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Asset Return Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to process return: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get active debts for a specific technician for return/settlement.
     */
    public function getTechnicianActiveDebts(User $technician): JsonResponse
    {
        $this->authorize('checkout-asset');
        $debts = TechnicianAssetDebt::with(['asset.asset_category', 'trackedAsset'])
                                ->where('technician_id', $technician->id)
                                ->where('status', 'active')
                                ->get()
                                ->map(function($debt) {
                                    $isTracked = ($debt->tracked_asset_id !== null);
                                    $identifier = $isTracked ? ($debt->trackedAsset->qr_code ?? $debt->trackedAsset->serial_number ?? $debt->trackedAsset->mac_address) : $debt->asset->name;
                                    $unitMeasure = $isTracked ? ($debt->trackedAsset->unit_of_measure ?? 'unit') : ($debt->asset->asset_category->unit ?? 'unit');
                                    
                                    return [
                                        'id' => $debt->id,
                                        'asset_name' => $debt->asset->name,
                                        'identifier' => $identifier, // QR/SN/MAC for tracked, name for non-tracked
                                        'asset_category' => $debt->asset->asset_category->name ?? '-',
                                        'quantity_taken' => $debt->quantity_taken,
                                        'current_debt_quantity' => $debt->current_debt_quantity,
                                        'current_debt_value' => 'Rp ' . number_format($debt->current_debt_value, 0, ',', '.'),
                                        'is_tracked' => $isTracked,
                                        'unit_of_measure' => $unitMeasure,
                                        'tracked_asset_id' => $debt->tracked_asset_id, // Important for UI to pass back
                                        // Include status of tracked asset if exists
                                        'tracked_asset_current_status' => $isTracked ? ($debt->trackedAsset->current_status ?? 'N/A') : 'N/A',
                                    ];
                                });
        return response()->json($debts);
    }
}