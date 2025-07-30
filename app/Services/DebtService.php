<?php
// File: app/Services/DebtService.php

namespace App\Services;

use App\Models\TechnicianAssetDebt;
use App\Models\SystemSetting;
use App\Models\User;
use Spatie\Permission\Models\Role; // ADDED

class DebtService
{
    /**
     * 💰 Check Technician Debt Limit
     */
    public function checkDebtLimit($technicianId, $additionalAmount): array
    {
        // Calculate current debt
        $currentDebt = TechnicianAssetDebt::where('technician_id', $technicianId)
            ->where('status', 'active')
            ->sum('current_debt_value');

        // Get debt limit from settings
        $debtLimit = SystemSetting::getValue('default_technician_debt_limit', 2000000);

        // Calculate new total debt
        $newTotalDebt = $currentDebt + $additionalAmount;

        // Check if exceeds limit
        $exceedLimit = $newTotalDebt > $debtLimit;

        return [
            'technician_id' => $technicianId,
            'current_debt' => $currentDebt,
            'debt_limit' => $debtLimit,
            'additional_amount' => $additionalAmount,
            'new_total_debt' => $newTotalDebt,
            'exceed_limit' => $exceedLimit,
            'available_credit' => max(0, $debtLimit - $currentDebt),
            'formatted_current_debt' => 'Rp ' . number_format($currentDebt, 0, ',', '.'),
            'formatted_debt_limit' => 'Rp ' . number_format($debtLimit, 0, ',', '.'),
            'formatted_new_total' => 'Rp ' . number_format($newTotalDebt, 0, ',', '.'),
            'formatted_available_credit' => 'Rp ' . number_format(max(0, $debtLimit - $currentDebt), 0, ',', '.')
        ];
    }

    /**
     * ✅ Check if NOC approval required
     */
    public function requiresNOCApproval($debtCheckResult): bool
    {
        return $debtCheckResult['exceed_limit'] && 
               SystemSetting::getValue('require_noc_approval_above_limit', true);
    }

    /**
     * 👥 Get Available Approvers (NOC, Manager, Super-Admin, Owner)
     */
    public function getAvailableApprovers(): array
    {
        $approverRoles = ['NOC', 'Manager', 'Super-Admin', 'Owner'];
        $approvers = User::role($approverRoles)->get();
        
        return $approvers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->getRoleNames()->first() // Get the first role name
            ];
        })->toArray();
    }

    /**
     * 📊 Get Debt Summary untuk Technician
     */
    public function getTechnicianDebtSummary($technicianId): array
    {
        $debts = TechnicianAssetDebt::where('technician_id', $technicianId)->get();

        return [
            'total_active_debt' => $debts->where('status', 'active')->sum('current_debt_value'),
            'total_items_out' => $debts->where('status', 'active')->sum('current_debt_quantity'),
            'total_historical_debt' => $debts->sum('total_debt_value'),
            'total_settled' => $debts->whereIn('status', ['fully_settled', 'written_off'])->sum('total_debt_value'),
            'active_debt_count' => $debts->where('status', 'active')->count(),
            'settlement_rate' => $debts->count() > 0 ? 
                ($debts->whereIn('status', ['fully_settled', 'written_off'])->count() / $debts->count()) * 100 : 0
        ];
    }

    /**
     * ⚠️ Get High Risk Technicians
     */
    public function getHighRiskTechnicians(): array
    {
        $debtLimit = SystemSetting::getValue('default_technician_debt_limit', 2000000);
        $highRiskThreshold = $debtLimit * 0.8; // 80% of limit

        $highRiskTechs = \DB::table('technician_asset_debts')
            ->join('users', 'technician_asset_debts.technician_id', '=', 'users.id')
            ->where('technician_asset_debts.status', 'active')
            ->groupBy('technician_asset_debts.technician_id', 'users.name')
            ->havingRaw('SUM(technician_asset_debts.current_debt_value) > ?', [$highRiskThreshold])
            ->select([
                'technician_asset_debts.technician_id',
                'users.name as technician_name',
                \DB::raw('SUM(technician_asset_debts.current_debt_value) as total_debt'),
                \DB::raw('COUNT(*) as debt_count')
            ])
            ->orderByDesc('total_debt')
            ->get();

        return $highRiskTechs->map(function ($tech) use ($debtLimit) {
            return [
                'technician_id' => $tech->technician_id,
                'technician_name' => $tech->technician_name,
                'total_debt' => $tech->total_debt,
                'debt_count' => $tech->debt_count,
                'debt_percentage' => ($tech->total_debt / $debtLimit) * 100,
                'formatted_debt' => 'Rp ' . number_format($tech->total_debt, 0, ',', '.'),
                'risk_level' => $tech->total_debt > ($debtLimit * 0.9) ? 'critical' : 'high'
            ];
        })->toArray();
    }

    /**
     * 💸 Calculate Settlement Amount
     */
    public function calculateSettlement($technicianId, $debtIds = null): array
    {
        $query = TechnicianAssetDebt::where('technician_id', $technicianId)
            ->where('status', 'active');

        if ($debtIds) {
            $query->whereIn('id', $debtIds);
        }

        $debts = $query->get();
        $totalAmount = $debts->sum('current_debt_value');

        return [
            'technician_id' => $technicianId,
            'debt_count' => $debts->count(),
            'total_amount' => $totalAmount,
            'formatted_total' => 'Rp ' . number_format($totalAmount, 0, ',', '.'),
            'debts' => $debts
        ];
    }
}