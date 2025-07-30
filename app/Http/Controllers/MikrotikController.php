<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;


class MikrotikController extends Controller
{

    public function index()
    {
        return view('mikrotiks.index');
    }

    public function getData(Request $request)
    {
        $query = Mikrotik::query();

        if ($request->status_filter !== null) {
            $query->where('is_enabled', $request->status_filter === 'active' ? 1 : 0);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($mikrotik) {
                return $mikrotik->is_enabled
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>';
            })
            ->addColumn('actions', function ($mikrotik) {
                return '
                    <button class="btn btn-sm btn-icon edit-record" data-id="' . $mikrotik->id . '" data-bs-toggle="modal" data-bs-target="#editMikrotikModal" title="Edit"><i class="ti ti-edit"></i></button>
                    <button class="btn btn-sm btn-icon delete-record" data-id="' . $mikrotik->id . '" title="Delete"><i class="ti ti-trash"></i></button>
                ';
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'ip_address' => 'required',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'is_enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Mikrotik::create($request->all());

        return response()->json(['success' => 'Mikrotik added successfully']);
    }

    public function edit($id)
    {
        $mikrotik = Mikrotik::findOrFail($id);
        return response()->json($mikrotik);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'is_enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $mikrotik = Mikrotik::findOrFail($id);
        $mikrotik->update($request->all());

        return response()->json(['success' => 'Mikrotik updated successfully']);
    }

    public function destroy($id)
    {
        $mikrotik = Mikrotik::findOrFail($id);
        $mikrotik->delete();

        return response()->json(['success' => 'Mikrotik deleted successfully']);
    }
}