<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;

class PackagesController extends Controller
{
    public function index()
    {
        return view('packages.index');
    }

    public function getData(Request $request)
    {
        $query = Package::query();

        if ($request->status_filter) {
            $query->where('status', $request->status_filter);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status_badge', function ($package) {
                return $package->status === 'active'
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Tidak Aktif</span>';
            })
            ->addColumn('price_formatted', function ($package) {
                return 'Rp ' . number_format($package->price, 2, ',', '.');
            })
            ->addColumn('actions', function ($package) {
                return '
                    <button class="btn btn-sm btn-icon edit-record" data-id="' . $package->id . '" data-bs-toggle="modal" data-bs-target="#editPackageModal" title="Edit"><i class="ti ti-edit"></i></button>
                    <button class="btn btn-sm btn-icon delete-record" data-id="' . $package->id . '" title="Hapus"><i class="ti ti-trash"></i></button>
                ';
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Package::create($request->all());

        return response()->json(['success' => 'Paket berhasil ditambahkan.']);
    }

    public function edit($id)
    {
        $package = Package::findOrFail($id);
        return response()->json($package);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $package = Package::findOrFail($id);
        $package->update($request->all());

        return response()->json(['success' => 'Paket berhasil diperbarui.']);
    }

    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        $package->delete();

        return response()->json(['success' => 'Paket berhasil dihapus.']);
    }
}