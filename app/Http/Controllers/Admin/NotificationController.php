<?php

// File: app/Http/Controllers/Admin/NotificationController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Customer;
use App\Models\ServiceLocation;
use App\Models\UserNotificationPreference;
use App\Models\CustomerNotificationPreference;
use App\Models\NotificationSetting;
use App\Models\NotificationLog;
use App\Services\TelegramService;
use App\Services\FontteWhatsAppService;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function __construct()
    {
        // Only Super-Admin can access
        $this->middleware(['auth', 'role:Super-Admin']);
    }

    /**
     * Dashboard notifikasi - overview
     */
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'users_with_notifications' => User::where('notifications_enabled', true)->count(),
            'total_customers' => Customer::count(),
            'customers_with_notifications' => Customer::where('notifications_enabled', true)->count(),
            'notifications_sent_today' => NotificationLog::whereDate('created_at', today())->where('status', 'sent')->count(),
            'notifications_failed_today' => NotificationLog::whereDate('created_at', today())->where('status', 'failed')->count(),
        ];

        $channelStats = NotificationSetting::get()->keyBy('channel');
        
        return view('admin.notifications.index', compact('stats', 'channelStats'));
    }

    /**
     * Manage user notifications
     */
    public function users()
    {
        return view('admin.notifications.users');
    }

    /**
     * Get users data for DataTable
     */
    public function getUsersData(Request $request)
    {
        $query = User::with(['roles', 'notificationPreferences'])
                    ->select('users.*');

        return DataTables::of($query)
            ->addColumn('role', function ($user) {
                $role = $user->roles->first();
                return $role ? $role->name : 'No Role';
            })
            ->addColumn('notification_status', function ($user) {
                if ($user->notifications_enabled) {
                    return '<span class="badge bg-label-success">Enabled</span>';
                } else {
                    return '<span class="badge bg-label-secondary">Disabled</span>';
                }
            })
            ->addColumn('telegram_status', function ($user) {
                if ($user->telegram_chat_id) {
                    return '<span class="badge bg-label-info">Connected</span>';
                } else {
                    return '<span class="badge bg-label-warning">Not Set</span>';
                }
            })
            ->addColumn('whatsapp_status', function ($user) {
                $phone = $user->whatsapp_number ?: $user->phone_number;
                if ($phone) {
                    return '<span class="badge bg-label-success">Available</span>';
                } else {
                    return '<span class="badge bg-label-danger">No Phone</span>';
                }
            })
            ->addColumn('preferences_count', function ($user) {
                $activeCount = $user->notificationPreferences()->where('is_active', true)->count();
                $totalCount = $user->notificationPreferences()->count();
                return "{$activeCount}/{$totalCount}";
            })
            ->addColumn('actions', function ($user) {
                return '
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                            <i class="ti ti-dots-vertical ti-sm"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="editUserNotification(' . $user->id . ')">
                                <i class="ti ti-settings me-2"></i>Manage Notifications</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0);" onclick="testUserNotification(' . $user->id . ')">
                                <i class="ti ti-send me-2"></i>Send Test Message</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="javascript:void(0);" onclick="toggleUserNotification(' . $user->id . ', ' . ($user->notifications_enabled ? 'false' : 'true') . ')">
                                <i class="ti ti-' . ($user->notifications_enabled ? 'bell-off' : 'bell') . ' me-2"></i>' . ($user->notifications_enabled ? 'Disable' : 'Enable') . ' Notifications</a></li>
                        </ul>
                    </div>';
            })
            ->rawColumns(['notification_status', 'telegram_status', 'whatsapp_status', 'actions'])
            ->make(true);
    }

    /**
     * Show user notification preferences form
     */
    public function editUser($userId)
    {
        $user = User::with(['roles', 'notificationPreferences'])->findOrFail($userId);
        
        $availableNotificationTypes = [
            'ticket_assigned' => 'Ticket Assignment',
            'ticket_completed' => 'Ticket Completed',
            'system_maintenance' => 'System Maintenance',
            'daily_report' => 'Daily Report'
        ];

        $availableChannels = [
            'telegram' => 'Telegram',
            'whatsapp' => 'WhatsApp'
        ];

        $currentPreferences = $user->notificationPreferences()->get()->keyBy('notification_type');

        return response()->json([
            'user' => $user,
            'availableNotificationTypes' => $availableNotificationTypes,
            'availableChannels' => $availableChannels,
            'currentPreferences' => $currentPreferences
        ]);
    }

    /**
     * Update user notification preferences
     */
    public function updateUser(Request $request, $userId)
    {
        if ($request->has('preferences')) {
            $preferencesData = json_decode($request->input('preferences'), true);
            // 2. Gabungkan kembali ke dalam request agar bisa divalidasi
            $request->merge(['preferences' => $preferencesData]);
        }
        
        $request->validate([
            'notifications_enabled' => 'sometimes|in:0,1,true,false,on,off',
            'telegram_chat_id' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
            'preferences' => 'array',
            'preferences.*.notification_type' => 'required|string',
            'preferences.*.channels' => 'array',
            'preferences.*.is_active' => 'boolean'
        ]);

        $user = User::findOrFail($userId);

        DB::beginTransaction();
        try {
            $isEnabled = $request->has('notifications_enabled');
            // Update user basic notification settings
            $user->update([
                'notifications_enabled' => $isEnabled,
                'telegram_chat_id' => $request->telegram_chat_id,
                'whatsapp_number' => $request->whatsapp_number,
            ]);

            // Update preferences
            if ($request->has('preferences')) {
                // Delete existing preferences
                $user->notificationPreferences()->delete();

                // Create new preferences
                foreach ($request->preferences as $preference) {
                    if (isset($preference['is_active']) && $preference['is_active']) {
                        UserNotificationPreference::create([
                            'user_id' => $user->id,
                            'notification_type' => $preference['notification_type'],
                            'channels' => $preference['channels'] ?? [],
                            'is_active' => true
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'User notification preferences updated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update preferences: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user notification enabled/disabled
     */
    public function toggleUser(Request $request, $userId)
    {
        $user = User::findOrFail($userId);
        
        $user->update([
            'notifications_enabled' => $request->enabled
        ]);

        $status = $request->enabled ? 'enabled' : 'disabled';
        
        return response()->json([
            'message' => "Notifications {$status} for {$user->name}"
        ]);
    }

    /**
     * Send test notification to user
     */
    public function testUser(Request $request, $userId)
    {
        $request->validate([
            'channel' => 'required|in:telegram,whatsapp',
            'message' => 'nullable|string'
        ]);

        $user = User::findOrFail($userId);
        $channel = $request->channel;
        $message = $request->message ?: "🧪 Test notification from admin panel";

        try {
            if ($channel === 'telegram') {
                if (!$user->telegram_chat_id) {
                    return response()->json([
                        'message' => 'User has no Telegram chat ID set'
                    ], 400);
                }

                $telegramService = app(TelegramService::class);
                $result = $telegramService->sendMessage($user->telegram_chat_id, $message);
                
            } elseif ($channel === 'whatsapp') {
                $phoneNumber = $user->whatsapp_number ?: $user->phone_number;
                
                if (!$phoneNumber) {
                    return response()->json([
                        'message' => 'User has no phone number set'
                    ], 400);
                }

                if (!class_exists('\App\Services\FontteWhatsAppService')) {
                    return response()->json([
                        'message' => 'WhatsApp service not available yet'
                    ], 400);
                }

                $whatsappService = app(FontteWhatsAppService::class);
                $result = $whatsappService->sendMessage($phoneNumber, $message);
            }

            if ($result['success']) {
                return response()->json([
                    'message' => "Test message sent successfully via {$channel}!"
                ]);
            } else {
                return response()->json([
                    'message' => "Failed to send test message: " . $result['error']
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error sending test message: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk enable/disable notifications for multiple users
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'action' => 'required|in:enable,disable',
        ]);

        $enabled = $request->action === 'enable';
        
        User::whereIn('id', $request->user_ids)
            ->update(['notifications_enabled' => $enabled]);

        $action = $enabled ? 'enabled' : 'disabled';
        $count = count($request->user_ids);

        return response()->json([
            'message' => "Notifications {$action} for {$count} users"
        ]);
    }

    /**
     * Global notification settings
     */
    public function settings()
    {
        $settings = NotificationSetting::all();
        return view('admin.notifications.settings', compact('settings'));
    }

    /**
     * Update global notification settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.channel' => 'required|string',
            'settings.*.is_enabled' => 'sometimes|in:0,1,true,false',
            'settings.*.config' => 'array'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->settings as $settingData) {
                // FIX: Convert checkbox value to proper boolean
                $isEnabled = isset($settingData['is_enabled']) && 
                            in_array($settingData['is_enabled'], [1, '1', 'true', true, 'on']);
                
                NotificationSetting::where('channel', $settingData['channel'])
                    ->update([
                        'is_enabled' => $isEnabled, // Use converted boolean
                        'config' => $settingData['config'] ?? []
                    ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Global notification settings updated successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notification logs
     */
    public function logs()
    {
        return view('admin.notifications.logs');
    }

    /**
     * Get notification logs data for DataTable
     */
    public function getLogsData(Request $request)
    {
        $query = NotificationLog::with(['notifiable', 'serviceLocation'])
                               ->select('notification_logs.*')
                               ->orderBy('created_at', 'desc');

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        return DataTables::of($query)
            ->addColumn('recipient', function ($log) {
                if ($log->notifiable_type === 'App\Models\User') {
                    return $log->notifiable->name ?? 'Unknown User';
                } elseif ($log->notifiable_type === 'App\Models\Customer') {
                    return $log->notifiable->name ?? 'Unknown Customer';
                }
                return 'Unknown';
            })
            ->addColumn('status_badge', function ($log) {
                $badgeClass = [
                    'sent' => 'success',
                    'failed' => 'danger',
                    'pending' => 'warning',
                    'delivered' => 'info'
                ][$log->status] ?? 'secondary';
                
                return '<span class="badge bg-label-' . $badgeClass . '">' . ucfirst($log->status) . '</span>';
            })
            ->addColumn('channel_badge', function ($log) {
                $icon = [
                    'telegram' => 'ti-brand-telegram',
                    'whatsapp' => 'ti-brand-whatsapp',
                    'email' => 'ti-mail'
                ][$log->channel] ?? 'ti-bell';
                
                return '<i class="' . $icon . ' me-1"></i>' . ucfirst($log->channel);
            })
            ->addColumn('sent_at_formatted', function ($log) {
                return $log->sent_at ? $log->sent_at->format('d M Y H:i') : '-';
            })
            ->addColumn('actions', function ($log) {
                return '<button type="button" class="btn btn-sm btn-icon btn-text-secondary" onclick="viewLogDetails(' . $log->id . ')" data-bs-toggle="tooltip" title="View Details">
                    <i class="ti ti-eye ti-sm"></i>
                </button>';
            })
            ->rawColumns(['status_badge', 'channel_badge', 'actions'])
            ->make(true);
    }

    /**
     * Get notification log details
     */
    public function getLogDetails($logId)
    {
        $log = NotificationLog::with(['notifiable', 'serviceLocation'])->findOrFail($logId);
        
        return response()->json([
            'log' => $log,
            'recipient_name' => $log->notifiable->name ?? 'Unknown',
            'service_location' => $log->serviceLocation->address ?? null
        ]);
    }

    public function testChannel(Request $request, $channel)
    {
        $request->validate([
            'recipient' => 'required|string',
            'message' => 'nullable|string'
        ]);

        $recipient = $request->recipient;
        $message = $request->message ?: "🧪 Test message from admin panel";

        try {
            switch ($channel) {
                case 'telegram':
                    $telegramService = app(TelegramService::class);
                    
                    if (!$telegramService->isConfigured()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Telegram not configured'
                        ], 400);
                    }

                    $result = $telegramService->sendMessage($recipient, $message);
                    break;

                case 'whatsapp':
                    if (!class_exists('\App\Services\FontteWhatsAppService')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'WhatsApp service not available'
                        ], 400);
                    }

                    $whatsappService = app(\App\Services\FontteWhatsAppService::class);
                    
                    if (!$whatsappService->isConfigured()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'WhatsApp not configured'
                        ], 400);
                    }

                    $result = $whatsappService->sendMessage($recipient, $message);
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unknown channel: ' . $channel
                    ], 400);
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 
                    "Test message sent successfully via {$channel}!" : 
                    "Failed to send test message: " . $result['error']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error testing channel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStats(Request $request)
    {
        $period = $request->get('period', 'today');
        
        $query = NotificationLog::query();
        
        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
                break;
            case 'year':
                $query->whereYear('created_at', now()->year);
                break;
        }
        
        $stats = [
            'total' => $query->count(),
            'sent' => $query->where('status', 'sent')->count(),
            'failed' => $query->where('status', 'failed')->count(),
            'pending' => $query->where('status', 'pending')->count(),
            'by_channel' => $query->select('channel', DB::raw('count(*) as total'))
                                ->groupBy('channel')
                                ->pluck('total', 'channel')
                                ->toArray(),
            'by_type' => $query->select('notification_type', DB::raw('count(*) as total'))
                            ->groupBy('notification_type')
                            ->pluck('total', 'notification_type')
                            ->toArray()
        ];
        
        return response()->json($stats);
    }

    /**
     * Bulk send test messages
     */
    public function bulkSendTest(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'channel' => 'required|in:telegram,whatsapp',
            'message' => 'nullable|string'
        ]);

        $users = User::whereIn('id', $request->user_ids)
                    ->where('notifications_enabled', true)
                    ->get();

        $message = $request->message ?: "🧪 Bulk test message from admin panel";
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($users as $user) {
            try {
                if ($request->channel === 'telegram') {
                    if (!$user->telegram_chat_id) {
                        $results[$user->id] = ['success' => false, 'error' => 'No Telegram chat ID'];
                        $failureCount++;
                        continue;
                    }

                    $telegramService = app(TelegramService::class);
                    $result = $telegramService->sendMessage($user->telegram_chat_id, $message);
                    
                } elseif ($request->channel === 'whatsapp') {
                    $phoneNumber = $user->whatsapp_number ?: $user->phone_number;
                    
                    if (!$phoneNumber) {
                        $results[$user->id] = ['success' => false, 'error' => 'No phone number'];
                        $failureCount++;
                        continue;
                    }

                    $whatsappService = app(\App\Services\FontteWhatsAppService::class);
                    $result = $whatsappService->sendMessage($phoneNumber, $message);
                }

                $results[$user->id] = $result;
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }

                // Add delay to avoid rate limiting
                if (count($request->user_ids) > 1) {
                    usleep(500000); // 0.5 second delay
                }

            } catch (\Exception $e) {
                $results[$user->id] = ['success' => false, 'error' => $e->getMessage()];
                $failureCount++;
            }
        }

        return response()->json([
            'message' => "Bulk test completed: {$successCount} successful, {$failureCount} failed",
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ]);
    }

    /**
     * Export notification logs
     */
    public function exportLogs(Request $request)
    {
        // Implementation for CSV export
        $logs = NotificationLog::with(['notifiable'])
                            ->when($request->date_from, function($q, $date) {
                                return $q->whereDate('created_at', '>=', $date);
                            })
                            ->when($request->date_to, function($q, $date) {
                                return $q->whereDate('created_at', '<=', $date);
                            })
                            ->when($request->status, function($q, $status) {
                                return $q->where('status', $status);
                            })
                            ->when($request->channel, function($q, $channel) {
                                return $q->where('channel', $channel);
                            })
                            ->get();

        $filename = 'notification_logs_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'Recipient Type', 'Recipient Name', 'Notification Type', 
                'Channel', 'Status', 'Message Content', 'Sent At', 'Error Message'
            ]);

            // CSV data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    class_basename($log->notifiable_type),
                    $log->notifiable->name ?? 'Unknown',
                    $log->notification_type,
                    $log->channel,
                    $log->status,
                    substr($log->message_content, 0, 100) . (strlen($log->message_content) > 100 ? '...' : ''),
                    $log->sent_at ? $log->sent_at->format('Y-m-d H:i:s') : '',
                    $log->error_message ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}