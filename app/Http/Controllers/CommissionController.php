<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\User;
use App\Models\InstallationRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $commissions = Commission::with(['sales', 'commissionable.customer'])
                ->where('commissionable_type', InstallationRequest::class)
                ->whereHas('commissionable', function ($query) {
                    $query->where('ticket_type', 'new');
                });

            return DataTables::of($commissions)
                ->addColumn('sales_name', function ($row) {
                    return $row->sales ? $row->sales->name : '-';
                })
                ->addColumn('customer_name', function ($row) {
                    return $row->commissionable && $row->commissionable->customer
                        ? $row->commissionable->customer->name
                        : '-';
                })
                ->addColumn('ticket_details', function ($row) {
                    if ($row->commissionable instanceof InstallationRequest) {
                        return 'Tiket #' . $row->commissionable->id . ' (' . $row->commissionable->ticket_type . ')';
                    }
                    return '-';
                })
                ->addColumn('amount_formatted', function ($row) {
                    return 'Rp ' . number_format($row->amount, 2);
                })
                ->addColumn('status', function ($row) {
                    return match ($row->status) {
                        Commission::STATUS_PAID => 'Dibayar',
                        Commission::STATUS_PENDING => 'Menunggu',
                        Commission::STATUS_CANCELLED => 'Dibatalkan',
                        default => $row->status,
                    };
                })
                ->addColumn('paid_at_formatted', function ($row) {
                    return $row->paid_at ? $row->paid_at->format('d M Y') : '-';
                })
                ->make(true);
        }

        return view('commissions.index');
    }

   public function report(Request $request)
    {
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $salesReport = User::whereHas('commissions', function ($query) use ($startDate, $endDate) {
                $query->where('commissionable_type', InstallationRequest::class)
                    ->where('status', Commission::STATUS_PAID)
                    ->whereHas('commissionable', function ($subQuery) {
                        $subQuery->where('ticket_type', 'new');
                    })
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->with(['commissions' => function ($query) use ($startDate, $endDate) {
                $query->where('commissionable_type', InstallationRequest::class)
                    ->where('status', Commission::STATUS_PAID)
                    ->whereHas('commissionable', function ($subQuery) {
                        $subQuery->where('ticket_type', 'new');
                    })
                    ->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->get()
            ->map(function ($sales) {
                return [
                    'sales_name' => $sales->name,
                    'total_commission' => $sales->commissions->sum('amount'),
                    'total_sales' => $sales->commissions->count(),
                ];
            });

        if ($request->ajax()) {
            return response()->json(['data' => $salesReport]);
        }

        return view('commissions.report', compact('salesReport', 'month'));
    }
}