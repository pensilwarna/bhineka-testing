<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInstalledAsset;
use App\Models\TrackedAsset;
use App\Models\Asset;
use App\Models\TechnicianAssetDebt;
use App\Models\CustomerAssetAuditLog; // ADDED
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CustomerAssetController extends Controller
{
    /**
     * Display customer installed assets index.
     */
    public function index()
    {
        $this->authorize('view-customer-assets'); // Define permission
        $warehouse = Warehouse::all();
        return view('asset-management.customer-assets.index', ['warehouses' => $warehouse]);
    }

    /**
     * Get customer installed assets data for DataTables.
     */
    public function getCustomerAssetsData(Request $request): JsonResponse
    {
        $this->authorize('view-customer-assets');
        $query = CustomerInstalledAsset::with(['customer', 'serviceLocation', 'technician', 'asset.asset_category', 'trackedAsset']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addColumn('customer_name', fn($cia) => $cia->customer->name ?? '-')
            ->addColumn('service_location_address', fn($cia) => $cia->serviceLocation->address ?? '-')
            ->addColumn('asset_name', fn($cia) => $cia->asset->name ?? '-')
            ->addColumn('asset_identifier', function($cia) {
                if ($cia->trackedAsset) {
                    return $cia->trackedAsset->qr_code ?? $cia->trackedAsset->serial_number ?? $cia->trackedAsset->mac_address ?? '-';
                }
                return '-';
            })
            ->addColumn('formatted_value', fn($cia) => 'Rp ' . number_format($cia->total_asset_value, 0, ',', '.'))
            ->addColumn('status_label', function($cia) {
                $badge = '';
                switch ($cia->status) {
                    case 'installed': $badge = 'bg-label-success'; break;
                    case 'removed': $badge = 'bg-label-info'; break;
                    case 'replaced': $badge = 'bg-label-primary'; break;
                    case 'damaged': $badge = 'bg-label-warning'; break;
                    default: $badge = 'bg-label-secondary'; break;
                }
                return '<span class="badge ' . $badge . '">' . ucfirst($cia->status) . '</span>';
            })
            ->addColumn('actions', function($cia) {
                $actions = '<div class="dropdown"><button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button><div class="dropdown-menu">';
                $actions .= '<a class="dropdown-item view-detail" data-id="'.$cia->id.'"><i class="ti ti-eye me-1"></i> Detail</a>';
                if ($cia->status === 'installed' || $cia->status === 'replaced') {
                    $actions .= '<a class="dropdown-item remove-asset" data-id="'.$cia->id.'"><i class="ti ti-trash me-1"></i> Remove</a>';
                    $actions .= '<a class="dropdown-item replace-asset" data-id="'.$cia->id.'"><i class="ti ti-exchange me-1"></i> Replace</a>';
                }
                if ($cia->asset->requires_qr_tracking && ($cia->asset->asset_category->unit && str_contains(strtolower($cia->asset->asset_category->unit), 'meter'))) {
                    // Action for auditing cable length
                    $actions .= '<a class="dropdown-item audit-cable-length" data-id="'.$cia->id.'"><i class="ti ti-ruler-alt me-1"></i> Audit Length</a>';
                }
                $actions .= '</div></div>';
                return $actions;
            })
            ->rawColumns(['status_label', 'actions'])
            ->make(true);
    }

    /**
     * Show installed assets for a specific customer.
     */
    public function showCustomerAssets(Customer $customer)
    {
        $this->authorize('view-customer-assets');
        return view('asset-management.customer-assets.customer-detail', compact('customer'));
    }

    /**
     * Get installed assets data for a specific customer.
     */
    public function getCustomerAssetData(Customer $customer): JsonResponse
    {
        $this->authorize('view-customer-assets');
        $assets = CustomerInstalledAsset::with(['serviceLocation', 'technician', 'asset.asset_category', 'trackedAsset', 'debt'])
                                        ->where('customer_id', $customer->id)
                                        ->get()
                                        ->map(function($cia) {
                                            $isCable = $cia->asset->asset_category->unit && str_contains(strtolower($cia->asset->asset_category->unit), 'meter');
                                            return [
                                                'id' => $cia->id,
                                                'asset_name' => $cia->asset->name ?? '-',
                                                'identifier' => $cia->trackedAsset ? ($cia->trackedAsset->qr_code ?? $cia->trackedAsset->serial_number ?? $cia->trackedAsset->mac_address) : 'N/A',
                                                'quantity_installed' => $cia->quantity_installed,
                                                'unit_value' => 'Rp ' . number_format($cia->unit_value, 0, ',', '.'),
                                                'total_asset_value' => 'Rp ' . number_format($cia->total_asset_value, 0, ',', '.'),
                                                'installation_date' => $cia->installation_date->format('d M Y'),
                                                'status' => $cia->status,
                                                'is_cable' => $isCable,
                                                'installed_length' => $isCable ? ($cia->installed_length . ' ' . ($cia->asset->asset_category->unit ?? 'unit')) : 'N/A',
                                                'current_length' => $isCable ? ($cia->current_length . ' ' . ($cia->asset->asset_category->unit ?? 'unit')) : 'N/A',
                                                'installation_notes' => $cia->installation_notes,
                                                'removed_date' => $cia->removed_date ? $cia->removed_date->format('d M Y') : '-',
                                                'removal_notes' => $cia->removal_notes,
                                                'technician_name' => $cia->technician->name ?? '-',
                                            ];
                                        });
        return response()->json($assets);
    }


    /**
     * Mark an asset as installed at customer location.
     * This requires linking to a TechnicianAssetDebt.
     */
    public function markAsInstalled(Request $request): JsonResponse
    {
        $this->authorize('install-customer-asset'); // Define this permission for Technician, NOC, Manager, Owner, Super-Admin

        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'customer_id' => 'required|exists:customers,id',
            'service_location_id' => 'required|exists:service_locations,id',
            'asset_id' => 'required|exists:assets,id',
            'tracked_asset_id' => 'nullable|exists:tracked_assets,id', // For tracked assets
            'quantity_installed' => 'required|numeric|min:0.001',
            'debt_id' => 'required|exists:technician_asset_debts,id', // Link to the technician's debt
            'installation_date' => 'required|date',
            'installation_photos' => 'nullable|array',
            'installation_photos.*' => 'image|mimes:jpeg,png,jpg|max:2048', // 2MB max
            'installation_notes' => 'nullable|string',
            'gps_latitude' => 'nullable|numeric|between:-90,90',
            'gps_longitude' => 'nullable|numeric|between:-180,180',
            'installed_length' => 'nullable|numeric|min:0', // For cables
        ]);

        DB::beginTransaction();
        try {
            $asset = Asset::find($request->asset_id);
            $unitValue = $asset->standard_price;
            $totalAssetValue = $unitValue * $request->quantity_installed;

            $installationPhotos = [];
            if ($request->hasFile('installation_photos')) {
                foreach ($request->file('installation_photos') as $photo) {
                    $path = $photo->store('customer-assets/installation', 'public');
                    $installationPhotos[] = $path;
                }
            }
            
            // Validate tracked asset if provided
            $trackedAsset = null;
            if ($request->filled('tracked_asset_id')) {
                $trackedAsset = TrackedAsset::find($request->tracked_asset_id);
                if (!$trackedAsset || $trackedAsset->current_status !== 'loaned') {
                    throw ValidationException::withMessages(['tracked_asset_id' => ['The selected tracked asset is not currently loaned or available for installation.']]);
                }
                 // If it's a cable, validate installed_length
                if ($trackedAsset->asset->requires_qr_tracking && ($trackedAsset->unit_of_measure && str_contains(strtolower($trackedAsset->unit_of_measure), 'meter'))) {
                    if (empty($request->installed_length) || $request->installed_length <= 0) {
                        throw ValidationException::withMessages(['installed_length' => ['Installed length is required for cable assets.']]);
                    }
                    if ($request->installed_length > $trackedAsset->current_length) {
                         throw ValidationException::withMessages(['installed_length' => ['Installed length cannot exceed the current length of the cable roll (' . $trackedAsset->current_length . ' ' . $trackedAsset->unit_of_measure . ').']]);
                    }
                    $trackedAsset->decrement('current_length', $request->installed_length); // Decrease the current length of the roll
                } else {
                    // For non-cable tracked assets, quantity_installed should be 1
                    if ($request->quantity_installed != 1) {
                         throw ValidationException::withMessages(['quantity_installed' => ['Quantity for this tracked asset must be 1.']]);
                    }
                }
            } else {
                // If not a tracked asset, decrement from master asset's available_quantity
                $asset->decrement('available_quantity', $request->quantity_installed);
            }

            $customerAsset = CustomerInstalledAsset::create([
                'customer_id' => $request->customer_id,
                'service_location_id' => $request->service_location_id,
                'ticket_id' => $request->ticket_id,
                'technician_id' => auth()->id(), // Technician is the current user
                'asset_id' => $request->asset_id,
                'tracked_asset_id' => $request->tracked_asset_id,
                'debt_id' => $request->debt_id,
                'quantity_installed' => $request->quantity_installed,
                'unit_value' => $unitValue,
                'total_asset_value' => $totalAssetValue,
                'installation_date' => $request->installation_date,
                'installation_photos' => $installationPhotos,
                'installation_notes' => $request->installation_notes,
                'gps_latitude' => $request->gps_latitude,
                'gps_longitude' => $request->gps_longitude,
                'status' => 'installed',
                'installed_length' => $request->installed_length, // For cables
                'current_length' => $request->installed_length, // Initial current length for audit
            ]);

            // Update TechnicianAssetDebt status (mark as installed, not fully settled yet)
            $debt = TechnicianAssetDebt::find($request->debt_id);
            if ($debt) {
                $debt->increment('quantity_installed', $request->quantity_installed);
                $debt->decrement('current_debt_quantity', $request->quantity_installed);
                $debt->decrement('current_debt_value', $unitValue * $request->quantity_installed);

                if ($debt->current_debt_quantity <= 0.001) {
                    $debt->status = 'fully_settled'; // Or 'settled_by_installation'
                    $debt->current_debt_quantity = 0;
                    $debt->current_debt_value = 0;
                } else {
                    $debt->status = 'partially_returned'; // If some quantity remains due
                }
                $debt->save();
            }

            // If tracked asset, update its status
            if ($trackedAsset) {
                // For cable rolls, they might still be partially used for other installations, so status remains 'loaned'
                // For actual devices, change status to 'installed'
                if (!($trackedAsset->asset->asset_category->unit && str_contains(strtolower($trackedAsset->asset->asset_category->unit), 'meter'))) {
                     $trackedAsset->update([
                        'current_status' => 'installed',
                        'last_status_change_by_user_id' => auth()->id(),
                        'notes' => 'Installed at customer ' . $customerAsset->customer->name . ' (Service Location: ' . $customerAsset->serviceLocation->address . ') for Ticket ID: ' . $customerAsset->ticket_id
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset installed successfully.', 'asset' => $customerAsset]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Asset Installation Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to install asset: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mark an installed asset as removed from customer location.
     */
    public function markAsRemoved(Request $request): JsonResponse
    {
        $this->authorize('remove-customer-asset'); // Define this permission for Technician, NOC, Manager, Owner, Super-Admin

        $request->validate([
            'customer_installed_asset_id' => 'required|exists:customer_installed_assets,id',
            'removed_date' => 'required|date',
            'removal_photos' => 'nullable|array',
            'removal_photos.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'removal_notes' => 'nullable|string',
            'returned_to_warehouse_id' => 'nullable|exists:warehouses,id', // If asset is physically returned to warehouse
            'status_on_return' => 'nullable|string|in:available,damaged,scrap', // Status of the returned asset
        ]);

        DB::beginTransaction();
        try {
            $customerAsset = CustomerInstalledAsset::with('trackedAsset', 'asset')->findOrFail($request->customer_installed_asset_id);

            if (!in_array($customerAsset->status, ['installed', 'replaced'])) {
                return response()->json(['success' => false, 'message' => 'Asset must be in installed or replaced status to be removed.'], 400);
            }

            $removalPhotos = [];
            if ($request->hasFile('removal_photos')) {
                foreach ($request->file('removal_photos') as $photo) {
                    $path = $photo->store('customer-assets/removal', 'public');
                    $removalPhotos[] = $path;
                }
            }

            $customerAsset->update([
                'status' => 'removed',
                'removed_date' => $request->removed_date,
                'removed_by' => auth()->id(),
                'removal_photos' => $removalPhotos,
                'removal_notes' => $request->removal_notes,
                'total_asset_value' => 0, // Value at customer becomes zero
                'current_length' => null, // For cables, assume removed length is gone
            ]);

            // Handle Tracked Asset status update if applicable
            if ($customerAsset->trackedAsset) {
                // If the asset is a device (not a cable roll), it's returned to warehouse
                if (!($customerAsset->trackedAsset->asset->asset_category->unit && str_contains(strtolower($customerAsset->trackedAsset->asset->asset_category->unit), 'meter'))) {
                    $trackedAssetStatus = $request->status_on_return ?? 'damaged'; // Default to damaged if not specified
                    if ($request->filled('returned_to_warehouse_id')) {
                        $customerAsset->trackedAsset->update([
                            'current_status' => $trackedAssetStatus,
                            'current_warehouse_id' => $request->returned_to_warehouse_id,
                            'last_status_change_by_user_id' => auth()->id(),
                            'notes' => 'Removed from customer ' . $customerAsset->customer->name . ' and returned to warehouse. Status: ' . $trackedAssetStatus
                        ]);
                    } else {
                        // If not returned to warehouse (e.g., left at site as scrap, or lost by tech)
                         $customerAsset->trackedAsset->update([
                            'current_status' => 'lost', // Or a more appropriate "not returned" status
                            'last_status_change_by_user_id' => auth()->id(),
                            'notes' => 'Removed from customer ' . $customerAsset->customer->name . ', but not returned to warehouse. Status: ' . $trackedAssetStatus
                        ]);
                    }
                } else {
                    // Cable: If a cable segment is 'removed', it's usually scrapped. The roll it came from might still be loaned.
                    // This scenario needs clear policy. For now, we assume the *segment* is removed from customer's record.
                    // The main cable roll's current_length is only reduced upon usage, not removal.
                    // If the whole *roll* was installed as a single unit, then its trackedAsset's status can change.
                    // For simplicity, removed cables are implicitly written off/scrapped, not returned to stock.
                }
            } else {
                // For non-tracked assets, they are removed but not returned to central stock. They are "consumed".
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset removed successfully.']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Asset Removal Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to remove asset: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Replace an installed asset with a new one.
     */
    public function replaceAsset(Request $request): JsonResponse
    {
        $this->authorize('replace-customer-asset'); // Define permission

        $request->validate([
            'old_customer_installed_asset_id' => 'required|exists:customer_installed_assets,id',
            'new_asset_id' => 'required|exists:assets,id',
            'new_tracked_asset_id' => 'nullable|exists:tracked_assets,id', // For tracked replacement
            'new_quantity_installed' => 'required|numeric|min:0.001',
            'new_debt_id' => 'required|exists:technician_asset_debts,id',
            'replacement_date' => 'required|date',
            'replacement_notes' => 'nullable|string',
            'replacement_photos' => 'nullable|array',
            'replacement_photos.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'status_old_asset_on_return' => 'nullable|string|in:available,damaged,scrap,lost',
            'old_asset_returned_to_warehouse_id' => 'nullable|exists:warehouses,id',
            'new_installed_length' => 'nullable|numeric|min:0', // For cable replacement
        ]);

        DB::beginTransaction();
        try {
            // Step 1: Mark the old asset as 'replaced'
            $oldCustomerAsset = CustomerInstalledAsset::with('trackedAsset', 'asset')->findOrFail($request->old_customer_installed_asset_id);

            if (!in_array($oldCustomerAsset->status, ['installed', 'replaced'])) {
                return response()->json(['success' => false, 'message' => 'Old asset must be in installed or replaced status to be replaced.'], 400);
            }

            $replacementPhotos = [];
            if ($request->hasFile('replacement_photos')) {
                foreach ($request->file('replacement_photos') as $photo) {
                    $path = $photo->store('customer-assets/replacement', 'public');
                    $replacementPhotos[] = $path;
                }
            }

            $oldCustomerAsset->update([
                'status' => 'replaced',
                'removed_date' => $request->replacement_date,
                'removed_by' => auth()->id(),
                'removal_notes' => $request->replacement_notes . ' (Replaced by new asset: ' . $request->new_asset_id . ')',
                'removal_photos' => $replacementPhotos,
                'total_asset_value' => 0, // Value at customer becomes zero for the old one
                'current_length' => null, // For old cables
            ]);

            // Step 2: Handle the old Tracked Asset status (if applicable)
            if ($oldCustomerAsset->trackedAsset) {
                if (!($oldCustomerAsset->trackedAsset->asset->asset_category->unit && str_contains(strtolower($oldCustomerAsset->trackedAsset->asset->asset_category->unit), 'meter'))) {
                    $oldTrackedAssetStatus = $request->status_old_asset_on_return ?? 'damaged';
                    if ($request->filled('old_asset_returned_to_warehouse_id')) {
                        $oldCustomerAsset->trackedAsset->update([
                            'current_status' => $oldTrackedAssetStatus,
                            'current_warehouse_id' => $request->old_asset_returned_to_warehouse_id,
                            'last_status_change_by_user_id' => auth()->id(),
                            'notes' => 'Replaced and returned from customer ' . $oldCustomerAsset->customer->name . '. Status: ' . $oldTrackedAssetStatus
                        ]);
                    } else {
                        $oldCustomerAsset->trackedAsset->update([
                            'current_status' => 'lost', // Or 'scrap' if left at site
                            'last_status_change_by_user_id' => auth()->id(),
                            'notes' => 'Replaced from customer ' . $oldCustomerAsset->customer->name . ', but not returned to warehouse. Status: ' . $oldTrackedAssetStatus
                        ]);
                    }
                }
            }

            // Step 3: Create a new CustomerInstalledAsset record for the new asset
            $newAsset = Asset::find($request->new_asset_id);
            $newUnitValue = $newAsset->standard_price;
            $newTotalAssetValue = $newUnitValue * $request->new_quantity_installed;

            $newTrackedAsset = null;
            if ($request->filled('new_tracked_asset_id')) {
                $newTrackedAsset = TrackedAsset::find($request->new_tracked_asset_id);
                 if (!$newTrackedAsset || $newTrackedAsset->current_status !== 'loaned') {
                    throw ValidationException::withMessages(['new_tracked_asset_id' => ['The selected new tracked asset is not currently loaned or available for installation.']]);
                }
                if ($newTrackedAsset->asset->requires_qr_tracking && ($newTrackedAsset->unit_of_measure && str_contains(strtolower($newTrackedAsset->unit_of_measure), 'meter'))) {
                    if (empty($request->new_installed_length) || $request->new_installed_length <= 0) {
                        throw ValidationException::withMessages(['new_installed_length' => ['Installed length is required for new cable assets.']]);
                    }
                    if ($request->new_installed_length > $newTrackedAsset->current_length) {
                         throw ValidationException::withMessages(['new_installed_length' => ['Installed length cannot exceed the current length of the new cable roll (' . $newTrackedAsset->current_length . ' ' . $newTrackedAsset->unit_of_measure . ').']]);
                    }
                    $newTrackedAsset->decrement('current_length', $request->new_installed_length); // Decrease the current length of the new roll
                } else {
                    if ($request->new_quantity_installed != 1) {
                         throw ValidationException::withMessages(['new_quantity_installed' => ['Quantity for this new tracked asset must be 1.']]);
                    }
                }
            } else {
                $newAsset->decrement('available_quantity', $request->new_quantity_installed);
            }

            $newCustomerAsset = CustomerInstalledAsset::create([
                'customer_id' => $oldCustomerAsset->customer_id,
                'service_location_id' => $oldCustomerAsset->service_location_id,
                'ticket_id' => $oldCustomerAsset->ticket_id, // Link to the same ticket or new ticket? Assuming same for replacement context
                'technician_id' => auth()->id(),
                'asset_id' => $request->new_asset_id,
                'tracked_asset_id' => $request->new_tracked_asset_id,
                'debt_id' => $request->new_debt_id,
                'quantity_installed' => $request->new_quantity_installed,
                'unit_value' => $newUnitValue,
                'total_asset_value' => $newTotalAssetValue,
                'installation_date' => $request->replacement_date,
                'installation_notes' => $request->replacement_notes . ' (Replaced old asset ' . $oldCustomerAsset->asset->name . ')',
                'installation_photos' => $replacementPhotos, // Use replacement photos as installation photos for new asset
                'gps_latitude' => $oldCustomerAsset->gps_latitude, // Keep same GPS
                'gps_longitude' => $oldCustomerAsset->gps_longitude,
                'status' => 'installed',
                'installed_length' => $request->new_installed_length,
                'current_length' => $request->new_installed_length,
            ]);

            // Step 4: Update debt for new asset
            $newDebt = TechnicianAssetDebt::find($request->new_debt_id);
            if ($newDebt) {
                $newDebt->increment('quantity_installed', $request->new_quantity_installed);
                $newDebt->decrement('current_debt_quantity', $request->new_quantity_installed);
                $newDebt->decrement('current_debt_value', $newUnitValue * $request->new_quantity_installed);

                if ($newDebt->current_debt_quantity <= 0.001) {
                    $newDebt->status = 'fully_settled';
                    $newDebt->current_debt_quantity = 0;
                    $newDebt->current_debt_value = 0;
                } else {
                    $newDebt->status = 'partially_returned';
                }
                $newDebt->save();
            }

            // If new asset is tracked, update its status
            if ($newTrackedAsset) {
                 if (!($newTrackedAsset->asset->asset_category->unit && str_contains(strtolower($newTrackedAsset->asset->asset_category->unit), 'meter'))) {
                    $newTrackedAsset->update([
                        'current_status' => 'installed',
                        'last_status_change_by_user_id' => auth()->id(),
                        'notes' => 'Installed as replacement at customer ' . $newCustomerAsset->customer->name . ' for Ticket ID: ' . $newCustomerAsset->ticket_id
                    ]);
                 }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Asset replaced successfully.', 'new_asset' => $newCustomerAsset]);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Asset Replacement Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to replace asset: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload photos for installation/removal (utility method)
     */
    public function uploadPhotos(Request $request): JsonResponse
    {
        $this->authorize('upload-asset-photos'); // Define this permission
        $request->validate([
            'photos' => 'required|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:2048',
            'type' => 'required|in:installation,removal,replacement',
        ]);

        $paths = [];
        try {
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('customer-assets/' . $request->type, 'public');
                $paths[] = Storage::url($path); // Return public URL
            }
            return response()->json(['success' => true, 'message' => 'Photos uploaded successfully.', 'paths' => $paths]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to upload photos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a specific photo from an installed asset.
     */
    public function deletePhoto($id, Request $request): JsonResponse
    {
        $this->authorize('delete-asset-photo'); // Define this permission
        $request->validate([
            'photo_path' => 'required|string',
            'type' => 'required|in:installation,removal', // 'replacement' photos are considered installation_photos for new CIA
        ]);

        try {
            $customerAsset = CustomerInstalledAsset::findOrFail($id);
            $photoKey = $request->type . '_photos'; // e.g., 'installation_photos'

            $currentPhotos = $customerAsset->$photoKey ?? [];
            $photoPathToDelete = Str::after($request->photo_path, '/storage/'); // Get path relative to storage disk

            $updatedPhotos = array_values(array_filter($currentPhotos, function($photo) use ($photoPathToDelete) {
                return !Str::contains($photo, $photoPathToDelete);
            }));

            $customerAsset->update([$photoKey => $updatedPhotos]);
            Storage::disk('public')->delete($photoPathToDelete);

            return response()->json(['success' => true, 'message' => 'Photo deleted successfully.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete photo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Audit and adjust cable length for customer installed asset.
     */
    public function auditAdjust(Request $request): JsonResponse
    {
        $this->authorize('audit-customer-assets'); // Define permission for Manager, Owner, Super-Admin, LAD

        $request->validate([
            'customer_installed_asset_id' => 'required|exists:customer_installed_assets,id',
            'new_current_length' => 'required|numeric|min:0',
            'change_reason' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $customerAsset = CustomerInstalledAsset::with('asset.asset_category')->findOrFail($request->customer_installed_asset_id);

            // Ensure it's a cable asset
            if (!($customerAsset->asset->asset_category->unit && str_contains(strtolower($customerAsset->asset->asset_category->unit), 'meter'))) {
                return response()->json(['success' => false, 'message' => 'This asset is not a cable and its length cannot be audited.'], 400);
            }

            $oldLength = $customerAsset->current_length;

            $customerAsset->update([
                'current_length' => $request->new_current_length,
            ]);

            CustomerAssetAuditLog::create([
                'customer_installed_asset_id' => $customerAsset->id,
                'audit_date' => now()->toDateString(),
                'audited_by_user_id' => auth()->id(),
                'old_value' => $oldLength,
                'new_value' => $request->new_current_length,
                'change_reason' => $request->change_reason,
                'notes' => $request->notes,
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Cable length audited and updated successfully.']);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Customer Asset Audit Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to update cable length: ' . $e->getMessage()], 500);
        }
    }

    // You might want to add a method to view audit logs for a specific customer asset
    public function getAuditLogsForCustomerAsset(CustomerInstalledAsset $customerInstalledAsset): JsonResponse
    {
        $this->authorize('view-customer-assets'); // Or a more specific 'view-audit-logs'
        $logs = $customerInstalledAsset->auditLogs()->with('auditedBy')->orderByDesc('audit_date')->get();
        return response()->json($logs->map(function($log) {
            return [
                'audit_date' => $log->audit_date->format('d M Y'),
                'audited_by' => $log->auditedBy->name ?? '-',
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'change_reason' => $log->change_reason,
                'notes' => $log->notes,
            ];
        }));
    }
}