<?php

namespace App\Http\Controllers;

use App\Models\Odp;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class OdpController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view odp')->only(['index', 'getData', 'edit']); 
        $this->middleware('can:create odp')->only(['create', 'store']);
        $this->middleware('can:edit odp')->only(['edit', 'update']);
        $this->middleware('can:delete odp')->only(['destroy']);

    }

    public function index()
    {
        return view('odps.index');
    }

    /**
     * Get data for DataTables.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        $query = Odp::query();
        $currentUser = Auth::user(); // Dapatkan user yang sedang login

        // Tambahkan filter berdasarkan status jika diperlukan
        $statusFilter = $request->input('status_filter');
        if (!is_null($statusFilter) && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('status_badge', function($odp) {
                // Logika status_badge yang disesuaikan untuk ODP
                $class = '';
                switch ($odp->status) {
                    case 'active':
                        $class = 'bg-label-success';
                        break;
                    case 'inactive':
                        $class = 'bg-label-secondary';
                        break;
                    case 'full':
                        $class = 'bg-label-info';
                        break;
                    case 'broken':
                        $class = 'bg-label-danger';
                        break;
                    case 'under_maintenance':
                        $class = 'bg-label-warning';
                        break;
                    case 'decommissioned':
                        $class = 'bg-label-dark';
                        break;
                    default:
                        $class = 'bg-label-light'; // Default jika status tidak dikenali
                        break;
                }
                return '<span class="badge ' . $class . '">' . ucfirst(str_replace('_', ' ', $odp->status)) . '</span>';
            })
            ->addColumn('coordinates', function ($odp) {
                return $odp->latitude . ', ' . $odp->longitude;
            })
            ->addColumn('meta_data_display', function ($odp) {
                $metaData = json_decode($odp->meta_data, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($metaData)) {
                    $html = '<ul class="list-unstyled mb-0">';
                    foreach ($metaData as $key => $value) {
                        $html .= '<li><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</li>';
                    }
                    $html .= '</ul>';
                } else {
                    $html = htmlspecialchars($odp->meta_data ?? 'N/A');
                }
                return $html;
            })
            ->addColumn('created_at_formatted', function ($odp) {
                return Carbon::parse($odp->created_at)->format('d F Y H:i:s');
            })
            ->addColumn('updated_at_formatted', function ($odp) {
                return Carbon::parse($odp->updated_at)->format('d F Y H:i:s');
            })
            // In OdpController.php, update the 'actions' column
            ->addColumn('actions', function ($odp) use ($currentUser) {
                $buttons = '<div class="d-flex justify-content-sm-center align-items-sm-center gap-2">';
                
                if ($currentUser->can('view odp')) {
                    $buttons .= '
                        <a href="' . route('odps.show', ['mode' => 'view', 'id' => $odp->id]) . '"
                            class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect"
                            data-bs-toggle="tooltip"
                            title="Show">
                            <i class="ti ti-eye ti-sm"></i>
                        </a>';
                }

                if ($currentUser->can('edit odp')) {
                    $buttons .= '
                        <a href="' . route('odps.show', ['mode' => 'edit', 'id' => $odp->id]) . '"
                            class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect"
                            data-bs-toggle="tooltip"
                            title="Edit">
                            <i class="ti ti-edit ti-sm"></i>
                        </a>';
                }

                if ($currentUser->can('delete odp')) {
                    $buttons .= '
                        <button type="button"
                            class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect delete-odp"
                            data-bs-toggle="tooltip"
                            title="Delete"
                            data-id="' . $odp->id . '">
                            <i class="ti ti-trash ti-sm"></i>
                        </button>';
                }

                $buttons .= '</div>';
                return $buttons;
            })  
            ->rawColumns(['status_badge', 'meta_data_display', 'actions'])
            ->make(true);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'meta_data' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $odp = Odp::create($request->all());
            return response()->json(['message' => 'ODP created successfully.', 'data' => $odp], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create ODP.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $odp = Odp::find($id);
        if (!$odp) {
            return response()->json(['message' => 'ODP not found.'], 404);
        }
        return response()->json($odp);
    }

    public function show(Request $request)
    {
        return view('odps.show');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'longitude' => 'nullable|numeric|between:-180,180',
            'latitude' => 'nullable|numeric|between:-90,90',
            'meta_data' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $odp = Odp::find($id);
            if (!$odp) {
                return response()->json(['message' => 'ODP not found.'], 404);
            }

            $odp->update($request->all());
            return response()->json(['message' => 'ODP updated successfully.', 'data' => $odp], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to update ODP.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $odp = Odp::find($id);
            if (!$odp) {
                return response()->json(['message' => 'ODP not found.'], 404);
            }

            $odp->delete();
            return response()->json(['message' => 'ODP deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete ODP.', 'error' => $e->getMessage()], 500);
        }
    }





























    // maps

    public function mapsIndex()
    {
        $odps = Odp::with('odpCustomers.odpCustomers')->get()->map(function ($odp) {
            return [
                'id' => $odp->id,
                'name' => $odp->name,
                'status' => $odp->status,
                'latitude' => $odp->latitude,
                'longitude' => $odp->longitude,
                'meta_data' => $odp->meta_data,
                'customers' => $odp->odpCustomers->map(function ($odpCustomer) {
                    return [
                        'name' => $odpCustomer->customer->name,
                        'profile' => $odpCustomer->customer->profile,
                        'status' => $odpCustomer->customer->status,
                    ];
                })->toArray(),
            ];
        });

        return view('odps.gmaps', compact('odps'));
    }

}
