<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;



class AssetController extends Controller
{
    public function index()
    {
        return view('assets.index');
    }

    public function data(Request $request)
    {
        $query = Asset::with(['warehouse', 'assetCategory'])->select('assets.*');

        $status = $request->input('status_filter');
        $warehouse = $request->input('warehouse_filter');
        $category = $request->input('category_filter');

        // Filter berdasarkan status
        if (!is_null($status)) {
            $query->where('status', $status);
        }
        
        if (!is_null($warehouse)) {
            $query->where('warehouse_id', $warehouse);
        }
        
        if (!is_null($category)) {
            $query->where('asset_category_id', $category);
        }

        return DataTables::of($query)
            ->addColumn('warehouse_name', function ($asset) {
                return $asset->warehouse ? $asset->warehouse->name : 'N/A';
            })
            ->addColumn('category_name', function ($asset) {
                return $asset->assetCategory ? $asset->assetCategory->name : 'N/A';
            })
            ->addColumn('status', function ($asset) {
                switch ($asset->status) {
                    case 'good':
                        return '<span class="badge text-outline-success">Good</span>';
                    case 'damaged':
                        return '<span class="badge text-outline-danger">Damaged</span>';
                    case 'out_of_stock':
                        return '<span class="badge text-outline-warning">Out of Stock</span>';
                    default:
                        return '<span class="badge text-outline-secondary">' . ucfirst(str_replace('_', ' ', $asset->status)) . '</span>';
                }
            })            
            ->rawColumns(['status'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_code' => 'required|unique:assets',
            'name' => 'required',
            'total_quantity' => 'required|integer',
            'available_quantity' => 'required|integer',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_type' => 'required|in:consumable,fixed',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'serial_number' => 'nullable|string',
            'mac_address' => 'nullable|string',
            'status' => 'required|in:good,damaged',
        ]);

        $asset = Asset::create($validated);
        return response()->json($asset);
    }

    public function edit($id)
    {
        $asset = Asset::findOrFail($id);
        return response()->json($asset);
    }

    public function update(Request $request, $id)
    {
        $asset = Asset::findOrFail($id);
        $validated = $request->validate([
            'asset_code' => 'required|unique:assets,asset_code,' . $id,
            'name' => 'required',
            'total_quantity' => 'required|integer',
            'available_quantity' => 'required|integer',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'asset_category_id' => 'required|exists:asset_categories,id',
            'asset_type' => 'required|in:consumable,fixed',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'serial_number' => 'nullable|string',
            'mac_address' => 'nullable|string',
            'status' => 'required|in:good,damaged,out_of_stock',
        ]);

        $asset->update($validated);
        return response()->json($asset);
    }

    public function destroy($id)
    {
        $asset = Asset::findOrFail($id);
        $asset->delete();
        return response()->json(['message' => 'Asset deleted']);
    }

    public function show($id)
    {
        $asset = Asset::with(['warehouse', 'assetCategory'])->findOrFail($id);
        return response()->json($asset);
    }

}