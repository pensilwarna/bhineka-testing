<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Odp;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use App\Jobs\SendTicketNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use App\Models\InstallationRequest;


class TicketController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppNotificationService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    public function index()
    {
        return view('tickets.index');
    }

    public function create()
    {
        $customers = \App\Models\Customer::select('id', 'name', 'email', 'phone')->get();
        $serviceLocations = \App\Models\ServiceLocation::with('customer:id,name')->get();
        $technicians = \App\Models\User::role('Technician')->select('id', 'name')->get();
        $supervisors = \App\Models\User::role(['NOC', 'Super-Admin'])->select('id', 'name')->get();
        $odps = \App\Models\Odp::where('status', 'active')->select('id', 'name')->get();

        return view('tickets.create', compact('customers', 'serviceLocations', 'technicians', 'supervisors', 'odps'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'service_location_id' => 'required|exists:service_locations,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,normal,high,urgent',
            'ticket_type' => 'required|in:new_installation,repair,reactivation',
            'technician_ids' => 'array',
            'technician_ids.*' => 'exists:users,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'odp_id' => 'nullable|exists:odps,id'
        ]);

        DB::beginTransaction();
        try {
            // Generate UUID for ticket
            $ticketId = (string) \Illuminate\Support\Str::uuid();

            $ticket = Ticket::create([
                'id' => $ticketId,
                'customer_id' => $request->customer_id,
                'service_location_id' => $request->service_location_id,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'ticket_type' => $request->ticket_type,
                'supervisor_id' => $request->supervisor_id,
                'odp_id' => $request->odp_id,
                'status' => 'open',
                'created_by' => Auth::id()
            ]);

            // Attach technicians if provided
            if ($request->has('technician_ids') && !empty($request->technician_ids)) {
                $ticket->technicians()->attach($request->technician_ids);
                $ticket->update(['status' => 'assigned', 'assigned_at' => now()]);
            }

            // Log ticket creation
            $ticket->logs()->create([
                'user_id' => Auth::id(),
                'action' => 'created',
                'description' => 'Tiket dibuat oleh ' . Auth::user()->name,
                'metadata' => [
                    'priority' => $request->priority,
                    'ticket_type' => $request->ticket_type
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tiket berhasil dibuat!',
                'ticket' => $ticket->load(['customer', 'serviceLocation', 'technicians'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating ticket: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat tiket: ' . $e->getMessage()
            ], 500);
        }
    }

    public function edit(string $id)
    {
        $ticket = Ticket::with(['customer', 'serviceLocation', 'technicians', 'supervisor'])->findOrFail($id);
        $customers = \App\Models\Customer::select('id', 'name', 'email', 'phone')->get();
        $serviceLocations = \App\Models\ServiceLocation::with('customer:id,name')->get();
        $technicians = \App\Models\User::role('Technician')->select('id', 'name')->get();
        $supervisors = \App\Models\User::role(['NOC', 'Super-Admin'])->select('id', 'name')->get();
        $odps = \App\Models\Odp::where('status', 'active')->select('id', 'name', 'location')->get();

        return view('tickets.edit', compact('ticket', 'customers', 'serviceLocations', 'technicians', 'supervisors', 'odps'));
    }

    public function update(Request $request, string $id)
    {
        $ticket = Ticket::findOrFail($id);

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'service_location_id' => 'required|exists:service_locations,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,normal,high,urgent',
            'ticket_type' => 'required|in:new_installation,repair,reactivation',
            'technician_ids' => 'array',
            'technician_ids.*' => 'exists:users,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'odp_id' => 'nullable|exists:odps,id',
            'status' => 'nullable|in:open,in_progress,waiting_supervisor,solved,closed,failed'
        ]);

        DB::beginTransaction();
        try {
            $oldData = $ticket->toArray();

            $ticket->update([
                'customer_id' => $request->customer_id,
                'service_location_id' => $request->service_location_id,
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'ticket_type' => $request->ticket_type,
                'supervisor_id' => $request->supervisor_id,
                'odp_id' => $request->odp_id,
                'status' => $request->status ?? $ticket->status
            ]);

            // Update technicians
            if ($request->has('technician_ids')) {
                $ticket->technicians()->sync($request->technician_ids);
                
                if (!empty($request->technician_ids) && $ticket->status === 'open') {
                    $ticket->update(['status' => 'assigned', 'assigned_at' => now()]);
                }
            }

            // Log ticket update
            $ticket->logs()->create([
                'user_id' => Auth::id(),
                'action' => 'updated',
                'description' => 'Tiket diupdate oleh ' . Auth::user()->name,
                'metadata' => [
                    'old_data' => $oldData,
                    'changes' => $ticket->getChanges()
                ]
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tiket berhasil diupdate!',
                'ticket' => $ticket->load(['customer', 'serviceLocation', 'technicians'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating ticket: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate tiket: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $ticket = Ticket::findOrFail($id);
            
            // Check if user has permission to delete
            if (!Auth::user()->hasRole(['Owner', 'Super-Admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk menghapus tiket'
                ], 403);
            }

            // Check if ticket can be deleted (only open or cancelled tickets)
            if (!in_array($ticket->status, ['open', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya tiket dengan status Open atau Cancelled yang dapat dihapus'
                ], 400);
            }

            DB::beginTransaction();

            // Log deletion before deleting
            $ticket->logs()->create([
                'user_id' => Auth::id(),
                'action' => 'deleted',
                'description' => 'Tiket dihapus oleh ' . Auth::user()->name,
                'metadata' => [
                    'ticket_data' => $ticket->toArray()
                ]
            ]);

            // Detach technicians
            $ticket->technicians()->detach();

            // Delete ticket (soft delete)
            $ticket->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tiket berhasil dihapus!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting ticket: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tiket: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getData(Request $request)
    {
        try {
            // Log request for debugging
            \Log::info('Tickets getData request', [
                'filters' => $request->all(),
                'user' => Auth::id()
            ]);
            
            // Check if we have any tickets at all
            $ticketCount = Ticket::count();
            \Log::info('Total tickets in database: ' . $ticketCount);
            
            if ($ticketCount == 0) {
                \Log::warning('No tickets found in database');
            }
            
            // Optimize query by selecting only needed columns first
            $query = Ticket::select('tickets.id', 'tickets.kode', 'tickets.customer_id', 'tickets.service_location_id', 
                            'tickets.supervisor_id', 'tickets.title', 'tickets.status', 'tickets.priority', 
                            'tickets.ticket_type', 'tickets.created_at', 'tickets.work_duration_minutes');
            
            // Then eager load relationships with specific columns
            $query->with([
                'customer:id,name,email,phone',
                'serviceLocation:id,address',
                'supervisor:id,name',
                'technicians:id,name'
            ]);

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by priority
            if ($request->filled('priority')) {
                $query->where('priority', $request->input('priority'));
            }

            // Filter by ticket type
            if ($request->filled('ticket_type')) {
                $query->where('ticket_type', $request->input('ticket_type'));
            }

            // Filter by technician (if user is technician)
            $user = Auth::user();
            if ($user->hasRole('Technician')) {
                $query->whereHas('technicians', function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }

            // Optimize ordering by using simple priority field ordering
            $query->orderByRaw("
                CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'normal' THEN 3 
                    WHEN 'low' THEN 4 
                    ELSE 5
                END
            ")->orderBy('created_at', 'desc');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer_name', function ($ticket) {
                    return $ticket->customer->name ?? 'N/A';
                })
                ->addColumn('location', function ($ticket) {
                    return $ticket->serviceLocation->address ?? 'N/A';
                })
                ->addColumn('created_at_formatted', function ($ticket) { // Tambahkan kolom baru untuk tanggal terformat
                    // Menggunakan Carbon di backend untuk format tanggal
                    return $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i') : ''; //
                })
                ->addColumn('priority_badge', function ($ticket) {
                    $badges = [
                        'low' => 'success',
                        'normal' => 'primary',
                        'high' => 'warning',
                        'urgent' => 'danger'
                    ];
                    $class = $badges[$ticket->priority] ?? 'secondary';
                    return '<span class="badge bg-' . $class . '">' . ucfirst($ticket->priority) . '</span>';
                })
                ->addColumn('status_badge', function ($ticket) {
                    $badges = [
                        'open' => 'secondary',
                        'in_progress' => 'warning',
                        'waiting_supervisor' => 'info',
                        'solved' => 'success',
                        'closed' => 'dark',
                        'failed' => 'danger'
                    ];
                    $class = $badges[$ticket->status] ?? 'secondary';
                    return '<span class="badge bg-' . $class . '">' . ucfirst(str_replace('_', ' ', $ticket->status)) . '</span>';
                })
                ->addColumn('technicians_list', function ($ticket) {
                    return $ticket->technicians->pluck('name')->join(', ') ?: 'Not assigned';
                })
                ->addColumn('work_duration', function ($ticket) {
                    if ($ticket->work_duration_minutes) {
                        $hours = floor($ticket->work_duration_minutes / 60);
                        $minutes = $ticket->work_duration_minutes % 60;
                        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                    }
                    return '-';
                })
                ->addColumn('actions', function ($ticket) {
                    $user = Auth::user();
                    $actions = '<div class="d-flex gap-1">';

                    // View button
                    $actions .= '<a href="' . route('tickets.show', $ticket->id) . '" class="btn btn-sm btn-icon btn-text-secondary rounded-pill waves-effect" data-bs-toggle="tooltip" title="View"><i class="ti ti-eye ti-sm"></i></a>';

                    // Technician actions
                    if ($user->hasRole('Technician') && $ticket->technicians->contains($user)) {
                        if ($ticket->status === 'assigned') {
                            $actions .= '<button type="button" class="btn btn-sm btn-icon btn-text-primary rounded-pill waves-effect acknowledge-ticket" data-id="' . $ticket->id . '" data-bs-toggle="tooltip" title="Acknowledge"><i class="ti ti-check ti-sm"></i></button>';
                        }

                        if (in_array($ticket->status, ['acknowledged', 'in_progress'])) {
                            $actions .= '<button type="button" class="btn btn-sm btn-icon btn-text-warning rounded-pill waves-effect checkin-ticket" data-id="' . $ticket->id . '" data-bs-toggle="tooltip" title="Check-in"><i class="ti ti-map-pin ti-sm"></i></button>';
                        }
                    }

                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['priority_badge', 'status_badge', 'actions'])
                ->make(true);
                
        } catch (\Exception $e) {
            \Log::error('Error in tickets getData: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => true,
                'message' => 'Error loading ticket data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        $ticket = Ticket::with([
            'customer',
            'serviceLocation',
            'supervisor',
            'technicians',
            'odp',
            'installationRequest'
        ])->findOrFail($id);

        $odps = Odp::where('status', 'active')->get();

        return view('tickets.show', compact('ticket', 'odps'));
    }

    /**
     * Technician acknowledges the ticket
     */
    public function acknowledge(Request $request, string $id)
    {
        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();

        // Validate technician is assigned to this ticket
        if (!$ticket->technicians->contains($user)) {
            return response()->json(['message' => 'You are not assigned to this ticket.'], 403);
        }

        if ($ticket->status !== 'open') {
            return response()->json(['message' => 'Ticket cannot be acknowledged in current status.'], 400);
        }

        $ticket->update([
            'status' => 'in_progress',
            'acknowledged_at' => now(),
            'work_started_at' => now()
        ]);

        return response()->json(['message' => 'Ticket acknowledged successfully!']);
    }

    /**
     * Technician check-in with GPS validation
     */
    public function checkin(Request $request, string $id)
    {
        $request->validate([
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
            ]
        ]);

        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();

        // Validate technician is assigned to this ticket
        if (!$ticket->technicians->contains($user)) {
            return response()->json(['message' => 'Anda tidak ditugaskan pada tiket ini.'], 403);
        }

        // Validate ticket status
        if (!in_array($ticket->status, ['open', 'in_progress'])) {
            return response()->json(['message' => 'Tiket tidak dalam status yang dapat di-checkin.'], 400);
        }

        // Get customer location from service location
        $customerLat = $ticket->serviceLocation->latitude;
        $customerLng = $ticket->serviceLocation->longitude;
        $technicianLat = $request->latitude;
        $technicianLng = $request->longitude;

        // Calculate distance from customer location
        $distance = $this->calculateDistance($customerLat, $customerLng, $technicianLat, $technicianLng);

        // Validate distance (max 10 meters)
        if ($distance > 10) {
            return response()->json([
                'message' => 'Lokasi check-in terlalu jauh dari lokasi pelanggan (lebih dari 10 meter).',
                'distance' => round($distance, 2) . ' meter',
                'max_distance' => '10 meter',
                'customer_location' => [
                    'latitude' => $customerLat,
                    'longitude' => $customerLng
                ]
            ], 422);
        }

        // Update ticket with check-in data
        $ticket->update([
            'status' => 'in_progress',
            'checkin_latitude' => $technicianLat,
            'checkin_longitude' => $technicianLng,
            'checkin_time' => now(),
            'customer_latitude' => $customerLat,
            'customer_longitude' => $customerLng,
            'checkin_distance_meters' => round($distance, 2),
            'checkin_validated' => true,
            'work_started_at' => now()
        ]);

        // Log the check-in
        $ticket->logs()->create([
            'user_id' => $user->id,
            'action' => 'checkin',
            'description' => "Teknisi {$user->name} melakukan check-in pada jarak " . round($distance, 2) . " meter dari lokasi pelanggan",
            'metadata' => [
                'technician_location' => [
                    'latitude' => $technicianLat,
                    'longitude' => $technicianLng
                ],
                'customer_location' => [
                    'latitude' => $customerLat,
                    'longitude' => $customerLng
                ],
                'distance' => round($distance, 2)
            ]
        ]);

        return response()->json([
            'message' => 'Check-in berhasil!',
            'distance' => round($distance, 2) . ' meter',
            'status' => 'in_progress',
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'checkin_time' => $ticket->checkin_time->format('Y-m-d H:i:s'),
                'work_started_at' => $ticket->work_started_at->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Update ODP for new installation tickets
     */
    public function updateOdp(Request $request, string $id)
    {
        $request->validate([
            'odp_id' => 'required|exists:odps,id'
        ]);

        $ticket = Ticket::findOrFail($id);

        if ($ticket->ticket_type !== 'new_installation') {
            return response()->json(['message' => 'ODP can only be updated for new installation tickets.'], 400);
        }

        $ticket->update(['odp_id' => $request->odp_id]);

        return response()->json(['message' => 'ODP updated successfully!']);
    }

    /**
     * Generate Mikrotik credentials for new installation
     */
    public function generateCredentials(Request $request, string $id)
    {
        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();

        // Validate technician is assigned to this ticket
        if (!$ticket->technicians->contains($user)) {
            return response()->json(['message' => 'Anda tidak ditugaskan pada tiket ini.'], 403);
        }

        if ($ticket->ticket_type !== 'new_installation') {
            return response()->json(['message' => 'Kredensial hanya dapat di-generate untuk instalasi baru.'], 400);
        }

        if ($ticket->credentials_generated) {
            return response()->json(['message' => 'Kredensial sudah di-generate untuk tiket ini.'], 400);
        }

        DB::beginTransaction();
        try {
            // Generate unique username and password
            $username = $this->generateUsername($ticket->customer);
            $password = $this->generatePassword();
            $profile = $this->getMikrotikProfile($ticket->serviceLocation->package);

            // Update ticket with credentials
            $ticket->update([
                'pppoe_username' => $username,
                'pppoe_password' => $password,
                'mikrotik_profile' => $profile,
                'credentials_generated' => true
            ]);

            // Update service location with credentials
            $ticket->serviceLocation->update([
                'pppoe_username' => $username,
                'mikrotik_profile' => $profile
            ]);

            // Log the action
            $ticket->logs()->create([
                'user_id' => $user->id,
                'action' => 'generate_credentials',
                'description' => "Teknisi {$user->name} men-generate kredensial PPPoE",
                'metadata' => [
                    'username' => $username,
                    'profile' => $profile
                ]
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Kredensial berhasil di-generate!',
                'username' => $username,
                'password' => $password,
                'profile' => $profile
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error generating credentials: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload before/after photos
     */
    public function uploadPhotos(Request $request, string $id)
    {
        $request->validate([
            'type' => 'required|in:before,after',
            'photos' => 'required|array|max:5',
            'photos.*' => 'image|mimes:jpeg,png,jpg|max:2048'
        ]);

        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();

        if (!$ticket->technicians->contains($user)) {
            return response()->json(['message' => 'You are not assigned to this ticket.'], 403);
        }

        $uploadedPhotos = [];
        $type = $request->type;

        foreach ($request->file('photos') as $photo) {
            $filename = time() . '_' . $type . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
            $path = $photo->storeAs("tickets/{$ticket->id}/{$type}", $filename, 'public');
            $uploadedPhotos[] = $path;
        }

        // Update ticket photos
        $currentPhotos = $ticket->{$type . '_photos'} ?? [];
        $allPhotos = array_merge($currentPhotos, $uploadedPhotos);

        $ticket->update([
            $type . '_photos' => $allPhotos
        ]);

        return response()->json([
            'message' => ucfirst($type) . ' photos uploaded successfully!',
            'photos' => $uploadedPhotos
        ]);
    }

    /**
     * Complete ticket and generate contract
     */
    public function complete(Request $request, string $id)
    {
        $request->validate([
            'resolution' => 'required|string',
            'customer_digital_signature' => 'required|string', // Base64 signature
            'technician_digital_signature' => 'required|string' // Base64 signature
        ]);

        $ticket = Ticket::findOrFail($id);
        $user = Auth::user();

        if (!$ticket->technicians->contains($user)) {
            return response()->json(['message' => 'You are not assigned to this ticket.'], 403);
        }

        DB::beginTransaction();
        try {
            // Calculate work duration
            $workDuration = null;
            if ($ticket->work_started_at) {
                $workDuration = $ticket->work_started_at->diffInMinutes(now());
            }

            // Generate contract number
            $contractNumber = 'CONTRACT-' . date('Ymd') . '-' . str_pad($ticket->id, 6, '0', STR_PAD_LEFT);

            // Update ticket
            $ticket->update([
                'status' => 'solved',
                'resolution' => $request->resolution,
                'resolution_time' => now(),
                'work_completed_at' => now(),
                'work_duration_minutes' => $workDuration,
                'customer_digital_signature' => $request->customer_digital_signature,
                'technician_digital_signature' => $request->technician_digital_signature,
                'contract_number' => $contractNumber,
                'contract_signed_at' => now()
            ]);

            // Generate PDF contract
            $pdfPath = $this->generateContract($ticket);
            $ticket->update(['contract_pdf' => $pdfPath]);

            // Send completion notification to customer
            SendTicketNotification::dispatch($ticket, 'completion');

            DB::commit();

            return response()->json([
                'message' => 'Ticket completed successfully!',
                'contract_number' => $contractNumber,
                'work_duration' => $workDuration ? floor($workDuration / 60) . 'h ' . ($workDuration % 60) . 'm' : null
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error completing ticket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Download contract PDF
     */
    public function downloadContract(string $id)
    {
        $ticket = Ticket::findOrFail($id);

        if (!$ticket->contract_pdf) {
            return response()->json(['message' => 'Kontrak tidak ditemukan.'], 404);
        }

        $filePath = storage_path('app/' . $ticket->contract_pdf);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'File kontrak tidak ditemukan.'], 404);
        }

        return response()->download($filePath, "Contract-{$ticket->contract_number}.pdf");
    }

    /**
     * Get ticket statistics for dashboard
     */
    public function getStats()
    {
        try {
            $stats = [
                'total' => Ticket::count(),
                'pending' => Ticket::whereIn('status', ['open', 'waiting_supervisor'])->count(),
                'in_progress' => Ticket::where('status', 'in_progress')->count(),
                'completed' => Ticket::whereIn('status', ['solved', 'closed'])->count(),
                'failed' => Ticket::where('status', 'failed')->count(),
            ];
            
            return response()->json($stats);
        } catch (\Exception $e) {
            \Log::error('Error getting ticket stats: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Error loading ticket statistics'
            ], 500);
        }
    }

    /**
     * Send reminder notifications for pending tickets
     */
    public function sendReminders()
    {
        $pendingTickets = Ticket::whereIn('status', ['assigned', 'acknowledged'])
            ->where('created_at', '<', now()->subHours(2)) // Tickets older than 2 hours
            ->with(['technicians'])
            ->get();

        $results = [];
        foreach ($pendingTickets as $ticket) {
            foreach ($ticket->technicians as $technician) {
                $results[] = $this->whatsappService->sendTicketReminder($ticket, $technician);
            }
        }

        return response()->json([
            'message' => 'Reminder berhasil dikirim',
            'tickets_count' => $pendingTickets->count(),
            'notifications_sent' => array_sum($results)
        ]);
    }


































    // preference for ticket notification and all features

    /**
     * Calculate distance between two GPS coordinates (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371000; // Earth radius in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance; // Distance in meters
    }

    /**
     * Generate PDF contract
     */
    private function generateContract(Ticket $ticket)
    {
        $pdf = app('dompdf.wrapper');

        $data = [
            'ticket' => $ticket,
            'customer' => $ticket->customer,
            'service_location' => $ticket->serviceLocation,
            'package' => $ticket->serviceLocation->package,
            'contract_number' => $ticket->contract_number,
            'generated_at' => now()
        ];

        $html = view('pdf.contract', $data)->render();
        $pdf->loadHTML($html);

        $filename = "contracts/contract-{$ticket->contract_number}.pdf";
        $path = storage_path('app/public/' . $filename);

        // Ensure directory exists
        $directory = dirname($path);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $pdf->save($path);

        return 'public/' . $filename;
    }

    /**
     * Generate unique PPPoE username
     */
    private function generateUsername($customer)
    {
        $baseName = strtolower(str_replace(' ', '', $customer->name));
        $baseName = preg_replace('/[^a-z0-9]/', '', $baseName);
        $baseName = substr($baseName, 0, 8);

        $counter = 1;
        $username = $baseName . sprintf('%03d', $counter);

        // Check if username exists in service_locations
        while (ServiceLocation::where('pppoe_username', $username)->exists()) {
            $counter++;
            $username = $baseName . sprintf('%03d', $counter);
        }

        return $username;
    }

    /**
     * Generate secure password
     */
    private function generatePassword()
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * Get Mikrotik profile based on package
     */
    private function getMikrotikProfile($package)
    {
        if (!$package) {
            return 'default';
        }

        // Extract speed from package name or use a mapping
        $speed = $package->speed ?? '10 Mbps';
        $speedValue = (int) filter_var($speed, FILTER_SANITIZE_NUMBER_INT);

        return "profile-{$speedValue}mbps";
    }

    /**
     * Get approved installation requests for new installations.
     */
    public function getApprovedInstallationRequests(Request $request)
    {
        $requests = InstallationRequest::where('ticket_type', 'new')
                                      ->where('status', 'pending')
                                      ->with(['customer:id,name', 'serviceLocation:id,address', 'package:id,name'])
                                      ->get()
                                      ->map(function ($ir) {
                                          return [
                                              'id' => $ir->id,
                                              'customer_id' => $ir->customer_id,
                                              'service_location_id' => $ir->service_location_id,
                                              'customer_name' => $ir->customer->name ?? 'N/A',
                                              'service_location_address' => $ir->serviceLocation->address ?? 'N/A',
                                              'package_name' => $ir->package->name ?? 'N/A',
                                              'proposed_installation_date' => $ir->proposed_installation_date->format('d/m/Y'),
                                              'notes' => $ir->notes,
                                          ];
                                      });

        return response()->json($requests);
    }

    /**
     * Get service location details for a given ID.
     * This will be used for repair/reactivation to populate customer/location info.
     */
    public function getServiceLocationDetails(string $id)
    {
        $serviceLocation = ServiceLocation::with('customer')->find($id);

        if (!$serviceLocation) {
            return response()->json(['message' => 'Service location not found'], 404);
        }

        return response()->json([
            'customer_id' => $serviceLocation->customer->id,
            'customer_name' => $serviceLocation->customer->name,
            'customer_email' => $serviceLocation->customer->email,
            'customer_phone' => $serviceLocation->customer->phone,
            'location_address' => $serviceLocation->address,
            'location_latitude' => $serviceLocation->latitude,
            'location_longitude' => $serviceLocation->longitude,
            'package_name' => $serviceLocation->package->name ?? 'N/A',
        ]);
    }

    /**
     * Show ticket via mini URL dengan auto-login mechanism
     */
    public function showViaLink($encryptedId)
    {
        try {
            $ticketId = $this->decryptId($encryptedId);
            
            // Check if user is authenticated
            if (!auth()->check()) {
                // Store intended URL dan redirect ke login
                session(['intended_ticket' => $encryptedId]);
                return redirect()->route('login')->with('info', 'Please login to view ticket details.');
            }
            
            // User sudah login, redirect ke ticket detail
            return redirect()->route('tickets.show', $ticketId);
            
        } catch (\Exception $e) {
            abort(404, 'Invalid ticket link.');
        }
    }

    /**
     * Redirect to ticket after login (dipanggil dari login controller)
     */
    public function redirectToTicket($encryptedId)
    {
        try {
            $ticketId = $this->decryptId($encryptedId);
            $ticket = Ticket::findOrFail($ticketId);
            
            // Check user permission to view this ticket
            $user = auth()->user();
            
            if ($user->hasRole(['Owner', 'Super-Admin'])) {
                // Owner/Super-Admin can view all tickets
                return redirect()->route('tickets.show', $ticketId);
            } elseif ($user->hasRole('NOC') && $ticket->supervisor_id === $user->id) {
                // NOC can view tickets they supervise
                return redirect()->route('tickets.show', $ticketId);
            } elseif ($user->hasRole('Technician') && $ticket->technicians->contains($user->id)) {
                // Technician can view tickets assigned to them
                return redirect()->route('tickets.show', $ticketId);
            } else {
                return redirect()->route('tickets.index')->with('error', 'You do not have permission to view this ticket.');
            }
            
        } catch (\Exception $e) {
            return redirect()->route('tickets.index')->with('error', 'Ticket not found.');
        }
    }

}