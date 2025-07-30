<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Yajra\DataTables\DataTables;

class CustomersController extends Controller
{

    public function index()
    {
        
        return view('customers.index');
    }

    public function data(Request $request)
    {
        $query = Customer::with(['odpCustomer.odp'])->select('customers.*');

       
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->has('odp') && $request->odp != '') {
            $query->whereHas('odpCustomer', function ($q) use ($request) {
                $q->where('odp_id', $request->odp);
            });
        }

        return DataTables::of($query)
            ->addColumn('odp_name', function ($customer) {
                return optional($customer->odpCustomer->odp)->name ?? 'N/A';
            })
            ->addColumn('tanggal_install', function ($customer) {
                return $customer->installation_date ? Carbon::parse($customer->installation_date)->format('d M Y') : 'N/A';
            })
            ->addColumn('status_badge', function ($customer) {
                $status = $customer->status;
                $colorClass = '';
            
                switch ($status) {
                    case 'active':
                        $colorClass = 'badge bg-label-success';
                        break;
                    case 'inactive':
                        $colorClass = 'badge bg-label-warning';
                        break;
                    case 'suspended':
                        $colorClass = 'badge bg-label-danger';
                        break;
                    default:
                        $colorClass = 'badge bg-label-secondary'; // Kelas default jika status tidak dikenali
                        break;
                }
            
                return '<span class="' . $colorClass . '">' . ucfirst($status) . '</span>';
            })
            ->rawColumns(['tanggal_install','status_badge'])
            ->make(true);
    }

    public function store(Request $request)
    {

    }

    public function edit($id)
    {
        $customers = Customer::findOrFail($id);
        return response()->json($customers);
    }

    public function update(Request $request, $id)
    {
        $customers = Customer::findOrFail($id);
        $validated = $request->validate([
           
        ]);

        $customers->update($validated);
        return response()->json($customers);
    }

    public function destroy($id)
    {
        $customers = Customer::findOrFail($id);
        $customers->delete();
        return response()->json(['message' => 'Customer deleted']);
    }

    public function show($id)
    {
        $customers = Customer::with(['odpCustomer'])->findOrFail($id);
        return response()->json($customers);
    }

    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    

}
