<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;


class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view employees')->only(['index']);

    }

    public function index()
    {
        return view('employees.index');
    }

    public function getData(Request $request)
    {
        $query = Employee::with(['user', 'position'])->select('employees.*')->leftJoin('users', 'users.employee_id', '=', 'employees.id');
     
        // Filter berdasarkan status karyawan jika ada request
        $employeeStatusFilter = $request->input('status_filter');
        if (!is_null($employeeStatusFilter) && $employeeStatusFilter !== '') {
            $query->where('status', $employeeStatusFilter);
        }

        return DataTables::of($query)
            ->addColumn('full_name', function ($employee) {
                return $employee->full_name;
            })
            ->addColumn('email', function ($employee) {
                return $employee->user->email ?? 'N/A'; 
            })
            ->addColumn('phone_number', function ($employee) {
                return $employee->user->phone_number ?? 'N/A'; 
            })
            ->addColumn('position', function ($employee) {
                return $employee->position->name ?? 'N/A';
            })
            ->addColumn('join_date', function ($employee) {
                return \Carbon\Carbon::parse($employee->join_date)->format('d F Y');
            })
            ->addColumn('employee_status_badge', function ($employee) {
                switch ($employee->status) {
                    case 'Active':
                        return '<span class="badge bg-label-success">Active</span>';
                    case 'Terminated':
                        return '<span class="badge bg-label-danger">Terminated</span>';
                    case 'On Leave':
                        return '<span class="badge bg-label-warning">On Leave</span>';
                    default:
                        return '<span class="badge bg-label-secondary">Unknown</span>';
                }
            })
            ->addColumn('roles', function ($employee) {
                $rolesHtml = '';
                if ($employee->user && $employee->user->roles->isNotEmpty()) {
                    foreach ($employee->user->roles as $role) {
                        $rolesHtml .= '<span class="badge bg-label-primary me-1">' . $role->name . '</span>';
                    }
                } else {
                    $rolesHtml = '<span class="badge bg-label-secondary">No Role</span>';
                }
                return $rolesHtml;
            })

            ->rawColumns(['employee_status_badge', 'roles'])
            ->make(true);
    }
}
