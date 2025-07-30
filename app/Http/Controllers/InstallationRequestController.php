<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Company;
use App\Models\ServiceLocation;
use App\Models\InstallationRequest;
use App\Models\Package;
use App\Models\Commission;
use App\Models\Odp;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use App\Jobs\SendTicketNotification;

class InstallationRequestController extends Controller
{
    public function encryptId($id)
    {
        return Crypt::encryptString($id);
    }

    public function decryptId($encryptedId)
    {
        try {
            return Crypt::decryptString($encryptedId);
        } catch (DecryptException $e) {
            // Anda bisa log error atau melempar exception kustom yang lebih spesifik
            abort(404, 'Invalid ID format or value.'); // Menghentikan eksekusi jika dekripsi gagal
        }
    }

    public function index()
    {
        return view('installation-requests.index');
    }

    public function getData(Request $request)
    {
        $query = InstallationRequest::query()
            ->select(
                'installation_requests.id',
                'installation_requests.customer_id',
                'installation_requests.service_location_id',
                'installation_requests.sales_id',
                'installation_requests.package_id',
                'installation_requests.proposed_installation_date',
                'installation_requests.notes',
                'installation_requests.status',
                'installation_requests.approved_by',
                'installation_requests.approved_at',
                'installation_requests.rejection_reason',
                'installation_requests.created_at',
                'installation_requests.updated_at',
                'installation_requests.ticket_type'
            )
            ->with('customer', 'serviceLocation', 'sales', 'package')
            ->leftJoin('service_locations', 'installation_requests.service_location_id', '=', 'service_locations.id')
            ->leftJoin('customers', 'installation_requests.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'installation_requests.sales_id', '=', 'users.id')
            ->leftJoin('packages', 'installation_requests.package_id', '=', 'packages.id');

        if ($request->filled('status')) {
            $query->where('installation_requests.status', $request->status);
        }

        if ($request->filled('ticket_type')) {
            $query->where('installation_requests.ticket_type', $request->ticket_type);
        }

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $filterMonth = $request->filled('month') ? $request->month : $currentMonth;
        $query->whereMonth('installation_requests.created_at', $filterMonth);

        $filterYear = $request->filled('year') ? $request->year : $currentYear;
        $query->whereYear('installation_requests.created_at', $filterYear);


        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('customer', function ($installationRequest) {
                return $installationRequest->customer->name ?? 'N/A';
            })
            ->addColumn('service_location', function ($installationRequest) {
                return $installationRequest->serviceLocation->address ?? 'N/A';
            })
            ->addColumn('sales', function ($installationRequest) {
                return $installationRequest->sales->name ?? 'N/A';
            })
            ->addColumn('package', function ($installationRequest) {
                return $installationRequest->package->name ?? 'N/A';
            })
            ->addColumn('status_badge', function ($installationRequest) {
                $badgeClass = [
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger'
                ][$installationRequest->status] ?? 'secondary';
                return '<span class="badge bg-' . $badgeClass . '">' . ucfirst($installationRequest->status) . '</span>';
            })
            ->addColumn('ticket_type', function ($installationRequest) {
                return ucfirst($installationRequest->ticket_type);
            })
            ->addColumn('proposed_installation_date_formatted', function ($installationRequest) {
                return $installationRequest->created_at->format('d M Y');
            })
            ->addColumn('created_at_formatted', function ($installationRequest) {
                return $installationRequest->created_at->format('d M Y H:i');
            })
            ->addColumn('actions', function ($installationRequest) {
                // Enkripsi ID sebelum membuat URL
                $encryptedId = $this->encryptId($installationRequest->id);

                $actions = '<div class="d-flex gap-1">';
                // View Button
                $actions .= '<a href="' . route('installation-requests.show', $encryptedId) . '" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect" data-bs-toggle="tooltip" title="View"><i class="ti ti-scan-eye ti-sm"></i></a>';
                // Edit Button
                $actions .= '<a href="' . route('installation-requests.edit', $encryptedId) . '" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect" data-bs-toggle="tooltip" title="Edit"><i class="ti ti-pencil ti-sm"></i></a>';
                // Delete Button (data-id juga diubah ke ID terenkripsi)
                $actions .= '<button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect delete-installation-request" data-id="' . $encryptedId . '" data-bs-toggle="tooltip" title="Delete"><i class="ti ti-trash ti-sm"></i></button>';
                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    public function selectType()
    {
        return view('installation-requests.create');
    }

    public function createNewCustomer()
    {
        $packages = Package::where('status', 'active')->get();
        return view('installation-requests.create_new', compact('packages'));
    }

    public function createExistingCustomer()
    {
        $packages = Package::where('status', 'active')->get();
        return view('installation-requests.create_existing', compact('packages'));
    }

    public function storeNew(Request $request)
    {
        try {
            $messages = [
                'email.unique' => 'Kemungkinan customer telah terdaftar. Cek data customer terlebih dahulu.',
                'phone.unique' => 'Nomor telepon ini sudah terdaftar. Kemungkinan customer telah terdaftar. Cek data customer terlebih dahulu.',
                'identity_number.unique' => 'Nomor identitas ini sudah terdaftar. Kemungkinan customer telah terdaftar. Cek data customer terlebih dahulu.',
            ];

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => [
                    'nullable',
                    'email',
                    'max:100',
                    Rule::unique('customers', 'email'),
                ],
                'phone' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('customers', 'phone'),
                ],
                'identity_number' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('customers', 'identity_number'),
                ],
                'customer_address' => 'nullable|string',
                'company_name' => 'nullable|string|max:255',
                'company_tax_id' => 'nullable|string|max:50',
                'company_phone' => 'nullable|string|max:20',
                'company_email' => 'nullable|email|max:100',
                'company_address' => 'nullable|string',
                'address' => 'required|string|max:255',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'package_id' => 'required|exists:packages,id',
                'proposed_installation_date' => 'required|date|after_or_equal:today',
                'ticket_type' => 'required|in:new',
                'notes' => 'nullable|string',
                'customer_type' => 'required|in:new',
            ], $messages);

            DB::beginTransaction();

            // Simpan perusahaan jika diisi
            $company = null;
            if ($request->filled('company_name')) {
                // Pertimbangkan unique untuk company fields juga jika perlu
                $company = Company::create([
                    'name' => $request->company_name,
                    'tax_id' => $request->company_tax_id,
                    'phone' => $request->company_phone,
                    'email' => $request->company_email,
                    'address' => $request->company_address,
                    'meta_data' => json_encode([]),
                ]);
            }

            // Simpan pelanggan baru
            $customer = Customer::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'identity_number' => $request->identity_number,
                'address' => $request->customer_address,
                'company_id' => $company ? $company->id : null,
                'status' => 'active',
                'meta_data' => json_encode([]),
            ]);

            // Simpan lokasi layanan
            $serviceLocation = ServiceLocation::create([
                'customer_id' => $customer->id,
                'company_id' => $company ? $company->id : null,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'package_id' => $request->package_id,
                'sales_id' => Auth::id(), // Sales adalah pengguna yang login
                'status' => 'pending',
            ]);

            // Simpan permintaan instalasi
            $installationRequest = InstallationRequest::create([
                'customer_id' => $customer->id,
                'service_location_id' => $serviceLocation->id,
                'sales_id' => Auth::id(),
                'package_id' => $request->package_id,
                'proposed_installation_date' => $request->proposed_installation_date,
                'notes' => $request->notes,
                'status' => 'pending',
                'ticket_type' => $request->ticket_type,
            ]);

            // Tambahkan komisi untuk pemasangan baru
            if ($request->ticket_type === 'new') {
                Commission::create([
                    'sales_id' => Auth::id(),
                    'commissionable_type' => InstallationRequest::class,
                    'commissionable_id' => $installationRequest->id,
                    'amount' => 25000,
                    'status' => Commission::STATUS_PENDING,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Permintaan instalasi untuk pelanggan baru berhasil dibuat.'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getServiceLocations(Request $request)
    {
        $customerId = $request->query('customer_id');

        $serviceLocations = ServiceLocation::where('customer_id', $customerId)
            ->with(['company'])
            // Mengecek apakah ada InstallationRequest terkait (tipe apa pun, status apa pun)
            ->withExists('installationRequests')
            // Mengecek apakah ada InstallationRequest dengan status 'pending' atau 'approved'
            ->withExists([
                'installationRequests as has_pending_or_approved_ir' => function ($query) {
                    $query->whereIn('status', ['pending', 'approved']);
                }
            ])
            ->get()
            ->map(function ($location) {
                $location->has_installation_request = $location->installation_requests_exists;

                // Logika 'can_delete' diselaraskan dengan aturan penghapusan di `deleteServiceLocation`:
                // 1. Status lokasi harus 'pending'.
                // 2. Dibuat dalam <= 7 hari terakhir.
                // 3. TIDAK memiliki permintaan instalasi berstatus 'pending' atau 'approved'.
                $location->can_delete = $location->status === 'pending' &&
                    Carbon::parse($location->created_at)->diffInDays(Carbon::now()) <= 7 &&
                    !$location->has_pending_or_approved_ir;

                return $location;
            });

        return response()->json(['service_locations' => $serviceLocations]);
    }

    /**
     * Membuat lokasi layanan baru.
     * Termasuk validasi dan penanganan jumlah lokasi pending yang diizinkan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createServiceLocation(Request $request)
    {
        try {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'address' => 'required|string|max:255',
                'latitude' => [
                    'required',
                    'numeric',
                    'between:-11,6', // Range latitude Indonesia
                    'regex:/^-?\d{1,2}\.?\d*$/' // Format decimal yang valid
                ],
                'longitude' => [
                    'required',
                    'numeric',
                    'between:95,141', // Range longitude Indonesia
                    'regex:/^-?\d{1,3}\.?\d*$/' // Format decimal yang valid
                ],
                'company_id' => 'nullable|exists:companies,id',
                'package_id' => 'nullable|exists:packages,id',
            ]);

            // Dapatkan ID service_location yang terkait dengan installation_request 'new' dan 'pending'
            $serviceLocationIdsWithPendingNewRequests = InstallationRequest::where('customer_id', $request->customer_id)
                ->where('ticket_type', 'new') // Hanya 'pasang baru'
                ->where('status', 'pending')  // Dan statusnya 'pending'
                ->pluck('service_location_id')
                ->unique();

            // Hitung berapa banyak dari service_location ini yang juga berstatus 'pending'
            $pendingLocationsCount = ServiceLocation::whereIn('id', $serviceLocationIdsWithPendingNewRequests)
                ->where('status', 'pending')
                ->count();

            if ($pendingLocationsCount >= 2) {
                return response()->json([
                    'message' => 'Tidak dapat membuat lokasi baru. Pelanggan ini sudah memiliki 2 atau lebih lokasi dengan permintaan instalasi pasang baru berstatus pending.'
                ], 422);
            }

            $companyId = $request->input('company_id');
            $packageId = $request->input('package_id');

            $serviceLocation = ServiceLocation::create([
                'customer_id' => $request->customer_id,
                'company_id' => $companyId,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'package_id' => $packageId,
                'status' => 'pending', // Status default saat dibuat
                'sales_id' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Lokasi layanan baru berhasil dibuat.',
                'service_location' => $serviceLocation
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat lokasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus lokasi layanan.
     * Menerapkan aturan bisnis yang ketat untuk penghapusan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteServiceLocation(Request $request)
    {
        try {
            $request->validate([
                'location_id' => 'required|exists:service_locations,id'
            ]);

            $location = ServiceLocation::findOrFail($request->location_id);

            // Aturan 1: Tidak bisa dihapus jika status aktif atau inaktif
            if (in_array($location->status, ['active', 'inactive'])) {
                return response()->json([
                    'message' => 'Lokasi dengan status aktif atau inaktif tidak dapat dihapus.'
                ], 403);
            }

            // Aturan 2: Hanya bisa dihapus jika status pending
            if ($location->status !== 'pending') {
                return response()->json([
                    'message' => 'Lokasi hanya dapat dihapus jika berstatus pending.'
                ], 403);
            }

            // Aturan 3: Hanya bisa dihapus dalam 7 hari sejak dibuat (jika pending)
            if (Carbon::parse($location->created_at)->diffInDays(Carbon::now()) > 7) {
                return response()->json([
                    'message' => 'Lokasi hanya dapat dihapus dalam 7 hari sejak dibuat jika berstatus pending.'
                ], 403);
            }

            // Aturan 4: Cek apakah ada permintaan instalasi yang masih pending atau disetujui
            // Jika ada, lokasi tidak bisa dihapus
            $hasPendingOrApprovedIR = InstallationRequest::where('service_location_id', $location->id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($hasPendingOrApprovedIR) {
                return response()->json([
                    'message' => 'Lokasi ini terkait dengan permintaan instalasi yang masih pending atau disetujui.'
                ], 403);
            }

            // Jika semua aturan dilewati, hapus lokasi
            $location->delete();
            return response()->json([
                'message' => 'Lokasi layanan berhasil dihapus.'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus lokasi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function searchCustomers(Request $request)
    {
        $query = $request->get('query');

        if (strlen($query) < 3) {
            return response()->json([]);
        }

        $customers = Customer::where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->orWhere('phone', 'LIKE', "%{$query}%")
                ->orWhere('identity_number', 'LIKE', "%{$query}%");
        })
            ->where('status', 'active')
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone', 'identity_number']);

        return response()->json($customers);
    }

    public function storeExisting(Request $request)
    {
        try {
            $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'service_location_id' => 'required|exists:service_locations,id',
                'company_name' => 'nullable|string|max:255',
                'company_tax_id' => 'nullable|string|max:50',
                'company_phone' => 'nullable|string|max:20',
                'company_email' => 'nullable|email|max:100',
                'company_address' => 'nullable|string',
                'package_id' => 'required|exists:packages,id',
                'proposed_installation_date' => 'required|date|after_or_equal:today',
                'ticket_type' => 'required|in:new',
                'notes' => 'nullable|string',
                'customer_type' => 'required|in:existing',
            ]);

            DB::beginTransaction();

            // Simpan perusahaan jika diisi
            $company = null;
            if ($request->filled('company_name')) {
                $company = Company::create([
                    'name' => $request->company_name,
                    'tax_id' => $request->company_tax_id,
                    'phone' => $request->company_phone,
                    'email' => $request->company_email,
                    'address' => $request->company_address,
                    'meta_data' => json_encode([])
                ]);
            }

            // Update service location dengan company_id jika ada
            $serviceLocation = ServiceLocation::findOrFail($request->service_location_id);
            if ($company) {
                $serviceLocation->company_id = $company->id;
                $serviceLocation->package_id = $request->package_id; // Update package juga
                $serviceLocation->save();
            }

            // Simpan permintaan instalasi
            $installationRequest = InstallationRequest::create([
                'customer_id' => $request->customer_id,
                'service_location_id' => $request->service_location_id,
                'sales_id' => Auth::id(),
                'package_id' => $request->package_id,
                'proposed_installation_date' => $request->proposed_installation_date,
                'notes' => $request->notes,
                'status' => 'pending',
                'ticket_type' => $request->ticket_type,
            ]);

            // Tambahkan komisi untuk pemasangan baru
            if ($request->ticket_type === 'new') {
                Commission::create([
                    'sales_id' => Auth::id(),
                    'commissionable_type' => InstallationRequest::class,
                    'commissionable_id' => $installationRequest->id,
                    'amount' => 25000, // Komisi Rp25.000 untuk pemasangan baru
                    'status' => Commission::STATUS_PENDING,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Permintaan instalasi untuk pelanggan existing berhasil dibuat.'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyimpan data: ' . $e->getMessage()
            ], 500);
        }
    }




    public function show(string $encryptedId)
    {
        $id = $this->decryptId($encryptedId);
        $installationRequest = InstallationRequest::with('customer', 'serviceLocation', 'sales', 'package')->findOrFail($id);

        $user = Auth::user();
        $supervisors = collect();

        if ($user->hasRole(['Owner', 'Super-Admin'])) {
            $supervisors = User::role('NOC')->where('is_banned', 'false')->get();
        } elseif ($user->hasRole('NOC')) {
            $supervisors = collect([$user]);
        }

        $technicians = User::role('Technician')->where('is_banned', 'false')->get();
        return view('installation-requests.show', compact('installationRequest', 'supervisors', 'technicians'));
    }

    public function edit(string $encryptedId)
    {
        $id = $this->decryptId($encryptedId);
        $installationRequest = InstallationRequest::with('customer', 'serviceLocation', 'sales')->findOrFail($id);

        $customerServiceLocations = ServiceLocation::where('customer_id', $installationRequest->customer_id)->get();

        $salesLAD = User::role('LAD')->get();

        $packages = Package::where('status', 'active')->get();
        $statuses = ['pending', 'approved', 'rejected'];
        $ticketTypes = ['new_installation', 'upgrade', 'downgrade', 'relocation'];

        return view('installation-requests.edit', compact(
            'installationRequest',
            'customerServiceLocations',
            'salesLAD',
            'packages',
            'statuses',
            'ticketTypes'
        ));
    }

    public function update(Request $request, string $id)
    {
        // Validasi data yang masuk
        $rules = [
            'customer_id' => 'required|exists:customers,id',
            'service_location_id' => 'required|exists:service_locations,id',
            'service_location_address' => 'required|string|max:255',
            'service_location_latitude' => 'required|numeric|between:-90,90',
            'service_location_longitude' => 'required|numeric|between:-180,180',
            'package_id' => 'required|exists:packages,id',
            'proposed_installation_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'sales_id' => 'required|exists:users,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => [
                'nullable',
                'email',
                'max:100',
                Rule::unique('customers', 'email')->ignore($request->customer_id),
            ],
            'customer_phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->ignore($request->customer_id),
            ],
            'customer_identity_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'identity_number')->ignore($request->customer_id),
            ],
            'customer_address' => 'nullable|string',
            'rejection_reason' => 'nullable|string|max:500', // Aturan validasi tetap
        ];

        $validatedData = $request->validate($rules);

        DB::beginTransaction();
        try {
            $installationRequest = InstallationRequest::with(['customer', 'serviceLocation'])->findOrFail($id);

            $originalSalesId = $installationRequest->sales_id;
            $newSalesId = $validatedData['sales_id'];

            // Update Customer
            $installationRequest->customer->update([
                'name' => $validatedData['customer_name'],
                'email' => $validatedData['customer_email'],
                'phone' => $validatedData['customer_phone'],
                'identity_number' => $validatedData['customer_identity_number'],
                'address' => $validatedData['customer_address'],
            ]);

            // Update Service Location
            $serviceLocationToUpdate = ServiceLocation::findOrFail($validatedData['service_location_id']);
            $serviceLocationToUpdate->update([
                'address' => $validatedData['service_location_address'],
                'latitude' => $validatedData['service_location_latitude'],
                'longitude' => $validatedData['service_location_longitude'],
            ]);

            // Logika Komisi jika Sales PIC berubah
            if ($newSalesId != $originalSalesId) {
                Commission::where('commissionable_type', InstallationRequest::class)
                    ->where('commissionable_id', $installationRequest->id)
                    ->where('sales_id', $originalSalesId)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);

                Commission::create([
                    'sales_id' => $newSalesId,
                    'commissionable_type' => InstallationRequest::class,
                    'commissionable_id' => $installationRequest->id,
                    'amount' => 25000, // Sesuaikan dengan logika komisi Anda
                    'status' => 'pending',
                ]);
            }

            // --- PERBAIKAN UTAMA DI SINI ---
            // Siapkan data untuk di-update
            $updateData = [
                'service_location_id' => $validatedData['service_location_id'],
                'package_id' => $validatedData['package_id'],
                'proposed_installation_date' => $validatedData['proposed_installation_date'],
                'notes' => $validatedData['notes'],
                'sales_id' => $newSalesId,
            ];

            // Hanya tambahkan 'rejection_reason' jika ada di dalam request
            if ($request->has('rejection_reason')) {
                $updateData['rejection_reason'] = $validatedData['rejection_reason'];
            }

            // Lakukan update dengan data yang sudah disiapkan
            $installationRequest->update($updateData);
            // --- AKHIR PERBAIKAN ---

            DB::commit();

            return response()->json(['message' => 'Data permintaan instalasi berhasil diperbarui!'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage()], 500);
        }
    }


    public function destroy(string $encryptedId)
    {
        $id = $this->decryptId($encryptedId);
        $installationRequest = InstallationRequest::findOrFail($id);
        try {
            $installationRequest->delete();
            return response()->json(['message' => 'Installation Request deleted successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete installation request. ' . $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, string $encryptedId)
    {
        $id = $this->decryptId($encryptedId);
        $installationRequest = InstallationRequest::findOrFail($id); // Ambil model setelah dekripsi
        // ... (sisa kode reject Anda, menggunakan $installationRequest) ...
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            if ($installationRequest->status !== 'pending') {
                return response()->json(['message' => 'This request cannot be rejected as its status is not pending.'], 400);
            }

            $commission = Commission::where('commissionable_type', InstallationRequest::class)
                ->where('commissionable_id', $installationRequest->id)
                ->where('status', Commission::STATUS_PENDING)
                ->first();

            if ($commission) {
                $commission->update([
                    'status' => Commission::STATUS_CANCELLED,
                    'paid_at' => null,
                ]);
            }

            if ($installationRequest->serviceLocation) {
                $installationRequest->serviceLocation->update([
                    'status' => ServiceLocation::STATUS_CANCELLED,
                ]);
            }

            $installationRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $request->input('rejection_reason'),
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return response()->json(['message' => 'Installation Request rejected successfully! Commission cancelled and Service Location status updated.'], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to reject installation request. ' . $e->getMessage()], 500);
        }
    }

    public function approveAndCreateTicket(Request $request, string $id)
    {
        // Validation dengan semua field optional - smart defaults akan digunakan
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'supervisor_id' => 'nullable|exists:users,id',
            'technician_ids' => 'nullable|array',
            'technician_ids.*' => 'exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $installationRequest = InstallationRequest::findOrFail($id);

            if ($installationRequest->status !== 'pending') {
                return response()->json(['message' => 'This request has already been processed.'], 409);
            }

            // Smart defaults - otomatis assign berdasarkan business logic
            $defaultSupervisor = $this->getDefaultSupervisor();
            $defaultTechnicians = $this->getAvailableTechnicians();

            $supervisorId = $validated['supervisor_id'] ?? $defaultSupervisor->id;
            $technicianIds = $validated['technician_ids'] ?? $defaultTechnicians;
            $title = $validated['title'] ?? "Installation: {$installationRequest->customer->name} - {$installationRequest->serviceLocation->address}";
            $description = $validated['description'] ?? $this->generateDefaultDescription($installationRequest);

            // Update installation request
            $installationRequest->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Update service location
            $installationRequest->serviceLocation->update(['status' => 'active']);

            // Calculate priority berdasarkan proposed date
            $priority = $this->calculatePriority($installationRequest);

            // Create ticket dengan smart defaults
            $ticket = Ticket::create([
                'id' => Str::uuid(),
                'customer_id' => $installationRequest->customer_id,
                'service_location_id' => $installationRequest->service_location_id,
                'supervisor_id' => $supervisorId,
                'odp_id' => null,
                'installation_request_id' => $installationRequest->id,
                'title' => $title,
                'description' => $description,
                'status' => Ticket::STATUS_OPEN,
                'priority' => $priority,
                'ticket_type' => 'new_installation',
                'created_by' => auth()->id(),
                'assigned_at' => now(),
            ]);

            // Assign technicians
            if (!empty($technicianIds)) {
                $ticket->technicians()->attach($technicianIds);
            }

            // COMMIT TRANSACTION DULU SEBELUM KIRIM NOTIFIKASI
            DB::commit();

            // Send notifications SETELAH data tersimpan - tidak akan rollback jika notifikasi gagal
            $this->sendTicketNotifications($ticket);

            $redirectUrl = route('tickets.show', $ticket->id);

            return response()->json([
                'message' => 'Installation request approved and ticket created successfully! Notifications sent to technicians.',
                'ticket_id' => $ticket->id,
                'redirect_url' => $redirectUrl
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Helper method untuk mendapatkan supervisor default
     */
    private function getDefaultSupervisor()
    {
        $user = auth()->user();

        // Jika user adalah NOC, assign ke dirinya sendiri
        if ($user->hasRole('NOC')) {
            return $user;
        }

        // Jika Owner/Super-Admin, pilih NOC yang available
        $availableSupervisor = User::role('NOC')
            ->where('is_banned', false)
            ->whereHas('supervisedTickets', function ($q) {
                $q->whereIn('status', ['open', 'in_progress']);
            }, '<', 5) // NOC dengan < 5 active tickets
            ->first();

        // Fallback ke NOC pertama yang tersedia
        return $availableSupervisor ?? User::role('NOC')->where('is_banned', false)->first();
    }

    /**
     * Helper method untuk mendapatkan technician yang tersedia
     */
    private function getAvailableTechnicians()
    {
        // Pilih 2 technician yang paling sedikit active tickets
        return User::role('Technician')
            ->where('is_banned', false)
            ->withCount([
                'assignedTickets' => function ($q) {
                    $q->whereIn('status', ['open', 'in_progress']);
                }
            ])
            ->orderBy('assigned_tickets_count', 'asc')
            ->limit(2)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Helper method untuk generate description default
     */
    private function generateDefaultDescription($installationRequest)
    {
        $description = "New installation request for {$installationRequest->customer->name}\n";
        $description .= "Package: {$installationRequest->package->name}\n";
        $description .= "Location: {$installationRequest->serviceLocation->address}\n";
        $description .= "Proposed Date: {$installationRequest->proposed_installation_date->format('d M Y')}\n";

        if ($installationRequest->notes) {
            $description .= "Notes: {$installationRequest->notes}\n";
        }

        return $description;
    }









































    /**
     * Helper method untuk calculate priority berdasarkan business logic
     */
    private function calculatePriority($installationRequest)
    {
        $proposedDate = $installationRequest->proposed_installation_date;
        $daysDiff = now()->diffInDays($proposedDate, false); // false = bisa negatif jika sudah lewat
        
        // Jika sudah lewat tanggal yang diusulkan
        if ($daysDiff < 0) {
            return Ticket::PRIORITY_URGENT;
        }
        
        // Jika hari ini atau besok
        if ($daysDiff <= 1) {
            return Ticket::PRIORITY_HIGH;
        }
        
        // Jika dalam 3 hari
        if ($daysDiff <= 3) {
            return Ticket::PRIORITY_NORMAL;
        }
        
        // Jika lebih dari 3 hari
        return Ticket::PRIORITY_LOW;
    }

    /**
     * Send notifications for ticket assignment using new NotificationManager
     */
    private function sendTicketNotifications(Ticket $ticket)
    {
        try {
            $notificationManager = app(\App\Services\NotificationManager::class);
            
            // Log mulai proses notifikasi
            Log::info('Starting ticket notification process for ticket: ' . $ticket->id);
            
            // Kirim notifikasi menggunakan NotificationManager
            $results = $notificationManager->notifyTicketAssigned($ticket);
            
            // Log hasil notifikasi
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($results as $recipient => $result) {
                if ($result['success']) {
                    $successCount++;
                    Log::info("Notification sent successfully to {$recipient} for ticket: {$ticket->id}");
                } else {
                    $failureCount++;
                    Log::warning("Notification failed for {$recipient} for ticket: {$ticket->id}. Error: " . ($result['error'] ?? 'Unknown error'));
                }
            }
            
            Log::info("Ticket notification summary for {$ticket->id}: {$successCount} success, {$failureCount} failures");
            
        } catch (\Exception $e) {
            // Log error tapi jangan throw exception agar tidak mengganggu proses utama
            Log::error('Notification manager error for ticket ' . $ticket->id . ': ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
        }
    }

}