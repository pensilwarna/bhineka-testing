<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;
use App\Models\Bank;
use App\Models\EmployeeFamily;
use App\Models\EmployeeHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PDF;


class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view users')->only(['index', 'show']);
        $this->middleware('can:create users')->only(['create', 'store']);
        $this->middleware('can:edit users')->only(['edit', 'update']);
        $this->middleware('can:delete users')->only(['destroy']);
        $this->middleware('can:ban users')->only(['ban', 'unban']);
        $this->middleware('can:terminate user')->only(['terminate']);
        $this->middleware('can:change role user')->only(['changeRole']);
    }


    public function index()
    {
        return view('users.index');
    }

    public function getData(Request $request)
    {
            $query = User::with(['employee.position', 'roles'])->select('users.*'); 
            $status = $request->input('status_filter');

        if (!is_null($status) && $status !== '') {
            if ($status === 'banned') {
                $query->where('is_banned', true);
            } else {
                $query->where('is_banned', false);
            }
        }

        $currentUser = Auth::user();

        // Ambil ID dari peran yang tidak boleh dilihat, jika ada
        $excludedRoleIds = [];

        if ($currentUser->hasRole('Super-Admin')) {
            // Super-Admin tidak bisa melihat Owner
            $ownerRole = Role::where('name', 'Owner')->first();
            if ($ownerRole) {
                $excludedRoleIds[] = $ownerRole->id;
            }
        } elseif ($currentUser->hasRole('NOC')) {
            // NOC tidak bisa melihat Owner dan Super-Admin
            $ownerRole = Role::where('name', 'Owner')->first();
            $superAdminRole = Role::where('name', 'Super-Admin')->first();
            if ($ownerRole) {
                $excludedRoleIds[] = $ownerRole->id;
            }
            if ($superAdminRole) {
                $excludedRoleIds[] = $superAdminRole->id;
            }
        } elseif (!$currentUser->hasAnyRole(['Owner', 'Super-Admin', 'NOC'])) {
            // Jika bukan Owner, Super-Admin, atau NOC, hanya bisa melihat diri sendiri
            $query->where('users.id', $currentUser->id);
            // Tidak perlu melanjutkan filter role, karena sudah dibatasi per user
        }


        // Terapkan filter berdasarkan peran yang tidak boleh dilihat
        if (!empty($excludedRoleIds)) {
            $query->whereDoesntHave('roles', function ($q) use ($excludedRoleIds) {
                $q->whereIn('roles.id', $excludedRoleIds);
            });
        }


        return DataTables::of($query)
            ->addColumn('name', function ($user) {
                return $user->employee ? $user->employee->full_name : $user->name;
            })
            ->addColumn('email', function ($user) {
                return $user->email;
            })
            ->addColumn('role_badge', function ($user) {
                $role = $user->roles->first();

                $roleMapping = [
                    'owner' => ['class' => 'bg-label-secondary', 'icon' => 'ti ti-home'],
                    'super-admin' => ['class' => 'bg-label-info', 'icon' => 'ti ti-shield-code'],
                    'noc' => ['class' => 'bg-label-primary', 'icon' => 'ti ti-device-desktop-analytics'],
                    'technician' => ['class' => 'bg-label-warning', 'icon' => 'ti ti-user-bolt'],
                    'kasir' => ['class' => 'bg-label-danger', 'icon' => 'ti ti-cash'],
                    'lad' => ['class' => 'bg-label-info', 'icon' => 'ti ti-device-ipad-horizontal-dollar'],
                    'no role' => ['class' => 'bg-label-secondary', 'icon' => 'ti ti-lock-question'],
                ];

                $roleName = strtolower($role?->name ?? 'no role');
                $config = $roleMapping[$roleName] ?? $roleMapping['no role'];

                return '<span class="text-truncate d-flex align-items-center">'
                    . '<span class="badge badge-center rounded-pill ' . $config['class'] . ' w-px-30 h-px-30 me-2">'
                    . '<i class="' . $config['icon'] . ' ti-sm"></i>'
                    . '</span>'
                    . ucfirst($roleName)
                    . '</span>';
            })
            ->addColumn('position', function ($user) {
                // Pastikan employee dan position tersedia sebelum mengakses name
                return ($user->employee && $user->employee->position) ? $user->employee->position->name : 'N/A';
            })
            ->addColumn('status_badge', function ($user) {
                if ($user->is_banned) {
                    return '<span class="badge bg-label-danger">Banned</span>';
                }
                switch ($user->status ?? 'active') { // Menggunakan status dari model User jika ada, atau default 'active'
                    case 'active':
                        return '<span class="badge bg-label-success">Active</span>';
                    case 'inactive':
                        return '<span class="badge text-outline-secondary">Inactive</span>';
                    case 'suspended':
                        return '<span class="badge text-outline-warning">Suspended</span>';
                    default:
                        // Jika status employee juga ingin dipertimbangkan, Anda perlu menambahkannya di sini
                        // Contoh: if ($user->employee && $user->employee->status === 'Terminated') { return '<span class="badge bg-label-danger">Terminated</span>'; }
                        return '<span class="badge bg-label-primary">Active</span>'; // Default jika tidak ada status spesifik
                }
            })
            ->addColumn('action', function ($user) use ($currentUser) {
                $buttons = '<div class="d-flex justify-content-sm-center align-items-sm-center gap-2">';

                // Tombol Show (view users)
                // Filter tombol action berdasarkan permission
                if ($currentUser->can('view users')) {
                    // Hanya izinkan melihat jika user yang dilihat tidak termasuk dalam role yang seharusnya tersembunyi
                    // atau jika user yang dilihat adalah diri sendiri.
                    $userRoles = $user->roles->pluck('name')->toArray();
                    $canViewUser = true; // Asumsi awalnya bisa melihat

                    if ($currentUser->hasRole('Super-Admin')) {
                        if (in_array('Owner', $userRoles)) {
                            $canViewUser = false;
                        }
                    } elseif ($currentUser->hasRole('NOC')) {
                        if (in_array('Owner', $userRoles) || in_array('Super-Admin', $userRoles)) {
                            $canViewUser = false;
                        }
                    } elseif (!$currentUser->hasAnyRole(['Owner', 'Super-Admin', 'NOC'])) {
                        // Jika bukan owner, super-admin, atau NOC, hanya bisa melihat dirinya sendiri
                        if ($user->id !== $currentUser->id) {
                            $canViewUser = false;
                        }
                    }

                    if ($canViewUser) {
                        $buttons .= '
                            <button type="button"
                                class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect show-record"
                                data-bs-toggle="tooltip"
                                title="Show"
                                data-id="'.$user->id.'">
                                <i class="ti ti-zoom-in-area ti-sm me-2"></i>
                            </button>';
                    }
                }


                // Tombol Edit (edit users)
                if ($currentUser->can('edit users')) {
            $userRoles = $user->roles->pluck('name')->toArray();
            $canEditUser = true; // Asumsi awalnya bisa mengedit

            if ($currentUser->hasRole('Super-Admin')) {
                    if (in_array('Owner', $userRoles)) {
                        $canEditUser = false;
                    }
                } elseif ($currentUser->hasRole('NOC')) {
                    if (in_array('Owner', $userRoles) || in_array('Super-Admin', $userRoles)) {
                        $canEditUser = false;
                    }
                } elseif (!$currentUser->hasAnyRole(['Owner', 'Super-Admin', 'NOC'])) {
                    // Jika bukan owner, super-admin, atau NOC, hanya bisa mengedit dirinya sendiri
                    if ($user->id !== $currentUser->id) {
                        $canEditUser = false;
                    }
                }

                if ($canEditUser) {
                    $buttons .= '
                        <button type="button"
                            class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect edit-record"
                            data-bs-toggle="tooltip"
                            title="Edit"
                            data-id="'.$user->id.'">
                            <i class="ti ti-edit ti-sm me-2"></i>
                        </button>';
                }
            }

            // Tombol Delete (delete users)
            if ($currentUser->can('delete users')) {
                // Pastikan user tidak bisa menghapus dirinya sendiri
                if ($user->id !== $currentUser->id) {
                    // Cek juga aturan role untuk delete
                    $userRoles = $user->roles->pluck('name')->toArray();
                    $canDeleteUser = true;

                    if ($currentUser->hasRole('Super-Admin')) {
                        if (in_array('Owner', $userRoles)) {
                            $canDeleteUser = false;
                        }
                    } elseif ($currentUser->hasRole('NOC')) {
                        if (in_array('Owner', $userRoles) || in_array('Super-Admin', $userRoles)) {
                            $canDeleteUser = false;
                        }
                    }

                    if ($canDeleteUser) {
                        $buttons .= '
                            <button type="button"
                                class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect delete-record"
                                data-bs-toggle="tooltip"
                                title="Delete"
                                data-id="'.$user->id.'">
                                <i class="ti ti-trash ti-sm mx-2"></i>
                            </button>';
                    }
                }
            }

            // Tombol Ban/Unban (ban users)
            if ($currentUser->can('ban users')) {
                // Pastikan user tidak bisa meng-ban dirinya sendiri
                if ($user->id !== $currentUser->id) {
                    // Cek juga aturan role untuk ban
                    $userRoles = $user->roles->pluck('name')->toArray();
                    $canBanUser = true;

                    if ($currentUser->hasRole('Super-Admin')) {
                        if (in_array('Owner', $userRoles)) {
                            $canBanUser = false;
                        }
                    } elseif ($currentUser->hasRole('NOC')) {
                        if (in_array('Owner', $userRoles) || in_array('Super-Admin', $userRoles)) {
                            $canBanUser = false;
                        }
                    }

                    if ($canBanUser) {
                        if ($user->is_banned) {
                            $buttons .= '
                                <button type="button"
                                    class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect unban-record"
                                    data-bs-toggle="tooltip"
                                    title="Unban User"
                                    data-id="'.$user->id.'" data-name="' . $user->name . '">
                                    <i class="ti ti-user-check ti-sm mx-2"></i>
                                </button>';
                        } else {
                            $buttons .= '
                                <button type="button"
                                    class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect ban-record"
                                    data-bs-toggle="tooltip"
                                    title="Ban User"
                                    data-id="'.$user->id.'" data-name="' . $user->name . '">
                                    <i class="ti ti-user-off ti-sm mx-2"></i>
                                </button>';
                        }
                    }
                }
            }

            $buttons .= '</div>';
            return $buttons;
        })
        ->rawColumns(['role_badge', 'status_badge', 'action']) // 'profile' tidak ada di adColum, jadi dihapus
        ->make(true);
    }

    public function ban(User $user)
    {
        // Pastikan user tidak mengunci dirinya sendiri (opsional, tapi disarankan)
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak bisa mengunci akun Anda sendiri!'
            ], 403);
        }

        // Cek apakah user sudah di-ban
        if ($user->is_banned) {
            return response()->json([
                'status' => 'info',
                'message' => 'Pengguna ini sudah di-ban.'
            ]);
        }

        $user->is_banned = true;
        $user->banned_at = Carbon::now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User has been banned.'
        ]);
    }

    public function unban(User $user)
    {
        // Cek apakah user tidak di-ban
        if (!$user->is_banned) {
            return response()->json([
                'status' => 'info',
                'message' => 'Pengguna ini tidak di-ban.'
            ]);
        }

        $user->is_banned = false;
        $user->banned_at = null;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'User has been un banned.'
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            // Validasi untuk User
            'user_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'user_phone_number' => 'nullable|string|max:20',
            'roles' => 'required|string|exists:roles,name',

            // Validasi untuk Employee
            'full_name' => 'required|string|max:255',
            'place_of_birth' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'address' => 'required|string',
            'phone_number' => 'nullable|string|max:20',
            'personal_email' => 'nullable|string|email|max:255|unique:employees,personal_email',
            'id_card_number' => 'required|string|max:255|unique:employees,id_card_number',
            'id_card_image_path' => 'nullable|file|mimes:jpg,png,pdf|max:2048',
            'gender' => 'required|in:Laki-laki,Perempuan',
            'marital_status' => 'required|in:Belum Menikah,Menikah,Duda,Janda',
            'basic_salary' => 'nullable|numeric|min:0',
            'bank_id' => 'nullable|exists:banks,id',
            'bank_account_number' => 'nullable|string|max:255',
            'bank_account_holder_name' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'position_id' => 'required|exists:positions,id',
            'profile_picture_path' => 'nullable|file|mimes:jpg,png|max:1024',
            'employment_contract_path' => 'nullable|file|mimes:pdf|max:5120',
            'join_date' => 'required|date',

            // Validasi untuk Employee Family (opsional, berdasarkan input yang ada)
            'family_name' => 'nullable|string|max:255',
            'family_relationship' => 'nullable|string|max:255',
            'family_date_of_birth' => 'nullable|date',
            'family_occupation' => 'nullable|string|max:255',
            'family_is_dependent' => 'nullable|boolean',
        ]);

        $user = null;
        $generatedPassword = Str::random(12);

        try { 
            DB::transaction(function () use ($request, &$user, $generatedPassword) {
                // Upload ID Card Image
                $idCardImagePath = null;
                if ($request->hasFile('id_card_image_path')) {
                    $idCardImagePath = $request->file('id_card_image_path')->store('id_cards', 'public');
                }

                // Upload Profile Picture
                $profilePicturePath = null;
                if ($request->hasFile('profile_picture_path')) {
                    $profilePicturePath = $request->file('profile_picture_path')->store('profile_pictures', 'public');
                }

                // Upload Employment Contract
                $employmentContractPath = null;
                if ($request->hasFile('employment_contract_path')) {
                    $employmentContractPath = $request->file('employment_contract_path')->store('contracts', 'public');
                }

                // 1. Buat Employee
                $employee = Employee::create([
                    'full_name' => $request->full_name,
                    'place_of_birth' => $request->place_of_birth,
                    'date_of_birth' => $request->date_of_birth,
                    'address' => $request->address,
                    'phone_number' => $request->phone_number,
                    'personal_email' => $request->personal_email,
                    'id_card_number' => $request->id_card_number,
                    'id_card_image_path' => $idCardImagePath,
                    'gender' => $request->gender,
                    'marital_status' => $request->marital_status,
                    'basic_salary' => $request->basic_salary,
                    'bank_id' => $request->bank_id,
                    'bank_account_number' => $request->bank_account_number,
                    'bank_account_holder_name' => $request->bank_account_holder_name,
                    'emergency_contact_name' => $request->emergency_contact_name,
                    'emergency_contact_relationship' => $request->emergency_contact_relationship,
                    'emergency_contact_phone' => $request->emergency_contact_phone,
                    'position_id' => $request->position_id,
                    'profile_picture_path' => $profilePicturePath,
                    'employment_contract_path' => $employmentContractPath,
                    'join_date' => $request->join_date,
                ]);

                // 2. Buat User yang terhubung ke Employee
                $user = User::create([
                    'name' => $request->user_name,
                    'email' => $request->email,
                    'phone_number' => $request->user_phone_number,
                    'password' => Hash::make($generatedPassword),
                    'employee_id' => $employee->id,
                ]);

                // Berikan role kepada user
                $user->assignRole($request->roles);

                // 3. Simpan data Employee Family jika ada inputnya
                if ($request->filled('family_name')) {
                    EmployeeFamily::create([
                        'employee_id' => $employee->id,
                        'name' => $request->family_name,
                        'relationship' => $request->family_relationship,
                        'date_of_birth' => $request->family_date_of_birth,
                        'occupation' => $request->family_occupation,
                        'is_dependent' => $request->has('family_is_dependent'),
                    ]);
                }

                // 4. Catat riwayat awal di Employee History
                EmployeeHistory::create([
                    'employee_id' => $employee->id,
                    'change_type' => 'Initial Creation',
                    'old_value' => null, // Tidak ada nilai lama saat pembuatan awal
                    'new_value' => json_encode($employee->toArray()), // Simpan semua data karyawan baru sebagai JSON
                    'notes' => 'Employee profile and user account created.',
                    'changed_by_user_id' => auth()->id(),
                ]);
            });

            if ($user) {
                try {
                    Mail::to($user->email)->send(new UserCredentialsMail($user, $generatedPassword));
                    return redirect()->route('users.index')->with('success', 'User and Employee created successfully! Credentials sent to email.');
                } catch (\Exception $e) {
                    return redirect()->route('users.index')->with('warning', 'User and Employee created, but email failed to send: ' . $e->getMessage());
                }
            } else {
                return redirect()->route('users.index')->with('error', 'User creation failed unexpectedly.');
            }

        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to create user/employee: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $user = User::find($id);
        if (!$user) {
            return redirect()->route('users.index')->with('error', 'User not found.');
        }

        $banks = Bank::all();
        $positions = Position::all();
        $roles = Role::all();

        $familyMembers = $user->familyMembers;
        return view('users.edit', compact('user', 'banks', 'positions', 'roles', 'familyMembers'));
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        // --- Validasi Data ---
        $rules = [
            'full_name'                      => 'required|string|max:255',
            'place_of_birth'                 => 'required|string|max:255',
            'date_of_birth'                  => 'required|date',
            'address'                        => 'required|string|max:1000',
            'phone_number'                   => 'nullable|string|max:20',
            'personal_email'                 => [
                'nullable',
                'email',
                'max:255',
                // Ignore current employee's personal_email if it exists
                Rule::unique('employees', 'personal_email')->ignore($user->employee->id ?? null),
            ],
            'id_card_number'                 => [
                'required',
                'string',
                'max:255',
                // Ignore current employee's id_card_number if it exists
                Rule::unique('employees', 'id_card_number')->ignore($user->employee->id ?? null),
            ],
            'id_card_image_path'             => 'nullable|file|mimes:jpg,png,pdf|max:2048', // 2MB
            'gender'                         => 'required|in:Laki-laki,Perempuan',
            'marital_status'                 => 'required|in:Belum Menikah,Menikah,Duda,Janda',
            'basic_salary'                   => 'nullable|numeric|min:0',
            'bank_id'                        => 'nullable|exists:banks,id',
            'bank_account_number'            => 'nullable|string|max:255',
            'bank_account_holder_name'       => 'nullable|string|max:255',
            'emergency_contact_name'         => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:255',
            'emergency_contact_phone'        => 'nullable|string|max:20',
            'position_id'                    => 'required|exists:positions,id',
            'profile_picture_path'           => 'nullable|file|mimes:jpg,png|max:1024', // 1MB
            'employment_contract_path'       => 'nullable|file|mimes:pdf|max:5120', // 5MB
            'join_date'                      => 'required|date',
            'termination_date'               => 'nullable|date|after_or_equal:join_date',
            'termination_reason'             => 'nullable|string|max:1000',

            // User account fields
            'name'                           => 'required|string|max:255',
            'email'                          => [
                'required',
                'email',
                'max:255',
                // Ignore current user's email
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            // Validation for family members (nested arrays)
            'family_members'                 => 'array',
            'family_members.*.id'            => 'nullable|exists:employee_families,id', // For existing family members
            'family_members.*.name'          => 'nullable|string|max:255', // Removed required_with because we filter for meaningful data below
            'family_members.*.relationship'  => 'nullable|string|max:255', // Removed required_with
            'family_members.*.date_of_birth' => 'nullable|date',
            'family_members.*.occupation'    => 'nullable|string|max:255',
            'family_members.*.is_dependent'  => 'nullable|boolean',
        ];

        $messages = [
            'full_name.required'                      => 'Nama lengkap wajib diisi.',
            'place_of_birth.required'                 => 'Tempat lahir wajib diisi.',
            'date_of_birth.required'                  => 'Tanggal lahir wajib diisi.',
            'date_of_birth.date'                      => 'Format tanggal lahir tidak valid.',
            'address.required'                        => 'Alamat wajib diisi.',
            'id_card_number.required'                 => 'Nomor KTP/Passport wajib diisi.',
            'id_card_number.unique'                   => 'Nomor KTP/Passport ini sudah digunakan.',
            'id_card_image_path.mimes'                => 'Format gambar KTP harus JPG, PNG, atau PDF.',
            'id_card_image_path.max'                  => 'Ukuran gambar KTP maksimal 2MB.',
            'gender.required'                         => 'Jenis kelamin wajib dipilih.',
            'marital_status.required'                 => 'Status pernikahan wajib dipilih.',
            'position_id.required'                    => 'Jabatan wajib dipilih.',
            'position_id.exists'                      => 'Jabatan tidak valid.',
            'profile_picture_path.mimes'              => 'Format foto profil harus JPG atau PNG.',
            'profile_picture_path.max'                => 'Ukuran foto profil maksimal 1MB.',
            'employment_contract_path.mimes'          => 'Format kontrak kerja harus PDF.',
            'employment_contract_path.max'            => 'Ukuran kontrak kerja maksimal 5MB.',
            'join_date.required'                      => 'Tanggal bergabung wajib diisi.',
            'bank_id.exists'                          => 'Bank yang dipilih tidak valid.',
            'personal_email.unique'                   => 'Email pribadi ini sudah terdaftar untuk karyawan lain.',
            'email.unique'                            => 'Email user ini sudah terdaftar untuk user lain.',
            // Removed specific required_with messages for family members as filtering handles empty entries
        ];

        // This will automatically redirect back with errors if validation fails
        $validatedData = $request->validate($rules, $messages);


        DB::beginTransaction();
        try {
            // --- Update Data User ---
            $user->name = $validatedData['name'];
            $user->email = $validatedData['email'];
            $user->save();

            // --- Update atau Buat Data Employee ---
            $employeeData = [
                'full_name'                      => $validatedData['full_name'],
                'place_of_birth'                 => $validatedData['place_of_birth'],
                'date_of_birth'                  => $validatedData['date_of_birth'],
                'address'                        => $validatedData['address'],
                'phone_number'                   => $validatedData['phone_number'],
                'personal_email'                 => $validatedData['personal_email'],
                'id_card_number'                 => $validatedData['id_card_number'],
                'gender'                         => $validatedData['gender'],
                'marital_status'                 => $validatedData['marital_status'],
                'basic_salary'                   => $validatedData['basic_salary'],
                'bank_id'                        => $validatedData['bank_id'],
                'bank_account_number'            => $validatedData['bank_account_number'],
                'bank_account_holder_name'       => $validatedData['bank_account_holder_name'],
                'emergency_contact_name'         => $validatedData['emergency_contact_name'],
                'emergency_contact_relationship' => $validatedData['emergency_contact_relationship'],
                'emergency_contact_phone'        => $validatedData['emergency_contact_phone'],
                'position_id'                    => $validatedData['position_id'],
                'join_date'                      => $validatedData['join_date'],
                'termination_date'               => $validatedData['termination_date'] ?? null,
                'termination_reason'             => $validatedData['termination_reason'] ?? null,
            ];

            if ($user->employee) {
                $employee = $user->employee;
                $employee->update($employeeData);
            } else {
                // If an employee record doesn't exist for this user, create one
                $employee = Employee::create(array_merge($employeeData, ['user_id' => $user->id]));
                $user->employee_id = $employee->id;
                $user->save();
            }

            // --- Penanganan File Upload untuk Employee ---
            if ($request->hasFile('id_card_image_path')) {
                if ($employee->id_card_image_path && Storage::disk('public')->exists($employee->id_card_image_path)) {
                    Storage::disk('public')->delete($employee->id_card_image_path);
                }
                $idCardPath = $request->file('id_card_image_path')->store('employee_docs/id_cards', 'public');
                $employee->id_card_image_path = $idCardPath;
            }

            if ($request->hasFile('profile_picture_path')) {
                if ($employee->profile_picture_path && Storage::disk('public')->exists($employee->profile_picture_path)) {
                    Storage::disk('public')->delete($employee->profile_picture_path);
                }
                $profilePicturePath = $request->file('profile_picture_path')->store('employee_docs/profile_pictures', 'public');
                $employee->profile_picture_path = $profilePicturePath;
            }

            if ($request->hasFile('employment_contract_path')) {
                if ($employee->employment_contract_path && Storage::disk('public')->exists($employee->employment_contract_path)) {
                    Storage::disk('public')->delete($employee->employment_contract_path);
                }
                $contractPath = $request->file('employment_contract_path')->store('employee_docs/contracts', 'public');
                $employee->employment_contract_path = $contractPath;
            }

            $employee->save();

           // --- Sinkronisasi Data Keluarga (EmployeeFamily) ---
            // Ambil ID anggota keluarga yang sudah ada di database untuk karyawan ini
            $existingFamilyMemberIds = $employee->familyMembers->pluck('id')->toArray();
            $updatedFamilyMemberIds = []; // Array untuk menyimpan ID anggota keluarga yang berhasil diperbarui/dibuat dari request

            // Filter data anggota keluarga yang dikirimkan agar hanya memproses yang memiliki konten berarti
            // Ini mencegah penyimpanan atau pembaruan baris kosong
            $submittedMeaningfulFamilyMembers = collect($validatedData['family_members'] ?? [])
                ->filter(function ($item) {
                    // Anggota keluarga dianggap "berarti" jika setidaknya salah satu dari bidang utama ini tidak kosong
                    return !empty($item['name']) || !empty($item['relationship']) || !empty($item['date_of_birth']) || !empty($item['occupation']);
                })->toArray();

            foreach ($submittedMeaningfulFamilyMembers as $familyMemberData) {
                $familyMemberFields = [
                    'name'          => $familyMemberData['name'] ?? null,
                    'relationship'  => $familyMemberData['relationship'] ?? null,
                    'date_of_birth' => $familyMemberData['date_of_birth'] ?? null,
                    'occupation'    => $familyMemberData['occupation'] ?? null,
                    // Pastikan is_dependent diset ke boolean, karena checkbox hanya mengirimkan nilai '1' jika dicentang
                    'is_dependent'  => isset($familyMemberData['is_dependent']) && $familyMemberData['is_dependent'] == '1',
                ];

                // Cek apakah anggota keluarga ini sudah ada (memiliki ID) atau baru
                if (isset($familyMemberData['id']) && $familyMemberData['id']) {
                    // Anggota keluarga yang sudah ada, cari dan perbarui
                    $familyMember = EmployeeFamily::find($familyMemberData['id']);
                    if ($familyMember) {
                        $familyMember->update($familyMemberFields);
                        $updatedFamilyMemberIds[] = $familyMember->id; // Tambahkan ID ke daftar yang diperbarui
                    }
                } else {
                    // Anggota keluarga baru, buat record baru
                    $newFamilyMember = $employee->familyMembers()->create(array_merge($familyMemberFields, ['employee_id' => $employee->id]));
                    $updatedFamilyMemberIds[] = $newFamilyMember->id; // Tambahkan ID ke daftar yang diperbarui
                }
            }

            // Hapus anggota keluarga yang ada di database tetapi tidak ada dalam data yang dikirimkan
            // Ini secara efektif menghapus anggota keluarga yang dihapus dari form di frontend
            $familyMembersToDelete = array_diff($existingFamilyMemberIds, $updatedFamilyMemberIds);
            EmployeeFamily::whereIn('id', $familyMembersToDelete)->delete();



            DB::commit();

            return redirect()->route('users.show', $user->id)
                             ->with('success', 'Employee and User data updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            // Log the exception for debugging
            return redirect()->back()
                             ->with('error', 'An unexpected error occurred while updating the data. Please try again.')
                             ->withInput();
        }
    }
    
    public function destroy(User $user)
    {
        // Optional: Prevent users from deleting their own account
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete your own account!'
            ], 403);
        }

        try {
            if ($user->employee) {
                $user->employee->delete();
            }
            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User and associated employee deleted successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user. Please try again.'
            ], 500);
        }
    }
    
    public function create()
    {
        $positions = Position::all();
        $banks = Bank::all();
        $roles = Role::all();

        return view('users.create', compact('positions', 'banks', 'roles'));
    }

    public function show($id)
    {
        $user = User::with([
            'employee.position',
            'employee.bank',
            'employee.familyMembers',
            'employee.history', 
            'roles'
        ])->findOrFail($id);
        
        if (!$user) {
            return redirect()->back()->with('error', 'User not found.');
        }
        $roles = Role::all();

        return view('users.show', compact('user', 'roles'));
    }

     /**
     * Terminate an employee and ban their user account.
     */
    public function terminate(Request $request, User $user) // Gunakan Route Model Binding
    {
        $request->validate([
            'termination_reason' => 'required|string|max:1000',
        ]);

        if (!$user->employee) {
            return response()->json(['status' => 'error', 'message' => 'User does not have an associated employee profile.'], 400);
        }

        // Cek apakah karyawan sudah di-terminasi
        // Periksa status atau termination_date
        if ($user->employee->status === 'Terminated' || $user->employee->termination_date !== null) {
            return response()->json(['status' => 'info', 'message' => 'Employee is already terminated.'], 400);
        }

        DB::transaction(function () use ($user, $request) {
            // Update employee status (termination date and reason)
            $user->employee->update([
                'termination_date' => Carbon::now(),
                'termination_reason' => $request->termination_reason,
                'status' => 'Terminated', // Pastikan field status ini ada di tabel employees
            ]);

            // Ban the associated user
            if (!$user->is_banned) {
                $user->is_banned = true;
                $user->save();
            }

            // Record the termination in employee history
            EmployeeHistory::create([
                'employee_id' => $user->employee->id,
                'change_type' => 'Termination',
                'old_value' => json_encode(['status' => 'Active']), // Asumsi status sebelumnya 'Active'
                'new_value' => json_encode([
                    'status' => 'Terminated',
                    'termination_date' => $user->employee->termination_date->format('Y-m-d'),
                    'termination_reason' => $user->employee->termination_reason,
                    'user_banned' => true
                ]),
                'notes' => 'Employee terminated. Reason: ' . $request->termination_reason,
                'changed_by_user_id' => auth()->id(),
            ]);
        });

        return response()->json(['status' => 'success', 'message' => 'Employee terminated and user banned successfully!']);
    }

     /**
     * Update user password.
     */
    public function updatePassword(Request $request, User $user)
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user->password = Hash::make($request->new_password);
        $user->save();
        
        EmployeeHistory::create([
                'employee_id' => $user->id,
                'change_type' => 'Change Password',
                'old_value' => json_encode(['Old Password' => '********']),
                'new_value' => json_encode([
                    'New Password' => '********',
                    
                ]),
                'notes' => 'User ' . auth()->user()->name . ' changed the password for user ID ' . $user->id,
                'changed_by_user_id' => auth()->id(),
            ]);

        return response()->json(['status' => 'success', 'message' => 'Password updated successfully!']);
    }

    /**
     * Update the user's role.
     * Tidak bisa multiple role, hanya 1 role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
   public function changeRole(Request $request, $id)
    {
        $user = User::with('employee')->find($id); // Eager load employee untuk EmployeeHistory

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'User not found.'], 404);
        }

        // Pastikan pengguna memiliki profil karyawan untuk mencatat history
        if (!$user->employee) {
             return response()->json(['status' => 'error', 'message' => 'User does not have an employee profile. Role change history cannot be recorded.'], 400);
        }


        $request->validate([
            'role_name' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        DB::beginTransaction();
        try {
            $oldRoleName = $user->roles->pluck('name')->first(); // Ambil role lama
            $newRoleName = $request->input('role_name');

            // Jika role baru sama dengan role lama, tidak perlu melakukan apa-apa
            if ($oldRoleName === $newRoleName) {
                DB::rollBack(); // Pastikan tidak ada transaksi yang tertunda
                return response()->json(['status' => 'info', 'message' => 'User already has the selected role.'], 200);
            }

            // Hapus semua role yang ada saat ini dari user
            $user->syncRoles([]);

            // Tetapkan role baru
            $user->assignRole($newRoleName);
            $employeeFullName = $user->employee->full_name ?? $user->name;
            // Catat ke EmployeeHistory
            EmployeeHistory::create([
                'employee_id' => $user->employee->id, // Menggunakan employee_id dari relasi
                'change_type' => 'Role Change',
                'old_value' => json_encode(['role' => $oldRoleName ?? 'No Role']), // Jika sebelumnya tidak punya role
                'new_value' => json_encode(['role' => $newRoleName]),
                'notes' => 'User ' . (auth()->check() ? auth()->user()->name : 'System') . ' changed role for ' . $employeeFullName . ' from ' . ($oldRoleName ?? 'No Role') . ' to ' . $newRoleName . '.',
                'changed_by_user_id' => auth()->id(), // ID user yang sedang login
            ]);

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'User role updated successfully to ' . $newRoleName . '.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Failed to update user role. ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export employee data to PDF.
     *
     * @param  int  $employeeId
     * @return \Illuminate\Http\Response
     */
    public function exportPdf($employeeId)
    {
        $employee = Employee::with(['user', 'position', 'bank', 'familyMembers'])->find($employeeId);

        if (!$employee) {
            return redirect()->back()->with('error', 'Employee not found.');
        }
        $data = [
            'employee' => $employee,
        ];

        // Load view yang akan dijadikan PDF
        $pdf = PDF::loadView('pdf.employee_profile', $data); // Buat file Blade ini

        // Unduh PDF
        return $pdf->download('employee_profile_' . $employee->full_name . '.pdf');
    }

}
