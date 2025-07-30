<?php

namespace App\Http\Controllers;

use App\Models\TechnicianAssetDebt;
use App\Models\DebtSettlement;
use App\Models\DebtSettlementItem;
use App\Models\User;
use App\Services\DebtService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class AssetDebtController extends Controller
{
    protected $debtService;

    public function __construct(DebtService $debtService)
    {
        $this->debtService = $debtService;
    }

    /**
     * Display technician debts index.
     */
    public function index()
    {
        $this->authorize('view-debt'); // Define this permission
        $technicians = User::role('Technician')->get();
        return view('asset-management.debts.index', compact('technicians'));
    }

    /**
     * Get technician debts data for DataTables.
     */
    public function getDebtsData(Request $request): JsonResponse
    {
        $this->authorize('view-debt');
        $query = TechnicianAssetDebt::with(['technician', 'asset.asset_category', 'trackedAsset', 'warehouse']);

        if ($request->filled('technician_id')) {
            $query->where('technician_id', $request->technician_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addColumn('technician_name', fn($debt) => $debt->technician->name ?? '-')
            ->addColumn('asset_name', fn($debt) => $debt->asset->name ?? '-')
            ->addColumn('asset_identifier', function($debt) {
                if ($debt->trackedAsset) {
                    return $debt->trackedAsset->qr_code ?? $debt->trackedAsset->serial_number ?? $debt->trackedAsset->mac_address ?? '-';
                }
                return '-';
            })
            ->addColumn('category_name', fn($debt) => $debt->asset->asset_category->name ?? '-')
            ->addColumn('warehouse_name', fn($debt) => $debt->warehouse->name ?? '-')
            ->addColumn('formatted_total_debt_value', fn($debt) => 'Rp ' . number_format($debt->total_debt_value, 0, ',', '.'))
            ->addColumn('formatted_current_debt_value', fn($debt) => 'Rp ' . number_format($debt->current_debt_value, 0, ',', '.'))
            ->addColumn('status_label', function($debt) {
                $badge = '';
                switch ($debt->status) {
                    case 'active': $badge = 'bg-label-warning'; break;
                    case 'partially_returned': $badge = 'bg-label-info'; break;
                    case 'fully_settled': $badge = 'bg-label-success'; break;
                    case 'written_off': $badge = 'bg-label-danger'; break;
                    default: $badge = 'bg-label-secondary'; break;
                }
                return '<span class="badge ' . $badge . '">' . ucfirst(str_replace('_', ' ', $debt->status)) . '</span>';
            })
            ->addColumn('actions', function($debt) {
                $actions = '<div class="dropdown"><button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button><div class="dropdown-menu">';
                if ($debt->status === 'active' || $debt->status === 'partially_returned') {
                    $actions .= '<a class="dropdown-item settle-debt" data-id="'.$debt->id.'" data-technician-id="'.$debt->technician_id.'" data-current-value="'.$debt->current_debt_value.'"><i class="ti ti-cash me-1"></i> Settle Debt</a>';
                }
                $actions .= '<a class="dropdown-item view-debt-detail" data-id="'.$debt->id.'"><i class="ti ti-eye me-1"></i> Detail</a>';
                $actions .= '</div></div>';
                return $actions;
            })
            ->rawColumns(['status_label', 'actions'])
            ->make(true);
    }

    /**
     * Show debts for a specific technician.
     */
    public function showTechnicianDebts(User $technician)
    {
        $this->authorize('view-debt');
        $summary = $this->debtService->getTechnicianDebtSummary($technician->id);
        return view('asset-management.debts.technician-debts', compact('technician', 'summary'));
    }

    /**
     * Process debt settlement.
     */
    public function settleDebt(Request $request): JsonResponse
    {
        $this->authorize('settle-debt'); // Define this permission for Warehouse, NOC, Manager, Owner, Super-Admin, Kasir, LAD

        $request->validate([
            'technician_id' => 'required|exists:users,id',
            'debt_ids' => 'required|array|min:1',
            'debt_ids.*' => 'exists:technician_asset_debts,id',
            'settlement_type' => ['required', Rule::in(['monthly', 'weekly', 'daily', 'adhoc'])],
            'salary_deduction_amount' => 'nullable|numeric|min:0',
            'cash_payment_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $calculatedSettlement = $this->debtService->calculateSettlement($request->technician_id, $request->debt_ids);
        $totalAmountToSettle = $calculatedSettlement['total_amount'];
        $salaryDeduction = $request->salary_deduction_amount ?? 0;
        $cashPayment = $request->cash_payment_amount ?? 0;
        $totalPayment = $salaryDeduction + $cashPayment;

        if ($totalPayment < $totalAmountToSettle) {
            return response()->json(['success' => false, 'message' => 'Total payment is less than the total debt amount selected.'], 400);
        }

        DB::beginTransaction();
        try {
            $settlement = DebtSettlement::create([
                'technician_id' => $request->technician_id,
                'settlement_period' => $request->settlement_type === 'adhoc' ? null : now()->format('Y-m'), // Example for monthly
                'settlement_type' => $request->settlement_type,
                'total_debt_amount' => $totalAmountToSettle,
                'salary_deduction' => $salaryDeduction,
                'cash_payment' => $cashPayment,
                'remaining_debt' => max(0, $totalAmountToSettle - $totalPayment), // Should be 0 if fully settled
                'settlement_date' => now()->toDateString(),
                'processed_by_user_id' => auth()->id(),
                'notes' => $request->notes,
                'status' => ($totalAmountToSettle - $totalPayment <= 0.001) ? 'processed' : 'pending', // Mark processed if fully paid
            ]);

            foreach ($calculatedSettlement['debts'] as $debt) {
                // Ensure the debt is fully settled
                $debt->update([
                    'current_debt_quantity' => 0,
                    'current_debt_value' => 0,
                    'status' => 'fully_settled',
                ]);

                DebtSettlementItem::create([
                    'settlement_id' => $settlement->id,
                    'debt_id' => $debt->id,
                    'settled_amount' => $debt->total_debt_value, // Amount of this specific debt settled
                    'settlement_method' => ($salaryDeduction > 0 && $cashPayment > 0) ? 'mixed' : ($salaryDeduction > 0 ? 'salary_deduction' : 'cash_payment'), // Simplified method
                ]);

                // If it was a tracked asset that was previously 'installed' but was still 'active' debt,
                // we assume it's settled because it's installed.
                if ($debt->trackedAsset && $debt->trackedAsset->current_status === 'installed') {
                    // No change to trackedAsset status, just debt cleared
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Debt settled successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Debt Settlement Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to settle debt: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display debt settlements history.
     */
    public function settlementsIndex()
    {
        $this->authorize('view-debt-settlements'); // Define this permission
        $technicians = User::role('Technician')->get();
        return view('asset-management.debts.settlements-index', compact('technicians'));
    }

    /**
     * Get debt settlements data for DataTables.
     */
    public function getSettlementsData(Request $request): JsonResponse
    {
        $this->authorize('view-debt-settlements');
        $query = DebtSettlement::with(['technician', 'processedBy']);

        if ($request->filled('technician_id')) {
            $query->where('technician_id', $request->technician_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addColumn('technician_name', fn($settlement) => $settlement->technician->name ?? '-')
            ->addColumn('processed_by_name', fn($settlement) => $settlement->processedBy->name ?? '-')
            ->addColumn('formatted_total_debt_amount', fn($settlement) => 'Rp ' . number_format($settlement->total_debt_amount, 0, ',', '.'))
            ->addColumn('formatted_salary_deduction', fn($settlement) => 'Rp ' . number_format($settlement->salary_deduction, 0, ',', '.'))
            ->addColumn('formatted_cash_payment', fn($settlement) => 'Rp ' . number_format($settlement->cash_payment, 0, ',', '.'))
            ->addColumn('status_label', function($settlement) {
                $badge = '';
                switch ($settlement->status) {
                    case 'pending': $badge = 'bg-label-warning'; break;
                    case 'processed': $badge = 'bg-label-success'; break;
                    case 'cancelled': $badge = 'bg-label-danger'; break;
                    default: $badge = 'bg-label-secondary'; break;
                }
                return '<span class="badge ' . $badge . '">' . ucfirst($settlement->status) . '</span>';
            })
            ->rawColumns(['status_label'])
            ->make(true);
    }

    /**
     * Approve technician debt limit exceedance.
     */
    public function approveExceedLimit(Request $request): JsonResponse
    {
        $this->authorize('approve-debt-limit'); // Define this permission for NOC, Manager, Owner, Super-Admin

        $request->validate([
            'debt_ids' => 'required|array',
            'debt_ids.*' => 'exists:technician_asset_debts,id',
            'reason' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            TechnicianAssetDebt::whereIn('id', $request->debt_ids)
                ->update([
                    'exceed_limit_approved_by' => auth()->id(),
                    'exceed_limit_approved_at' => now(),
                    'approval_reason' => $request->reason,
                    // Optionally change status from 'awaiting_approval' to 'active' if such a status existed
                ]);
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Debt limit exceedance approved successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to approve: ' . $e->getMessage()], 500);
        }
    }
}