<?php 

namespace App\Services;

use App\Models\Asset;
use App\Models\TechnicianAssetDebt;
use App\Models\CustomerInstalledAsset;
use App\Models\AssetCheckout;
use App\Models\TrackedAsset; // ADDED
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetManagementService
{
    /**
     * 📊 Get Complete Dashboard Stats
     */
    public function getDashboardStats(): array
    {
        // Hitung total aset non-tracked
        $nonTrackedTotal = Asset::where('requires_qr_tracking', false)->sum('total_quantity');
        $nonTrackedAvailable = Asset::where('requires_qr_tracking', false)->where('available_quantity', '>', 0)->sum('available_quantity');
        $nonTrackedLowStock = Asset::where('requires_qr_tracking', false)->where('available_quantity', '<=', 5)->where('available_quantity', '>', 0)->count();
        $nonTrackedOutOfStock = Asset::where('requires_qr_tracking', false)->where('available_quantity', '<=', 0)->count();

        // Hitung total aset tracked
        $trackedTotal = TrackedAsset::count();
        $trackedAvailable = TrackedAsset::where('current_status', 'available')->count();
        $trackedInUse = TrackedAsset::whereIn('current_status', ['loaned', 'in_transit', 'installed'])->count();
        $trackedProblematic = TrackedAsset::whereIn('current_status', ['damaged', 'in_repair', 'awaiting_return_to_supplier', 'lost', 'scrap'])->count();
        $trackedQrGenerated = TrackedAsset::where('qr_generated', true)->count();

        return [
            // Asset Stats
            'assets' => [
                'total_master_assets' => Asset::count(), // Total jenis aset
                'total_physical_units' => $nonTrackedTotal + $trackedTotal, // Total unit fisik
                'available_units' => $nonTrackedAvailable + $trackedAvailable, // Total unit tersedia
                'low_stock_items' => $nonTrackedLowStock, // Ini hanya untuk jenis non-tracked
                'out_of_stock_items' => $nonTrackedOutOfStock, // Ini hanya untuk jenis non-tracked
                'qr_generated_units' => $trackedQrGenerated, // Hanya untuk tracked assets
                'tracked_in_use_units' => $trackedInUse, // Total unit yang sedang digunakan/dipasang
                'tracked_problematic_units' => $trackedProblematic, // Total unit yang bermasalah
            ],
            
            // Debt Stats
            'debts' => [
                'active_technicians' => TechnicianAssetDebt::where('status', 'active')->distinct('technician_id')->count(),
                'total_debt_value' => TechnicianAssetDebt::where('status', 'active')->sum('current_debt_value'),
                'total_items_out' => TechnicianAssetDebt::where('status', 'active')->sum('current_debt_quantity'),
                'overdue_settlements' => $this->getOverdueSettlementsCount(),
            ],
            
            // Customer Asset Stats
            'customer_assets' => [
                'total_installed' => CustomerInstalledAsset::where('status', 'installed')->count(),
                'total_value' => CustomerInstalledAsset::where('status', 'installed')->sum('total_asset_value'),
                'unique_customers' => CustomerInstalledAsset::where('status', 'installed')->distinct('customer_id')->count(),
                'recent_installations' => CustomerInstalledAsset::where('installation_date', '>=', Carbon::now()->subDays(7))->count(),
            ],
            
            // Today's Activity
            'today' => [
                'checkouts' => AssetCheckout::whereDate('checkout_date', today())->count(),
                'checkout_value' => AssetCheckout::whereDate('checkout_date', today())->sum('total_value'),
                'installations' => CustomerInstalledAsset::whereDate('installation_date', today())->count(),
                'returns' => $this->getTodayReturnsCount(),
            ]
        ];
    }

    /**
     * ⚠️ Get Low Stock Alerts (MODIFIED to include tracked assets in consideration)
     */
    public function getLowStockAlerts($limit = 10): array
    {
        // Alerts for non-tracked assets
        $nonTrackedAlerts = Asset::with('asset_category')
            ->where('requires_qr_tracking', false)
            ->where('available_quantity', '<=', 5)
            ->orderBy('available_quantity', 'asc')
            ->take($limit)
            ->get()
            ->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'type' => 'master_asset', // Added type for distinction
                    'category' => $asset->asset_category->name ?? '-',
                    'current_stock' => $asset->available_quantity,
                    'total_stock' => $asset->total_quantity,
                    'status' => $asset->available_quantity == 0 ? 'out_of_stock' : 'low_stock',
                    'urgency' => $asset->available_quantity == 0 ? 'critical' : 
                               ($asset->available_quantity <= 2 ? 'high' : 'medium')
                ];
            });

        // Alerts for problematic tracked assets (e.g., damaged, lost, or very few available)
        // This interpretation of 'low stock' for tracked assets might vary.
        // For now, let's include 'damaged' or 'lost' as requiring attention.
        $trackedProblemAlerts = TrackedAsset::with('asset')
            ->whereIn('current_status', ['damaged', 'lost', 'in_repair', 'awaiting_return_to_supplier'])
            ->take($limit) // Could be a separate limit
            ->get()
            ->map(function($trackedAsset) {
                return [
                    'id' => $trackedAsset->id,
                    'name' => $trackedAsset->asset->name . ' (SN: ' . ($trackedAsset->serial_number ?? $trackedAsset->qr_code ?? 'N/A') . ')',
                    'type' => 'tracked_asset', // Added type for distinction
                    'category' => $trackedAsset->asset->asset_category->name ?? '-',
                    'current_status' => $trackedAsset->current_status,
                    'urgency' => in_array($trackedAsset->current_status, ['damaged', 'lost']) ? 'critical' : 'medium'
                ];
            });

        return $nonTrackedAlerts->concat($trackedProblemAlerts)->toArray(); // Combine and return
    }

    /**
     * 📈 Get Asset Utilization Report (MODIFIED for tracked assets)
     */
    public function getAssetUtilizationReport(): array
    {
        // Utilization for non-tracked assets
        $nonTrackedUtilization = DB::table('assets')
            ->where('requires_qr_tracking', false)
            ->select([
                'id',
                'name',
                'total_quantity',
                'available_quantity',
                DB::raw('(total_quantity - available_quantity) as items_out'), // Simplistic 'items_out' for non-tracked
                DB::raw('0 as items_installed'), // Not directly tracked at this level
                DB::raw('CASE WHEN total_quantity > 0 THEN ((total_quantity - available_quantity) / total_quantity * 100) ELSE 0 END as utilization_rate')
            ])
            ->get();

        // Utilization for tracked assets
        $trackedUtilization = DB::table('assets')
            ->where('requires_qr_tracking', true)
            ->leftJoin('tracked_assets', 'assets.id', '=', 'tracked_assets.asset_id')
            ->select([
                'assets.id',
                'assets.name',
                DB::raw('COUNT(tracked_assets.id) as total_quantity'), // Count of physical units
                DB::raw('COUNT(CASE WHEN tracked_assets.current_status = "available" THEN 1 END) as available_quantity'),
                DB::raw('COUNT(CASE WHEN tracked_assets.current_status IN ("loaned", "in_transit", "installed") THEN 1 END) as items_out'),
                DB::raw('COUNT(CASE WHEN tracked_assets.current_status = "installed" THEN 1 END) as items_installed'),
                DB::raw('CASE WHEN COUNT(tracked_assets.id) > 0 THEN (COUNT(CASE WHEN tracked_assets.current_status IN ("loaned", "in_transit", "installed") THEN 1 END) / COUNT(tracked_assets.id) * 100) ELSE 0 END as utilization_rate')
            ])
            ->groupBy('assets.id', 'assets.name')
            ->get();

        $combinedUtilization = $nonTrackedUtilization->concat($trackedUtilization);

        return $combinedUtilization->map(function ($item) {
            return [
                'asset_id' => $item->id,
                'asset_name' => $item->name,
                'total_quantity' => $item->total_quantity,
                'available_quantity' => $item->available_quantity,
                'items_out' => $item->items_out,
                'items_installed' => $item->items_installed,
                'utilization_rate' => round($item->utilization_rate, 2),
                'status' => $item->utilization_rate > 80 ? 'high_utilization' : 
                          ($item->utilization_rate > 50 ? 'medium_utilization' : 'low_utilization')
            ];
        })->toArray();
    }

    /**
     * 🎯 Get Technician Performance
     */
    public function getTechnicianPerformance(): array
    {
        $performance = DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->leftJoin('technician_asset_debts', 'users.id', '=', 'technician_asset_debts.technician_id')
            ->where('roles.name', 'Technician')
            ->select([
                'users.id',
                'users.name',
                DB::raw('COALESCE(SUM(CASE WHEN technician_asset_debts.status = "active" THEN technician_asset_debts.current_debt_value ELSE 0 END), 0) as current_debt'),
                DB::raw('COUNT(CASE WHEN technician_asset_debts.status = "active" THEN 1 END) as active_debts'),
                DB::raw('COUNT(CASE WHEN technician_asset_debts.status = "fully_settled" OR technician_asset_debts.status = "written_off" THEN 1 END) as settled_debts_count'), // MODIFIED: Include written_off
                DB::raw('COALESCE(SUM(CASE WHEN technician_asset_debts.status = "fully_settled" OR technician_asset_debts.status = "written_off" THEN technician_asset_debts.total_debt_value ELSE 0 END), 0) as settled_debts_value'), // Added for value
                DB::raw('COALESCE(AVG(DATEDIFF(CURDATE(), technician_asset_debts.checkout_date)), 0) as avg_debt_age_days')
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('current_debt')
            ->get();

        return $performance->map(function ($tech) {
            $totalDebts = $tech->active_debts + $tech->settled_debts_count;
            $settlementRate = $totalDebts > 0 ? ($tech->settled_debts_count / $totalDebts) * 100 : 100;
            
            return [
                'technician_id' => $tech->id,
                'technician_name' => $tech->name,
                'current_debt' => $tech->current_debt,
                'formatted_debt' => 'Rp ' . number_format($tech->current_debt, 0, ',', '.'),
                'active_debts' => $tech->active_debts,
                'settled_debts_count' => $tech->settled_debts_count,
                'settled_debts_value' => 'Rp ' . number_format($tech->settled_debts_value, 0, ',', '.'),
                'settlement_rate' => round($settlementRate, 2),
                'avg_debt_age_days' => round($tech->avg_debt_age_days, 1),
                'performance_score' => $this->calculatePerformanceScore($settlementRate, $tech->avg_debt_age_days, $tech->current_debt)
            ];
        })->toArray();
    }

    /**
     * 🔢 Calculate Performance Score
     */
    private function calculatePerformanceScore($settlementRate, $avgDebtAge, $currentDebt): string
    {
        $score = 0;
        
        // Settlement rate (40% weight)
        $score += ($settlementRate / 100) * 40;
        
        // Debt age (30% weight) - lower is better
        // Assuming max acceptable age is 30 days for full score for example
        $ageScore = max(0, 30 - ($avgDebtAge / 30) * 30);
        $score += $ageScore;
        
        // Current debt (30% weight) - lower is better
        // Assuming max debt for full score is 2,000,000 (default limit)
        $debtScore = max(0, 30 - ($currentDebt / SystemSetting::getValue('default_technician_debt_limit', 2000000)) * 30);
        $score += $debtScore;
        
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    /**
     * 📊 Get Monthly Trends
     */
    public function getMonthlyTrends($months = 6): array
    {
        $trends = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('Y-m');
            
            $trends[$monthKey] = [
                'month' => $date->format('M Y'),
                'checkouts' => AssetCheckout::whereYear('checkout_date', $date->year)
                    ->whereMonth('checkout_date', $date->month)
                    ->count(),
                'checkout_value' => AssetCheckout::whereYear('checkout_date', $date->year)
                    ->whereMonth('checkout_date', $date->month)
                    ->sum('total_value'),
                'installations' => CustomerInstalledAsset::whereYear('installation_date', $date->year)
                    ->whereMonth('installation_date', $date->month)
                    ->count(),
                'installation_value' => CustomerInstalledAsset::whereYear('installation_date', $date->year)
                    ->whereMonth('installation_date', $date->month)
                    ->sum('total_asset_value')
            ];
        }
        
        return $trends;
    }

    /**
     * 🕐 Get Overdue Settlements Count
     */
    private function getOverdueSettlementsCount(): int
    {
        $reminderDays = \App\Models\SystemSetting::getValue('settlement_reminder_days', 7);
        $overdueDate = Carbon::now()->subDays($reminderDays);
        
        return TechnicianAssetDebt::where('status', 'active')
            ->where('checkout_date', '<=', $overdueDate)
            ->distinct('technician_id')
            ->count();
    }

    /**
     * 📈 Get Today Returns Count (MODIFIED to also check for tracked assets returned/installed today)
     */
    private function getTodayReturnsCount(): int
    {
        // Count returns for non-tracked assets by updated_at on debt
        $nonTrackedReturns = TechnicianAssetDebt::whereDate('updated_at', today())
            ->whereNull('tracked_asset_id') // Filter for non-tracked
            ->where('quantity_returned', '>', 0)
            ->count();

        // Count tracked assets whose status changed to 'available' today (meaning returned to warehouse)
        $trackedReturns = TrackedAsset::whereDate('updated_at', today())
            ->where('current_status', 'available') // Returned to available state
            ->count();

        return $nonTrackedReturns + $trackedReturns;
    }
}